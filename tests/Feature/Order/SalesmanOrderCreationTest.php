<?php

namespace Tests\Feature\Order;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesmanOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesman;
    protected User $otherSalesman;
    protected User $admin;
    protected Customer $activeCustomer;
    protected Category $category;
    protected TaxProfile $standardTaxProfile;
    protected TaxProfile $zeroTaxProfile;
    protected Product $standardProduct;
    protected Product $secondProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesman = User::factory()->create([
            'name' => 'Salesman Sam',
            'email' => 'sam.sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->otherSalesman = User::factory()->create([
            'name' => 'Salesman Bob',
            'email' => 'bob.sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Alice',
            'email' => 'alice.admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->activeCustomer = Customer::create([
            'code' => 'CUST-00010',
            'name' => 'Atlanta Supermarket',
            'contact_name' => 'Dave Miller',
            'email' => 'buyer@atlantasuper.test',
            'phone' => '+1 (555) 019-2834',
            'billing_address_line1' => '100 Peachtree St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '100 Peachtree St',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'credit_limit' => 25000.00,
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV',
            'status' => 'ACTIVE',
            'sort_order' => 1,
        ]);

        $this->standardTaxProfile = TaxProfile::create([
            'name' => 'Standard Rate 8.25%',
            'code' => 'STD-825',
            'rate' => '8.2500',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->zeroTaxProfile = TaxProfile::create([
            'name' => 'Exempt 0%',
            'code' => 'EXEMPT-0',
            'rate' => '0.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->standardProduct = Product::create([
            'sku' => 'BEV-COLA-01',
            'name' => 'Classic Cola 24-pack',
            'description' => '24x 12oz cans',
            'category_id' => $this->category->id,
            'unit' => 'CASE',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 10.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 15.00,
            'mrp' => 20.00,
            'tax_profile_id' => $this->standardTaxProfile->id,
        ]);

        $this->secondProduct = Product::create([
            'sku' => 'BEV-WATER-01',
            'name' => 'Spring Water 32-pack',
            'description' => '32x 16.9oz bottles',
            'category_id' => $this->category->id,
            'unit' => 'CASE',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 4.00,
            'minimum_allowed_price' => 5.00,
            'default_selling_price' => 7.00,
            'mrp' => 10.00,
            'tax_profile_id' => $this->zeroTaxProfile->id,
        ]);
    }

    /* =========================================================================
     * 1. GET /salesman/orders/create (Order Builder Page)
     * ========================================================================= */

    public function test_salesman_can_render_order_builder_with_scoped_active_customers(): void
    {
        // Unassigned customer that shouldn't appear in salesman's list
        Customer::create([
            'code' => 'CUST-00099',
            'name' => 'Unassigned Mart',
            'contact_name' => 'Bob Owner',
            'phone' => '+1 (555) 999-0099',
            'billing_address_line1' => '200 Main St',
            'billing_city' => 'Dallas',
            'billing_state' => 'TX',
            'billing_postal_code' => '75001',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->otherSalesman->id,
        ]);

        $response = $this->actingAs($this->salesman)
            ->get('/salesman/orders/create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Create')
            ->has('customers', 1)
            ->where('customers.0.code', 'CUST-00010')
            ->has('categories', 1)
            ->has('products.data', 2)
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/salesman/orders/create');
        $response->assertRedirect(route('login'));
    }

    /* =========================================================================
     * 2. CUSTOMER AUTHORIZATION & SCOPE (RULE-SEC-003, RULE-ORD-003)
     * ========================================================================= */

    public function test_salesman_can_create_order_for_assigned_active_customer(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Please deliver by Friday 10 AM.',
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 10,
                    'unit_price' => '15.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();

        $this->assertNotNull($order);
        $response->assertRedirect("/salesman/orders/{$order->id}");
        $response->assertSessionHas('success');

        $this->assertEquals('ORD-', substr($order->order_number, 0, 4));
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertEquals(PaymentStatus::UNPAID, $order->payment_status);
        $this->assertEquals(DeliveryStatus::PENDING_ASSIGNMENT, $order->delivery_status);
        $this->assertEquals(AdjustmentStatus::NONE, $order->adjustment_status);
        $this->assertEquals($this->salesman->id, $order->salesman_id);
        $this->assertEquals($this->salesman->id, $order->created_by);
        $this->assertEquals($this->activeCustomer->id, $order->customer_id);

        // Financial Totals Assertion: 10 * 15.00 = 150.00 taxable, 8.25% tax = 12.38, grand_total = 162.38
        $this->assertEquals('150.00', (string) $order->subtotal);
        $this->assertEquals('12.38', (string) $order->tax_total);
        $this->assertEquals('162.38', (string) $order->grand_total);

        // Line Item Snapshot Assertions
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertEquals($this->standardProduct->id, $item->product_id);
        $this->assertEquals('Classic Cola 24-pack', $item->product_name_snapshot);
        $this->assertEquals('BEV-COLA-01', $item->sku_snapshot);
        $this->assertEquals('CASE', $item->unit_snapshot);
        $this->assertEquals(10, $item->ordered_quantity);
        $this->assertEquals(0, $item->cancelled_quantity);
        $this->assertEquals(10, $item->fulfillableQuantity());
        $this->assertEquals('15.00', (string) $item->unit_price);
        $this->assertFalse($item->is_price_overridden);
        $this->assertEquals($this->standardTaxProfile->id, $item->tax_profile_id);
        $this->assertEquals('STD-825', $item->tax_profile_code_snapshot);
        $this->assertEquals('Standard Rate 8.25%', $item->tax_profile_name_snapshot);
        $this->assertEquals('8.2500', (string) $item->tax_rate_snapshot);
        $this->assertEquals('150.00', (string) $item->taxable_amount);
        $this->assertEquals('12.38', (string) $item->tax_amount);
        $this->assertEquals('162.38', (string) $item->line_total);
    }

    public function test_salesman_cannot_create_order_for_unassigned_customer(): void
    {
        $unassignedCustomer = Customer::create([
            'code' => 'CUST-00050',
            'name' => 'Miami Foods',
            'contact_name' => 'Carlos Buyer',
            'phone' => '+1 (555) 050-3313',
            'billing_address_line1' => '50 Biscayne Blvd',
            'billing_city' => 'Miami',
            'billing_state' => 'FL',
            'billing_postal_code' => '33132',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->otherSalesman->id,
        ]);

        $payload = [
            'customer_id' => $unassignedCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_salesman_cannot_create_order_for_on_hold_customer(): void
    {
        $onHoldCustomer = Customer::create([
            'code' => 'CUST-00051',
            'name' => 'On Hold Store',
            'contact_name' => 'Hold Contact',
            'phone' => '+1 (555) 051-3030',
            'billing_address_line1' => '50 Peachtree St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'status' => CustomerStatus::ON_HOLD,
            'salesman_id' => $this->salesman->id,
        ]);

        $payload = [
            'customer_id' => $onHoldCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_salesman_cannot_create_order_for_inactive_customer(): void
    {
        $inactiveCustomer = Customer::create([
            'code' => 'CUST-00052',
            'name' => 'Inactive Store',
            'contact_name' => 'Inactive Contact',
            'phone' => '+1 (555) 052-3030',
            'billing_address_line1' => '50 Peachtree St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'status' => CustomerStatus::INACTIVE,
            'salesman_id' => $this->salesman->id,
        ]);

        $payload = [
            'customer_id' => $inactiveCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_or_suspended_salesman_cannot_create_order(): void
    {
        $this->salesman->update(['status' => AccountStatus::SUSPENDED]);

        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertRedirect();
        $this->assertDatabaseCount('orders', 0);
    }

    /* =========================================================================
     * 3. PRODUCT & QUANTITY VALIDATION
     * ========================================================================= */

    public function test_inactive_product_is_rejected_at_submission(): void
    {
        $inactiveProduct = Product::create([
            'sku' => 'BEV-DISC-01',
            'name' => 'Discontinued Soda',
            'category_id' => $this->category->id,
            'unit' => 'CASE',
            'status' => ProductStatus::INACTIVE,
            'cost_price' => 5.00,
            'minimum_allowed_price' => 6.00,
            'default_selling_price' => 8.00,
            'mrp' => 10.00,
            'tax_profile_id' => $this->standardTaxProfile->id,
        ]);

        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $inactiveProduct->id, 'quantity' => 2],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_quantity_must_be_positive_integer(): void
    {
        // Test Zero Quantity
        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', [
                'customer_id' => $this->activeCustomer->id,
                'idempotency_key' => (string) Str::uuid(),
                'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 0]],
            ]);
        $response->assertSessionHasErrors('items.0.quantity');

        // Test Negative Quantity
        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', [
                'customer_id' => $this->activeCustomer->id,
                'idempotency_key' => (string) Str::uuid(),
                'items' => [['product_id' => $this->standardProduct->id, 'quantity' => -5]],
            ]);
        $response->assertSessionHasErrors('items.0.quantity');

        // Test Fractional Quantity
        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', [
                'customer_id' => $this->activeCustomer->id,
                'idempotency_key' => (string) Str::uuid(),
                'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 2.5]],
            ]);
        $response->assertSessionHasErrors('items.0.quantity');

        // Test Excessive Quantity (>999999)
        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', [
                'customer_id' => $this->activeCustomer->id,
                'idempotency_key' => (string) Str::uuid(),
                'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 1000000]],
            ]);
        $response->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_empty_cart_is_rejected(): void
    {
        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', [
                'customer_id' => $this->activeCustomer->id,
                'idempotency_key' => (string) Str::uuid(),
                'items' => [],
            ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    /* =========================================================================
     * 4. PRICE BOUNDARY & OVERRIDES (RULE-PRI-002, RULE-PRI-003)
     * ========================================================================= */

    public function test_order_creation_with_omitted_price_uses_default_selling_price(): void
    {
        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 2,
                    // unit_price omitted
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals('15.00', (string) $order->items->first()->unit_price);
    }

    public function test_order_creation_with_custom_in_bound_price_succeeds(): void
    {
        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 4,
                    'unit_price' => '13.50', // between min 12.00 and mrp 20.00
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::with('items')->first();
        $this->assertNotNull($order);
        $this->assertEquals('13.50', (string) $order->items->first()->unit_price);
        $this->assertEquals('54.00', (string) $order->subtotal); // 4 * 13.50
    }

    public function test_exact_minimum_and_mrp_prices_are_valid(): void
    {
        // Exact Min
        $responseMin = $this->actingAs($this->salesman)
            ->post('/salesman/orders', [
                'customer_id' => $this->activeCustomer->id,
                'idempotency_key' => (string) Str::uuid(),
                'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => '12.00']],
            ]);
        $responseMin->assertSessionHasNoErrors();

        // Exact MRP
        $responseMrp = $this->actingAs($this->salesman)
            ->post('/salesman/orders', [
                'customer_id' => $this->activeCustomer->id,
                'idempotency_key' => (string) Str::uuid(),
                'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => '20.00']],
            ]);
        $responseMrp->assertSessionHasNoErrors();
    }

    public function test_salesman_cannot_submit_price_below_minimum_allowed_price(): void
    {
        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 1,
                    'unit_price' => '11.99', // Below min 12.00
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('unit_price');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_salesman_cannot_submit_price_above_mrp(): void
    {
        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 1,
                    'unit_price' => '20.01', // Above mrp 20.00
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('unit_price');
        $this->assertDatabaseCount('orders', 0);
    }

    /* =========================================================================
     * 5. TAX CALCULATIONS & AGGREGATIONS (RULE-TAX-001, RULE-TAX-002)
     * ========================================================================= */

    public function test_mixed_tax_rate_products_calculate_exact_totals_without_float_drift(): void
    {
        // Line 1: 3 x Cola @ 15.00 = 45.00 taxable, 8.25% tax = 3.7125 -> ROUND_HALF_UP = 3.71, line total = 48.71
        // Line 2: 5 x Water @ 7.00 = 35.00 taxable, 0.00% tax = 0.00, line total = 35.00
        // Expected Subtotal = 80.00, Tax Total = 3.71, Grand Total = 83.71

        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 3, 'unit_price' => '15.00'],
                ['product_id' => $this->secondProduct->id, 'quantity' => 5, 'unit_price' => '7.00'],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::with('items')->first();

        $this->assertNotNull($order);
        $this->assertEquals('80.00', (string) $order->subtotal);
        $this->assertEquals('3.71', (string) $order->tax_total);
        $this->assertEquals('83.71', (string) $order->grand_total);
        $this->assertCount(2, $order->items);

        // Assert Line 1
        $item1 = $order->items->firstWhere('product_id', $this->standardProduct->id);
        $this->assertEquals('45.00', (string) $item1->taxable_amount);
        $this->assertEquals('3.71', (string) $item1->tax_amount);
        $this->assertEquals('48.71', (string) $item1->line_total);

        // Assert Line 2
        $item2 = $order->items->firstWhere('product_id', $this->secondProduct->id);
        $this->assertEquals('35.00', (string) $item2->taxable_amount);
        $this->assertEquals('0.00', (string) $item2->tax_amount);
        $this->assertEquals('35.00', (string) $item2->line_total);
    }

    public function test_inactive_retained_tax_profile_on_product_is_used_for_tax_calculation(): void
    {
        // Deactivate the tax profile (non-destructive deactivation)
        $this->standardTaxProfile->update(['status' => TaxProfileStatus::INACTIVE]);

        // Order creation for the active product still uses product's assigned tax profile rate
        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 2, 'unit_price' => '15.00'],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals('30.00', (string) $order->subtotal);
        $this->assertEquals('2.48', (string) $order->tax_total); // 30 * 8.25% = 2.475 -> 2.48
    }

    /* =========================================================================
     * 6. SNAPSHOT IMMUTABILITY & HISTORICAL INTEGRITY
     * ========================================================================= */

    public function test_subsequent_changes_to_product_master_do_not_alter_order_snapshot(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $this->actingAs($this->salesman)->post('/salesman/orders', [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 10, 'unit_price' => '15.00']],
        ]);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();
        $item = $order->items->first();

        // Mutate Product Master Data
        $this->standardProduct->update([
            'name' => 'NEW RENAME COLA 2027',
            'sku' => 'RENAMED-SKU-99',
            'unit' => 'BOX',
            'default_selling_price' => 25.00,
            'minimum_allowed_price' => 20.00,
            'mrp' => 30.00,
            'status' => ProductStatus::INACTIVE,
        ]);

        // Mutate Tax Profile
        $this->standardTaxProfile->update([
            'name' => 'Altered Rate 15%',
            'rate' => '15.0000',
        ]);

        // Reload Order and Item fresh from DB
        $freshItem = OrderItem::find($item->id);

        $this->assertEquals('Classic Cola 24-pack', $freshItem->product_name_snapshot);
        $this->assertEquals('BEV-COLA-01', $freshItem->sku_snapshot);
        $this->assertEquals('CASE', $freshItem->unit_snapshot);
        $this->assertEquals('15.00', (string) $freshItem->unit_price);
        $this->assertEquals('8.2500', (string) $freshItem->tax_rate_snapshot);
        $this->assertEquals('150.00', (string) $freshItem->taxable_amount);
        $this->assertEquals('12.38', (string) $freshItem->tax_amount);
        $this->assertEquals('162.38', (string) $freshItem->line_total);
    }

    /* =========================================================================
     * 7. IDEMPOTENCY & CONCURRENCY REPLAY
     * ========================================================================= */

    public function test_duplicate_submission_with_same_idempotency_key_returns_existing_order(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Urgent order',
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 5, 'unit_price' => '15.00'],
            ],
        ];

        // First Submit
        $response1 = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $this->assertDatabaseCount('orders', 1);
        $order = Order::first();

        // Second Duplicate Submit (Network retry / double click)
        $response2 = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response2->assertRedirect("/salesman/orders/{$order->id}");
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_idempotency_key_replay_with_conflicting_payload_returns_409_conflict(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Initial Submit
        $this->actingAs($this->salesman)->post('/salesman/orders', [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 5]],
        ]);

        // Replay with DIFFERENT quantity under the SAME idempotency key
        $response = $this->actingAs($this->salesman)->post('/salesman/orders', [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [['product_id' => $this->standardProduct->id, 'quantity' => 10]], // Changed from 5 to 10
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('orders', 1);
    }

    /* =========================================================================
     * 8. SECURITY & MASS ASSIGNMENT (RULE-SEC-001, RULE-SEC-002)
     * ========================================================================= */

    public function test_client_supplied_forged_financial_totals_and_status_are_ignored(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $forgedPayload = [
            'customer_id' => $this->activeCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'salesman_id' => 9999,
            'created_by' => 9999,
            'order_number' => 'FORGED-ORD-999999',
            'status' => 'APPROVED',
            'fulfillment_status' => 'DISPATCHED',
            'payment_status' => 'PAID',
            'subtotal' => '1.00',
            'tax_total' => '0.00',
            'grand_total' => '1.00',
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 10,
                    'unit_price' => '15.00',
                    'taxable_amount' => '1.00',
                    'tax_amount' => '0.00',
                    'line_total' => '1.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $forgedPayload);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();

        $this->assertNotNull($order);
        $this->assertEquals($this->salesman->id, $order->salesman_id);
        $this->assertEquals($this->salesman->id, $order->created_by);
        $this->assertNotEquals('FORGED-ORD-999999', $order->order_number);
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertEquals(PaymentStatus::UNPAID, $order->payment_status);

        // Authoritative Totals
        $this->assertEquals('150.00', (string) $order->subtotal);
        $this->assertEquals('12.38', (string) $order->tax_total);
        $this->assertEquals('162.38', (string) $order->grand_total);
    }

    /* =========================================================================
     * 9. GET /salesman/orders/{order} (Order Confirmation View)
     * ========================================================================= */

    public function test_salesman_can_view_own_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-000001',
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->activeCustomer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'subtotal' => 150.00,
            'tax_total' => 12.38,
            'grand_total' => 162.38,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->standardProduct->id,
            'product_name_snapshot' => 'Classic Cola 24-pack',
            'sku_snapshot' => 'BEV-COLA-01',
            'unit_snapshot' => 'CASE',
            'ordered_quantity' => 10,
            'unit_price' => 15.00,
            'tax_rate_snapshot' => 8.2500,
            'taxable_amount' => 150.00,
            'tax_amount' => 12.38,
            'line_total' => 162.38,
        ]);

        $response = $this->actingAs($this->salesman)
            ->get("/salesman/orders/{$order->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Show')
            ->where('order.order_number', 'ORD-2026-000001')
            ->where('order.subtotal', '150.00')
            ->has('order.items', 1)
        );
    }

    public function test_salesman_cannot_view_another_salesmans_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-000002',
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->activeCustomer->id,
            'salesman_id' => $this->otherSalesman->id, // Belongs to other salesman
            'created_by' => $this->otherSalesman->id,
            'status' => OrderStatus::SUBMITTED,
            'subtotal' => 100.00,
            'tax_total' => 0.00,
            'grand_total' => 100.00,
        ]);

        $response = $this->actingAs($this->salesman)
            ->get("/salesman/orders/{$order->id}");

        $response->assertForbidden();
    }
}
