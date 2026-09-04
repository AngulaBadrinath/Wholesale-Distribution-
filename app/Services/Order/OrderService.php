<?php

namespace App\Services\Order;

use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Order\CreateOrderItemDTO;
use App\Enums\AdjustmentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Pricing\PriceBoundaryService;
use App\Services\Tax\TaxCalculationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected PriceBoundaryService $priceBoundaryService,
        protected TaxCalculationService $taxCalculationService,
        protected OrderNumberGenerator $orderNumberGenerator,
    ) {}

    /**
     * Authoritatively create and submit a new wholesale sales order.
     * Executes inside a single database transaction with pessimistic row locking,
     * exact financial calculations, immutable line snapshots, and idempotency protection.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ConflictHttpException
     */
    public function createOrder(User $actor, CreateOrderDTO $dto, ?string $clientIp = null): Order
    {
        // 1. Authorize actor permissions
        $this->permissionService->authorize($actor, Permission::ORDER_CREATE);
        $this->permissionService->authorize($actor, Permission::ORDER_SUBMIT);

        // 2. Validate basic payload constraints
        if (empty($dto->items)) {
            throw ValidationException::withMessages([
                'items' => 'An order must contain at least one product item.',
            ]);
        }

        return DB::transaction(function () use ($actor, $dto, $clientIp) {
            // 3. Idempotency Check & Race-Safe Replay
            $existingOrder = Order::with(['items', 'customer', 'salesman'])
                ->where('idempotency_key', $dto->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                // If existing order belongs to another salesman, fail authorization
                if ($actor->role === UserRole::SALESMAN && $existingOrder->salesman_id !== $actor->id) {
                    throw new AuthorizationException('This order was submitted by another salesman.');
                }

                // Verify request fingerprint matches existing order
                if ($this->doesOrderMatchDto($existingOrder, $dto)) {
                    return $existingOrder;
                }

                throw new ConflictHttpException('An order with this idempotency key already exists with different payload details.');
            }

            // 4. Resolve & Scoped-Lock Customer
            /** @var Customer|null $customer */
            $customer = Customer::forUser($actor)
                ->where('id', $dto->customerId)
                ->lockForUpdate()
                ->first();

            if (! $customer) {
                if ($actor->role === UserRole::SALESMAN) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'The selected customer is not assigned to your salesman account.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'customer_id' => 'The selected customer does not exist.',
                ]);
            }

            // Verify customer lifecycle ordering eligibility (ACTIVE only)
            $customer->ensureCanPlaceOrders();

            // 5. Collect and sort product IDs in ascending order for deterministic deadlock-free locking
            $productIds = array_values(array_unique(array_map(
                fn (CreateOrderItemDTO $item) => $item->productId,
                $dto->items
            )));
            sort($productIds, SORT_NUMERIC);

            // 6. Lock all Products in deterministic order (SELECT FOR UPDATE)
            $products = Product::with('taxProfile')
                ->whereIn('id', $productIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more selected products are invalid or no longer exist in the catalog.',
                ]);
            }

            // 7. Validate each Product and Calculate Line-Level Taxes & Snapshots
            $orderItemRows = [];
            $lineTaxResults = [];

            foreach ($dto->items as $index => $itemDto) {
                /** @var Product $product */
                $product = $products->get($itemDto->productId);

                // Verify product is ACTIVE and can be ordered
                $product->ensureCanOrder();

                // Determine requested unit price (defaults to product default_selling_price if omitted)
                $rawPrice = $itemDto->unitPrice !== null && $itemDto->unitPrice !== ''
                    ? $itemDto->unitPrice
                    : (string) $product->default_selling_price;

                // Validate price boundary (min <= price <= mrp).
                // Salesman lacks pricing.override, so out-of-bound prices throw ValidationException.
                $validatedUnitPrice = $this->priceBoundaryService->validateOrderUnitPrice($product, $rawPrice);

                // Calculate line tax using exact BCMath and ROUND_HALF_UP
                $taxResult = $this->taxCalculationService->calculateLineTax(
                    productOrTaxProfile: $product->taxProfile,
                    unitPrice: $validatedUnitPrice,
                    quantity: $itemDto->quantity
                );

                $lineTaxResults[] = $taxResult;

                $orderItemRows[] = [
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'sku_snapshot' => $product->sku,
                    'unit_snapshot' => $product->unit,
                    'ordered_quantity' => $itemDto->quantity,
                    'cancelled_quantity' => 0,
                    'reserved_quantity' => 0,
                    'picked_quantity' => 0,
                    'dispatched_quantity' => 0,
                    'delivered_quantity' => 0,
                    'returned_quantity' => 0,
                    'unit_price' => $validatedUnitPrice,
                    'is_price_overridden' => false,
                    'price_override_reason' => null,
                    'price_override_approved_by' => null,
                    'tax_profile_id' => $product->tax_profile_id,
                    'tax_profile_code_snapshot' => $product->taxProfile?->code,
                    'tax_profile_name_snapshot' => $product->taxProfile?->name,
                    'tax_rate_snapshot' => $taxResult->taxRate,
                    'taxable_amount' => $taxResult->taxableAmount,
                    'tax_amount' => $taxResult->taxAmount,
                    'line_total' => $taxResult->lineTotal,
                ];
            }

            // 8. Calculate aggregate order totals
            $totals = $this->taxCalculationService->calculateOrderTotals($lineTaxResults);

            // 9. Generate next collision-safe sequential Order Number (ORD-YYYY-XXXXXX)
            $orderNumber = $this->orderNumberGenerator->generate();

            // 10. Determine authoritative salesman ID
            $salesmanId = $actor->role === UserRole::SALESMAN
                ? $actor->id
                : ($customer->salesman_id ?? $actor->id);

            // 11. Persist Order Header
            $order = Order::create([
                'order_number' => $orderNumber,
                'idempotency_key' => $dto->idempotencyKey,
                'customer_id' => $customer->id,
                'salesman_id' => $salesmanId,
                'created_by' => $actor->id,
                'status' => OrderStatus::SUBMITTED,
                'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
                'payment_status' => PaymentStatus::UNPAID,
                'delivery_status' => DeliveryStatus::PENDING_ASSIGNMENT,
                'adjustment_status' => AdjustmentStatus::NONE,
                'currency' => 'USD',
                'subtotal' => $totals['taxable_total'],
                'tax_total' => $totals['tax_total'],
                'adjustment_total' => '0.00',
                'grand_total' => $totals['grand_total'],
                'notes' => $dto->notes,
                'submitted_at' => Carbon::now(),
            ]);

            // 12. Persist Order Items
            $order->items()->createMany($orderItemRows);

            // 13. Emit Structured Audit Event
            Log::info('commerce.order_event', [
                'action' => 'ORDER_CREATED',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'salesman_id' => $order->salesman_id,
                'created_by' => $order->created_by,
                'item_count' => count($orderItemRows),
                'subtotal' => $order->subtotal,
                'tax_total' => $order->tax_total,
                'grand_total' => $order->grand_total,
                'idempotency_key' => $order->idempotency_key,
                'ip_address' => $clientIp,
                'timestamp' => Carbon::now()->toIso8601String(),
            ]);

            return $order->load(['items', 'customer', 'salesman']);
        });
    }

    /**
     * Determine whether an existing committed order matches the DTO's client intent.
     */
    protected function doesOrderMatchDto(Order $order, CreateOrderDTO $dto): bool
    {
        if ($order->customer_id !== $dto->customerId) {
            return false;
        }

        if (($order->notes ?? '') !== ($dto->notes ?? '')) {
            return false;
        }

        if ($order->items->count() !== count($dto->items)) {
            return false;
        }

        $existingItems = $order->items->keyBy('product_id');

        foreach ($dto->items as $itemDto) {
            $existingItem = $existingItems->get($itemDto->productId);

            if (! $existingItem) {
                return false;
            }

            if ($existingItem->ordered_quantity !== $itemDto->quantity) {
                return false;
            }

            if ($itemDto->unitPrice !== null && bccomp((string) $existingItem->unit_price, (string) $itemDto->unitPrice, 2) !== 0) {
                return false;
            }
        }

        return true;
    }
}
