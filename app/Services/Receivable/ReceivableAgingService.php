<?php

declare(strict_types=1);

namespace App\Services\Receivable;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReceivableAgingService
{
    public function __construct(
        protected ReceivableLedgerService $ledgerService
    ) {}

    /**
     * Compute receivable aging buckets for a single customer.
     *
     * @return array{
     *     customer_id: int,
     *     customer_name: string,
     *     customer_code: string,
     *     reference_date: string,
     *     current: string,
     *     days_1_30: string,
     *     days_31_60: string,
     *     days_61_90: string,
     *     days_91_plus: string,
     *     total_receivable: string,
     *     pending_payments: string,
     *     operational_outstanding: string,
     *     available_credit: string,
     *     invoices: array<int, array<string, mixed>>
     * }
     */
    public function getAgingForCustomer(Customer|int $customerInput, ?Carbon $referenceDate = null): array
    {
        /** @var Customer $customer */
        $customer = $customerInput instanceof Customer
            ? $customerInput
            : Customer::findOrFail($customerInput);

        $refDate = ($referenceDate ?? Carbon::now())->startOfDay();

        // 0. Ensure ledger sync
        $this->ledgerService->syncUnpostedHistoricalEvents();
        $financialSummary = $this->ledgerService->getCustomerFinancialSummary($customer);

        // 1. Fetch all open invoices for this customer (amount_due > 0 and not VOID)
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [InvoiceStatus::VOID->value])
            ->where('amount_due', '>', 0)
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $current = '0.00';
        $days1To30 = '0.00';
        $days31To60 = '0.00';
        $days61To90 = '0.00';
        $days91Plus = '0.00';
        $totalReceivable = '0.00';

        $invoiceDetails = [];

        foreach ($invoices as $invoice) {
            $amountDue = number_format((float) $invoice->amount_due, 2, '.', '');
            if (bccomp($amountDue, '0.00', 2) <= 0) {
                continue;
            }

            $totalReceivable = bcadd($totalReceivable, $amountDue, 2);

            $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->startOfDay() : $refDate;
            
            // Days overdue = reference date - due date
            $daysOverdue = $dueDate->isAfter($refDate) ? 0 : (int) $dueDate->diffInDays($refDate);

            $bucket = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => 'days_1_30',
                $daysOverdue <= 60 => 'days_31_60',
                $daysOverdue <= 90 => 'days_61_90',
                default => 'days_91_plus',
            };

            match ($bucket) {
                'current' => $current = bcadd($current, $amountDue, 2),
                'days_1_30' => $days1To30 = bcadd($days1To30, $amountDue, 2),
                'days_31_60' => $days31To60 = bcadd($days31To60, $amountDue, 2),
                'days_61_90' => $days61To90 = bcadd($days61To90, $amountDue, 2),
                'days_91_plus' => $days91Plus = bcadd($days91Plus, $amountDue, 2),
            };

