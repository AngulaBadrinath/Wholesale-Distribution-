<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentReasonCode;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderAdjustmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\StockStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;
use App\Services\Delivery\DeliveryWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * QA-002: Authorization & IDOR Penetration Test Suite
 *
 * Comprehensive validation of server-side resource scope enforcement,
 * anti-IDOR fail-closed boundaries, nested resource protection, maker-checker
 * segregation of duties, and actor-field tampering defenses across all domains.
 */
class AuthorizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $accountant;
    protected User $salesmanA;
    protected User $salesmanB;
    protected User $driverA;
    protected User $driverB;
    protected User $warehouseManager;

    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Category $category;
    protected Product $productA;
    protected Product $productB;

    protected Customer $customerA;
    protected Customer $customerB;

    protected Order $orderA;
    protected Order $orderB;

    protected Delivery $deliveryA;
    protected Delivery $deliveryB;

    protected ReturnRequest $returnA;
    protected ReturnRequest $returnB;

    protected Payment $paymentA;
    protected Payment $paymentB;

    protected Invoice $invoiceA;
    protected Invoice $invoiceB;

    protected ResourceScopeService $resourceScopeService;
    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);

        $this->resourceScopeService = app(ResourceScopeService::class);
        $this->permissionService = app(PermissionService::class);

        // 1. Establish Actors across all operational roles
        $this->superAdmin = User::factory()->superAdmin()->create(['name' => 'Super Admin', 'email' => 'super@example.com']);
        $this->admin = User::factory()->admin()->create(['name' => 'General Admin', 'email' => 'admin@example.com']);
        $this->accountant = User::factory()->accountant()->create(['name' => 'Accountant User', 'email' => 'accountant@example.com']);
        $this->salesmanA = User::factory()->salesman()->create(['name' => 'Salesman Alpha', 'email' => 'salesman.a@example.com']);
        $this->salesmanB = User::factory()->salesman()->create(['name' => 'Salesman Beta', 'email' => 'salesman.b@example.com']);
        $this->driverA = User::factory()->deliveryPartner()->create(['name' => 'Driver Alpha', 'email' => 'driver.a@example.com']);
        $this->driverB = User::factory()->deliveryPartner()->create(['name' => 'Driver Beta', 'email' => 'driver.b@example.com']);
        $this->warehouseManager = User::factory()->warehouseManager()->create(['name' => 'Warehouse Lead', 'email' => 'warehouse@example.com']);

        // 2. Establish Warehouses & Categories
        $this->warehouseA = Warehouse::create([
            'code' => 'WH-ALPHA',
            'name' => 'Alpha Distribution Hub',
            'address_line1' => '100 Alpha Way',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'code' => 'WH-BETA',
            'name' => 'Beta Logistics Center',
            'address_line1' => '200 Beta Road',
            'city' => 'Gotham',
            'state' => 'NJ',
            'postal_code' => '07001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->category = Category::create([
            'code' => 'BEV',
            'name' => 'Beverages',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'category_id' => $this->category->id,
            'sku' => 'SKU-BEV-001',
            'name' => 'Sparkling Water 500ml',
            'unit' => 'BOTTLE',
            'status' => ProductStatus::ACTIVE,
            'default_selling_price' => '10.00',
            'cost_price' => '5.00',
            'minimum_allowed_price' => '8.00',
            'mrp' => '12.00',
        ]);

        $this->productB = Product::create([
            'category_id' => $this->category->id,
            'sku' => 'SKU-BEV-002',
            'name' => 'Artisanal Cola 330ml',
            'unit' => 'CAN',
            'status' => ProductStatus::ACTIVE,
            'default_selling_price' => '15.00',
            'cost_price' => '7.50',
            'minimum_allowed_price' => '12.00',
            'mrp' => '18.00',
        ]);

        // 3. Establish Stock Balances
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouseA->id, 'product_id' => $this->productA->id],
            [
                'on_hand_quantity' => 500,
                'reserved_quantity' => 0,
                'available_quantity' => 500,
                'damaged_quantity' => 0,
                'reorder_point' => 50,
                'is_active' => true,
            ]
        );

        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouseB->id, 'product_id' => $this->productB->id],
            [
                'on_hand_quantity' => 300,
                'reserved_quantity' => 0,
                'available_quantity' => 300,
                'damaged_quantity' => 0,
                'reorder_point' => 30,
                'is_active' => true,
            ]
        );

        // 4. Establish Customers scoped to Salesman A and Salesman B
        $this->customerA = Customer::create([
            'salesman_id' => $this->salesmanA->id,
            'name' => 'Alpha Grocery Corp',
            'code' => 'CUST-ALPHA-01',
            'contact_name' => 'Arthur Alpha',
            'phone' => '+15551112222',
            'email' => 'arthur@alpha.com',
            'billing_address_line1' => '111 Alpha St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'shipping_address_line1' => '111 Alpha St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->customerB = Customer::create([
            'salesman_id' => $this->salesmanB->id,
            'name' => 'Beta Mart LLC',
            'code' => 'CUST-BETA-02',
            'contact_name' => 'Beatrice Beta',
            'phone' => '+15553334444',
            'email' => 'beatrice@beta.com',
            'billing_address_line1' => '222 Beta Blvd',
            'billing_city' => 'Gotham',
            'billing_state' => 'NJ',
            'billing_postal_code' => '07001',
            'shipping_address_line1' => '222 Beta Blvd',
            'shipping_city' => 'Gotham',
            'shipping_state' => 'NJ',
            'shipping_postal_code' => '07001',
            'status' => CustomerStatus::ACTIVE,
        ]);

        // 5. Establish Orders
        $this->orderA = Order::create([
            'order_number' => 'ORD-TEST-001',
            'customer_id' => $this->customerA->id,
            'salesman_id' => $this->salesmanA->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => '100.00',
            'tax_total' => '10.00',
            'grand_total' => '110.00',
            'idempotency_key' => 'idemp-order-test-001',
            'created_by' => $this->salesmanA->id,
        ]);

        OrderItem::create([
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => 'BOTTLE',
            'unit_price' => '10.00',
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'taxable_amount' => '100.00',
            'tax_rate_snapshot' => '10.00',
            'tax_amount' => '10.00',
            'line_total' => '110.00',
        ]);

        $this->orderB = Order::create([
            'order_number' => 'ORD-TEST-002',
            'customer_id' => $this->customerB->id,
            'salesman_id' => $this->salesmanB->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => '150.00',
            'tax_total' => '15.00',
            'grand_total' => '165.00',
            'idempotency_key' => 'idemp-order-test-002',
            'created_by' => $this->salesmanB->id,
        ]);

        OrderItem::create([
            'order_id' => $this->orderB->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => 'CAN',
            'unit_price' => '15.00',
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'taxable_amount' => '150.00',
            'tax_rate_snapshot' => '10.00',
            'tax_amount' => '15.00',
            'line_total' => '165.00',
        ]);

        // 6. Establish Deliveries assigned to Driver A and Driver B
        $this->deliveryA = Delivery::create([
            'delivery_number' => 'DEL-TEST-001',
            'order_id' => $this->orderA->id,
            'customer_id' => $this->customerA->id,
            'driver_id' => $this->driverA->id,
            'status' => DeliveryStatus::ASSIGNED,
            'scheduled_date' => Carbon::today(),
            'delivery_address_line1' => '111 Alpha St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'total_items_count' => 1,
            'total_units_count' => 10,
            'created_by' => $this->admin->id,
        ]);

        $this->deliveryB = Delivery::create([
            'delivery_number' => 'DEL-TEST-002',
            'order_id' => $this->orderB->id,
            'customer_id' => $this->customerB->id,
            'driver_id' => $this->driverB->id,
            'status' => DeliveryStatus::ASSIGNED,
            'scheduled_date' => Carbon::today(),
            'delivery_address_line1' => '222 Beta Blvd',
            'delivery_city' => 'Gotham',
            'delivery_state' => 'NJ',
            'delivery_postal_code' => '07001',
            'delivery_country_code' => 'USA',
            'total_items_count' => 1,
            'total_units_count' => 10,
            'created_by' => $this->admin->id,
        ]);

        // 7. Establish Return Requests
        $this->returnA = ReturnRequest::create([
            'return_number' => 'RET-TEST-001',
            'order_id' => $this->orderA->id,
            'customer_id' => $this->customerA->id,
            'salesman_id' => $this->salesmanA->id,
            'warehouse_id' => $this->warehouseA->id,
            'status' => ReturnStatus::REQUESTED,
            'created_by' => $this->salesmanA->id,
            'requested_at' => Carbon::now(),
            'total_requested_quantity' => 2,
        ]);

        $this->returnB = ReturnRequest::create([
            'return_number' => 'RET-TEST-002',
            'order_id' => $this->orderB->id,
            'customer_id' => $this->customerB->id,
            'salesman_id' => $this->salesmanB->id,
            'warehouse_id' => $this->warehouseB->id,
            'status' => ReturnStatus::REQUESTED,
            'created_by' => $this->salesmanB->id,
            'requested_at' => Carbon::now(),
            'total_requested_quantity' => 3,
        ]);

        // 8. Establish Payments
        $this->paymentA = Payment::create([
            'payment_number' => 'PAY-TEST-001',
            'customer_id' => $this->customerA->id,
            'order_id' => $this->orderA->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '110.00',
            'payment_date' => Carbon::today(),
            'recorded_by' => $this->salesmanA->id,
        ]);

        $this->paymentB = Payment::create([
            'payment_number' => 'PAY-TEST-002',
            'customer_id' => $this->customerB->id,
            'order_id' => $this->orderB->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '165.00',
            'payment_date' => Carbon::today(),
            'recorded_by' => $this->salesmanB->id,
        ]);

        // 9. Establish Invoices
        $this->invoiceA = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'order_id' => $this->orderA->id,
            'customer_id' => $this->customerA->id,
            'subtotal' => '100.00',
            'tax_total' => '10.00',
            'grand_total' => '110.00',
            'amount_paid' => '0.00',
            'amount_due' => '110.00',
            'invoice_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'customer_name_snapshot' => $this->customerA->name,
            'customer_code_snapshot' => $this->customerA->code,
            'customer_contact_snapshot' => $this->customerA->contact_name,
            'customer_email_snapshot' => $this->customerA->email,
            'customer_phone_snapshot' => $this->customerA->phone,
            'billing_address_line1_snapshot' => '111 Alpha St',
            'billing_city_snapshot' => 'Metropolis',
            'billing_state_snapshot' => 'NY',
            'billing_postal_code_snapshot' => '10001',
            'billing_country_snapshot' => 'USA',
            'shipping_address_line1_snapshot' => '111 Alpha St',
            'shipping_city_snapshot' => 'Metropolis',
            'shipping_state_snapshot' => 'NY',
            'shipping_postal_code_snapshot' => '10001',
            'shipping_country_snapshot' => 'USA',
            'company_legal_name_snapshot' => 'WDMS Distribution Corp',
            'company_address_snapshot' => '1 Distribution Way',
            'company_phone_snapshot' => '+15559990000',
            'company_email_snapshot' => 'billing@wdms.com',
            'company_tax_id_snapshot' => 'TAX-12345',
            'created_by' => $this->admin->id,
        ]);

        $this->invoiceB = Invoice::create([
            'invoice_number' => 'INV-TEST-002',
            'order_id' => $this->orderB->id,
            'customer_id' => $this->customerB->id,
            'subtotal' => '150.00',
            'tax_total' => '15.00',
            'grand_total' => '165.00',
            'amount_paid' => '0.00',
            'amount_due' => '165.00',
            'invoice_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(30),
            'customer_name_snapshot' => $this->customerB->name,
            'customer_code_snapshot' => $this->customerB->code,
            'customer_contact_snapshot' => $this->customerB->contact_name,
            'customer_email_snapshot' => $this->customerB->email,
            'customer_phone_snapshot' => $this->customerB->phone,
            'billing_address_line1_snapshot' => '222 Beta Blvd',
            'billing_city_snapshot' => 'Gotham',
            'billing_state_snapshot' => 'NJ',
            'billing_postal_code_snapshot' => '07001',
            'billing_country_snapshot' => 'USA',
            'shipping_address_line1_snapshot' => '222 Beta Blvd',
            'shipping_city_snapshot' => 'Gotham',
            'shipping_state_snapshot' => 'NJ',
            'shipping_postal_code_snapshot' => '07001',
            'shipping_country_snapshot' => 'USA',
            'company_legal_name_snapshot' => 'WDMS Distribution Corp',
            'company_address_snapshot' => '1 Distribution Way',
            'company_phone_snapshot' => '+15559990000',
            'company_email_snapshot' => 'billing@wdms.com',
            'company_tax_id_snapshot' => 'TAX-12345',
            'created_by' => $this->admin->id,
        ]);
    }

    /* =========================================================================
     * CATEGORY A — POSITIVE AUTHORIZATION
     * ========================================================================= */

    public function test_salesman_can_view_own_assigned_customer(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertTrue(Gate::allows('view', $this->customerA));
        $this->assertTrue($this->resourceScopeService->canAccessCustomer($this->salesmanA, $this->customerA));

        $response = $this->get(route('customers.show', $this->customerA->id));
        $response->assertOk();
    }

    public function test_salesman_can_view_own_assigned_order(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertTrue(Gate::allows('view', $this->orderA));
        $this->assertTrue($this->resourceScopeService->canAccessOrder($this->salesmanA, $this->orderA));

        $response = $this->get(route('salesman.orders.show', $this->orderA->id));
        $response->assertOk();
    }

    public function test_salesman_can_view_own_assigned_invoice(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertTrue(Gate::allows('view', $this->invoiceA));
        $this->assertTrue($this->resourceScopeService->canAccessInvoice($this->salesmanA, $this->invoiceA));

        $response = $this->get(route('salesman.invoices.show', $this->invoiceA->id));
        $response->assertOk();
    }

    public function test_salesman_can_view_own_assigned_payment(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertTrue(Gate::allows('view', $this->paymentA));
        $this->assertTrue($this->resourceScopeService->canAccessPayment($this->salesmanA, $this->paymentA));
    }

    public function test_salesman_can_view_own_assigned_return(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertTrue(Gate::allows('view', $this->returnA));
        $this->assertTrue($this->resourceScopeService->canAccessReturn($this->salesmanA, $this->returnA));

        $response = $this->get(route('salesman.returns.show', $this->returnA->id));
        $response->assertOk();
    }

    public function test_driver_can_view_assigned_delivery(): void
    {
        $this->actingAs($this->driverA);

        $this->assertTrue(Gate::allows('view', $this->deliveryA));
        $this->assertTrue($this->resourceScopeService->canAccessDelivery($this->driverA, $this->deliveryA));

        $response = $this->get(route('delivery.show', $this->deliveryA->id));
        $response->assertOk();
    }

    public function test_warehouse_manager_can_view_inventory(): void
    {
        $this->actingAs($this->warehouseManager);

        $balance = InventoryBalance::first();
        $this->assertTrue(Gate::allows('view', $balance));
        $this->assertTrue($this->resourceScopeService->canAccessInventoryBalance($this->warehouseManager, $balance));

        $response = $this->get(route('admin.inventory.index'));
        $response->assertOk();
    }

    public function test_admin_and_super_admin_have_broad_operational_access(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::allows('view', $this->customerA));
        $this->assertTrue(Gate::allows('view', $this->customerB));
        $this->assertTrue(Gate::allows('view', $this->orderA));
        $this->assertTrue(Gate::allows('view', $this->orderB));
        $this->assertTrue(Gate::allows('view', $this->deliveryA));
        $this->assertTrue(Gate::allows('view', $this->deliveryB));
        $this->assertTrue(Gate::allows('view', $this->returnA));
        $this->assertTrue(Gate::allows('view', $this->returnB));
    }

    /* =========================================================================
     * CATEGORY B — CROSS-SALESMAN IDOR
     * ========================================================================= */

    public function test_salesman_a_cannot_view_salesman_b_customer_fail_closed(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertFalse(Gate::allows('view', $this->customerB));
        $this->assertFalse($this->resourceScopeService->canAccessCustomer($this->salesmanA, $this->customerB));

        $response = $this->get(route('customers.show', $this->customerB->id));
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_salesman_a_cannot_view_salesman_b_order_fail_closed_404(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertFalse(Gate::allows('view', $this->orderB));
        $this->assertFalse($this->resourceScopeService->canAccessOrder($this->salesmanA, $this->orderB));

        $response = $this->get(route('salesman.orders.show', $this->orderB->id));
        $response->assertNotFound();
    }

    public function test_salesman_a_cannot_create_order_for_salesman_b_customer(): void
    {
        $this->actingAs($this->salesmanA);

        $response = $this->post(route('salesman.orders.store'), [
            'customer_id' => $this->customerB->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 5,
                    'unit_price' => '10.00',
                ],
            ],
            'idempotency_key' => 'scope-order-test-01',
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404, 422, 302], true));
    }

    public function test_salesman_a_cannot_view_salesman_b_invoice_fail_closed_404(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertFalse(Gate::allows('view', $this->invoiceB));
        $this->assertFalse($this->resourceScopeService->canAccessInvoice($this->salesmanA, $this->invoiceB));

        $response = $this->get(route('salesman.invoices.show', $this->invoiceB->id));
        $response->assertNotFound();
    }

    public function test_salesman_a_cannot_view_salesman_b_return_fail_closed_404(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertFalse(Gate::allows('view', $this->returnB));
        $this->assertFalse($this->resourceScopeService->canAccessReturn($this->salesmanA, $this->returnB));

        $response = $this->get(route('salesman.returns.show', $this->returnB->id));
        $response->assertNotFound();
    }

    public function test_salesman_a_cannot_request_adjustment_on_salesman_b_order_fail_closed_404(): void
    {
        $this->actingAs($this->salesmanA);

        $itemB = $this->orderB->items->first();

        $response = $this->post(route('orders.adjustments.store', $this->orderB->id), [
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST->value,
            'items' => [
                [
                    'order_item_id' => $itemB->id,
                    'reduction_quantity' => 1,
                ],
            ],
            'idempotency_key' => 'scope-adj-test-key-01',
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    /* =========================================================================
     * CATEGORY C — CROSS-DRIVER IDOR
     * ========================================================================= */

    public function test_driver_a_cannot_view_driver_b_delivery_fail_closed_404(): void
    {
        $this->actingAs($this->driverA);

        $this->assertFalse(Gate::allows('view', $this->deliveryB));
        $this->assertFalse($this->resourceScopeService->canAccessDelivery($this->driverA, $this->deliveryB));

        $response = $this->get(route('delivery.show', $this->deliveryB->id));
        $response->assertNotFound();
    }

    public function test_driver_a_cannot_pickup_driver_b_delivery_fail_closed_404(): void
    {
        $this->actingAs($this->driverA);

        $response = $this->post(route('delivery.pickup', $this->deliveryB->id));
        $response->assertNotFound();
    }

    public function test_driver_a_cannot_start_route_driver_b_delivery_fail_closed_404(): void
    {
        $this->actingAs($this->driverA);

        $response = $this->post(route('delivery.start-route', $this->deliveryB->id));
        $response->assertNotFound();
    }

    public function test_driver_a_cannot_complete_driver_b_delivery_fail_closed_404(): void
    {
        $this->actingAs($this->driverA);

        $response = $this->post(route('delivery.complete', $this->deliveryB->id), [
            'recipient_name' => 'Imposter Driver',
        ]);
        $response->assertNotFound();
    }

    public function test_driver_a_cannot_fail_driver_b_delivery_fail_closed_404(): void
    {
        $this->actingAs($this->driverA);

        $response = $this->post(route('delivery.fail', $this->deliveryB->id), [
            'failure_reason' => 'CUSTOMER_UNAVAILABLE',
            'driver_notes' => 'Unauthorized attempt by driver A',
        ]);
        $response->assertNotFound();
    }

    public function test_driver_a_cannot_reschedule_driver_b_delivery_fail_closed_404(): void
    {
        $this->actingAs($this->driverA);

        $response = $this->post(route('delivery.reschedule', $this->deliveryB->id), [
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ]);
        $response->assertNotFound();
    }

    public function test_driver_a_cannot_return_driver_b_delivery_to_warehouse_fail_closed_404(): void
    {
        $this->actingAs($this->driverA);

        $response = $this->post(route('delivery.return-warehouse', $this->deliveryB->id), [
            'notes' => 'Attempting unauthorized return to warehouse',
        ]);
        $response->assertNotFound();
    }

    /* =========================================================================
     * CATEGORY D — CROSS-WAREHOUSE / INVENTORY IDOR
     * ========================================================================= */

    public function test_unprivileged_role_cannot_adjust_inventory_fail_closed_403(): void
    {
        $this->actingAs($this->salesmanA);

        $balance = InventoryBalance::first();
        $this->assertFalse(Gate::allows('adjust', $balance));

        $response = $this->post(route('admin.inventory.adjustments.store'), [
            'inventory_balance_id' => $balance->id,
            'adjustment_type' => 'INCREASE_FOUND',
            'quantity' => 10,
            'reason_code' => 'CYCLE_COUNT_DISCREPANCY',
            'notes' => 'Salesman attempting stock adjustment',
        ]);

        $response->assertForbidden();
    }

    public function test_unprivileged_role_cannot_report_stock_exception_without_permission_fail_closed_403(): void
    {
        $this->actingAs($this->driverA);

        $balance = InventoryBalance::first();
        $this->assertFalse(Gate::allows('reportException', $balance));

        $response = $this->post(route('admin.inventory.exceptions.store'), [
            'inventory_balance_id' => $balance->id,
            'exception_type' => 'DAMAGED_IN_STORAGE',
            'quantity' => 5,
            'description' => 'Driver reporting warehouse stock exception',
        ]);

        $response->assertForbidden();
    }

    /* =========================================================================
     * CATEGORY E — NESTED RESOURCE IDOR
     * ========================================================================= */

    public function test_cannot_withdraw_adjustment_under_mismatched_foreign_order_fail_closed_404(): void
    {
        $adjustmentB = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-TEST-B-01',
            'order_id' => $this->orderB->id,
            'order_number_snapshot' => $this->orderB->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => $this->orderB->status instanceof OrderStatus ? $this->orderB->status->value : (string) $this->orderB->status,
            'order_subtotal_snapshot' => $this->orderB->subtotal,
            'order_tax_total_snapshot' => $this->orderB->tax_total,
            'order_grand_total_snapshot' => $this->orderB->grand_total,
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::SUBMITTED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Beta adjustment',
            'requested_by' => $this->salesmanB->id,
            'requested_at' => Carbon::now(),
            'projected_subtotal_reduction' => '15.00',
            'projected_tax_reduction' => '1.50',
            'projected_grand_total_reduction' => '16.50',
            'idempotency_key' => 'nested-adj-test-key-01',
            'request_fingerprint' => hash('sha256', 'nested-adj-test-key-01'),
        ]);

        $this->actingAs($this->salesmanB);

        // Salesman B attempts to withdraw adjustment B using Order A's route parameter: /orders/{orderA}/adjustments/{adjustmentB}/withdraw
        $response = $this->post(route('orders.adjustments.withdraw', [
            'order' => $this->orderA->id,
            'adjustment' => $adjustmentB->id,
        ]), [
            'reason' => 'Testing mismatched order parameter',
        ]);

        $response->assertNotFound();
    }

    public function test_cannot_manipulate_product_image_under_mismatched_product_fail_closed_404(): void
    {
        $imageB = ProductImage::create([
            'product_id' => $this->productB->id,
            'object_key' => 'products/test-b.jpg',
            'original_filename' => 'test-b.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin);

        // Admin attempts to delete image B using Product A's route parameter: /products/{productA}/images/{imageB}
        $response = $this->delete(route('products.images.destroy', [
            'product' => $this->productA->id,
            'image' => $imageB->id,
        ]));

        $response->assertNotFound();
    }

    /* =========================================================================
     * CATEGORY F — MUTATION IDOR & MAKER-CHECKER SEGREGATION
     * ========================================================================= */

    public function test_salesman_cannot_approve_order_403(): void
    {
        $this->actingAs($this->salesmanA);

        $this->assertFalse(Gate::allows('approve', $this->orderA));

        $response = $this->post(route('admin.orders.approve', $this->orderA->id));
        $response->assertForbidden();
    }

    public function test_requester_cannot_approve_own_order_adjustment_unless_super_admin(): void
    {
        $adjustmentA = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-TEST-A-02',
            'order_id' => $this->orderA->id,
            'order_number_snapshot' => $this->orderA->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => $this->orderA->status instanceof OrderStatus ? $this->orderA->status->value : (string) $this->orderA->status,
            'order_subtotal_snapshot' => $this->orderA->subtotal,
            'order_tax_total_snapshot' => $this->orderA->tax_total,
            'order_grand_total_snapshot' => $this->orderA->grand_total,
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::SUBMITTED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Alpha adjustment requested by admin',
            'requested_by' => $this->admin->id,
            'requested_at' => Carbon::now(),
            'projected_subtotal_reduction' => '10.00',
            'projected_tax_reduction' => '1.00',
            'projected_grand_total_reduction' => '11.00',
            'idempotency_key' => 'maker-checker-adj-01',
            'request_fingerprint' => hash('sha256', 'maker-checker-adj-01'),
        ]);

        // 1. Admin who requested the adjustment CANNOT approve it (Maker-Checker violation)
        $this->actingAs($this->admin);
        $this->assertFalse(Gate::allows('approve', $adjustmentA));

        // 2. Super Admin CAN approve it under emergency override
        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('approve', $adjustmentA));
    }

    public function test_requester_cannot_approve_own_return_request_unless_super_admin(): void
    {
        $returnCreatedByAdmin = ReturnRequest::create([
            'return_number' => 'RET-TEST-ADM-01',
            'order_id' => $this->orderA->id,
            'customer_id' => $this->customerA->id,
            'salesman_id' => $this->salesmanA->id,
            'warehouse_id' => $this->warehouseA->id,
            'status' => ReturnStatus::INSPECTED,
            'created_by' => $this->admin->id,
            'requested_at' => Carbon::now(),
            'total_requested_quantity' => 1,
        ]);

        // 1. Admin who created the return CANNOT approve it
        $this->actingAs($this->admin);
        $this->assertFalse(Gate::allows('approve', $returnCreatedByAdmin));

        // 2. Super Admin CAN approve it
        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('approve', $returnCreatedByAdmin));
    }

    public function test_recorder_cannot_verify_own_payment_unless_super_admin(): void
    {
        $paymentRecordedByAdmin = Payment::create([
            'payment_number' => 'PAY-TEST-ADM-01',
            'customer_id' => $this->customerA->id,
            'order_id' => $this->orderA->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '50.00',
            'payment_date' => Carbon::today(),
            'recorded_by' => $this->admin->id,
        ]);

        // 1. Admin who recorded the payment CANNOT verify it
        $this->actingAs($this->admin);
        $this->assertFalse(Gate::allows('verify', $paymentRecordedByAdmin));

        // 2. Super Admin CAN verify it
        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('verify', $paymentRecordedByAdmin));
    }

    public function test_user_cannot_suspend_themselves_403(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse(Gate::allows('suspend', $this->admin));
    }

    /* =========================================================================
     * CATEGORY G — ACTOR FIELD TAMPERING
     * ========================================================================= */

    public function test_salesman_cannot_spoof_salesman_id_in_order_submission(): void
    {
        $this->actingAs($this->salesmanA);

        // Salesman A attempts to submit an order with salesman_id pointing to Salesman B
        $response = $this->post(route('salesman.orders.store'), [
            'customer_id' => $this->customerA->id,
            'salesman_id' => $this->salesmanB->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => '10.00',
                ],
            ],
            'idempotency_key' => 'actor-spoof-order-01',
        ]);

        $createdOrder = Order::where('customer_id', $this->customerA->id)->latest('id')->first();
        if ($createdOrder && $createdOrder->id !== $this->orderA->id) {
            // Server MUST enforce that salesman_id was assigned to the authenticated user Salesman A
            $this->assertEquals($this->salesmanA->id, $createdOrder->salesman_id);
        }
    }

    public function test_unauthorized_user_cannot_assign_delivery_driver_by_payload_injection(): void
    {
        $this->actingAs($this->salesmanA);

        $response = $this->post(route('admin.deliveries.assign'), [
            'order_id' => $this->orderA->id,
            'driver_id' => $this->driverA->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    /* =========================================================================
     * CATEGORY H — ROUTE PARAMETER TAMPERING
     * ========================================================================= */

    public function test_direct_route_url_tampering_with_foreign_ids_fails_closed(): void
    {
        $this->actingAs($this->salesmanA);

        // Sequential enumeration attempts for foreign resources
        $foreignOrderIds = [$this->orderB->id, 999999, 888888];

        foreach ($foreignOrderIds as $id) {
            $response = $this->get(route('salesman.orders.show', $id));
            $response->assertNotFound();
        }

        $foreignInvoiceIds = [$this->invoiceB->id, 999999];
        foreach ($foreignInvoiceIds as $id) {
            $response = $this->get(route('salesman.invoices.show', $id));
            $response->assertNotFound();
        }

        $foreignReturnIds = [$this->returnB->id, 999999];
        foreach ($foreignReturnIds as $id) {
            $response = $this->get(route('salesman.returns.show', $id));
            $response->assertNotFound();
        }
    }

    /* =========================================================================
     * CATEGORY I — ROLE CROSSING
     * ========================================================================= */

    public function test_salesman_cannot_access_delivery_driver_workspace(): void
    {
        $this->actingAs($this->salesmanA);

        $response = $this->get(route('delivery.index'));
        $response->assertForbidden();
    }

    public function test_delivery_partner_cannot_access_salesman_workspace(): void
    {
        $this->actingAs($this->driverA);

        $response = $this->get(route('salesman.orders.create'));
        $response->assertForbidden();
    }

    public function test_accountant_cannot_modify_delivery_or_physical_stock(): void
    {
        $this->actingAs($this->accountant);

        // Delivery modification
        $response1 = $this->post(route('admin.deliveries.assign'), [
            'order_id' => $this->orderA->id,
            'driver_id' => $this->driverA->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ]);
        $response1->assertForbidden();

        // Physical inventory adjustment
        $balance = InventoryBalance::first();
        $response2 = $this->post(route('admin.inventory.adjustments.store'), [
            'inventory_balance_id' => $balance->id,
            'adjustment_type' => 'INCREASE_FOUND',
            'quantity' => 1,
            'reason_code' => 'CYCLE_COUNT_DISCREPANCY',
            'notes' => 'Accountant attempting physical adjustment',
        ]);
        $response2->assertForbidden();
    }

    /* =========================================================================
     * CATEGORY J — SCOPE AFTER RELATIONSHIP CHANGE
     * ========================================================================= */

    public function test_customer_reassignment_immediately_transfers_order_and_invoice_access(): void
    {
        // 1. Initial State: Salesman A has access, Salesman B does not
        $this->actingAs($this->salesmanA);
        $this->assertTrue($this->resourceScopeService->canAccessCustomer($this->salesmanA, $this->customerA));

        $this->actingAs($this->salesmanB);
        $this->assertFalse($this->resourceScopeService->canAccessCustomer($this->salesmanB, $this->customerA));

        // 2. Authoritative Reassignment: Customer A is reassigned to Salesman B
        $this->customerA->salesman_id = $this->salesmanB->id;
        $this->customerA->save();

        // 3. Post-Reassignment Verification
        $this->actingAs($this->salesmanA);
        $this->assertFalse($this->resourceScopeService->canAccessCustomer($this->salesmanA, $this->customerA));

        $this->actingAs($this->salesmanB);
        $this->assertTrue($this->resourceScopeService->canAccessCustomer($this->salesmanB, $this->customerA));
    }

    public function test_delivery_reassignment_immediately_transfers_driver_access(): void
    {
        // 1. Initial State: Driver A has access, Driver B does not
        $this->actingAs($this->driverA);
        $this->assertTrue($this->resourceScopeService->canAccessDelivery($this->driverA, $this->deliveryA));

        $this->actingAs($this->driverB);
        $this->assertFalse($this->resourceScopeService->canAccessDelivery($this->driverB, $this->deliveryA));

        // 2. Reassign Delivery A to Driver B
        $this->deliveryA->driver_id = $this->driverB->id;
        $this->deliveryA->save();

        // 3. Post-Reassignment Verification
        $this->actingAs($this->driverA);
        $this->assertFalse($this->resourceScopeService->canAccessDelivery($this->driverA, $this->deliveryA));

        $this->actingAs($this->driverB);
        $this->assertTrue($this->resourceScopeService->canAccessDelivery($this->driverB, $this->deliveryA));
    }

    /* =========================================================================
     * CATEGORY K — EXISTENCE DISCLOSURE
     * ========================================================================= */

    public function test_unassigned_foreign_order_lookup_returns_404_same_as_nonexistent_id(): void
    {
        $this->actingAs($this->salesmanA);

        $responseExistingForeign = $this->get(route('salesman.orders.show', $this->orderB->id));
        $responseNonExistent = $this->get(route('salesman.orders.show', 999999));

        $this->assertEquals(404, $responseExistingForeign->status());
        $this->assertEquals(404, $responseNonExistent->status());
    }

    public function test_unassigned_foreign_delivery_lookup_returns_404_same_as_nonexistent_id(): void
    {
        $this->actingAs($this->driverA);

        $responseExistingForeign = $this->get(route('delivery.show', $this->deliveryB->id));
        $responseNonExistent = $this->get(route('delivery.show', 999999));

        $this->assertEquals(404, $responseExistingForeign->status());
        $this->assertEquals(404, $responseNonExistent->status());
    }

    public function test_unassigned_foreign_return_lookup_returns_404_same_as_nonexistent_id(): void
    {
        $this->actingAs($this->salesmanA);

        $responseExistingForeign = $this->get(route('salesman.returns.show', $this->returnB->id));
        $responseNonExistent = $this->get(route('salesman.returns.show', 999999));

        $this->assertEquals(404, $responseExistingForeign->status());
        $this->assertEquals(404, $responseNonExistent->status());
    }

    /* =========================================================================
     * CATEGORY L — DIRECT SERVICE / DOMAIN GUARD
     * ========================================================================= */

    public function test_direct_service_guards_reject_inactive_or_unauthorized_actors(): void
    {
        $suspendedAdmin = User::factory()->admin()->suspended()->create();

        $deliveryWorkflowService = app(DeliveryWorkflowService::class);

        $this->expectException(AuthorizationException::class);
        $deliveryWorkflowService->confirmPickup($this->deliveryA, $suspendedAdmin);
    }
}
