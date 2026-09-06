<?php

namespace App\Services\Adjustment;

use App\Enums\AllocationStatus;
use App\Enums\OrderAdjustmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItemAllocation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderAdjustmentClassifier
{
    public const FLAG_CONFLICTED = 'CONFLICTED';
    public const FLAG_INELIGIBLE_LIFECYCLE = 'INELIGIBLE_LIFECYCLE';
    public const FLAG_PICKED_ENCROACHMENT = 'PICKED_ENCROACHMENT';
    public const FLAG_STALE_VERSION = 'STALE_VERSION';
    public const FLAG_STALE_STATUS = 'STALE_STATUS';
    public const FLAG_AGING = 'AGING';

    public const PRECEDENCE = [
        self::FLAG_CONFLICTED,
        self::FLAG_INELIGIBLE_LIFECYCLE,
        self::FLAG_PICKED_ENCROACHMENT,
        self::FLAG_STALE_VERSION,
        self::FLAG_STALE_STATUS,
        self::FLAG_AGING,
    ];

    public const ELIGIBLE_ORDER_STATUSES = [
        OrderStatus::SUBMITTED,
        OrderStatus::PENDING_APPROVAL,
        OrderStatus::APPROVED,
        OrderStatus::PROCESSING,
    ];

    /**
     * Get the operational aging threshold (24 hours ago).
     */
    public static function getAgingThreshold(): Carbon
    {
        return Carbon::now()->subHours(24);
    }

    /**
     * Determine if an order lifecycle status allows active adjustment review/application.
     */
    public static function isOrderLifecycleEligible(OrderStatus|string $status): bool
    {
        $statusValue = $status instanceof OrderStatus ? $status->value : (string) $status;
        $eligibleValues = array_map(fn (OrderStatus $s) => $s->value, self::ELIGIBLE_ORDER_STATUSES);

        return in_array($statusValue, $eligibleValues, true);
    }

    /**
     * Authoritatively classify an adjustment model against live order state.
     *
     * @return array{
     *     attention_flags: string[],
     *     has_blocker: bool,
     *     active_blockers: string[],
     *     is_aging: bool,
     *     needs_attention: bool,
     *     is_ready_to_apply: bool,
     *     primary_exception: string|null,
     *     age_hours: int,
     *     age_relative: string
     * }
     */
    public static function classify(OrderAdjustment $adjustment, ?Order $order = null): array
    {
        $order = $order ?? $adjustment->order;

        $flags = [];

        // 1. Version Mismatch
        if ($order && (int) $order->version !== (int) $adjustment->order_version_snapshot) {
            $flags[] = self::FLAG_STALE_VERSION;
        }

        // 2. Status Mismatch
        $orderStatusValue = $order ? ($order->status instanceof OrderStatus ? $order->status->value : (string) $order->status) : '';
        $snapshotStatusValue = is_string($adjustment->order_status_snapshot)
            ? $adjustment->order_status_snapshot
            : ($adjustment->order_status_snapshot instanceof OrderStatus ? $adjustment->order_status_snapshot->value : '');

        if ($order && $orderStatusValue !== $snapshotStatusValue) {
            $flags[] = self::FLAG_STALE_STATUS;
        }

        // 3. Order Lifecycle Check
        if ($order && ! self::isOrderLifecycleEligible($order->status)) {
            $flags[] = self::FLAG_INELIGIBLE_LIFECYCLE;
        }

        // 4. Line Items Check (Conflict & Picked Encroachment)
        $hasConflict = false;
        $hasEncroachment = false;

        foreach ($adjustment->items as $adjItem) {
            $orderItem = $adjItem->orderItem;
            if (! $orderItem) {
                continue;
            }

            $currentFulfillable = $orderItem->fulfillableQuantity();
            $reduction = (int) $adjItem->requested_quantity_reduction;

            if ($reduction > $currentFulfillable) {
                $hasConflict = true;
            }

            // Picked Encroachment Check
            $activeAllocations = $orderItem->relationLoaded('allocations')
                ? $orderItem->allocations->filter(fn (OrderItemAllocation $a) => ! in_array($a->status, [AllocationStatus::CANCELLED, AllocationStatus::RELEASED], true))
                : $orderItem->allocations()->whereNotIn('status', [AllocationStatus::CANCELLED, AllocationStatus::RELEASED])->get();

            $totalPickedOnActive = (int) $activeAllocations->sum('picked_quantity');
            $totalAllocatedOnActive = (int) $activeAllocations->sum('allocated_quantity');
            $unpickedAllocated = max(0, $totalAllocatedOnActive - $totalPickedOnActive);
            $currentUnallocated = $orderItem->unallocatedQuantity();
            $currentAffected = max(0, $reduction - $currentUnallocated);

            if ($currentAffected > $unpickedAllocated) {
                $hasEncroachment = true;
            }
        }

        if ($hasConflict) {
            $flags[] = self::FLAG_CONFLICTED;
        }

        if ($hasEncroachment) {
            $flags[] = self::FLAG_PICKED_ENCROACHMENT;
        }

        // 5. Aging Check: SUBMITTED for > 24 hours
        $isAging = false;
        $agingThreshold = self::getAgingThreshold();
        if ($adjustment->status === OrderAdjustmentStatus::SUBMITTED && $adjustment->requested_at && $adjustment->requested_at->lt($agingThreshold)) {
            $flags[] = self::FLAG_AGING;
            $isAging = true;
        }

        $blockerFlags = [
            self::FLAG_CONFLICTED,
            self::FLAG_INELIGIBLE_LIFECYCLE,
            self::FLAG_PICKED_ENCROACHMENT,
            self::FLAG_STALE_VERSION,
            self::FLAG_STALE_STATUS,
        ];

        $activeBlockers = array_values(array_intersect($flags, $blockerFlags));
        $hasBlocker = count($activeBlockers) > 0;

        // Open adjustments needing attention
        $isOpen = in_array($adjustment->status, [OrderAdjustmentStatus::SUBMITTED, OrderAdjustmentStatus::APPROVED], true);
        $needsAttention = $isOpen && ($hasBlocker || $isAging);

        // Ready to apply: APPROVED and NO active blockers
        $isReadyToApply = ($adjustment->status === OrderAdjustmentStatus::APPROVED) && ! $hasBlocker;

        // Determine primary exception based on precedence
        $primaryException = null;
        foreach (self::PRECEDENCE as $candidate) {
            if (in_array($candidate, $flags, true)) {
                $primaryException = $candidate;
                break;
            }
        }

        $ageHours = $adjustment->requested_at ? (int) $adjustment->requested_at->diffInHours(Carbon::now()) : 0;
        $ageRelative = $adjustment->requested_at ? $adjustment->requested_at->diffForHumans(['short' => true]) : '—';

        return [
            'attention_flags' => $flags,
            'has_blocker' => $hasBlocker,
            'active_blockers' => $activeBlockers,
            'is_aging' => $isAging,
            'needs_attention' => $needsAttention,
            'is_ready_to_apply' => $isReadyToApply,
            'primary_exception' => $primaryException,
            'age_hours' => $ageHours,
            'age_relative' => $ageRelative,
        ];
    }

    /**
     * Apply the canonical queue scope to an OrderAdjustment Eloquent query.
     */
    public static function applyQueueScope(Builder $query, string $queue): Builder
    {
        return match ($queue) {
            'attention' => self::scopeAttention($query),
            'pending' => $query->where('order_adjustments.status', OrderAdjustmentStatus::SUBMITTED->value),
            'ready_to_apply' => self::scopeReadyToApply($query),
            'applied' => $query->where('order_adjustments.status', OrderAdjustmentStatus::APPLIED->value),
            'reversed' => $query->where('order_adjustments.status', OrderAdjustmentStatus::REVERSED->value),
            'closed' => $query->whereIn('order_adjustments.status', [
                OrderAdjustmentStatus::REJECTED->value,
                OrderAdjustmentStatus::CANCELLED->value,
            ]),
            'all' => $query,
            default => $query->where('order_adjustments.status', OrderAdjustmentStatus::SUBMITTED->value),
        };
    }

    /**
     * Scope for adjustments needing attention:
     * Open (SUBMITTED or APPROVED) AND (has_blocker OR is_aging).
     */
    public static function scopeAttention(Builder $query): Builder
    {
        $agingThreshold = self::getAgingThreshold();

        return $query->whereIn('order_adjustments.status', [
            OrderAdjustmentStatus::SUBMITTED->value,
            OrderAdjustmentStatus::APPROVED->value,
        ])->where(function (Builder $q) use ($agingThreshold) {
            $q->where(function (Builder $bq) {
                self::applyBlockerConditions($bq);
            })->orWhere(function (Builder $aq) use ($agingThreshold) {
                $aq->where('order_adjustments.status', OrderAdjustmentStatus::SUBMITTED->value)
                    ->where('order_adjustments.requested_at', '<', $agingThreshold);
            });
        });
    }

    /**
     * Scope for Ready to Apply adjustments:
     * APPROVED AND NO active blockers.
     */
    public static function scopeReadyToApply(Builder $query): Builder
    {
        return $query->where('order_adjustments.status', OrderAdjustmentStatus::APPROVED->value)
            ->whereNot(function (Builder $q) {
                self::applyBlockerConditions($q);
            });
    }

    /**
     * Apply the specific blocker sub-conditions (Version, Status, Lifecycle, Conflict, Encroachment).
     */
    public static function applyBlockerConditions(Builder $query): void
    {
        $eligibleStatuses = array_map(fn (OrderStatus $s) => $s->value, self::ELIGIBLE_ORDER_STATUSES);

        $query->where(function (Builder $q) use ($eligibleStatuses) {
            // 1. Version Mismatch
            $q->whereHas('order', function (Builder $oq) {
                $oq->whereColumn('order_adjustments.order_version_snapshot', '!=', 'orders.version');
            })
            // 2. Status Mismatch
            ->orWhereHas('order', function (Builder $oq) {
                $oq->whereColumn('order_adjustments.order_status_snapshot', '!=', 'orders.status');
            })
            // 3. Ineligible Lifecycle
            ->orWhereHas('order', function (Builder $oq) use ($eligibleStatuses) {
                $oq->whereNotIn('orders.status', $eligibleStatuses);
            })
            // 4. Quantity Conflict
            ->orWhereExists(function ($sq) {
                $sq->select(DB::raw(1))
                    ->from('order_adjustment_items as oai')
                    ->join('order_items as oi', 'oi.id', '=', 'oai.order_item_id')
                    ->whereColumn('oai.adjustment_id', 'order_adjustments.id')
                    ->whereRaw('oai.requested_quantity_reduction > (oi.ordered_quantity - oi.cancelled_quantity)');
            })
            // 5. Picked Encroachment
            ->orWhereExists(function ($sq) {
                $sq->select(DB::raw(1))
                    ->from('order_adjustment_items as oai')
                    ->join('order_items as oi', 'oi.id', '=', 'oai.order_item_id')
                    ->whereColumn('oai.adjustment_id', 'order_adjustments.id')
                    ->whereRaw('
                        (SELECT COALESCE(SUM(oia.picked_quantity), 0)
                         FROM order_item_allocations oia
                         WHERE oia.order_item_id = oi.id AND oia.status NOT IN (\'CANCELLED\', \'RELEASED\')) > 0
                        AND ((oi.ordered_quantity - oi.cancelled_quantity) - oai.requested_quantity_reduction) < (
                            SELECT COALESCE(SUM(oia.picked_quantity), 0)
                            FROM order_item_allocations oia
                            WHERE oia.order_item_id = oi.id AND oia.status NOT IN (\'CANCELLED\', \'RELEASED\')
                        )
                    ');
            });
        });
    }

    /**
     * Apply specific exception type filter.
     */
    public static function applyExceptionTypeScope(Builder $query, string $exceptionType): Builder
    {
        $eligibleStatuses = array_map(fn (OrderStatus $s) => $s->value, self::ELIGIBLE_ORDER_STATUSES);
        $agingThreshold = self::getAgingThreshold();

        return match ($exceptionType) {
            'CONFLICTED' => $query->whereExists(function ($sq) {
                $sq->select(DB::raw(1))
                    ->from('order_adjustment_items as oai')
                    ->join('order_items as oi', 'oi.id', '=', 'oai.order_item_id')
                    ->whereColumn('oai.adjustment_id', 'order_adjustments.id')
                    ->whereRaw('oai.requested_quantity_reduction > (oi.ordered_quantity - oi.cancelled_quantity)');
            }),
            'INELIGIBLE_LIFECYCLE' => $query->whereHas('order', function (Builder $oq) use ($eligibleStatuses) {
                $oq->whereNotIn('orders.status', $eligibleStatuses);
            }),
            'PICKED_ENCROACHMENT' => $query->whereExists(function ($sq) {
                $sq->select(DB::raw(1))
                    ->from('order_adjustment_items as oai')
                    ->join('order_items as oi', 'oi.id', '=', 'oai.order_item_id')
                    ->whereColumn('oai.adjustment_id', 'order_adjustments.id')
                    ->whereRaw('
                        (SELECT COALESCE(SUM(oia.picked_quantity), 0)
                         FROM order_item_allocations oia
                         WHERE oia.order_item_id = oi.id AND oia.status NOT IN (\'CANCELLED\', \'RELEASED\')) > 0
                        AND ((oi.ordered_quantity - oi.cancelled_quantity) - oai.requested_quantity_reduction) < (
                            SELECT COALESCE(SUM(oia.picked_quantity), 0)
                            FROM order_item_allocations oia
                            WHERE oia.order_item_id = oi.id AND oia.status NOT IN (\'CANCELLED\', \'RELEASED\')
                        )
                    ');
            }),
            'STALE' => $query->where(function (Builder $q) {
                $q->whereHas('order', function (Builder $oq) {
                    $oq->whereColumn('order_adjustments.order_version_snapshot', '!=', 'orders.version')
                        ->orWhereColumn('order_adjustments.order_status_snapshot', '!=', 'orders.status');
                });
            }),
            'AGING' => $query->where('order_adjustments.status', OrderAdjustmentStatus::SUBMITTED->value)
                ->where('order_adjustments.requested_at', '<', $agingThreshold),
            default => $query,
        };
    }

    /**
     * Compute aggregate badge counts across all operational queue categories using a single SQL query.
     *
     * @return array{
     *     attention: int,
     *     pending: int,
     *     ready_to_apply: int,
     *     applied: int,
     *     reversed: int,
     *     closed: int,
     *     all: int,
     *     case_b: int
     * }
     */
    public static function getBadgeCounts(): array
    {
        $agingThreshold = self::getAgingThreshold()->toIso8601String();
        $eligibleStatusesSql = "'" . implode("','", array_map(fn (OrderStatus $s) => $s->value, self::ELIGIBLE_ORDER_STATUSES)) . "'";

        $blockerSql = "
            (
                oa.order_version_snapshot != o.version
                OR oa.order_status_snapshot != o.status
                OR o.status NOT IN ({$eligibleStatusesSql})
                OR EXISTS (
                    SELECT 1 FROM order_adjustment_items oai
                    JOIN order_items oi ON oi.id = oai.order_item_id
                    WHERE oai.adjustment_id = oa.id
                      AND oai.requested_quantity_reduction > (oi.ordered_quantity - oi.cancelled_quantity)
                )
                OR EXISTS (
                    SELECT 1 FROM order_adjustment_items oai
                    JOIN order_items oi ON oi.id = oai.order_item_id
                    WHERE oai.adjustment_id = oa.id
                      AND (SELECT COALESCE(SUM(oia.picked_quantity), 0)
                           FROM order_item_allocations oia
                           WHERE oia.order_item_id = oi.id AND oia.status NOT IN ('CANCELLED', 'RELEASED')) > 0
                      AND ((oi.ordered_quantity - oi.cancelled_quantity) - oai.requested_quantity_reduction) < (
                          SELECT COALESCE(SUM(oia.picked_quantity), 0)
                          FROM order_item_allocations oia
                          WHERE oia.order_item_id = oi.id AND oia.status NOT IN ('CANCELLED', 'RELEASED')
                      )
                )
            )
        ";

        $countRow = DB::table('order_adjustments as oa')
            ->join('orders as o', 'o.id', '=', 'oa.order_id')
            ->selectRaw("
                COUNT(CASE WHEN oa.status = 'SUBMITTED' THEN 1 END) as pending_count,
                COUNT(CASE WHEN oa.status = 'APPROVED' THEN 1 END) as approved_count,
                COUNT(CASE WHEN oa.status = 'APPROVED' AND NOT ({$blockerSql}) THEN 1 END) as ready_to_apply_count,
                COUNT(CASE WHEN oa.status IN ('SUBMITTED', 'APPROVED') AND (({$blockerSql}) OR (oa.status = 'SUBMITTED' AND oa.requested_at < ?)) THEN 1 END) as attention_count,
                COUNT(CASE WHEN oa.status = 'APPLIED' THEN 1 END) as applied_count,
                COUNT(CASE WHEN oa.status = 'REVERSED' THEN 1 END) as reversed_count,
                COUNT(CASE WHEN oa.status = 'REJECTED' THEN 1 END) as rejected_count,
                COUNT(CASE WHEN oa.status = 'CANCELLED' THEN 1 END) as cancelled_count,
                COUNT(CASE WHEN oa.status IN ('REJECTED', 'CANCELLED') THEN 1 END) as closed_count,
                COUNT(*) as all_count,
                COUNT(CASE WHEN oa.status = 'SUBMITTED' AND EXISTS (
                    SELECT 1 FROM order_adjustment_items oai WHERE oai.adjustment_id = oa.id AND oai.affected_allocation_quantity > 0
                ) THEN 1 END) as case_b_count
            ", [$agingThreshold])
            ->first();

        return [
            'attention' => (int) ($countRow->attention_count ?? 0),
            'pending' => (int) ($countRow->pending_count ?? 0),
            'submitted' => (int) ($countRow->pending_count ?? 0), // legacy alias
            'ready_to_apply' => (int) ($countRow->ready_to_apply_count ?? 0),
            'approved' => (int) ($countRow->approved_count ?? 0), // legacy alias
            'applied' => (int) ($countRow->applied_count ?? 0),
            'reversed' => (int) ($countRow->reversed_count ?? 0),
            'closed' => (int) ($countRow->closed_count ?? 0),
            'rejected' => (int) ($countRow->rejected_count ?? 0), // legacy alias
            'cancelled' => (int) ($countRow->cancelled_count ?? 0), // legacy alias
            'all' => (int) ($countRow->all_count ?? 0),
            'case_b' => (int) ($countRow->case_b_count ?? 0),
        ];
    }
}
