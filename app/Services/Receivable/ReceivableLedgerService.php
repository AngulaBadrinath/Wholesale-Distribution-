<?php

declare(strict_types=1);

namespace App\Services\Receivable;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ReceivableTransactionType;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReceivableTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReceivableLedgerService
{
    public function __construct(
        protected ReceivableTransactionNumberGenerator $numberGenerator
    ) {}

    /**
     * Authoritatively record an invoice charge into the customer receivable ledger.
     *
     * @throws ValidationException
     */
    public function recordInvoiceCharge(Invoice $invoice, ?User $actor = null): ReceivableTransaction
    {
        // 1. Validate invoice status (only ISSUED / final invoices create AR charges)
        if ($invoice->status !== InvoiceStatus::ISSUED && $invoice->status !== InvoiceStatus::PAID && $invoice->status !== InvoiceStatus::PARTIALLY_PAID) {
            throw ValidationException::withMessages([
                'invoice' => "Cannot post invoice in status '{$invoice->status->value}' to accounts receivable.",
            ]);
        }

        return DB::transaction(function () use ($invoice, $actor) {
            // Check for existing ledger entry to guarantee idempotency
            $existing = ReceivableTransaction::where('source_type', 'invoice')
                ->where('source_id', $invoice->id)
                ->where('type', ReceivableTransactionType::INVOICE_CHARGE)
                ->first();

            if ($existing) {
                return $existing;
            }

            $transactionNumber = $this->numberGenerator->generate();
            $transactionDate = $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->toDateString() : Carbon::now()->toDateString();
            $postingDate = Carbon::now()->toDateString();
            $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->toDateString() : null;

            return ReceivableTransaction::create([
                'transaction_number' => $transactionNumber,
                'customer_id' => $invoice->customer_id,
                'order_id' => $invoice->order_id,
                'invoice_id' => $invoice->id,
                'type' => ReceivableTransactionType::INVOICE_CHARGE,
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'source_number' => $invoice->invoice_number,
                'amount' => $invoice->grand_total,
                'debit_amount' => $invoice->grand_total,
                'credit_amount' => '0.00',
                'transaction_date' => $transactionDate,
                'posting_date' => $postingDate,
                'due_date' => $dueDate,
                'currency' => $invoice->currency ?? 'USD',
                'description' => "Invoice #{$invoice->invoice_number} charge",
                'created_by' => $actor?->id ?? $invoice->created_by,
            ]);
        });
    }

    /**
     * Authoritatively record a verified payment credit into the customer receivable ledger.
     *
     * @throws ValidationException
     */
    public function recordPaymentCredit(Payment $payment, ?User $actor = null): ReceivableTransaction
    {
        // 1. Validate payment status (only VERIFIED payments create AR credits)
        if ($payment->status !== PaymentTransactionStatus::VERIFIED) {
            throw ValidationException::withMessages([
                'payment' => "Cannot post payment in status '{$payment->status->value}' to accounts receivable. Payment must be verified.",
            ]);
        }

        return DB::transaction(function () use ($payment, $actor) {
            $existing = ReceivableTransaction::where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('type', ReceivableTransactionType::PAYMENT)
                ->first();

            if ($existing) {
                return $existing;
            }

            $transactionNumber = $this->numberGenerator->generate();
            $transactionDate = $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateString() : Carbon::now()->toDateString();
            $postingDate = Carbon::now()->toDateString();
            $methodLabel = $payment->payment_method ? $payment->payment_method->label() : 'Payment';

            return ReceivableTransaction::create([
                'transaction_number' => $transactionNumber,
                'customer_id' => $payment->customer_id,
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'type' => ReceivableTransactionType::PAYMENT,
                'source_type' => 'payment',
                'source_id' => $payment->id,
                'source_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'debit_amount' => '0.00',
                'credit_amount' => $payment->amount,
                'transaction_date' => $transactionDate,
                'posting_date' => $postingDate,
                'due_date' => null,
                'currency' => 'USD',
                'description' => "Payment #{$payment->payment_number} ({$methodLabel}) received",
                'created_by' => $actor?->id ?? $payment->verified_by ?? $payment->recorded_by,
            ]);
        });
    }

