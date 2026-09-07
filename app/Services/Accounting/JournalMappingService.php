<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\CreditNoteStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InventoryAdjustmentType;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentStatus;
use App\Models\CreditNote;
use App\Models\InventoryAdjustment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundTransaction;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class JournalMappingService
{
    public function __construct(
        protected JournalService $journalService,
        protected AccountService $accountService
    ) {}

    /**
     * Map Invoice Issuance event to GL Journal:
     * Debit: Accounts Receivable (1100) = grand_total
     * Credit: Wholesale Sales Revenue (4010) = subtotal
     * Credit: Sales Tax Payable (2100) = tax_total
     * Debit/Credit: Sales Discounts (4020) for negative/positive adjustment_total
     */
    public function postInvoiceIssued(Invoice $invoice, ?User $actor = null): JournalEntry
    {
        $arAccount = $this->accountService->resolveAccount('1100');
        $revAccount = $this->accountService->resolveAccount('4010');
        $taxAccount = $this->accountService->resolveAccount('2100');
        $discountAccount = $this->accountService->resolveAccount('4020');

        $grandTotal = number_format((float) $invoice->grand_total, 2, '.', '');
        $subtotal = number_format((float) $invoice->subtotal, 2, '.', '');
        $taxTotal = number_format((float) $invoice->tax_total, 2, '.', '');
        $adjustmentTotal = number_format((float) ($invoice->adjustment_total ?? 0.00), 2, '.', '');

        $lines = [];

        // 1. Debit Accounts Receivable for total invoice amount
        $lines[] = [
            'account_id' => $arAccount->id,
            'debit' => $grandTotal,
            'credit' => '0.00',
            'description' => "Invoice #{$invoice->invoice_number} trade receivable",
        ];

        // 2. Credit Sales Revenue for subtotal
        if (bccomp($subtotal, '0.00', 2) > 0) {
            $lines[] = [
                'account_id' => $revAccount->id,
                'debit' => '0.00',
                'credit' => $subtotal,
                'description' => "Invoice #{$invoice->invoice_number} wholesale merchandise revenue",
            ];
        }

        // 3. Credit Sales Tax Payable
        if (bccomp($taxTotal, '0.00', 2) > 0) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'debit' => '0.00',
                'credit' => $taxTotal,
                'description' => "Invoice #{$invoice->invoice_number} sales tax collected",
            ];
        }

        // 4. Adjustments / Discounts
        if (bccomp($adjustmentTotal, '0.00', 2) < 0) {
            // Negative adjustment = Discount (Debit Contra-Revenue)
            $discountAmount = number_format(abs((float) $adjustmentTotal), 2, '.', '');
            $lines[] = [
                'account_id' => $discountAccount->id,
                'debit' => $discountAmount,
                'credit' => '0.00',
                'description' => "Invoice #{$invoice->invoice_number} order adjustment discount",
            ];
        } elseif (bccomp($adjustmentTotal, '0.00', 2) > 0) {
            // Positive adjustment = Additional Charge / Revenue
            $lines[] = [
                'account_id' => $revAccount->id,
                'debit' => '0.00',
                'credit' => $adjustmentTotal,
                'description' => "Invoice #{$invoice->invoice_number} order adjustment charge",
            ];
        }

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'source_number' => $invoice->invoice_number,
            'source_event' => 'INVOICE_ISSUED',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Invoice #{$invoice->invoice_number} revenue & receivable recognition",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Verified Customer Payment to GL Journal:
     * Debit: Cash on Hand (1010) / Undeposited Cheques (1020) / Bank Account (1030) = amount
     * Credit: Accounts Receivable (1100) = amount
     */
    public function postPaymentVerified(Payment $payment, ?User $actor = null): JournalEntry
    {
        $arAccount = $this->accountService->resolveAccount('1100');

        $cashAccountCode = match ($payment->payment_method) {
            PaymentMethod::CASH => '1010', // Cash on Hand
            PaymentMethod::CHEQUE, PaymentMethod::MONEY_ORDER => '1020', // Undeposited Cheques & Money Orders
            default => '1010',
        };

        $cashAccount = $this->accountService->resolveAccount($cashAccountCode);
        $amount = number_format((float) $payment->amount, 2, '.', '');
        $methodLabel = $payment->payment_method ? $payment->payment_method->label() : 'Payment';

        $lines = [
            [
                'account_id' => $cashAccount->id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => "Payment #{$payment->payment_number} ({$methodLabel}) receipt",
            ],
            [
                'account_id' => $arAccount->id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => "Payment #{$payment->payment_number} customer receivable settlement",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'source_number' => $payment->payment_number,
            'source_event' => 'PAYMENT_VERIFIED',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Customer payment #{$payment->payment_number} ({$methodLabel}) verified",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Reversed Customer Payment to GL Journal:
     * Debit: Accounts Receivable (1100) = amount
     * Credit: Cash on Hand (1010) / Undeposited Cheques (1020) / Bank Account (1030) = amount
     */
    public function postPaymentReversed(Payment $payment, ?User $actor = null): JournalEntry
    {
        $arAccount = $this->accountService->resolveAccount('1100');

        $cashAccountCode = match ($payment->payment_method) {
            PaymentMethod::CASH => '1010',
            PaymentMethod::CHEQUE, PaymentMethod::MONEY_ORDER => '1020',
            default => '1010',
        };

        $cashAccount = $this->accountService->resolveAccount($cashAccountCode);
        $amount = number_format((float) $payment->amount, 2, '.', '');
        $reasonLabel = $payment->reversal_reason_code ? $payment->reversal_reason_code->label() : 'Reversal';

        $lines = [
            [
                'account_id' => $arAccount->id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => "Payment #{$payment->payment_number} reversal receivable restoration",
            ],
            [
                'account_id' => $cashAccount->id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => "Payment #{$payment->payment_number} reversal ({$reasonLabel}) cash reduction",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'source_number' => $payment->payment_number,
            'source_event' => 'PAYMENT_REVERSED',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $payment->reversed_at ? Carbon::parse($payment->reversed_at)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Payment #{$payment->payment_number} reversed ({$reasonLabel})",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Issued Credit Note to GL Journal:
     * Debit: Sales Discounts & Allowances (4020) = subtotal
     * Debit: Sales Tax Payable (2100) = tax_total
     * Credit: Accounts Receivable (1100) = total_amount
     */
    public function postCreditNoteIssued(CreditNote $creditNote, ?User $actor = null): JournalEntry
    {
        $discountAccount = $this->accountService->resolveAccount('4020');
        $taxAccount = $this->accountService->resolveAccount('2100');
        $arAccount = $this->accountService->resolveAccount('1100');

        $total = number_format((float) $creditNote->total_amount, 2, '.', '');
        $subtotal = number_format((float) $creditNote->subtotal, 2, '.', '');
        $taxTotal = number_format((float) $creditNote->tax_total, 2, '.', '');

        $lines = [];

        if (bccomp($subtotal, '0.00', 2) > 0) {
            $lines[] = [
                'account_id' => $discountAccount->id,
                'debit' => $subtotal,
                'credit' => '0.00',
                'description' => "Credit Note #{$creditNote->credit_number} sales allowance",
            ];
        }

        if (bccomp($taxTotal, '0.00', 2) > 0) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'debit' => $taxTotal,
                'credit' => '0.00',
                'description' => "Credit Note #{$creditNote->credit_number} sales tax reversal",
            ];
        }

        $lines[] = [
            'account_id' => $arAccount->id,
            'debit' => '0.00',
            'credit' => $total,
            'description' => "Credit Note #{$creditNote->credit_number} receivable reduction",
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'credit_note',
            'source_id' => $creditNote->id,
            'source_number' => $creditNote->credit_number,
            'source_event' => 'CREDIT_NOTE_ISSUED',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $creditNote->issued_at ? Carbon::parse($creditNote->issued_at)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Credit Note #{$creditNote->credit_number} issued for returns/allowances",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Processed Customer Refund to GL Journal:
     * Debit: Accounts Receivable / Customer Credit (1100) = amount
     * Credit: Cash on Hand (1010) / Operating Bank Account (1030) = amount
     */
    public function postRefundProcessed(RefundTransaction $refundTxn, ?User $actor = null): JournalEntry
    {
        $arAccount = $this->accountService->resolveAccount('1100');

        $cashAccountCode = match ($refundTxn->payment_method) {
            PaymentMethod::CASH => '1010',
            PaymentMethod::BANK_TRANSFER, PaymentMethod::CHEQUE, PaymentMethod::MONEY_ORDER => '1030',
            default => '1010',
        };

        $cashAccount = $this->accountService->resolveAccount($cashAccountCode);
        $amount = number_format((float) $refundTxn->amount, 2, '.', '');
        $methodLabel = $refundTxn->payment_method ? $refundTxn->payment_method->label() : 'Refund';

        $lines = [
            [
                'account_id' => $arAccount->id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => "Refund #{$refundTxn->transaction_number} customer credit settlement",
            ],
            [
                'account_id' => $cashAccount->id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => "Refund #{$refundTxn->transaction_number} ({$methodLabel}) disbursement",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'refund_transaction',
            'source_id' => $refundTxn->id,
            'source_number' => $refundTxn->transaction_number,
            'source_event' => 'REFUND_COMPLETED',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $refundTxn->processed_at ? Carbon::parse($refundTxn->processed_at)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Customer refund #{$refundTxn->transaction_number} processed",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Posted Supplier Bill to GL Journal:
     * Debit: Operating & Procurement Expenses (5030) = total_amount
     * Credit: Accounts Payable (2010) = total_amount
     */
    public function postSupplierBillPosted(SupplierBill $bill, ?User $actor = null): JournalEntry
    {
        $expenseAccount = $this->accountService->resolveAccount('5030');
        $apAccount = $this->accountService->resolveAccount('2010');

        $totalAmount = number_format((float) $bill->total_amount, 2, '.', '');

        $lines = [
            [
                'account_id' => $expenseAccount->id,
                'debit' => $totalAmount,
                'credit' => '0.00',
                'description' => "Supplier Bill #{$bill->bill_number} procurement expense",
            ],
            [
                'account_id' => $apAccount->id,
                'debit' => '0.00',
                'credit' => $totalAmount,
                'description' => "Supplier Bill #{$bill->bill_number} trade payable liability",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'supplier_bill',
            'source_id' => $bill->id,
            'source_number' => $bill->bill_number,
            'source_event' => 'BILL_POSTED',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $bill->bill_date ? Carbon::parse($bill->bill_date)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Supplier Bill #{$bill->bill_number} liability recognized",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Completed Supplier Payment to GL Journal:
     * Debit: Accounts Payable (2010) = amount
     * Credit: Operating Bank Account (1030) / Cash on Hand (1010) = amount
     */
    public function postSupplierPaymentCompleted(SupplierPayment $payment, ?User $actor = null): JournalEntry
    {
        $apAccount = $this->accountService->resolveAccount('2010');
        $cashAccountCode = ($payment->payment_method === SupplierPaymentMethod::CASH || $payment->payment_method?->value === 'CASH') ? '1010' : '1030';
        $cashAccount = $this->accountService->resolveAccount($cashAccountCode);

        $amount = number_format((float) $payment->amount, 2, '.', '');
        $methodLabel = $payment->payment_method ? $payment->payment_method->label() : 'Payment';

        $lines = [
            [
                'account_id' => $apAccount->id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => "Supplier Payment #{$payment->payment_number} payable liability reduction",
            ],
            [
                'account_id' => $cashAccount->id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => "Supplier Payment #{$payment->payment_number} ({$methodLabel}) disbursement",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'supplier_payment',
            'source_id' => $payment->id,
            'source_number' => $payment->payment_number,
            'source_event' => 'BILL_PAYMENT',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Supplier Payment #{$payment->payment_number} completed",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Reversed Supplier Payment to GL Journal:
     * Debit: Bank Account (1030) / Cash (1010) = amount
     * Credit: Accounts Payable (2010) = amount
     */
    public function postSupplierPaymentReversed(SupplierPayment $payment, ?User $actor = null): JournalEntry
    {
        $apAccount = $this->accountService->resolveAccount('2010');
        $cashAccountCode = $payment->payment_method === PaymentMethod::CASH ? '1010' : '1030';
        $cashAccount = $this->accountService->resolveAccount($cashAccountCode);

        $amount = number_format((float) $payment->amount, 2, '.', '');

        $lines = [
            [
                'account_id' => $cashAccount->id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => "Supplier Payment #{$payment->payment_number} reversal cash restoration",
            ],
            [
                'account_id' => $apAccount->id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => "Supplier Payment #{$payment->payment_number} reversal payable restoration",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'supplier_payment',
            'source_id' => $payment->id,
            'source_number' => $payment->payment_number,
            'source_event' => 'BILL_PAYMENT_REVERSED',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => $payment->reversed_at ? Carbon::parse($payment->reversed_at)->toDateString() : Carbon::now()->toDateString(),
            'description' => "Supplier Payment #{$payment->payment_number} reversed ({$payment->reversal_reason})",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Order Delivered COGS Recognition:
     * Debit: Cost of Goods Sold (5010) = sum(delivered_quantity * product.cost_price)
     * Credit: Inventory Asset (1200) = sum(delivered_quantity * product.cost_price)
     */
    public function postOrderDeliveredCogs(Order $order, ?User $actor = null): ?JournalEntry
    {
        $cogsAccount = $this->accountService->resolveAccount('5010');
        $inventoryAccount = $this->accountService->resolveAccount('1200');

        $totalCogs = '0.00';
        $order->loadMissing(['items.product']);

        foreach ($order->items as $item) {
            $deliveredQty = max(0, (int) $item->delivered_quantity);
            if ($deliveredQty <= 0) {
                // If delivered_quantity is 0 but order is COMPLETED/APPROVED, check ordered minus cancelled
                if (in_array($order->status, [OrderStatus::COMPLETED, OrderStatus::APPROVED], true) && in_array($order->fulfillment_status, [FulfillmentStatus::DELIVERED, FulfillmentStatus::DISPATCHED], true)) {
                    $deliveredQty = max(0, (int) $item->ordered_quantity - (int) $item->cancelled_quantity);
                }
            }

            if ($deliveredQty > 0 && $item->product) {
                $costPrice = (string) ($item->product->cost_price ?? '0.00');
                if (bccomp($costPrice, '0.00', 2) > 0) {
                    $lineCost = bcmul((string) $deliveredQty, $costPrice, 2);
                    $totalCogs = bcadd($totalCogs, $lineCost, 2);
                }
            }
        }

        if (bccomp($totalCogs, '0.00', 2) <= 0) {
            return null;
        }

        $lines = [
            [
                'account_id' => $cogsAccount->id,
                'debit' => $totalCogs,
                'credit' => '0.00',
                'description' => "Order #{$order->order_number} Cost of Goods Sold recognition",
            ],
            [
                'account_id' => $inventoryAccount->id,
                'debit' => '0.00',
                'credit' => $totalCogs,
                'description' => "Order #{$order->order_number} Inventory reduction at cost",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'order',
            'source_id' => $order->id,
            'source_number' => $order->order_number,
            'source_event' => 'ORDER_DELIVERED_COGS',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => Carbon::now()->toDateString(),
            'description' => "Order #{$order->order_number} COGS & inventory fulfillment",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Map Inventory Adjustment (Gain / Loss):
     * Write-down / Shrinkage: Debit 5020 (Shrinkage), Credit 1200 (Inventory Asset)
     * Receipt / Increase: Debit 1200 (Inventory Asset), Credit 5020 (Shrinkage)
     */
    public function postInventoryAdjustment(InventoryAdjustment $adjustment, ?User $actor = null): ?JournalEntry
    {
        $adjustment->loadMissing(['product']);
        $product = $adjustment->product;

        if (! $product) {
            return null;
        }

        $costPrice = (string) ($product->cost_price ?? '0.00');
        if (bccomp($costPrice, '0.00', 2) <= 0) {
            return null;
        }

        $qty = (string) abs((int) $adjustment->quantity);
        if (bccomp($qty, '0', 0) <= 0) {
            return null;
        }

        $totalValue = bcmul($qty, $costPrice, 2);
        if (bccomp($totalValue, '0.00', 2) <= 0) {
            return null;
        }

        $shrinkageAccount = $this->accountService->resolveAccount('5020');
        $inventoryAccount = $this->accountService->resolveAccount('1200');

        $isIncrease = $adjustment->adjustment_type === InventoryAdjustmentType::INCREASE;

        if ($isIncrease) {
            $lines = [
                [
                    'account_id' => $inventoryAccount->id,
                    'debit' => $totalValue,
                    'credit' => '0.00',
                    'description' => "Inventory Adjustment #{$adjustment->adjustment_number} stock gain",
                ],
                [
                    'account_id' => $shrinkageAccount->id,
                    'debit' => '0.00',
                    'credit' => $totalValue,
                    'description' => "Inventory Adjustment #{$adjustment->adjustment_number} offset",
                ],
            ];
        } else {
            $lines = [
                [
                    'account_id' => $shrinkageAccount->id,
                    'debit' => $totalValue,
                    'credit' => '0.00',
                    'description' => "Inventory Adjustment #{$adjustment->adjustment_number} shrinkage / write-off",
                ],
                [
                    'account_id' => $inventoryAccount->id,
                    'debit' => '0.00',
                    'credit' => $totalValue,
                    'description' => "Inventory Adjustment #{$adjustment->adjustment_number} inventory reduction",
                ],
            ];
        }

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'inventory_adjustment',
            'source_id' => $adjustment->id,
            'source_number' => $adjustment->adjustment_number,
            'source_event' => 'STOCK_ADJUSTMENT',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => Carbon::now()->toDateString(),
            'description' => "Inventory Adjustment #{$adjustment->adjustment_number} valuation adjustment",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }

    /**
     * Synchronize all unposted historical business events to General Ledger.
     *
     * @return array<string, int>
     */
    public function syncUnpostedHistoricalEvents(?User $actor = null): array
    {
        $synced = [
            'invoices' => 0,
            'payments' => 0,
            'payment_reversals' => 0,
            'credit_notes' => 0,
            'refunds' => 0,
            'supplier_bills' => 0,
            'supplier_payments' => 0,
            'supplier_payment_reversals' => 0,
            'cogs' => 0,
            'adjustments' => 0,
        ];

        // 1. Invoices
        $invoices = Invoice::whereNotIn('status', [InvoiceStatus::VOID])->get();
        foreach ($invoices as $invoice) {
            $exists = JournalEntry::where('source_type', 'invoice')
                ->where('source_id', $invoice->id)
                ->where('source_event', 'INVOICE_ISSUED')
                ->exists();

            if (! $exists) {
                try {
                    $this->postInvoiceIssued($invoice, $actor);
                    $synced['invoices']++;
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync invoice failed: {$e->getMessage()}");
                }
            }
        }

        // 2. Payments (Verified & Reversed)
        $payments = Payment::whereIn('status', [PaymentTransactionStatus::VERIFIED, PaymentTransactionStatus::REVERSED])->get();
        foreach ($payments as $payment) {
            $existsVerified = JournalEntry::where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('source_event', 'PAYMENT_VERIFIED')
                ->exists();

            if (! $existsVerified) {
                try {
                    $this->postPaymentVerified($payment, $actor);
                    $synced['payments']++;
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync payment verified failed: {$e->getMessage()}");
                }
            }

            if ($payment->status === PaymentTransactionStatus::REVERSED) {
                $existsReversed = JournalEntry::where('source_type', 'payment')
                    ->where('source_id', $payment->id)
                    ->where('source_event', 'PAYMENT_REVERSED')
                    ->exists();

                if (! $existsReversed) {
                    try {
                        $this->postPaymentReversed($payment, $actor);
                        $synced['payment_reversals']++;
                    } catch (\Throwable $e) {
                        Log::warning("Accounting sync payment reversal failed: {$e->getMessage()}");
                    }
                }
            }
        }

        // 3. Credit Notes
        $creditNotes = CreditNote::whereNotIn('status', [CreditNoteStatus::VOID])->get();
        foreach ($creditNotes as $cn) {
            $exists = JournalEntry::where('source_type', 'credit_note')
                ->where('source_id', $cn->id)
                ->where('source_event', 'CREDIT_NOTE_ISSUED')
                ->exists();

            if (! $exists) {
                try {
                    $this->postCreditNoteIssued($cn, $actor);
                    $synced['credit_notes']++;
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync credit note failed: {$e->getMessage()}");
                }
            }
        }

        // 4. Refunds
        $refunds = RefundTransaction::where('status', RefundTransactionStatus::COMPLETED)->get();
        foreach ($refunds as $refund) {
            $exists = JournalEntry::where('source_type', 'refund_transaction')
                ->where('source_id', $refund->id)
                ->where('source_event', 'REFUND_COMPLETED')
                ->exists();

            if (! $exists) {
                try {
                    $this->postRefundProcessed($refund, $actor);
                    $synced['refunds']++;
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync refund failed: {$e->getMessage()}");
                }
            }
        }

        // 5. Supplier Bills
        $bills = SupplierBill::whereIn('status', [SupplierBillStatus::POSTED, SupplierBillStatus::PARTIALLY_PAID, SupplierBillStatus::PAID])->get();
        foreach ($bills as $bill) {
            $exists = JournalEntry::where('source_type', 'supplier_bill')
                ->where('source_id', $bill->id)
                ->where('source_event', 'BILL_POSTED')
                ->exists();

            if (! $exists) {
                try {
                    $this->postSupplierBillPosted($bill, $actor);
                    $synced['supplier_bills']++;
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync supplier bill failed: {$e->getMessage()}");
                }
            }
        }

        // 6. Supplier Payments
        $supplierPayments = SupplierPayment::whereIn('status', [SupplierPaymentStatus::COMPLETED, SupplierPaymentStatus::REVERSED])->get();
        foreach ($supplierPayments as $sp) {
            $existsCompleted = JournalEntry::where('source_type', 'supplier_payment')
                ->where('source_id', $sp->id)
                ->where('source_event', 'BILL_PAYMENT')
                ->exists();

            if (! $existsCompleted) {
                try {
                    $this->postSupplierPaymentCompleted($sp, $actor);
                    $synced['supplier_payments']++;
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync supplier payment completed failed: {$e->getMessage()}");
                }
            }

            if ($sp->status === SupplierPaymentStatus::REVERSED) {
                $existsReversed = JournalEntry::where('source_type', 'supplier_payment')
                    ->where('source_id', $sp->id)
                    ->where('source_event', 'BILL_PAYMENT_REVERSED')
                    ->exists();

                if (! $existsReversed) {
                    try {
                        $this->postSupplierPaymentReversed($sp, $actor);
                        $synced['supplier_payment_reversals']++;
                    } catch (\Throwable $e) {
                        Log::warning("Accounting sync supplier payment reversal failed: {$e->getMessage()}");
                    }
                }
            }
        }

        // 7. COGS for Delivered Orders
        $orders = Order::whereIn('status', [OrderStatus::COMPLETED, OrderStatus::APPROVED])
            ->whereIn('fulfillment_status', [FulfillmentStatus::DELIVERED, FulfillmentStatus::DISPATCHED])
            ->get();

        foreach ($orders as $order) {
            $exists = JournalEntry::where('source_type', 'order')
                ->where('source_id', $order->id)
                ->where('source_event', 'ORDER_DELIVERED_COGS')
                ->exists();

            if (! $exists) {
                try {
                    $cogsResult = $this->postOrderDeliveredCogs($order, $actor);
                    if ($cogsResult) {
                        $synced['cogs']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync COGS failed: {$e->getMessage()}");
                }
            }
        }

        // 8. Inventory Adjustments
        $adjustments = InventoryAdjustment::all();
        foreach ($adjustments as $adj) {
            $exists = JournalEntry::where('source_type', 'inventory_adjustment')
                ->where('source_id', $adj->id)
                ->where('source_event', 'STOCK_ADJUSTMENT')
                ->exists();

            if (! $exists) {
                try {
                    $adjResult = $this->postInventoryAdjustment($adj, $actor);
                    if ($adjResult) {
                        $synced['adjustments']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting sync adjustment failed: {$e->getMessage()}");
                }
            }
        }

        return $synced;
    }
}
