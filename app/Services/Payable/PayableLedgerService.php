<?php

declare(strict_types=1);

namespace App\Services\Payable;

use App\Enums\PayableTransactionType;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentStatus;
use App\Enums\SupplierStatus;
use App\Models\PayableTransaction;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayableLedgerService
{
    public function __construct(
        protected SupplierCodeGenerator $codeGenerator,
        protected SupplierBillNumberGenerator $billNumberGenerator,
        protected SupplierPaymentNumberGenerator $paymentNumberGenerator,
        protected PayableTransactionNumberGenerator $transactionNumberGenerator,
    ) {}

    /**
     * Create a new supplier master record.
     *
     * @param array{
     *     name: string,
     *     supplier_code?: ?string,
     *     contact_person?: ?string,
     *     email?: ?string,
     *     phone?: ?string,
     *     address?: ?string,
     *     payment_terms_days?: ?int,
     *     tax_id?: ?string,
     *     status?: ?string,
     *     notes?: ?string
     * } $data
     */
    public function createSupplier(array $data, ?User $actor = null): Supplier
    {
        $code = ! empty($data['supplier_code'])
            ? trim($data['supplier_code'])
            : $this->codeGenerator->generate();

        return Supplier::create([
            'supplier_code' => $code,
            'name' => trim($data['name']),
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'payment_terms_days' => $data['payment_terms_days'] ?? 30,
            'tax_id' => $data['tax_id'] ?? null,
            'status' => $data['status'] ?? SupplierStatus::ACTIVE->value,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Create a new supplier bill in DRAFT state.
     *
     * @param array{
     *     bill_number?: ?string,
     *     supplier_invoice_number?: ?string,
     *     bill_date: string,
     *     due_date?: ?string,
     *     subtotal: string|float,
     *     tax_total?: string|float,
     *     total_amount: string|float,
     *     description?: ?string,
     *     notes?: ?string
     * } $data
     */
    public function createBill(Supplier $supplier, array $data, ?User $actor = null): SupplierBill
    {
        $subtotal = number_format((float) $data['subtotal'], 2, '.', '');
        $taxTotal = number_format((float) ($data['tax_total'] ?? 0.00), 2, '.', '');
        $totalAmount = number_format((float) $data['total_amount'], 2, '.', '');

        // Financial validation: subtotal + tax = total
        $expectedTotal = bcadd($subtotal, $taxTotal, 2);
        if (bccomp($expectedTotal, $totalAmount, 2) !== 0) {
            throw ValidationException::withMessages([
                'total_amount' => "Total amount ({$totalAmount}) must equal subtotal ({$subtotal}) + tax ({$taxTotal}) = {$expectedTotal}.",
            ]);
        }

        if (bccomp($totalAmount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'total_amount' => 'Bill total amount must be strictly greater than zero.',
            ]);
        }

        $billDate = Carbon::parse($data['bill_date']);
        $dueDate = ! empty($data['due_date'])
            ? Carbon::parse($data['due_date'])
            : $billDate->copy()->addDays($supplier->payment_terms_days ?: 30);

        $billNumber = ! empty($data['bill_number'])
            ? trim($data['bill_number'])
            : $this->billNumberGenerator->generate();

        return SupplierBill::create([
            'bill_number' => $billNumber,
            'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
            'supplier_id' => $supplier->id,
            'bill_date' => $billDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total_amount' => $totalAmount,
            'amount_paid' => '0.00',
            'amount_due' => $totalAmount,
            'status' => SupplierBillStatus::DRAFT->value,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Post a supplier bill to the immutable accounts payable ledger.
     */
    public function recordSupplierBill(SupplierBill $bill, ?User $actor = null): PayableTransaction
    {
        return DB::transaction(function () use ($bill, $actor) {
            // Lock bill row
            $bill = SupplierBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->status !== SupplierBillStatus::DRAFT) {
                // Check if already posted
                $existingTxn = PayableTransaction::where('source_type', 'SUPPLIER_BILL')
                    ->where('source_id', $bill->id)
                    ->where('type', PayableTransactionType::SUPPLIER_BILL->value)
                    ->first();

                if ($existingTxn) {
                    return $existingTxn;
                }
            }

            $bill->status = SupplierBillStatus::POSTED;
            $bill->posted_at = Carbon::now();
            $bill->posted_by = $actor?->id;
            $bill->save();

            $txnNumber = $this->transactionNumberGenerator->generate();

            $transaction = PayableTransaction::create([
                'transaction_number' => $txnNumber,
                'supplier_id' => $bill->supplier_id,
                'supplier_bill_id' => $bill->id,
                'supplier_payment_id' => null,
                'source_type' => 'SUPPLIER_BILL',
                'source_id' => $bill->id,
                'source_number' => $bill->bill_number,
                'type' => PayableTransactionType::SUPPLIER_BILL,
                'amount' => $bill->total_amount,
                'debit_amount' => '0.00',
                'credit_amount' => $bill->total_amount,
                'transaction_date' => $bill->bill_date,
                'posting_date' => Carbon::now()->toDateString(),
                'due_date' => $bill->due_date,
                'currency' => 'USD',
                'description' => "Supplier Bill {$bill->bill_number} - {$bill->supplier->name}",
                'notes' => $bill->description,
                'created_by' => $actor?->id,
            ]);

            $this->recalculateRunningBalances($bill->supplier_id);

            return $transaction;
        });
    }

    /**
     * Record a supplier payment against a supplier bill or supplier account.
     *
     * @param array{
     *     supplier_bill_id?: ?int,
     *     payment_date: string,
     *     amount: string|float,
     *     payment_method: string,
     *     reference_number?: ?string,
     *     notes?: ?string
     * } $data
     */
    public function recordSupplierPayment(Supplier $supplier, array $data, ?User $actor = null): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $data, $actor) {
            $amount = number_format((float) $data['amount'], 2, '.', '');

            if (bccomp($amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be strictly greater than zero.',
                ]);
            }

            $bill = null;
            if (! empty($data['supplier_bill_id'])) {
                $bill = SupplierBill::where('id', (int) $data['supplier_bill_id'])
                    ->where('supplier_id', $supplier->id)
                    ->lockForUpdate()
                    ->first();

                if (! $bill) {
                    throw ValidationException::withMessages([
                        'supplier_bill_id' => 'The selected supplier bill was not found or belongs to another supplier.',
                    ]);
                }

                if (! in_array($bill->status, [SupplierBillStatus::POSTED, SupplierBillStatus::PARTIALLY_PAID], true)) {
                    throw ValidationException::withMessages([
                        'supplier_bill_id' => "Cannot apply payment to bill in {$bill->status->label()} state. Only POSTED or PARTIALLY_PAID bills accept payments.",
                    ]);
                }

                // Overpayment Prevention: Payment cannot exceed remaining bill amount due
                if (bccomp($amount, (string) $bill->amount_due, 2) > 0) {
                    throw ValidationException::withMessages([
                        'amount' => "Payment amount \${$amount} exceeds the bill outstanding amount due of \${$bill->amount_due}.",
                    ]);
                }
            }

            $paymentNumber = $this->paymentNumberGenerator->generate();

            $paymentMethod = SupplierPaymentMethod::tryFrom($data['payment_method'])
                ?? SupplierPaymentMethod::BANK_TRANSFER;

            $payment = SupplierPayment::create([
                'payment_number' => $paymentNumber,
                'supplier_id' => $supplier->id,
                'supplier_bill_id' => $bill?->id,
                'payment_date' => Carbon::parse($data['payment_date'])->toDateString(),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_number' => $data['reference_number'] ?? null,
                'status' => SupplierPaymentStatus::COMPLETED,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            if ($bill) {
                $newPaid = bcadd((string) $bill->amount_paid, $amount, 2);
                $newDue = bcsub((string) $bill->amount_due, $amount, 2);

                $bill->amount_paid = $newPaid;
                $bill->amount_due = $newDue;
                $bill->status = (bccomp($newDue, '0.00', 2) === 0)
                    ? SupplierBillStatus::PAID
                    : SupplierBillStatus::PARTIALLY_PAID;
                $bill->save();
            }

            $txnNumber = $this->transactionNumberGenerator->generate();

            PayableTransaction::create([
                'transaction_number' => $txnNumber,
                'supplier_id' => $supplier->id,
                'supplier_bill_id' => $bill?->id,
                'supplier_payment_id' => $payment->id,
                'source_type' => 'SUPPLIER_PAYMENT',
                'source_id' => $payment->id,
                'source_number' => $payment->payment_number,
                'type' => PayableTransactionType::SUPPLIER_PAYMENT,
                'amount' => $amount,
                'debit_amount' => $amount,
                'credit_amount' => '0.00',
                'transaction_date' => $payment->payment_date,
                'posting_date' => Carbon::now()->toDateString(),
                'due_date' => $bill?->due_date,
                'currency' => 'USD',
                'description' => "Supplier Payment {$payment->payment_number}" . ($bill ? " for Bill {$bill->bill_number}" : ''),
                'notes' => $payment->notes,
                'created_by' => $actor?->id,
            ]);

            $this->recalculateRunningBalances($supplier->id);

            return $payment;
        });
    }

    /**
     * Reverse a completed supplier payment.
     */
    public function recordPaymentReversal(SupplierPayment $payment, string $reason, ?User $actor = null): PayableTransaction
    {
        return DB::transaction(function () use ($payment, $reason, $actor) {
            $payment = SupplierPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === SupplierPaymentStatus::REVERSED) {
                throw ValidationException::withMessages([
                    'payment' => "Supplier payment {$payment->payment_number} has already been reversed.",
                ]);
            }

            $payment->status = SupplierPaymentStatus::REVERSED;
            $payment->reversed_at = Carbon::now();
            $payment->reversed_by = $actor?->id;
            $payment->reversal_reason = trim($reason);
            $payment->save();

            // Restore bill if payment was linked to a bill
            if ($payment->supplier_bill_id) {
                $bill = SupplierBill::where('id', $payment->supplier_bill_id)->lockForUpdate()->first();
                if ($bill) {
                    $restoredPaid = bcsub((string) $bill->amount_paid, (string) $payment->amount, 2);
                    $restoredDue = bcadd((string) $bill->amount_due, (string) $payment->amount, 2);

                    $bill->amount_paid = bccomp($restoredPaid, '0.00', 2) < 0 ? '0.00' : $restoredPaid;
                    $bill->amount_due = $restoredDue;
                    $bill->status = bccomp($bill->amount_paid, '0.00', 2) === 0
                        ? SupplierBillStatus::POSTED
                        : SupplierBillStatus::PARTIALLY_PAID;
                    $bill->save();
                }
            }

            $txnNumber = $this->transactionNumberGenerator->generate();

            $transaction = PayableTransaction::create([
                'transaction_number' => $txnNumber,
                'supplier_id' => $payment->supplier_id,
                'supplier_bill_id' => $payment->supplier_bill_id,
                'supplier_payment_id' => $payment->id,
                'source_type' => 'PAYMENT_REVERSAL',
                'source_id' => $payment->id,
                'source_number' => $payment->payment_number,
                'type' => PayableTransactionType::PAYMENT_REVERSAL,
                'amount' => $payment->amount,
                'debit_amount' => '0.00',
                'credit_amount' => $payment->amount,
                'transaction_date' => Carbon::now()->toDateString(),
                'posting_date' => Carbon::now()->toDateString(),
                'due_date' => null,
                'currency' => 'USD',
                'description' => "Payment Reversal {$payment->payment_number}: {$reason}",
                'notes' => $reason,
                'created_by' => $actor?->id,
            ]);

            $this->recalculateRunningBalances($payment->supplier_id);

            return $transaction;
        });
    }

    /**
     * Calculate authoritative outstanding balance for a supplier.
     * Formula: Total Credit (Bills + Reversals) - Total Debit (Payments)
     */
    public function getSupplierOutstandingBalance(Supplier|int $supplier): string
    {
        $supplierId = $supplier instanceof Supplier ? $supplier->id : (int) $supplier;

        $result = DB::table('payable_transactions')
            ->where('supplier_id', $supplierId)
            ->selectRaw('COALESCE(SUM(credit_amount), 0) - COALESCE(SUM(debit_amount), 0) AS balance')
            ->value('balance');

        return number_format((float) ($result ?? 0.00), 2, '.', '');
    }

    /**
     * Get authoritative outstanding balance for a specific supplier bill.
     */
    public function getBillOutstandingBalance(SupplierBill|int $bill): string
    {
        $billId = $bill instanceof SupplierBill ? $bill->id : (int) $bill;

        $amountDue = SupplierBill::where('id', $billId)->value('amount_due');

        return number_format((float) ($amountDue ?? 0.00), 2, '.', '');
    }

    /**
     * Get the supplier transaction sub-ledger with optional date bounds and pagination.
     */
    public function getSupplierLedger(
        Supplier|int $supplier,
        ?string $startDate = null,
        ?string $endDate = null,
        int $perPage = 50
    ): LengthAwarePaginator|Collection {
        $supplierId = $supplier instanceof Supplier ? $supplier->id : (int) $supplier;

        $query = PayableTransaction::where('supplier_id', $supplierId)
            ->with(['supplierBill', 'supplierPayment', 'creator'])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        return $query->paginate($perPage);
    }

    /**
     * Recalculate deterministic running balances for a supplier across their entire transaction history.
     */
    public function recalculateRunningBalances(Supplier|int $supplier): void
    {
        $supplierId = $supplier instanceof Supplier ? $supplier->id : (int) $supplier;

        $txns = DB::table('payable_transactions')
            ->where('supplier_id', $supplierId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'debit_amount', 'credit_amount']);

        $running = '0.00';
        foreach ($txns as $t) {
            // Liability increases with credit, decreases with debit
            $running = bcadd($running, (string) $t->credit_amount, 2);
            $running = bcsub($running, (string) $t->debit_amount, 2);

            DB::table('payable_transactions')
                ->where('id', $t->id)
                ->update(['running_balance' => $running]);
        }
    }
}
