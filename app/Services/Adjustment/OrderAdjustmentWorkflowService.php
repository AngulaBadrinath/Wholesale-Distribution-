<?php

namespace App\Services\Adjustment;

use App\Enums\AdjustmentStatus;
use App\Enums\OrderAdjustmentStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderAdjustmentWorkflowService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected OrderAdjustmentReviewService $reviewService,
    ) {}

    /**
     * Authoritatively approve an order adjustment request.
     * Executes within DB::transaction(..., 3) with deterministic lock ordering:
     * 1. orders (Root lock)
     * 2. order_items ASC
     * 3. order_item_allocations ASC
     * 4. order_adjustments row
     *
     * Validates live quantity conservation, stale state, maker-checker, and Case B acknowledgments.
     * ZERO mutation of quantities, allocations, or order financials (reserved for FEAT-ADJ-004).
     *
     * @param  array<string, mixed>  $options
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ConflictHttpException
     * @throws NotFoundHttpException
     */
    public function approveAdjustment(
        User $actor,
        Order $order,
        OrderAdjustment $adjustment,
        array $options = [],
        ?string $clientIp = null
    ): OrderAdjustment {
        // Pre-transaction authorization checks
        if (! $actor->isActive()) {
            throw new AuthorizationException('Your account is not active.');
        }

        $this->permissionService->authorize($actor, Permission::ORDER_ADJUST_APPROVE);

        // Pre-transaction IDOR guard
        if ((int) $adjustment->order_id !== (int) $order->id) {
            throw new NotFoundHttpException('Adjustment request does not belong to the specified order.');
        }

        $caseType = 'CASE_A';
        $totalAffectedAllocation = 0;
        $isEmergencyOverride = false;
        $overrideReason = null;

        /** @var OrderAdjustment $approvedAdjustment */
        $approvedAdjustment = DB::transaction(function () use (
            $actor,
            $order,
            $adjustment,
            $options,
            $clientIp,
            &$caseType,
            &$totalAffectedAllocation,
            &$isEmergencyOverride,
            &$overrideReason
        ) {
            // 1. Lock orders (Root lock)
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // 2. Lock order_items in deterministic ASC order
            OrderItem::where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->orderBy('id', 'asc')
                ->get();

            // 3. Lock order_item_allocations in deterministic ASC order
            OrderItemAllocation::where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->orderBy('id', 'asc')
                ->get();

            // 4. Lock order_adjustments row
            /** @var OrderAdjustment $lockedAdjustment */
            $lockedAdjustment = OrderAdjustment::where('id', $adjustment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-verify IDOR under lock
            if ((int) $lockedAdjustment->order_id !== (int) $lockedOrder->id) {
                throw new NotFoundHttpException('Adjustment request does not belong to the specified order.');
            }

            // 5. State Machine Guard: Only SUBMITTED -> APPROVED is valid.
            // Duplicate/terminal decisions deterministically return 409 Conflict.
            if ($lockedAdjustment->status !== OrderAdjustmentStatus::SUBMITTED) {
                throw new ConflictHttpException(
                    "Cannot approve adjustment {$lockedAdjustment->adjustment_number}: status is '{$lockedAdjustment->status->label()}', expected 'Submitted'."
                );
            }

            // 6. Maker-Checker Segregation of Duties
            $isSelfDecision = ((int) $lockedAdjustment->requested_by === (int) $actor->id);
            if ($isSelfDecision) {
                if ($actor->role !== UserRole::SUPER_ADMIN) {
                    throw new AuthorizationException(
                        'Segregation of duties violation: You cannot approve an adjustment request that you personally submitted.'
                    );
                }

                $rawOverride = $options['emergency_override_reason'] ?? null;
                $overrideReason = is_string($rawOverride) ? trim($rawOverride) : '';

                if (mb_strlen($overrideReason) < 10 || mb_strlen($overrideReason) > 1000) {
                    throw ValidationException::withMessages([
                        'emergency_override_reason' => 'Super Admin self-approval requires an emergency override reason between 10 and 1000 characters.',
                    ]);
                }

                $isEmergencyOverride = true;
            }

            // 7. Order Lifecycle Check
            $eligibleStatuses = [
                OrderStatus::SUBMITTED,
                OrderStatus::PENDING_APPROVAL,
                OrderStatus::APPROVED,
                OrderStatus::PROCESSING,
            ];
            if (! in_array($lockedOrder->status, $eligibleStatuses, true)) {
                $blockReason = "Order {$lockedOrder->order_number} has transitioned to '{$lockedOrder->status->label()}', which is not an eligible adjustment lifecycle state.";
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            // 8. Order Version Guard
            if ((int) $lockedOrder->version !== (int) $lockedAdjustment->order_version_snapshot) {
                $blockReason = "Order version has changed from {$lockedAdjustment->order_version_snapshot} to {$lockedOrder->version}.";
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            // 9. Single-Open Request Invariant
            $openCount = OrderAdjustment::where('order_id', $lockedOrder->id)
                ->where('status', OrderAdjustmentStatus::SUBMITTED)
                ->count();
            if ($openCount !== 1) {
                $blockReason = "Found {$openCount} open adjustment requests for this order; expected exactly 1.";
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            // 10. Deep Live Review Revalidation
            $lockedAdjustment->load([
                'items' => fn ($q) => $q->orderBy('id', 'asc'),
                'items.orderItem' => fn ($q) => $q->with([
                    'allocations' => fn ($allocQ) => $allocQ->orderBy('id', 'asc'),
                ]),
                'items.product',
            ]);

            $evaluation = $this->reviewService->evaluate($lockedAdjustment, $lockedOrder);

            if ($evaluation->evaluationStatus === 'TERMINAL_REQUEST') {
                $blockReason = "Adjustment request is already in terminal state '{$lockedAdjustment->status->label()}'.";
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException($blockReason);
            }

            if ($evaluation->evaluationStatus === 'INELIGIBLE_LIFECYCLE') {
                $blockReason = "Order is in lifecycle state '{$lockedOrder->status->label()}'.";
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            if ($evaluation->evaluationStatus === 'CONFLICTED') {
                $blockReason = $evaluation->staleReasons[0] ?? 'Requested reduction conflicts with current fulfillable quantity.';
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            if ($evaluation->evaluationStatus === 'WARNING_PICKED_ENCROACHMENT') {
                $blockReason = 'Requested reduction encroaches on already picked stock.';
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            if ($evaluation->evaluationStatus === 'STALE') {
                $blockReason = $evaluation->staleReasons[0] ?? 'Adjustment request is stale due to upstream order modifications.';
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            if ($evaluation->financialDiscrepancy) {
                $blockReason = 'Live financial calculation differs from stored projection.';
                $this->logApprovalBlocked($lockedAdjustment, $lockedOrder, $actor, $blockReason, $clientIp);
                throw new ConflictHttpException("Cannot approve adjustment: {$blockReason}");
            }

            // 11. Case B Allocation Impact Check & Mandatory Acknowledgment
            $totalAffectedAllocation = $evaluation->totalAffectedAllocationQuantity;
            $caseType = $totalAffectedAllocation > 0 ? 'CASE_B' : 'CASE_A';

            if ($totalAffectedAllocation > 0) {
                $acknowledged = filter_var($options['acknowledge_allocation_impact'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (! $acknowledged) {
                    throw ValidationException::withMessages([
                        'acknowledge_allocation_impact' => 'Acknowledgment of allocation impact is required to approve this adjustment request.',
                    ]);
                }
            }

            // 12. Execute Authoritative Transition
            $lockedAdjustment->status = OrderAdjustmentStatus::APPROVED;
            $lockedAdjustment->reviewed_by = $actor->id;
            $lockedAdjustment->reviewed_at = Carbon::now();
            $lockedAdjustment->save();

            // Order adjustment_status remains REQUESTED awaiting FEAT-ADJ-004 application
            // Ensures orders table maintains consistent state without mutating quantities/finances
            if ($lockedOrder->adjustment_status !== AdjustmentStatus::REQUESTED) {
                $lockedOrder->adjustment_status = AdjustmentStatus::REQUESTED;
                $lockedOrder->save();
            }

            return $lockedAdjustment->load(['order', 'items', 'reviewer', 'requester']);
        }, 3);

        // 13. Post-Commit Observability Logging
        Log::info('commerce.order_adjustment_event', [
            'action' => 'ADJUSTMENT_APPROVED',
            'adjustment_id' => $approvedAdjustment->id,
            'adjustment_number' => $approvedAdjustment->adjustment_number,
            'order_id' => $approvedAdjustment->order_id,
            'order_number' => $approvedAdjustment->order_number_snapshot,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role->value,
            'case_type' => $caseType,
            'affected_allocation_quantity' => $totalAffectedAllocation,
            'is_emergency_override' => $isEmergencyOverride,
            'override_reason' => $overrideReason,
            'ip_address' => $clientIp,
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);

        if ($isEmergencyOverride) {
            Log::info('commerce.order_adjustment_event', [
                'action' => 'ADJUSTMENT_EMERGENCY_OVERRIDE',
                'adjustment_id' => $approvedAdjustment->id,
                'adjustment_number' => $approvedAdjustment->adjustment_number,
                'order_id' => $approvedAdjustment->order_id,
                'order_number' => $approvedAdjustment->order_number_snapshot,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_role' => $actor->role->value,
                'override_reason' => $overrideReason,
                'decision' => 'APPROVED',
                'ip_address' => $clientIp,
                'timestamp' => Carbon::now()->toIso8601String(),
            ]);
        }

        return $approvedAdjustment;
    }

    /**
     * Authoritatively reject an order adjustment request.
     * Executes within DB::transaction(..., 3) with deterministic lock ordering:
     * 1. orders (Root lock)
     * 2. order_items ASC
     * 3. order_item_allocations ASC
     * 4. order_adjustments row
     *
     * Rejection does NOT require live quantity/allocation satisfiability.
     * ZERO mutation of quantities, allocations, or order financials.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ConflictHttpException
     * @throws NotFoundHttpException
     */
    public function rejectAdjustment(
        User $actor,
        Order $order,
        OrderAdjustment $adjustment,
        string $reason,
        array $options = [],
        ?string $clientIp = null
    ): OrderAdjustment {
        // Pre-transaction authorization checks
        if (! $actor->isActive()) {
            throw new AuthorizationException('Your account is not active.');
        }

        $this->permissionService->authorize($actor, Permission::ORDER_ADJUST_APPROVE);

        // Pre-transaction IDOR guard
        if ((int) $adjustment->order_id !== (int) $order->id) {
            throw new NotFoundHttpException('Adjustment request does not belong to the specified order.');
        }

        $validatedReason = trim($reason);
        if (mb_strlen($validatedReason) < 5 || mb_strlen($validatedReason) > 1000) {
            throw ValidationException::withMessages([
                'reason' => 'Rejection reason must be between 5 and 1000 characters.',
            ]);
        }

        $isEmergencyOverride = false;
        $overrideReason = null;

        /** @var OrderAdjustment $rejectedAdjustment */
        $rejectedAdjustment = DB::transaction(function () use (
            $actor,
            $order,
            $adjustment,
            $validatedReason,
            $options,
            &$isEmergencyOverride,
            &$overrideReason
        ) {
            // 1. Lock orders (Root lock)
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // 2. Lock order_items in deterministic ASC order
            OrderItem::where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->orderBy('id', 'asc')
                ->get();

            // 3. Lock order_item_allocations in deterministic ASC order
            OrderItemAllocation::where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->orderBy('id', 'asc')
                ->get();

            // 4. Lock order_adjustments row
            /** @var OrderAdjustment $lockedAdjustment */
            $lockedAdjustment = OrderAdjustment::where('id', $adjustment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-verify IDOR under lock
            if ((int) $lockedAdjustment->order_id !== (int) $lockedOrder->id) {
                throw new NotFoundHttpException('Adjustment request does not belong to the specified order.');
            }

            // 5. State Machine Guard: Only SUBMITTED -> REJECTED is valid.
            // Duplicate/terminal decisions deterministically return 409 Conflict.
            if ($lockedAdjustment->status !== OrderAdjustmentStatus::SUBMITTED) {
                throw new ConflictHttpException(
                    "Cannot reject adjustment {$lockedAdjustment->adjustment_number}: status is '{$lockedAdjustment->status->label()}', expected 'Submitted'."
                );
            }

            // 6. Maker-Checker Segregation of Duties
            $isSelfDecision = ((int) $lockedAdjustment->requested_by === (int) $actor->id);
            if ($isSelfDecision) {
                if ($actor->role !== UserRole::SUPER_ADMIN) {
                    throw new AuthorizationException(
                        'Segregation of duties violation: You cannot reject an adjustment request that you personally submitted.'
                    );
                }

                $rawOverride = $options['emergency_override_reason'] ?? null;
                $overrideReason = is_string($rawOverride) ? trim($rawOverride) : '';

                if (mb_strlen($overrideReason) < 10 || mb_strlen($overrideReason) > 1000) {
                    throw ValidationException::withMessages([
                        'emergency_override_reason' => 'Super Admin self-rejection requires an emergency override reason between 10 and 1000 characters.',
                    ]);
                }

                $isEmergencyOverride = true;
            }

            // 7. Transition adjustment to REJECTED
            $lockedAdjustment->status = OrderAdjustmentStatus::REJECTED;
            $lockedAdjustment->reviewed_by = $actor->id;
            $lockedAdjustment->reviewed_at = Carbon::now();
            $lockedAdjustment->rejection_reason = $validatedReason;
            $lockedAdjustment->save();

            // 8. Reset orders.adjustment_status to NONE (or APPLIED if earlier adjustment remains APPLIED)
            $hasPriorApplied = OrderAdjustment::where('order_id', $lockedOrder->id)
                ->where('status', OrderAdjustmentStatus::APPLIED)
                ->exists();

            $lockedOrder->adjustment_status = $hasPriorApplied ? AdjustmentStatus::APPLIED : AdjustmentStatus::NONE;
            $lockedOrder->save();

            return $lockedAdjustment->load(['order', 'items', 'reviewer', 'requester']);
        }, 3);

        // 9. Post-Commit Observability Logging
        Log::info('commerce.order_adjustment_event', [
            'action' => 'ADJUSTMENT_REJECTED',
            'adjustment_id' => $rejectedAdjustment->id,
            'adjustment_number' => $rejectedAdjustment->adjustment_number,
            'order_id' => $rejectedAdjustment->order_id,
            'order_number' => $rejectedAdjustment->order_number_snapshot,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role->value,
            'rejection_reason' => $validatedReason,
            'is_emergency_override' => $isEmergencyOverride,
            'override_reason' => $overrideReason,
            'ip_address' => $clientIp,
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);

        if ($isEmergencyOverride) {
            Log::info('commerce.order_adjustment_event', [
                'action' => 'ADJUSTMENT_EMERGENCY_OVERRIDE',
                'adjustment_id' => $rejectedAdjustment->id,
                'adjustment_number' => $rejectedAdjustment->adjustment_number,
                'order_id' => $rejectedAdjustment->order_id,
                'order_number' => $rejectedAdjustment->order_number_snapshot,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_role' => $actor->role->value,
                'override_reason' => $overrideReason,
                'decision' => 'REJECTED',
                'ip_address' => $clientIp,
                'timestamp' => Carbon::now()->toIso8601String(),
            ]);
        }

        return $rejectedAdjustment;
    }

    /**
     * Helper to log blocked approval attempts for audit/observability.
     */
    protected function logApprovalBlocked(
        OrderAdjustment $adjustment,
        Order $order,
        User $actor,
        string $reason,
        ?string $clientIp
    ): void {
        Log::warning('commerce.order_adjustment_event', [
            'action' => 'ADJUSTMENT_APPROVAL_BLOCKED',
            'adjustment_id' => $adjustment->id,
            'adjustment_number' => $adjustment->adjustment_number,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role->value,
            'reason' => $reason,
            'ip_address' => $clientIp,
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }
}
