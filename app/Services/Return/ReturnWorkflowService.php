<?php

namespace App\Services\Return;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReturnWorkflowService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ReturnInventoryService $inventoryService,
    ) {}

    /**
     * Authoritative approval of return request with item-by-item stock disposition.
     *
     * @param  array{
     *     items: array<int, array{
     *         item_id: int,
     *         accepted_good_quantity: int,
     *         accepted_damaged_quantity: int,
     *         rejected_quantity: int
     *     }>
     * } $dispositionData
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function approveReturn(ReturnRequest $returnRequest, array $dispositionData, User $actor): ReturnRequest
    {
        // 1. Authorize actor
        $this->permissionService->authorize($actor, Permission::RETURN_APPROVE);

        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive user accounts cannot approve returns.');
        }

        // 2. Maker-checker policy check: Requester cannot approve their own return unless Super Admin
        if ($actor->id === $returnRequest->created_by && $actor->role !== UserRole::SUPER_ADMIN) {
            throw ValidationException::withMessages([
                'approved_by' => 'Maker-checker policy violation: Return approver cannot be the same user who created the return request.',
            ]);
        }

        if (empty($dispositionData['items']) || ! is_array($dispositionData['items'])) {
            throw ValidationException::withMessages([
                'items' => 'Approval requires line item disposition quantities.',
            ]);
        }

        // 3. Execute authoritative approval and inventory disposition inside ACID transaction
        // Lock hierarchy: Customer -> Order -> OrderItems -> OrderItemAllocations -> InventoryBalances -> ReturnRequest
        return DB::transaction(function () use ($returnRequest, $dispositionData, $actor) {
            /** @var ReturnRequest $lockedReturn */
            $lockedReturn = ReturnRequest::where('id', $returnRequest->id)->lockForUpdate()->firstOrFail();

            if (! $lockedReturn->status->canApprove()) {
                throw new ConflictHttpException("Return #{$lockedReturn->return_number} is in '{$lockedReturn->status->value}' status and cannot be approved. It must be INSPECTED first.");
            }

            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::where('id', $lockedReturn->customer_id)->lockForUpdate()->firstOrFail();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $lockedReturn->order_id)->lockForUpdate()->firstOrFail();

            $lockedOrderItems = OrderItem::where('order_id', $lockedOrder->id)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedAllocations = OrderItemAllocation::where('order_id', $lockedOrder->id)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $lockedReturnItems = ReturnRequestItem::where('return_request_id', $lockedReturn->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $productIds = $lockedReturnItems->pluck('product_id')->unique()->sort()->values();
            foreach ($productIds as $pId) {
                InventoryBalance::where('product_id', $pId)
                    ->where('warehouse_id', $lockedReturn->warehouse_id)
                    ->lockForUpdate()
                    ->first();
            }

            $eligibleCreditSubtotal = '0.00';
            $eligibleCreditTax = '0.00';
            $eligibleCreditTotal = '0.00';
            $approvalItemsSummary = [];

            foreach ($dispositionData['items'] as $index => $itemInput) {
                $itemId = (int) ($itemInput['item_id'] ?? 0);
                $goodQty = (int) ($itemInput['accepted_good_quantity'] ?? 0);
                $damagedQty = (int) ($itemInput['accepted_damaged_quantity'] ?? 0);
                $rejectedQty = (int) ($itemInput['rejected_quantity'] ?? 0);

                /** @var ReturnRequestItem|null $returnItem */
                $returnItem = $lockedReturnItems->get($itemId);
                if (! $returnItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}.item_id" => "Return item #{$itemId} does not belong to return request #{$lockedReturn->return_number}.",
                    ]);
                }

                if ($goodQty < 0 || $damagedQty < 0 || $rejectedQty < 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => 'Disposition quantities cannot be negative.',
                    ]);
                }

                // Hard rule: received_quantity = accepted_good + accepted_damaged + rejected
                $dispositionSum = $goodQty + $damagedQty + $rejectedQty;
                if ($dispositionSum !== $returnItem->received_quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => "Disposition sum (Good: {$goodQty} + Damaged: {$damagedQty} + Rejected: {$rejectedQty} = {$dispositionSum}) must exactly equal received quantity ({$returnItem->received_quantity}) for item #{$returnItem->id}.",
                    ]);
                }

                $approvedQty = $goodQty + $damagedQty;

                // Validate order item conservation: already returned + this return <= delivered
                /** @var OrderItem $orderItem */
                $orderItem = $lockedOrderItems->get($returnItem->order_item_id);
                $newCumulativeReturned = (int) $orderItem->returned_quantity + $approvedQty;
                if ($newCumulativeReturned > (int) $orderItem->delivered_quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => "Approved return quantity ({$approvedQty}) would cause total returned quantity ({$newCumulativeReturned}) to exceed delivered quantity ({$orderItem->delivered_quantity}) for item {$orderItem->product_name_snapshot}.",
                    ]);
                }

                // Calculate proportional financial eligibility
                $unitPrice = (string) $returnItem->unit_price_snapshot;
                $taxRate = (string) ($returnItem->tax_rate_snapshot ?? '0.0000');
                $approvedSubtotal = bcmul($unitPrice, (string) $approvedQty, 4);
                $approvedTax = bcmul($approvedSubtotal, $taxRate, 4);
                $approvedSubtotalFormatted = number_format((float) $approvedSubtotal, 2, '.', '');
                $approvedTaxFormatted = number_format((float) $approvedTax, 2, '.', '');
                $approvedTotalFormatted = number_format((float) bcadd($approvedSubtotalFormatted, $approvedTaxFormatted, 4), 2, '.', '');

                $eligibleCreditSubtotal = bcadd($eligibleCreditSubtotal, $approvedSubtotalFormatted, 2);
                $eligibleCreditTax = bcadd($eligibleCreditTax, $approvedTaxFormatted, 2);
                $eligibleCreditTotal = bcadd($eligibleCreditTotal, $approvedTotalFormatted, 2);

                $returnItem->update([
                    'accepted_good_quantity' => $goodQty,
                    'accepted_damaged_quantity' => $damagedQty,
                    'rejected_quantity' => $rejectedQty,
                ]);

                $approvalItemsSummary[] = [
                    'item_id' => $returnItem->id,
                    'product_id' => $returnItem->product_id,
                    'requested_quantity' => $returnItem->requested_quantity,
                    'received_quantity' => $returnItem->received_quantity,
                    'accepted_good_quantity' => $goodQty,
                    'accepted_damaged_quantity' => $damagedQty,
                    'rejected_quantity' => $rejectedQty,
                    'approved_quantity' => $approvedQty,
                ];
            }

            // Execute Physical Inventory Movement & Allocation Synchronization (FEAT-RET-004)
            $this->inventoryService->executeDisposition($lockedReturn, $lockedReturnItems, $actor);

            // Update ReturnRequest to APPROVED with financial eligibility snapshot
            $now = Carbon::now();
            $lockedReturn->update([
                'status' => ReturnStatus::APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => $now,
                'estimated_refund_subtotal' => $eligibleCreditSubtotal,
                'estimated_refund_tax' => $eligibleCreditTax,
                'estimated_refund_total' => $eligibleCreditTotal,
            ]);

            // Record immutable event
            ReturnRequestEvent::create([
                'return_request_id' => $lockedReturn->id,
                'actor_id' => $actor->id,
                'event_type' => 'APPROVED',
                'payload' => [
                    'approved_by' => $actor->id,
                    'approved_at' => $now->toIso8601String(),
                    'items' => $approvalItemsSummary,
                    'eligible_credit_subtotal' => $eligibleCreditSubtotal,
                    'eligible_credit_tax' => $eligibleCreditTax,
                    'eligible_credit_total' => $eligibleCreditTotal,
                ],
                'created_at' => $now,
            ]);

            return $lockedReturn->fresh(['items.product', 'order', 'customer', 'approvedBy', 'inspectedBy', 'createdBy']);
        }, 3);
    }

    /**
     * Authoritative rejection of a return request.
     *
     * @param  array{rejection_reason: string}  $data
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function rejectReturn(ReturnRequest $returnRequest, array $data, User $actor): ReturnRequest
    {
        $this->permissionService->authorize($actor, Permission::RETURN_APPROVE);

        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive user accounts cannot reject returns.');
        }

        $reason = trim((string) ($data['rejection_reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is strictly required.',
            ]);
        }

        return DB::transaction(function () use ($returnRequest, $reason, $actor) {
            /** @var ReturnRequest $lockedReturn */
            $lockedReturn = ReturnRequest::where('id', $returnRequest->id)->lockForUpdate()->firstOrFail();

            if ($lockedReturn->status->isTerminal()) {
                throw new ConflictHttpException("Return #{$lockedReturn->return_number} is already in terminal status '{$lockedReturn->status->value}'.");
            }

            // Mark all items rejected
            $items = ReturnRequestItem::where('return_request_id', $lockedReturn->id)->lockForUpdate()->get();
            foreach ($items as $item) {
                $rejectedQty = $item->received_quantity > 0 ? $item->received_quantity : $item->requested_quantity;
                $item->update([
                    'accepted_good_quantity' => 0,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => $rejectedQty,
                ]);
            }

            $now = Carbon::now();
            $lockedReturn->update([
                'status' => ReturnStatus::REJECTED,
                'rejected_at' => $now,
                'rejection_reason' => $reason,
            ]);

            ReturnRequestEvent::create([
                'return_request_id' => $lockedReturn->id,
                'actor_id' => $actor->id,
                'event_type' => 'REJECTED',
                'payload' => [
                    'rejected_by' => $actor->id,
                    'rejected_at' => $now->toIso8601String(),
                    'rejection_reason' => $reason,
                ],
                'created_at' => $now,
            ]);

            return $lockedReturn->fresh(['items.product', 'order', 'customer', 'createdBy']);
        }, 3);
    }

    /**
     * Requester cancellation of a pending return request.
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     */
    public function cancelReturn(ReturnRequest $returnRequest, User $actor): ReturnRequest
    {
        $this->permissionService->authorize($actor, Permission::RETURN_REQUEST);

        return DB::transaction(function () use ($returnRequest, $actor) {
            /** @var ReturnRequest $lockedReturn */
            $lockedReturn = ReturnRequest::where('id', $returnRequest->id)->lockForUpdate()->firstOrFail();

            if (! $lockedReturn->status->canCancel()) {
                throw new ConflictHttpException("Return #{$lockedReturn->return_number} cannot be cancelled in status '{$lockedReturn->status->value}'. Only REQUESTED returns can be cancelled.");
            }

            if ($actor->role === UserRole::SALESMAN && $lockedReturn->created_by !== $actor->id && $lockedReturn->salesman_id !== $actor->id) {
                throw new NotFoundHttpException('Return request not found or not created by you.');
            }

            $now = Carbon::now();
            $lockedReturn->update([
                'status' => ReturnStatus::CANCELLED,
                'cancelled_at' => $now,
            ]);

            ReturnRequestEvent::create([
                'return_request_id' => $lockedReturn->id,
                'actor_id' => $actor->id,
                'event_type' => 'CANCELLED',
                'payload' => [
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => $now->toIso8601String(),
                ],
                'created_at' => $now,
            ]);

            return $lockedReturn->fresh(['items.product', 'order', 'customer', 'createdBy']);
        }, 3);
    }
}