    /**
     * Authoritatively record a payment reversal debit (compensating entry) into the customer receivable ledger.
     *
     * @throws ValidationException
     */
    public function recordPaymentReversal(Payment $payment, ?User $actor = null): ReceivableTransaction
    {
        // 1. Validate payment status (only REVERSED payments create payment reversal debits)
        if ($payment->status !== PaymentTransactionStatus::REVERSED) {
            throw ValidationException::withMessages([
                'payment' => "Cannot post payment reversal in status '{$payment->status->value}'. Payment must be reversed.",
            ]);
        }

        return DB::transaction(function () use ($payment, $actor) {
            $existing = ReceivableTransaction::where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('type', ReceivableTransactionType::PAYMENT_REVERSAL)
                ->first();

            if ($existing) {
                return $existing;
            }

            $transactionNumber = $this->numberGenerator->generate();
            $transactionDate = $payment->reversed_at ? Carbon::parse($payment->reversed_at)->toDateString() : Carbon::now()->toDateString();
            $postingDate = Carbon::now()->toDateString();
            $reasonLabel = $payment->reversal_reason_code ? $payment->reversal_reason_code->label() : 'Reversal';

            return ReceivableTransaction::create([
                'transaction_number' => $transactionNumber,
                'customer_id' => $payment->customer_id,
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'type' => ReceivableTransactionType::PAYMENT_REVERSAL,
                'source_type' => 'payment',
                'source_id' => $payment->id,
                'source_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'debit_amount' => $payment->amount, // Compensating debit to restore receivable balance
                'credit_amount' => '0.00',
                'transaction_date' => $transactionDate,
                'posting_date' => $postingDate,
                'due_date' => null,
                'currency' => 'USD',
                'description' => "Payment #{$payment->payment_number} reversed ({$reasonLabel})",
                'created_by' => $actor?->id ?? $payment->reversed_by,
            ]);
        });
    }

    /**
     * Authoritatively record an issued credit note into the customer receivable ledger.
     *
     * @throws ValidationException
     */
    public function recordCreditNote(CreditNote $creditNote, ?User $actor = null): ReceivableTransaction
    {
        // 1. Validate credit note status
        if (! in_array($creditNote->status, [CreditNoteStatus::ISSUED, CreditNoteStatus::PARTIALLY_REFUNDED, CreditNoteStatus::FULLY_REFUNDED, CreditNoteStatus::APPLIED, CreditNoteStatus::CLOSED], true)) {
            throw ValidationException::withMessages([
                'credit_note' => "Cannot post credit note in status '{$creditNote->status->value}' to accounts receivable.",
            ]);
        }

        return DB::transaction(function () use ($creditNote, $actor) {
            $existing = ReceivableTransaction::where('source_type', 'credit_note')
                ->where('source_id', $creditNote->id)
                ->where('type', ReceivableTransactionType::CREDIT_NOTE)
                ->first();

            if ($existing) {
                return $existing;
            }

            $transactionNumber = $this->numberGenerator->generate();
            $transactionDate = $creditNote->issued_at ? Carbon::parse($creditNote->issued_at)->toDateString() : Carbon::now()->toDateString();
            $postingDate = Carbon::now()->toDateString();

            return ReceivableTransaction::create([
                'transaction_number' => $transactionNumber,
                'customer_id' => $creditNote->customer_id,
                'credit_note_id' => $creditNote->id,
                'type' => ReceivableTransactionType::CREDIT_NOTE,
                'source_type' => 'credit_note',
                'source_id' => $creditNote->id,
                'source_number' => $creditNote->credit_number,
                'amount' => $creditNote->total_amount,
                'debit_amount' => '0.00',
                'credit_amount' => $creditNote->total_amount,
                'transaction_date' => $transactionDate,
                'posting_date' => $postingDate,
                'due_date' => null,
                'currency' => $creditNote->currency ?? 'USD',
                'description' => "Credit Note #{$creditNote->credit_number} issued",
                'created_by' => $actor?->id ?? $creditNote->issued_by,
            ]);
        });
    }

