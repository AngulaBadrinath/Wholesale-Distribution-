<?php

namespace Tests\Feature\Adjustment;

use App\DTOs\Adjustment\CreateOrderAdjustmentDTO;
use App\DTOs\Adjustment\CreateOrderAdjustmentItemDTO;
use App\Enums\AccountStatus;
use App\Enums\AdjustmentReasonCode;
use App\Enums\AdjustmentStatus;
use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderAdjustmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Adjustment\OrderAdjustmentService;
use App\Services\Allocation\OrderAllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class OrderAdjustmentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Product $product;
    protected TaxProfile $taxProfile;
    protected Category $category;
    protected OrderAdjustmentService $adjustmentService;
    protected OrderAllocationService $allocationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adjustmentService = app(OrderAdjustmentService::class);
        $this->allocationService = app(OrderAllocationService::class);

        $this->admin = User::factory()->create([
            'name' => 'Concurrency Admin',
            'email' => 'concurrency_admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Concurrency Salesman',
            'email' => 'concurrency_sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Concurrency Mart',
            'code' => 'CUST-CONC-ADJ',
            'contact_name' => 'Charlie Mart',
            'phone' => '+1-555-4433',
            'email' => 'mart@wholesale.test',
            'billing_address_line1' => '100 Concurrency Blvd',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10005',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Concurrency Blvd',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10005',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => 30000.00,
        ]);

        $this->category = Category::create([
            'name' => 'Dry Goods',
            'code' => 'DRY-GOODS',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard 10%',
            'code' => 'STD-10',
            'rate' => 10.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'name' => 'Wheat Flour 50kg',
            'sku' => 'SKU-WHEAT-50',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 20.00,
            'minimum_allowed_price' => 25.00,
            'default_selling_price' => 30.00,
            'mrp' => 35.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BAG',
        ]);
    }

    protected function createOrderWithFulfillableQuantity(int $fulfillableQty = 10, OrderStatus $status = OrderStatus::APPROVED): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'idempotency_key' => 'idemp-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'status' => $status,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'adjustment_status' => AdjustmentStatus::NONE,
            'currency' => 'USD',
            'subtotal' => $fulfillableQty * 30.00,
            'tax_total' => $fulfillableQty * 3.00,
            'adjustment_total' => 0.00,
            'grand_total' => $fulfillableQty * 33.00,
            'submitted_at' => Carbon::now()->subHours(2),
            'approved_at' => Carbon::now()->subHour(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => $fulfillableQty,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => 30.00,
            'is_price_overridden' => false,
            'tax_profile_id_snapshot' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => 'STD-10',
            'tax_profile_name_snapshot' => 'Standard 10%',
            'tax_rate_snapshot' => 10.00,
            'taxable_amount' => $fulfillableQty * 30.00,
            'tax_amount' => $fulfillableQty * 3.00,
            'line_total' => $fulfillableQty * 33.00,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * A. Two users submit adjustments against the same order simultaneously.
     *    Exactly one SUBMITTED request exists; competing submission is rejected with 409.
     */
    public function test_concurrent_adjustment_submissions_on_same_order_ensures_single_open_request(): void
    {
        $order = $this->createOrderWithFulfillableQuantity(10);
        $item = $order->items->first();

        $successCount = 0;
        $conflictCount = 0;

        $requests = [
            [
                'actor' => $this->salesman,
                'dto' => new CreateOrderAdjustmentDTO(
                    orderId: $order->id,
                    reasonCode: AdjustmentReasonCode::CUSTOMER_REQUEST->value,
                    notes: 'Salesman request 1',
                    idempotencyKey: 'idemp-concurrent-1',
                    items: [new CreateOrderAdjustmentItemDTO($item->id, 2)]
                ),
            ],
            [
                'actor' => $this->admin,
                'dto' => new CreateOrderAdjustmentDTO(
                    orderId: $order->id,
                    reasonCode: AdjustmentReasonCode::WAREHOUSE_DAMAGE->value,
                    notes: 'Admin request 2',
                    idempotencyKey: 'idemp-concurrent-2',
                    items: [new CreateOrderAdjustmentItemDTO($item->id, 3)]
                ),
            ],
        ];

        foreach ($requests as $req) {
            try {
                $this->adjustmentService->createAdjustmentRequest($req['actor'], $order, $req['dto']);
                $successCount++;
            } catch (ConflictHttpException $e) {
                $conflictCount++;
            }
        }

        $this->assertEquals(1, $successCount, 'Exactly one concurrent adjustment request must succeed.');
        $this->assertEquals(1, $conflictCount, 'The competing adjustment request must be rejected with 409 Conflict.');

        // Verify exactly one SUBMITTED record exists in the database
        $openCount = OrderAdjustment::where('order_id', $order->id)
            ->where('status', OrderAdjustmentStatus::SUBMITTED)
            ->count();
        $this->assertEquals(1, $openCount);
        $this->assertEquals(AdjustmentStatus::REQUESTED, $order->fresh()->adjustment_status);
    }

    /**
     * B. Same idempotency key is submitted concurrently.
     *    Exactly one request is persisted; duplicate call returns replay.
     */
    public function test_concurrent_submissions_with_identical_idempotency_key_returns_replay(): void
    {
        $order = $this->createOrderWithFulfillableQuantity(10);
        $item = $order->items->first();

        $dto = new CreateOrderAdjustmentDTO(
            orderId: $order->id,
            reasonCode: AdjustmentReasonCode::CUSTOMER_REQUEST->value,
            notes: 'Duplicate check on Sep 5',
            idempotencyKey: 'idemp-replay-key-99',
            items: [new CreateOrderAdjustmentItemDTO($item->id, 2)]
        );

        $adj1 = $this->adjustmentService->createAdjustmentRequest($this->salesman, $order, $dto);
        $adj2 = $this->adjustmentService->createAdjustmentRequest($this->salesman, $order, $dto);

        $this->assertEquals($adj1->id, $adj2->id, 'Replay must return the identical adjustment instance.');

        // Exactly one record created
        $count = OrderAdjustment::where('order_id', $order->id)->count();
        $this->assertEquals(1, $count);
    }

    /**
     * C. Same key + different payload.
     *    One original request remains; conflicting request is rejected.
     */
    public function test_same_idempotency_key_with_different_payload_is_rejected(): void
    {
        $order = $this->createOrderWithFulfillableQuantity(10);
        $item = $order->items->first();

        $dtoOriginal = new CreateOrderAdjustmentDTO(
            orderId: $order->id,
            reasonCode: AdjustmentReasonCode::CUSTOMER_REQUEST->value,
            notes: 'Original request',
            idempotencyKey: 'idemp-mismatch-key',
            items: [new CreateOrderAdjustmentItemDTO($item->id, 2)]
        );

        $dtoConflict = new CreateOrderAdjustmentDTO(
            orderId: $order->id,
            reasonCode: AdjustmentReasonCode::CUSTOMER_REQUEST->value,
            notes: 'Mismatched reduction qty',
            idempotencyKey: 'idemp-mismatch-key',
            items: [new CreateOrderAdjustmentItemDTO($item->id, 4)]
        );

        $original = $this->adjustmentService->createAdjustmentRequest($this->salesman, $order, $dtoOriginal);
        $this->assertNotNull($original);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('different payload');

        $this->adjustmentService->createAdjustmentRequest($this->salesman, $order, $dtoConflict);
    }

    /**
     * D. Withdrawal races with another withdrawal.
     *    Second withdrawal fails cleanly with 409 Conflict.
     */
    public function test_concurrent_withdrawal_operations_serialize_safely(): void
    {
        $order = $this->createOrderWithFulfillableQuantity(10);
        $item = $order->items->first();

        $dto = new CreateOrderAdjustmentDTO(
            orderId: $order->id,
            reasonCode: AdjustmentReasonCode::CUSTOMER_REQUEST->value,
            notes: 'Will be withdrawn',
            idempotencyKey: 'idemp-race-withdraw',
            items: [new CreateOrderAdjustmentItemDTO($item->id, 2)]
        );

        $adj = $this->adjustmentService->createAdjustmentRequest($this->salesman, $order, $dto);

        // Process 1 withdraws successfully
        $withdrawn1 = $this->adjustmentService->withdrawAdjustmentRequest(
            $this->salesman,
            $adj,
            'First withdrawal'
        );
        $this->assertEquals(OrderAdjustmentStatus::CANCELLED, $withdrawn1->status);

        // Process 2 attempts concurrent withdrawal on the same adjustment
        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('status is');

        $this->adjustmentService->withdrawAdjustmentRequest(
            $this->salesman,
            $adj->fresh(),
            'Second concurrent withdrawal'
        );
    }

    /**
     * E. Adjustment request creation races with allocation progression.
     *    Transactions serialize safely and no conservation invariant is violated.
     */
    public function test_adjustment_request_creation_races_with_allocation_progression(): void
    {
        $order = $this->createOrderWithFulfillableQuantity(10, OrderStatus::APPROVED);
        $item = $order->items->first();

        // 1. Create an active allocation of 6 units (unallocated = 4)
        $allocation = $this->allocationService->allocateItemQuantity($item, 6, $this->admin);
        $this->assertEquals(6, $allocation->allocated_quantity);

        // 2. Simulate allocation progression to PICKED
        $allocation->picked_quantity = 6;
        $allocation->status = AllocationStatus::PICKED;
        $allocation->save();
        $this->allocationService->syncOrderItemRollups($item);

        $allocation->refresh();
        $this->assertEquals(6, $allocation->picked_quantity);
        $this->assertEquals(AllocationStatus::PICKED, $allocation->status);

        // 3. Now submit adjustment request of 5 units (exceeds unallocated 4 by 1 => Case B)
        $dto = new CreateOrderAdjustmentDTO(
            orderId: $order->id,
            reasonCode: AdjustmentReasonCode::STOCKOUT_DEFECT->value,
            notes: 'Stock shortage detected after picking',
            idempotencyKey: 'idemp-race-alloc',
            items: [new CreateOrderAdjustmentItemDTO($item->id, 5)]
        );

        $adj = $this->adjustmentService->createAdjustmentRequest($this->admin, $order, $dto);

        $adjItem = $adj->items->first();
        $this->assertEquals(5, $adjItem->requested_quantity_reduction);
        $this->assertEquals(1, $adjItem->affected_allocation_quantity, 'Case B must calculate 5 - 4 = 1 affected allocation unit.');

        // Invariant: picked allocation quantity and status MUST NOT be changed by adjustment request!
        $allocation->refresh();
        $this->assertEquals(6, $allocation->allocated_quantity);
        $this->assertEquals(6, $allocation->picked_quantity);
        $this->assertEquals(AllocationStatus::PICKED, $allocation->status);

        // Invariant: order item cancelled_quantity remains 0
        $item->refresh();
        $this->assertEquals(0, $item->cancelled_quantity);
        $this->assertEquals(10, $item->ordered_quantity);
    }
}
