<?php

declare(strict_types=1);

namespace App\Services\Credit;

use App\Enums\ReturnStatus;
use App\Models\Invoice;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CreditEligibilityService
{
    /**
     * Calculate and return authoritative financial credit eligibility for an approved return request.
     *
     * @return array{
     *     return_request_id: int,
     *     order_id: int,
     *     customer_id: int,
     *     salesman_id: int|null,
     *     invoice_id: int|null,
     *     currency: string,
     *     eligible_subtotal: string,
     *     eligible_tax: string,
     *     eligible_total: string,
     *     items: array<int, array{
     *         return_request_item_id: int,
     *         order_item_id: int,
     *         product_id: int,
     *         product_name_snapshot: string,
     *         sku_snapshot: string,
     *         eligible_quantity: int,
     *         accepted_good_quantity: int,
     *         accepted_damaged_quantity: int,
     *         rejected_quantity: int,
     *         unit_price_snapshot: string,
     *         tax_rate_snapshot: string,
     *         tax_amount_snapshot: string,
     *         line_subtotal: string,
     *         line_total: string
     *     }>,
     *     calculated_at: string
     * }
     */
    public function calculateReturnEligibility(ReturnRequest $returnRequest): array
    {
        // 1. Return lifecycle state verification: Only approved returns generate financial credit eligibility
        if ($returnRequest->status !== ReturnStatus::APPROVED) {
            throw ValidationException::withMessages([
                'return_request' => "Return request #{$returnRequest->return_number} is in status [{$returnRequest->status->value}]. Only APPROVED returns can generate credit notes.",
            ]);
        }

        // 2. Duplicate credit processing guard
        if ($returnRequest->is_credit_processed) {
            throw ValidationException::withMessages([
                'return_request' => "Credit has already been processed for return request #{$returnRequest->return_number} (Credit Note ID: {$returnRequest->credit_note_id}).",
            ]);
        }

        $order = $returnRequest->order;
        if (! $order) {
            throw ValidationException::withMessages([
                'return_request' => "Source order not found for return request #{$returnRequest->return_number}.",
            ]);
        }

        $items = $returnRequest->items()->with(['orderItem', 'product'])->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'return_request' => "Return request #{$returnRequest->return_number} has no line items.",
            ]);
        }

        // 3. Resolve associated issued invoice where available
        $invoiceId = Invoice::where('order_id', $order->id)->value('id');

        $eligibleSubtotal = '0.00';
        $eligibleTax = '0.00';
        $eligibleTotal = '0.00';
        $eligibleItems = [];

        foreach ($items as $item) {
            $itemBreakdown = $this->calculateItemEligibility($item);

            if ($itemBreakdown['eligible_quantity'] > 0) {
                $eligibleSubtotal = bcadd($eligibleSubtotal, $itemBreakdown['line_subtotal'], 2);
                $eligibleTax = bcadd($eligibleTax, $itemBreakdown['tax_amount_snapshot'], 2);
                $eligibleTotal = bcadd($eligibleTotal, $itemBreakdown['line_total'], 2);

                $eligibleItems[] = $itemBreakdown;
            }
        }

        if (empty($eligibleItems) || bccomp($eligibleTotal, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'return_request' => "Return request #{$returnRequest->return_number} has zero accepted items eligible for credit.",
            ]);
        }

        return [
            'return_request_id' => $returnRequest->id,
            'order_id' => $order->id,
            'customer_id' => $returnRequest->customer_id,
            'salesman_id' => $returnRequest->salesman_id,
            'invoice_id' => $invoiceId,
            'currency' => $order->currency ?? 'USD',
            'eligible_subtotal' => $eligibleSubtotal,
            'eligible_tax' => $eligibleTax,
            'eligible_total' => $eligibleTotal,
            'items' => $eligibleItems,
            'calculated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Calculate credit eligibility for an individual return item.
     *
     * Damaged Goods Policy Boundary (RULE-RET-003):
     * - Accepted Good units and Accepted Damaged units confirmed in warehouse custody are eligible for credit note issuance.
     * - Rejected units are strictly ineligible for financial credit.
     *
     * @return array{
     *     return_request_item_id: int,
     *     order_item_id: int,
     *     product_id: int,
     *     product_name_snapshot: string,
     *     sku_snapshot: string,
     *     eligible_quantity: int,
     *     accepted_good_quantity: int,
     *     accepted_damaged_quantity: int,
     *     rejected_quantity: int,
     *     unit_price_snapshot: string,
     *     tax_rate_snapshot: string,
     *     tax_amount_snapshot: string,
     *     line_subtotal: string,
     *     line_total: string
     * }
     */
    public function calculateItemEligibility(ReturnRequestItem $item): array
    {
        $goodQty = (int) $item->accepted_good_quantity;
        $damagedQty = (int) $item->accepted_damaged_quantity;
        $rejectedQty = (int) $item->rejected_quantity;

        // Eligible quantity is strictly accepted units in warehouse custody
        $eligibleQty = $goodQty + $damagedQty;

        $orderItem = $item->orderItem;
        $product = $item->product;

        $productName = $orderItem?->product_name_snapshot ?? $product?->name ?? 'Unknown Product';
        $sku = $orderItem?->sku_snapshot ?? $product?->sku ?? 'UNKNOWN';

        $unitPrice = (string) ($item->unit_price_snapshot ?? $orderItem?->unit_price ?? '0.00');
        $taxRate = (string) ($item->tax_rate_snapshot ?? $orderItem?->tax_rate ?? '0.0000');

        if ($eligibleQty <= 0) {
            return [
                'return_request_item_id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'product_id' => $item->product_id,
                'product_name_snapshot' => $productName,
                'sku_snapshot' => $sku,
                'eligible_quantity' => 0,
                'accepted_good_quantity' => $goodQty,
                'accepted_damaged_quantity' => $damagedQty,
                'rejected_quantity' => $rejectedQty,
                'unit_price_snapshot' => number_format((float) $unitPrice, 2, '.', ''),
                'tax_rate_snapshot' => number_format((float) $taxRate, 4, '.', ''),
                'tax_amount_snapshot' => '0.00',
                'line_subtotal' => '0.00',
                'line_total' => '0.00',
            ];
        }

        // BCMath high precision money calculation
        $rawSubtotal = bcmul($unitPrice, (string) $eligibleQty, 4);
        $rawTax = bcmul($rawSubtotal, $taxRate, 4);

        $lineSubtotal = number_format((float) $rawSubtotal, 2, '.', '');
        $lineTax = number_format((float) $rawTax, 2, '.', '');
        $lineTotal = number_format((float) bcadd($lineSubtotal, $lineTax, 4), 2, '.', '');

        return [
            'return_request_item_id' => $item->id,
            'order_item_id' => $item->order_item_id,
            'product_id' => $item->product_id,
            'product_name_snapshot' => $productName,
            'sku_snapshot' => $sku,
            'eligible_quantity' => $eligibleQty,
            'accepted_good_quantity' => $goodQty,
            'accepted_damaged_quantity' => $damagedQty,
            'rejected_quantity' => $rejectedQty,
            'unit_price_snapshot' => number_format((float) $unitPrice, 2, '.', ''),
            'tax_rate_snapshot' => number_format((float) $taxRate, 4, '.', ''),
            'tax_amount_snapshot' => $lineTax,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
        ];
    }
}