    /**
     * Authoritative financial summary derived from active business transactions
     * (Invoices, Un-invoiced Orders, Verified Payments, Pending Payments, Credit Notes).
     *
     * @return array{
     *     total_debits: string,
     *     verified_credits: string,
     *     pending_payments: string,
     *     net_receivable: string,
     *     operational_outstanding: string,
     *     credit_limit: string,
     *     available_credit: string
     * }
     */
    public function getCustomerFinancialSummary(Customer|int $customer): array
    {
        $customerModel = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);
        $customerId = $customerModel->id;

        // 1. Invoices (debited)
        $invoiceDebits = Invoice::where('customer_id', $customerId)
            ->whereNotIn('status', [InvoiceStatus::VOID->value])
            ->sum('grand_total');

        // 2. Active Orders without invoices (debited)
        $uninvoicedOrderDebits = Order::where('customer_id', $customerId)
            ->whereIn('status', [
                OrderStatus::APPROVED->value,
                OrderStatus::PROCESSING->value,
                OrderStatus::COMPLETED->value,
            ])
            ->whereDoesntHave('invoices')
            ->sum('grand_total');

        $totalDebits = bcadd((string) $invoiceDebits, (string) $uninvoicedOrderDebits, 2);

        // 3. Verified Payments (credited)
        $verifiedPayments = Payment::where('customer_id', $customerId)
            ->where('status', PaymentTransactionStatus::VERIFIED->value)
            ->sum('amount');

        // 4. Issued Credit Notes (credited)
        $creditNotes = CreditNote::where('customer_id', $customerId)
            ->whereIn('status', [
                CreditNoteStatus::ISSUED->value,
                CreditNoteStatus::APPLIED->value,
                CreditNoteStatus::PARTIALLY_REFUNDED->value,
            ])
            ->sum('total_amount');

        $totalVerifiedCredits = bcadd((string) $verifiedPayments, (string) $creditNotes, 2);

        // 5. Pending Payments (operational credit offset)
        $pendingPayments = Payment::where('customer_id', $customerId)
            ->where('status', PaymentTransactionStatus::PENDING_VERIFICATION->value)
            ->sum('amount');

        // Verified Accounting AR = max(0, Debits - Verified Credits)
        $netVerifiedReceivable = bcsub($totalDebits, $totalVerifiedCredits, 2);
        if (bccomp($netVerifiedReceivable, '0.00', 2) < 0) {
            $netVerifiedReceivable = '0.00';
        }

        // Operational Outstanding = max(0, Verified AR - Pending Payments)
        $operationalOutstanding = bcsub($netVerifiedReceivable, (string) $pendingPayments, 2);
        if (bccomp($operationalOutstanding, '0.00', 2) < 0) {
            $operationalOutstanding = '0.00';
        }

        $creditLimit = (string) ($customerModel->credit_limit ?? '0.00');
        $unappliedCredit = $this->getCustomerCreditBalance($customerModel);

        // Available Credit = max(0, Credit Limit - Operational Outstanding) + Unapplied Credit
        $remainingCreditLimit = bcsub($creditLimit, $operationalOutstanding, 2);
        if (bccomp($remainingCreditLimit, '0.00', 2) < 0) {
            $remainingCreditLimit = '0.00';
        }
        $totalAvailableCredit = bcadd($remainingCreditLimit, $unappliedCredit, 2);

