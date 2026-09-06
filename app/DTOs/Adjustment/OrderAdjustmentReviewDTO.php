<?php

namespace App\DTOs\Adjustment;

class OrderAdjustmentReviewDTO
{
    /**
     * @param array<int, string> $staleReasons
     * @param array<int, OrderAdjustmentItemReviewDTO> $lineEvaluations
     * @param array<string, string> $requestFinancialSnapshot
     * @param array<string, string> $liveFinancialPreview
     */
    public function __construct(
        public readonly int $adjustmentId,
        public readonly string $adjustmentNumber,
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly int $orderVersionSnapshot,
        public readonly int $currentOrderVersion,
        public readonly string $orderStatusSnapshot,
        public readonly string $currentOrderStatus,
        public readonly bool $isStale,
        public readonly array $staleReasons,
        public readonly string $evaluationStatus,
        public readonly bool $hasAllocationImpact,
        public readonly int $totalAffectedAllocationQuantity,
        public readonly int $totalUnpickedAffectedQuantity,
        public readonly bool $encroachesOnPicked,
        public readonly array $lineEvaluations,
        public readonly array $requestFinancialSnapshot,
        public readonly array $liveFinancialPreview,
        public readonly bool $financialDiscrepancy,
    ) {}

    /**
     * Convert to an array for Inertia serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'adjustment_id' => $this->adjustmentId,
            'adjustment_number' => $this->adjustmentNumber,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'order_version_snapshot' => $this->orderVersionSnapshot,
            'current_order_version' => $this->currentOrderVersion,
            'order_status_snapshot' => $this->orderStatusSnapshot,
            'current_order_status' => $this->currentOrderStatus,
            'is_stale' => $this->isStale,
            'stale_reasons' => $this->staleReasons,
            'evaluation_status' => $this->evaluationStatus,
            'has_allocation_impact' => $this->hasAllocationImpact,
            'total_affected_allocation_quantity' => $this->totalAffectedAllocationQuantity,
            'total_unpicked_affected_quantity' => $this->totalUnpickedAffectedQuantity,
            'encroaches_on_picked' => $this->encroachesOnPicked,
            'line_evaluations' => array_map(fn (OrderAdjustmentItemReviewDTO $line) => $line->toArray(), $this->lineEvaluations),
            'request_financial_snapshot' => $this->requestFinancialSnapshot,
            'live_financial_preview' => $this->liveFinancialPreview,
            'financial_discrepancy' => $this->financialDiscrepancy,
        ];
    }
}
