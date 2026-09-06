<?php

namespace App\Services\Return;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReturnRequestService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ReturnNumberGenerator $numberGenerator,
    ) {}

    /**
     * Calculate returnable quantities for all items of an order.
     *
     * @return array<int, array{
     *     order_item_id: int,
     *     product_id: int,
     *     product_name: string,
     *     sku: string,
     *     delivered_quantity: int,
     *     returned_quantity: int,
     *     pending_return_quantity: int,
     *     returnable_quantity: int,
     *     unit_price: string,
     *     tax_rate: string
     * }>
     */
    public function calculateReturnableQuantities(Order $order): array
    {
        $order->loadMissing(['items', 'customer']);

        $openReturnQuantities = ReturnRequestItem::query()
            ->whereHas('returnRequest', function ($q) use ($order) {
                $q->where('order_id', $order->id)
                    ->whereIn('status', [
                        ReturnStatus::REQUESTED->value,
                        ReturnStatus::UNDER_REVIEW->value,
                        ReturnStatus::INSPECTED->value,
                    ]);
            })
            ->groupBy('order_item_id')
            ->selectRaw('order_item_id, sum(requested_quantity) as total_pending')
            ->pluck('total_pending', 'order_item_id')
            ->toArray();

        $results = [];
        foreach ($order->items as $item) {
            $pending = (int) ($openReturnQuantities[$item->id] ?? 0);
            $alreadyReturned = (int) $item->returned_quantity;
            $delivered = (int) $item->delivered_quantity;
            $returnable = max(0, $delivered - ($alreadyReturned + $pending));

            $results[$item->id] = [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name_snapshot ?? '',
                'sku' => $item->sku_snapshot ?? '',
                'delivered_quantity' => $delivered,
                'returned_quantity' => $alreadyReturned,
                'pending_return_quantity' => $pending,
                'returnable_quantity' => $returnable,
                'unit_price' => (string) $item->unit_price,
                'tax_rate' => (string) $item->tax_rate_snapshot,
            ];
        }

        return $results;
    }

    /**
     * Create an authoritative return request for delivered order items.
     *
     * @param  array{
     *     order_id: int,
     *     customer_id?: int,
     *     warehouse_id?: int,
     *     notes?: string,
     *     items: array<int, array{
     *         order_item_id: int,
     *         requested_quantity: int,
     *         reason_code?: string,
     *         item_notes?: string
     *     }>
     * } $data
     *
     * @throws AuthorizationException
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    public function createRequest(array $data, User $actor): ReturnRequest
    {
        // 1. Authorize actor
        $this->permissionService->authorize($actor, Permission::RETURN_REQUEST);

        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive user accounts cannot create return requests.');
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'At least one line item must be selected for return.',
            ]);
        }

        $orderId = (int) $data['order_id'];

        // 2. Execute within ACID transaction with deterministic lock order
        return DB::transaction(function () use ($data, $actor, $orderId) {
            // Find Order first to know customer
            $initialOrder = Order::with('customer')->findOrFail($orderId);

            // Salesman Scope Validation (Fail-closed Anti-IDOR)
            if ($actor->role === UserRole::SALESMAN) {
                $isAssigned = ($initialOrder->salesman_id === $actor->id) ||
                    ($initialOrder->customer && $initialOrder->customer->salesman_id === $actor->id);

                if (! $isAssigned) {
                    throw new NotFoundHttpException("Order #{$orderId} not found or not assigned to you.");
                }
            }

            // Lock hierarchy: Customer -> Order -> OrderItems -> OrderItemAllocations
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::where('id', $initialOrder->customer_id)->lockForUpdate()->firstOrFail();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();

            $lockedItems = OrderItem::where('order_id', $orderId)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedAllocations = OrderItemAllocation::where('order_id', $orderId)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Validate order delivered quantities
            $totalDelivered = $lockedItems->sum('delivered_quantity');
            if ($totalDelivered <= 0) {
                throw ValidationException::withMessages([
                    'order_id' => 'This order has 0 delivered items and is not eligible for merchandise return.',
                ]);
            }

            // Calculate pending open returns for each locked item inside the transaction
            $openReturnQuantities = ReturnRequestItem::query()
                ->whereHas('returnRequest', function ($q) use ($orderId) {
                    $q->where('order_id', $orderId)
                        ->whereIn('status', [
                            ReturnStatus::REQUESTED->value,
                            ReturnStatus::UNDER_REVIEW->value,
                            ReturnStatus::INSPECTED->value,
                        ]);
                })
                ->groupBy('order_item_id')
                ->selectRaw('order_item_id, sum(requested_quantity) as total_pending')
                ->pluck('total_pending', 'order_item_id')
                ->toArray();

            // Resolve Warehouse
            $warehouseId = $data['warehouse_id'] ?? null;
            if (! $warehouseId) {
                $allocationWarehouseCode = $lockedAllocations->first()?->warehouse_code;
                if ($allocationWarehouseCode) {
                    $wh = Warehouse::where('code', $allocationWarehouseCode)->first();
                    $warehouseId = $wh?->id;
                }
            }
            if (! $warehouseId) {
                $warehouseId = Warehouse::value('id');
            }

            if (! $warehouseId) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'No warehouse found for receiving return.',
                ]);
            }

            $estimatedSubtotal = '0.00';
            $estimatedTax = '0.00';
            $estimatedTotal = '0.00';
            $itemsToCreate = [];

            foreach ($data['items'] as $index => $itemInput) {
                $orderItemId = (int) ($itemInput['order_item_id'] ?? 0);
                $requestedQty = (int) ($itemInput['requested_quantity'] ?? 0);
                $reasonCodeRaw = $itemInput['reason_code'] ?? ReturnReasonCode::DEFECTIVE->value;
                $itemNotes = $itemInput['item_notes'] ?? null;

                if ($requestedQty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.requested_quantity" => 'Requested quantity must be strictly greater than 0.',
                    ]);
                }

                /** @var OrderItem|null $orderItem */
                $orderItem = $lockedItems->get($orderItemId);
                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}.order_item_id" => "Order item #{$orderItemId} does not belong to order #{$orderId}.",
                    ]);
                }

                $pendingQty = (int) ($openReturnQuantities[$orderItemId] ?? 0);
                $alreadyReturned = (int) $orderItem->returned_quantity;
                $deliveredQty = (int) $orderItem->delivered_quantity;
                $returnableQty = max(0, $deliveredQty - ($alreadyReturned + $pendingQty));

                if ($requestedQty > $returnableQty) {
                    throw ValidationException::withMessages([
                        "items.{$index}.requested_quantity" => "Requested return quantity ({$requestedQty}) exceeds returnable quantity ({$returnableQty}) for item {$orderItem->product_name_snapshot}.",
                    ]);
                }

                // Financial snapshots and proportional tax calculations
                $unitPrice = (string) $orderItem->unit_price;
                $taxRate = (string) ($orderItem->tax_rate_snapshot ?? '0.0000');
                $lineSubtotal = bcmul($unitPrice, (string) $requestedQty, 4);
                $lineTax = bcmul($lineSubtotal, $taxRate, 4);
                $lineSubtotalFormatted = number_format((float) $lineSubtotal, 2, '.', '');
                $lineTaxFormatted = number_format((float) $lineTax, 2, '.', '');
                $lineTotalFormatted = number_format((float) bcadd($lineSubtotalFormatted, $lineTaxFormatted, 4), 2, '.', '');

                $estimatedSubtotal = bcadd($estimatedSubtotal, $lineSubtotalFormatted, 2);
                $estimatedTax = bcadd($estimatedTax, $lineTaxFormatted, 2);
                $estimatedTotal = bcadd($estimatedTotal, $lineTotalFormatted, 2);

                $reasonCode = ReturnReasonCode::tryFrom($reasonCodeRaw) ?? ReturnReasonCode::DEFECTIVE;

                $itemsToCreate[] = [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'requested_quantity' => $requestedQty,
                    'received_quantity' => 0,
                    'accepted_good_quantity' => 0,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                    'unit_price_snapshot' => $unitPrice,
                    'tax_rate_snapshot' => $taxRate,
                    'tax_profile_code_snapshot' => $orderItem->tax_profile_code_snapshot,
                    'tax_profile_name_snapshot' => $orderItem->tax_profile_name_snapshot,
                    'tax_amount_snapshot' => $lineTaxFormatted,
                    'line_total' => $lineTotalFormatted,
                    'reason_code' => $reasonCode->value,
                    'item_notes' => $itemNotes,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            // Create ReturnRequest header
            $returnNumber = $this->numberGenerator->generate();

            /** @var ReturnRequest $returnRequest */
            $returnRequest = ReturnRequest::create([
                'return_number' => $returnNumber,
                'order_id' => $lockedOrder->id,
                'customer_id' => $lockedCustomer->id,
                'salesman_id' => $lockedOrder->salesman_id ?? ($actor->role === UserRole::SALESMAN ? $actor->id : null),
                'warehouse_id' => $warehouseId,
                'status' => ReturnStatus::REQUESTED,
                'created_by' => $actor->id,
                'requested_at' => Carbon::now(),
                'notes' => $data['notes'] ?? null,
                'estimated_refund_subtotal' => $estimatedSubtotal,
                'estimated_refund_tax' => $estimatedTax,
                'estimated_refund_total' => $estimatedTotal,
                'is_credit_processed' => false,
            ]);

            // Insert line items
            foreach ($itemsToCreate as &$itemData) {
                $itemData['return_request_id'] = $returnRequest->id;
                ReturnRequestItem::create($itemData);
            }

            // Record lifecycle event
            ReturnRequestEvent::create([
                'return_request_id' => $returnRequest->id,
                'actor_id' => $actor->id,
                'event_type' => 'REQUESTED',
                'payload' => [
                    'return_number' => $returnNumber,
                    'order_id' => $lockedOrder->id,
                    'order_number' => $lockedOrder->order_number,
                    'customer_id' => $lockedCustomer->id,
                    'item_count' => count($itemsToCreate),
                    'estimated_refund_total' => $estimatedTotal,
                    'notes' => $data['notes'] ?? null,
                ],
                'created_at' => Carbon::now(),
            ]);

            return $returnRequest->load(['items.product', 'order', 'customer', 'warehouse', 'createdBy']);
        }, 3);
    }
}