        return [
            'total_debits' => $totalDebits,
            'verified_credits' => $totalVerifiedCredits,
            'pending_payments' => number_format((float) $pendingPayments, 2, '.', ''),
            'net_receivable' => $netVerifiedReceivable,
            'operational_outstanding' => $operationalOutstanding,
            'credit_limit' => $creditLimit,
            'available_credit' => $totalAvailableCredit,
        ];
    }

    /**
     * Get authoritative net receivable balance for a customer (Total Debits - Total Credits).
     */
    public function getCustomerReceivableBalance(Customer|int $customer): string
    {
        $summary = $this->getCustomerFinancialSummary($customer);

        return $summary['net_receivable'];
    }

    /**
     * Get authoritative available credit balance for a customer from unallocated/refundable credit notes.
     */
    public function getCustomerCreditBalance(Customer|int $customer): string
    {
        $customerId = $customer instanceof Customer ? $customer->id : $customer;

        $totalRemaining = CreditNote::where('customer_id', $customerId)
            ->whereIn('status', [CreditNoteStatus::ISSUED->value, CreditNoteStatus::PARTIALLY_REFUNDED->value])
            ->sum('remaining_balance');

        return number_format((float) $totalRemaining, 2, '.', '');
    }

    /**
     * Sync unposted historical events (Invoices, Verified Payments, Payment Reversals, Credit Notes).
     */
    public function syncUnpostedHistoricalEvents(): int
    {
        $count = 0;

        // 1. Sync Invoices
        $invoices = Invoice::whereNotIn('status', [InvoiceStatus::VOID])->get();
        foreach ($invoices as $invoice) {
            $exists = ReceivableTransaction::where('source_type', 'invoice')
                ->where('source_id', $invoice->id)
                ->where('type', ReceivableTransactionType::INVOICE_CHARGE)
                ->exists();

            if (! $exists) {
                $this->recordInvoiceCharge($invoice);
                $count++;
            }
        }

        // 2. Sync Verified Payments
        $verifiedPayments = Payment::where('status', PaymentTransactionStatus::VERIFIED)->get();
        foreach ($verifiedPayments as $payment) {
            $exists = ReceivableTransaction::where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('type', ReceivableTransactionType::PAYMENT)
                ->exists();

            if (! $exists) {
                $this->recordPaymentCredit($payment);
                $count++;
            }
        }

        // 3. Sync Payment Reversals
        $reversedPayments = Payment::where('status', PaymentTransactionStatus::REVERSED)->get();
        foreach ($reversedPayments as $payment) {
            // Ensure the original payment credit exists first
            $paymentCreditExists = ReceivableTransaction::where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('type', ReceivableTransactionType::PAYMENT)
                ->exists();

            if (! $paymentCreditExists) {
                // Post historical payment credit as well
                $this->recordPaymentCreditDirectly($payment);
                $count++;
            }

            $reversalExists = ReceivableTransaction::where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('type', ReceivableTransactionType::PAYMENT_REVERSAL)
                ->exists();

            if (! $reversalExists) {
                $this->recordPaymentReversal($payment);
                $count++;
            }
        }

        // 4. Sync Credit Notes
        $creditNotes = CreditNote::all();
        foreach ($creditNotes as $creditNote) {
            $exists = ReceivableTransaction::where('source_type', 'credit_note')
                ->where('source_id', $creditNote->id)
                ->where('type', ReceivableTransactionType::CREDIT_NOTE)
                ->exists();

            if (! $exists) {
                $this->recordCreditNote($creditNote);
                $count++;
            }
        }

        Log::info("Synced {$count} unposted accounts receivable transactions.");

        return $count;
    }

    /**
     * Helper for backfilling historical payment credits on already reversed payments.
     */
    protected function recordPaymentCreditDirectly(Payment $payment): ReceivableTransaction
    {
        $transactionNumber = $this->numberGenerator->generate();
        $transactionDate = $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateString() : Carbon::now()->toDateString();
        $postingDate = Carbon::now()->toDateString();
        $methodLabel = $payment->payment_method ? $payment->payment_method->label() : 'Payment';

        return ReceivableTransaction::create([
            'transaction_number' => $transactionNumber,
            'customer_id' => $payment->customer_id,
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'type' => ReceivableTransactionType::PAYMENT,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'source_number' => $payment->payment_number,
            'amount' => $payment->amount,
            'debit_amount' => '0.00',
            'credit_amount' => $payment->amount,
            'transaction_date' => $transactionDate,
            'posting_date' => $postingDate,
            'due_date' => null,
            'currency' => 'USD',
            'description' => "Payment #{$payment->payment_number} ({$methodLabel}) received",
            'created_by' => $payment->verified_by ?? $payment->recorded_by,
        ]);
    }
}
