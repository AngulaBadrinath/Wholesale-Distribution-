<?php

namespace Tests\Feature\Inventory;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Order\OrderWorkflowService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryMovementLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Warehouse $warehouse;
    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;
    protected InventoryBalance $balance;
    protected OrderWorkflowService $workflowService;
    protected InventoryMovementService $movementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflowService = app(OrderWorkflowService::class);
        $this->movementService = app(InventoryMovementService::class);

        $this->admin = User::create([
            'name' => 'Admin Auditor',
            'email' => 'admin.audit@example.com',
            'password' => bcrypt('Password123!'),
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::create([
            'name' => 'Sales Person',
            'email' => 'sales.audit@example.com',
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
            'name' => 'City Mart',
            'code' => 'CUST-CITY-01',
            'contact_name' => 'Alice Mart',
            'email' => 'alice@citymart.test',
            'phone' => '+1-555-9876',
            'billing_address_line1' => '500 Broadway',
            'billing_city' => 'New York',
            'billing_state' => 'NY',
            'billing_postal_code' => '10012',
            'billing_country' => 'USA',
            'shipping_address_line1' => '500 Broadway',
            'shipping_city' => 'New York',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10012',
            'shipping_country' => 'USA',
            'credit_limit' => 20000.00,
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

        $this->product = Product::create([
            'name' => 'Sparkling Lemonade 500ml',
            'sku' => 'BEV-LEMON-01',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 1.20,
            'minimum_allowed_price' => 1.80,
            'default_selling_price' => 2.50,
            'mrp' => 3.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BOTTLE',
        ]);

        $this->balance = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();
        $this->balance->update([
            'on_hand_quantity' => 100,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'available_quantity' => 100,
            'version' => 1,
        ]);
    }

    protected function createSubmittedOrder(int $qty = 12): Order
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
            'subtotal' => $qty * 2.50,
            'tax_total' => ($qty * 2.50) * 0.10,
            'grand_total' => ($qty * 2.50) * 1.10,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => $qty,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => 2.50,
            'tax_rate' => 0.10,
            'tax_amount' => ($qty * 2.50) * 0.10,
            'line_total' => ($qty * 2.50) * 1.10,
        ]);

        return $order;
    }

    /**
     * 1. Stock Reservation Generates Immutable Movement Record
     */
    public function test_stock_reservation_creates_immutable_movement_record_atomically(): void
    {
        $order = $this->createSubmittedOrder(12);

        $this->assertEquals(0, InventoryMovement::where('inventory_balance_id', $this->balance->id)->count());

        $this->workflowService->approveOrder($order, $this->admin);

        $movements = InventoryMovement::where('inventory_balance_id', $this->balance->id)->get();
        $this->assertCount(1, $movements);

        $movement = $movements->first();
        $this->assertNotNull($movement);
        $this->assertStringStartsWith('MOV-', $movement->movement_number);
        $this->assertEquals(InventoryMovementType::RESERVATION, $movement->movement_type);
        $this->assertEquals(InventoryStockState::AVAILABLE, $movement->from_state);
        $this->assertEquals(InventoryStockState::RESERVED, $movement->to_state);
        $this->assertEquals(12, $movement->quantity);
        $this->assertEquals(100, $movement->on_hand_before);
        $this->assertEquals(100, $movement->on_hand_after);
        $this->assertEquals(0, $movement->reserved_before);
        $this->assertEquals(12, $movement->reserved_after);
        $this->assertEquals(100, $movement->available_before);
        $this->assertEquals(88, $movement->available_after);
        $this->assertEquals(0, $movement->damaged_before);
        $this->assertEquals(0, $movement->damaged_after);
        $this->assertEquals('order', $movement->reference_type);
        $this->assertEquals($order->id, $movement->reference_id);
        $this->assertEquals($order->order_number, $movement->reference_number);
        $this->assertEquals($this->admin->id, $movement->actor_id);
    }

    /**
     * 2. Stock Release Generates Second Movement Record
     */
    public function test_stock_release_creates_immutable_movement_record_atomically(): void
    {
        $order = $this->createSubmittedOrder(15);
        $approvedOrder = $this->workflowService->approveOrder($order, $this->admin);

        $this->workflowService->cancelOrder($approvedOrder, $this->admin, 'Customer cancellation request');

        $movements = InventoryMovement::where('inventory_balance_id', $this->balance->id)->orderBy('id', 'asc')->get();
        $this->assertCount(2, $movements);

        // 1st Movement: Reservation
        $resMov = $movements[0];
        $this->assertEquals(InventoryMovementType::RESERVATION, $resMov->movement_type);
        $this->assertEquals(15, $resMov->quantity);

        // 2nd Movement: Release
        $relMov = $movements[1];
        $this->assertEquals(InventoryMovementType::RELEASE, $relMov->movement_type);
        $this->assertEquals(InventoryStockState::RESERVED, $relMov->from_state);
        $this->assertEquals(InventoryStockState::AVAILABLE, $relMov->to_state);
        $this->assertEquals(15, $relMov->quantity);
        $this->assertEquals(100, $relMov->on_hand_before);
        $this->assertEquals(100, $relMov->on_hand_after);
        $this->assertEquals(15, $relMov->reserved_before);
        $this->assertEquals(0, $relMov->reserved_after);
        $this->assertEquals(85, $relMov->available_before);
        $this->assertEquals(100, $relMov->available_after);
        $this->assertEquals($order->order_number, $relMov->reference_number);
    }

    /**
     * 3. Immutability: Updates are strictly rejected
     */
    public function test_movement_records_are_strictly_immutable_and_reject_updates(): void
    {
        $order = $this->createSubmittedOrder(10);
        $this->workflowService->approveOrder($order, $this->admin);

        $movement = InventoryMovement::where('inventory_balance_id', $this->balance->id)->firstOrFail();

        $this->expectException(DomainException::class);
        $movement->update(['notes' => 'Tampered note']);
    }

    /**
     * 4. Immutability: Deletions are strictly rejected
     */
    public function test_movement_records_are_strictly_immutable_and_reject_deletions(): void
    {
        $order = $this->createSubmittedOrder(10);
        $this->workflowService->approveOrder($order, $this->admin);

        $movement = InventoryMovement::where('inventory_balance_id', $this->balance->id)->firstOrFail();

        $this->expectException(DomainException::class);
        $movement->delete();
    }

    /**
     * 5. Detail Workspace Exposes Movement Ledger
     */
    public function test_detail_workspace_renders_movement_ledger(): void
    {
        $order = $this->createSubmittedOrder(8);
        $this->workflowService->approveOrder($order, $this->admin);

        $response = $this->actingAs($this->admin)->get("/admin/inventory/{$this->balance->id}");
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Show')
            ->has('detail.recent_movements', 1)
            ->where('detail.recent_movements.0.movement_type', InventoryMovementType::RESERVATION->value)
            ->where('detail.recent_movements.0.quantity', 8)
            ->where('detail.recent_movements.0.reference_number', $order->order_number)
            ->where('detail.recent_movements.0.actor_name', $this->admin->name)
        );
    }
}