            $invoiceDetails[] = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'grand_total' => (string) $invoice->grand_total,
                'amount_paid' => (string) $invoice->amount_paid,
                'amount_due' => $amountDue,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
                'status' => $invoice->status->value,
            ];
        }

        // 2. Also check active un-invoiced orders
        $uninvoicedOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                OrderStatus::APPROVED->value,
                OrderStatus::PROCESSING->value,
                OrderStatus::COMPLETED->value,
            ])
            ->whereDoesntHave('invoices')
            ->get();

        foreach ($uninvoicedOrders as $order) {
            $orderGrandTotal = number_format((float) $order->grand_total, 2, '.', '');
            if (bccomp($orderGrandTotal, '0.00', 2) <= 0) {
                continue;
            }

            $totalReceivable = bcadd($totalReceivable, $orderGrandTotal, 2);
            $orderDate = $order->created_at ? Carbon::parse($order->created_at)->startOfDay() : $refDate;
            $daysOverdue = $orderDate->isAfter($refDate) ? 0 : (int) $orderDate->diffInDays($refDate);

            $bucket = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => 'days_1_30',
                $daysOverdue <= 60 => 'days_31_60',
                $daysOverdue <= 90 => 'days_61_90',
                default => 'days_91_plus',
            };

            match ($bucket) {
                'current' => $current = bcadd($current, $orderGrandTotal, 2),
                'days_1_30' => $days1To30 = bcadd($days1To30, $orderGrandTotal, 2),
                'days_31_60' => $days31To60 = bcadd($days31To60, $orderGrandTotal, 2),
                'days_61_90' => $days61To90 = bcadd($days61To90, $orderGrandTotal, 2),
                'days_91_plus' => $days91Plus = bcadd($days91Plus, $orderGrandTotal, 2),
            };

            $invoiceDetails[] = [
                'id' => $order->id,
                'invoice_number' => "ORD-{$order->order_number} (Unbilled)",
                'invoice_date' => $order->created_at?->toDateString(),
                'due_date' => $order->created_at?->toDateString(),
                'grand_total' => $orderGrandTotal,
                'amount_paid' => '0.00',
                'amount_due' => $orderGrandTotal,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
                'status' => 'PENDING_INVOICE',
            ];
        }

        // Align total receivable with authoritative net receivable if invoices exist
        if (bccomp($financialSummary['net_receivable'], '0.00', 2) > 0 && bccomp($totalReceivable, '0.00', 2) === 0) {
            $totalReceivable = $financialSummary['net_receivable'];
            $current = $financialSummary['net_receivable'];
        }

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_code' => $customer->code ?? $customer->customer_code ?? '',
            'reference_date' => $refDate->toDateString(),
            'current' => $current,
            'days_1_30' => $days1To30,
            'days_31_60' => $days31To60,
            'days_61_90' => $days61To90,
            'days_91_plus' => $days91Plus,
            'total_receivable' => $totalReceivable,
            'pending_payments' => $financialSummary['pending_payments'],
            'operational_outstanding' => $financialSummary['operational_outstanding'],
            'available_credit' => $financialSummary['available_credit'],
            'invoices' => $invoiceDetails,
        ];
    }

    /**
     * Compute system-wide or salesman-scoped aging summary across customers.
     *
     * @return array{
     *     reference_date: string,
     *     summary: array{
     *         current: string,
     *         days_1_30: string,
     *         days_31_60: string,
     *         days_61_90: string,
     *         days_91_plus: string,
     *         total_receivable: string,
     *         total_pending_payments: string,
     *         total_operational_outstanding: string,
     *         total_available_credit: string
     *     },
     *     customers: array<int, array<string, mixed>>
     * }
     */
    public function getAgingReport(?Carbon $referenceDate = null, ?User $scopedUser = null, ?string $searchTerm = null): array
    {
        $refDate = ($referenceDate ?? Carbon::now())->startOfDay();

        $customerQuery = Customer::query()->orderBy('name', 'asc');

        if ($scopedUser && $scopedUser->isSalesman()) {
            $customerQuery->where('salesman_id', $scopedUser->id);
        }

        if ($searchTerm) {
            $customerQuery->where(function (Builder $q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('code', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        $customers = $customerQuery->get();

        $totalCurrent = '0.00';
        $totalDays1To30 = '0.00';
        $totalDays31To60 = '0.00';
        $totalDays61To90 = '0.00';
        $totalDays91Plus = '0.00';
        $totalReceivable = '0.00';
        $totalPendingPayments = '0.00';
        $totalOperationalOutstanding = '0.00';
        $totalAvailableCredit = '0.00';

        $customerRows = [];

        foreach ($customers as $customer) {
            $aging = $this->getAgingForCustomer($customer, $refDate);

            $totalCurrent = bcadd($totalCurrent, $aging['current'], 2);
            $totalDays1To30 = bcadd($totalDays1To30, $aging['days_1_30'], 2);
            $totalDays31To60 = bcadd($totalDays31To60, $aging['days_31_60'], 2);
            $totalDays61To90 = bcadd($totalDays61To90, $aging['days_61_90'], 2);
            $totalDays91Plus = bcadd($totalDays91Plus, $aging['days_91_plus'], 2);
            $totalReceivable = bcadd($totalReceivable, $aging['total_receivable'], 2);
            $totalPendingPayments = bcadd($totalPendingPayments, $aging['pending_payments'], 2);
            $totalOperationalOutstanding = bcadd($totalOperationalOutstanding, $aging['operational_outstanding'], 2);
            $totalAvailableCredit = bcadd($totalAvailableCredit, $aging['available_credit'], 2);

            $customerRows[] = $aging;
        }

        return [
            'reference_date' => $refDate->toDateString(),
            'summary' => [
                'current' => $totalCurrent,
                'days_1_30' => $totalDays1To30,
                'days_31_60' => $totalDays31To60,
                'days_61_90' => $totalDays61To90,
                'days_91_plus' => $totalDays91Plus,
                'total_receivable' => $totalReceivable,
                'total_pending_payments' => $totalPendingPayments,
                'total_operational_outstanding' => $totalOperationalOutstanding,
                'total_available_credit' => $totalAvailableCredit,
            ],
            'customers' => $customerRows,
        ];
    }
}
