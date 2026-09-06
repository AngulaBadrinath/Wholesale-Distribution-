<?php

namespace Tests\Feature\Inventory;

use App\Enums\AccountStatus;
use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Order\OrderWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Warehouse $warehouse;
    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;
    protected InventoryBalance $balanceA;
    protected InventoryBalance $balanceB;
    protected OrderWorkflowService $workflowService;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflowService = app(OrderWorkflowService::class);
        $this->inventoryService = app(InventoryService::class);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.res@example.com',
            'password' => bcrypt('Password123!'),
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::create([
            'name' => 'Sales Representative',
            'email' => 'sales.res@example.com',
            'password' => bcrypt('Password123!'),
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Distribution Center',
                'country_code' => 'US',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $this->customer = Customer::create([
            'name' => 'Acme Supermarket',
            'code' => 'CUST-ACME-01',
            'contact_name' => 'John Doe',
            'email' => 'john@acme.test',
            'phone' => '+1-555-0100',
            'billing_address_line1' => '100 Main St',
            'billing_city' => 'New York',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Main St',
            'shipping_city' => 'New York',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'credit_limit' => 50000.00,
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->admin->id,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Rate',
            'code' => 'TAX-STD',
            'rate' => 0.10,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productA = Product::create([
            'name' => 'Cola Can 330ml',
            'sku' => 'BEV-COLA-01',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 1.00,
            'minimum_allowed_price' => 1.50,
            'default_selling_price' => 2.00,
            'mrp' => 2.50,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'CAN',
        ]);

        $this->productB = Product::create([
            'name' => 'Orange Juice 1L',
            'sku' => 'BEV-JUICE-02',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 2.00,
            'minimum_allowed_price' => 3.00,
            'default_selling_price' => 4.00,
            'mrp' => 5.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BOTTLE',
        ]);

        $this->balanceA = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productA->id)
            ->firstOrFail();
        $this->balanceA->update([
            'on_hand_quantity' => 100,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'available_quantity' => 100,
            'version' => 1,
        ]);

        $this->balanceB = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productB->id)
            ->firstOrFail();
        $this->balanceB->update([
            'on_hand_quantity' => 50,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'available_quantity' => 50,
            'version' => 1,
        ]);
    }

    protected function createSubmittedOrder(int $qtyA = 10, int $qtyB = 5): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => ($qtyA * 2.00) + ($qtyB * 4.00),
            'tax_total' => (($qtyA * 2.00) + ($qtyB * 4.00)) * 0.10,
            'grand_total' => (($qtyA * 2.00) + ($qtyB * 4.00)) * 1.10,
        ]);

        if ($qtyA > 0) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $this->productA->id,
                'product_name_snapshot' => $this->productA->name,
                'sku_snapshot' => $this->productA->sku,
                'unit_snapshot' => $this->productA->unit,
                'ordered_quantity' => $qtyA,
                'cancelled_quantity' => 0,
                'reserved_quantity' => 0,
                'picked_quantity' => 0,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'unit_price' => 2.00,
                'tax_rate' => 0.10,
                'tax_amount' => ($qtyA * 2.00) * 0.10,
                'line_total' => ($qtyA * 2.00) * 1.10,
            ]);
        }

        if ($qtyB > 0) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $this->productB->id,
                'product_name_snapshot' => $this->productB->name,
                'sku_snapshot' => $this->productB->sku,
                'unit_snapshot' => $this->productB->unit,
                'ordered_quantity' => $qtyB,
                'cancelled_quantity' => 0,
                'reserved_quantity' => 0,
                'picked_quantity' => 0,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'unit_price' => 4.00,
                'tax_rate' => 0.10,
                'tax_amount' => ($qtyB * 4.00) * 0.10,
                'line_total' => ($qtyB * 4.00) * 1.10,
            ]);
        }

        return $order;
    }

    /**
     * 1. Order Approval Physical Reservation Test
     */
    public function test_authoritative_order_approval_reserves_physical_inventory_atomically(): void
    {
        $order = $this->createSubmittedOrder(10, 5);

        $approvedOrder = $this->workflowService->approveOrder($order, $this->admin);

        $this->assertEquals(OrderStatus::APPROVED, $approvedOrder->status);
        $this->assertEquals(FulfillmentStatus::RESERVED, $approvedOrder->fulfillment_status);

        // Verify balance A mutations
        $this->balanceA->refresh();
        $this->assertEquals(100, $this->balanceA->on_hand_quantity);
        $this->assertEquals(10, $this->balanceA->reserved_quantity);
        $this->assertEquals(90, $this->balanceA->available_quantity);
        $this->assertEquals(2, $this->balanceA->version);

        // Verify balance B mutations
        $this->balanceB->refresh();
        $this->assertEquals(50, $this->balanceB->on_hand_quantity);
        $this->assertEquals(5, $this->balanceB->reserved_quantity);
        $this->assertEquals(45, $this->balanceB->available_quantity);
        $this->assertEquals(2, $this->balanceB->version);

        // Verify Order items have reserved_quantity updated
        $items = $approvedOrder->items()->get();
        $itemA = $items->firstWhere('product_id', $this->productA->id);
        $itemB = $items->firstWhere('product_id', $this->productB->id);
        $this->assertEquals(10, $itemA->reserved_quantity);
        $this->assertEquals(5, $itemB->reserved_quantity);

        // Verify baseline allocations created
        $allocations = OrderItemAllocation::where('order_id', $order->id)->get();
        $this->assertCount(2, $allocations);
    }

    /**
     * 2. Insufficient Stock Atomically Aborts Approval
     */
    public function test_insufficient_stock_aborts_approval_atomically_without_partial_reservation(): void
    {
        // Balance B only has 50 available. Request 60.
        $order = $this->createSubmittedOrder(10, 60);

        try {
            $this->workflowService->approveOrder($order, $this->admin);
            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $e) {
            $this->assertEquals($this->productB->id, $e->productId);
            $this->assertEquals('BEV-JUICE-02', $e->sku);
            $this->assertEquals(60, $e->requestedQuantity);
            $this->assertEquals(50, $e->availableQuantity);
        }

        // Verify neither balance was mutated (rollback verified)
        $this->balanceA->refresh();
        $this->assertEquals(100, $this->balanceA->on_hand_quantity);
        $this->assertEquals(0, $this->balanceA->reserved_quantity);
        $this->assertEquals(100, $this->balanceA->available_quantity);
        $this->assertEquals(1, $this->balanceA->version);

        $this->balanceB->refresh();
        $this->assertEquals(50, $this->balanceB->on_hand_quantity);
        $this->assertEquals(0, $this->balanceB->reserved_quantity);
        $this->assertEquals(50, $this->balanceB->available_quantity);
        $this->assertEquals(1, $this->balanceB->version);

        // Order state remains unapproved
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertEquals(0, OrderItemAllocation::where('order_id', $order->id)->count());
    }

    /**
     * 3. Order Cancellation Releases Physical Stock Atomically
     */
    public function test_order_cancellation_releases_physical_stock_and_marks_allocations_cancelled(): void
    {
        $order = $this->createSubmittedOrder(15, 10);
        $approvedOrder = $this->workflowService->approveOrder($order, $this->admin);

        $this->balanceA->refresh();
        $this->balanceB->refresh();
        $this->assertEquals(15, $this->balanceA->reserved_quantity);
        $this->assertEquals(85, $this->balanceA->available_quantity);
        $this->assertEquals(10, $this->balanceB->reserved_quantity);
        $this->assertEquals(40, $this->balanceB->available_quantity);

        // Cancel the approved order
        $cancelledOrder = $this->workflowService->cancelOrder($approvedOrder, $this->admin, 'Customer cancelled order prior to shipment');

        $this->assertEquals(OrderStatus::CANCELLED, $cancelledOrder->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $cancelledOrder->fulfillment_status);

        // Verify physical reservations released
        $this->balanceA->refresh();
        $this->balanceB->refresh();
        $this->assertEquals(0, $this->balanceA->reserved_quantity);
        $this->assertEquals(100, $this->balanceA->available_quantity);
        $this->assertEquals(3, $this->balanceA->version);

        $this->balanceB->refresh();
        $this->assertEquals(0, $this->balanceB->reserved_quantity);
        $this->assertEquals(50, $this->balanceB->available_quantity);
        $this->assertEquals(3, $this->balanceB->version);

        // Verify order item reserved quantities reset to 0
        foreach ($cancelledOrder->items as $item) {
            $this->assertEquals(0, $item->reserved_quantity);
        }

        // Verify allocations marked CANCELLED
        $allocations = OrderItemAllocation::where('order_id', $order->id)->get();
        foreach ($allocations as $allocation) {
            $this->assertEquals(AllocationStatus::CANCELLED, $allocation->status);
            $this->assertEquals(0, $allocation->reserved_quantity);
        }
    }

    /**
     * 4. Cancelling Unreserved Order Does Not Mutate Physical Stock
     */
    public function test_cancelling_unapproved_order_does_not_mutate_physical_stock(): void
    {
        $order = $this->createSubmittedOrder(20, 10);

        $cancelledOrder = $this->workflowService->cancelOrder($order, $this->admin, 'Cancelled before review');

        $this->assertEquals(OrderStatus::CANCELLED, $cancelledOrder->status);

        $this->balanceA->refresh();
        $this->balanceB->refresh();
        $this->assertEquals(0, $this->balanceA->reserved_quantity);
        $this->assertEquals(100, $this->balanceA->available_quantity);
        $this->assertEquals(1, $this->balanceA->version);
    }

    /**
     * 5. Multi-line Same Product Demand Aggregation
     */
    public function test_multi_line_same_product_aggregates_demand_properly(): void
    {
        // Balance A has 100 available. Set to 15.
        $this->balanceA->update(['on_hand_quantity' => 15, 'available_quantity' => 15]);

        $order = Order::create([
            'order_number' => 'ORD-MULTI-' . Str::random(5),
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => 40.00,
            'tax_total' => 4.00,
            'grand_total' => 44.00,
        ]);

        // Line 1: 10 units of product A
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => 2.00,
            'tax_rate' => 0.10,
            'tax_amount' => 2.00,
            'line_total' => 22.00,
        ]);

        // Line 2: 10 units of product A (Total demand = 20 > 15 available)
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => 2.00,
            'tax_rate' => 0.10,
            'tax_amount' => 2.00,
            'line_total' => 22.00,
        ]);

        $this->expectException(InsufficientStockException::class);
        $this->workflowService->approveOrder($order, $this->admin);
    }

    /**
     * 6. Concurrency Safety: Preventing Double-Reservation
     */
    public function test_concurrency_safe_reservation_prevents_overselling(): void
    {
        // Available stock: 10 units of Product A
        $this->balanceA->update(['on_hand_quantity' => 10, 'available_quantity' => 10]);

        $order1 = $this->createSubmittedOrder(7, 0);
        $order2 = $this->createSubmittedOrder(7, 0);

        // First order approval reserves 7 units
        $this->workflowService->approveOrder($order1, $this->admin);
        $this->balanceA->refresh();
        $this->assertEquals(7, $this->balanceA->reserved_quantity);
        $this->assertEquals(3, $this->balanceA->available_quantity);

        // Second order approval attempts to reserve 7 units, but only 3 available
        $this->expectException(InsufficientStockException::class);
        $this->workflowService->approveOrder($order2, $this->admin);
    }

    /**
     * 7. RBAC Protection: Salesman Cannot Approve Orders
     */
    public function test_salesman_cannot_approve_orders(): void
    {
        $order = $this->createSubmittedOrder(5, 5);

        $this->expectException(AuthorizationException::class);
        $this->workflowService->approveOrder($order, $this->salesman);
    }

    /**
     * 8. RBAC Protection: Salesman Cannot Cancel Orders Directly
     */
    public function test_salesman_cannot_cancel_orders_directly(): void
    {
        $order = $this->createSubmittedOrder(5, 5);

        $this->expectException(AuthorizationException::class);
        $this->workflowService->cancelOrder($order, $this->salesman, 'Direct cancel');
    }

    /**
     * 9. Inactive User Cannot Perform Reservations
     */
    public function test_inactive_user_cannot_approve_orders(): void
    {
        $inactiveAdmin = User::create([
            'name' => 'Inactive Admin',
            'email' => 'inactive.admin@example.com',
            'password' => bcrypt('Password123!'),
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::DISABLED,
        ]);

        $order = $this->createSubmittedOrder(5, 5);

        $this->expectException(AuthorizationException::class);
        $this->workflowService->approveOrder($order, $inactiveAdmin);
    }

    /**
     * 10. Already Approved Order Cannot Be Re-Approved (Idempotency / State Guard)
     */
    public function test_already_approved_order_cannot_be_reapproved(): void
    {
        $order = $this->createSubmittedOrder(5, 5);
        $approvedOrder = $this->workflowService->approveOrder($order, $this->admin);

        $this->expectException(ConflictHttpException::class);
        $this->workflowService->approveOrder($approvedOrder, $this->admin);
    }
}
