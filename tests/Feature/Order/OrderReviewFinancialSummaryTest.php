<?php

namespace Tests\Feature\Order;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
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

class OrderReviewFinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesman;
    protected User $otherSalesman;
    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $standardTaxProfile;
    protected TaxProfile $reducedTaxProfile;
    protected TaxProfile $exemptTaxProfile;
    protected Product $standardProduct;
    protected Product $reducedTaxProduct;
    protected Product $exemptProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesman = User::factory()->create([
            'name' => 'Review Salesman',
            'email' => 'salesman.review@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->otherSalesman = User::factory()->create([
            'name' => 'Other Salesman',
            'email' => 'salesman.other@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'code' => 'CUST-REV-001',
            'name' => 'Grand Horizon Wholesale Mart',
            'contact_name' => 'Sarah Johnson',
            'email' => 'sarah@grandhorizon.test',
            'phone' => '+1 (555) 345-6789',
            'billing_address_line1' => '500 Commerce Way',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '500 Commerce Way',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'credit_limit' => 50000.00,
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->category = Category::create([
            'name' => 'General Merchandise',
            'code' => 'GEN',
            'status' => 'ACTIVE',
            'sort_order' => 1,
        ]);

        $this->standardTaxProfile = TaxProfile::create([
            'name' => 'Standard Sales Tax 8.25%',
            'code' => 'STD-825',
            'rate' => '8.2500',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->reducedTaxProfile = TaxProfile::create([
            'name' => 'Reduced Grocery Tax 4.00%',
            'code' => 'RED-400',
            'rate' => '4.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->exemptTaxProfile = TaxProfile::create([
            'name' => 'Exempt 0.00%',
            'code' => 'EXEMPT-0',
            'rate' => '0.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->standardProduct = Product::create([
            'sku' => 'GEN-STD-01',
            'name' => 'Premium Hardware Tool Set',
            'description' => 'Heavy duty professional kit',
            'category_id' => $this->category->id,
            'unit' => 'CASE',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 20.00,
            'minimum_allowed_price' => 25.00,
            'default_selling_price' => 30.00,
            'mrp' => 45.00,
            'tax_profile_id' => $this->standardTaxProfile->id,
        ]);

        $this->reducedTaxProduct = Product::create([
            'sku' => 'GEN-RED-02',
            'name' => 'Packaged Organic Snacks',
            'description' => 'Case of 24 boxes',
            'category_id' => $this->category->id,
            'unit' => 'BOX',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 15.00,
            'minimum_allowed_price' => 18.00,
            'default_selling_price' => 25.00,
            'mrp' => 35.00,
            'tax_profile_id' => $this->reducedTaxProfile->id,
        ]);

        $this->exemptProduct = Product::create([
            'sku' => 'GEN-EXM-03',
            'name' => 'Agricultural Feed Seeds',
            'description' => '50lb sack exempt',
            'category_id' => $this->category->id,
            'unit' => 'BAG',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 8.00,
            'minimum_allowed_price' => 9.50,
            'default_selling_price' => 10.00,
            'mrp' => 15.00,
            'tax_profile_id' => $this->exemptTaxProfile->id,
        ]);
    }

    // =========================================================================
    // 1. MULTI-LINE MIXED TAX RATES & SNAPSHOT VERIFICATION
    // =========================================================================

    public function test_order_creation_with_multi_line_mixed_tax_rates_calculates_and_snapshots_authoritatively(): void
    {
        // Line 1: Standard product (8.25%) -> Qty: 2 @ $30.00 -> Taxable: 60.00, Tax: 60 * 0.0825 = 4.95 -> LineTotal: 64.95
        // Line 2: Reduced product (4.00%) -> Qty: 3 @ $25.00 -> Taxable: 75.00, Tax: 75 * 0.04 = 3.00 -> LineTotal: 78.00
        // Line 3: Exempt product (0.00%) -> Qty: 5 @ $10.00 -> Taxable: 50.00, Tax: 0.00 -> LineTotal: 50.00
        // Expected Header:
        // Subtotal = 60.00 + 75.00 + 50.00 = 185.00
        // Tax Total = 4.95 + 3.00 + 0.00 = 7.95
        // Grand Total = 185.00 + 7.95 = 192.95

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'notes' => 'Deliver before noon; PO #REV-2026-99',
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 2,
                    'unit_price' => '30.00',
                ],
                [
                    'product_id' => $this->reducedTaxProduct->id,
                    'quantity' => 3,
                    'unit_price' => '25.00',
                ],
                [
                    'product_id' => $this->exemptProduct->id,
                    'quantity' => 5,
                    'unit_price' => '10.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertRedirect();

        $order = Order::with('items')->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::SUBMITTED, $order->status);
        $this->assertSame('185.00', (string) $order->subtotal);
        $this->assertSame('7.95', (string) $order->tax_total);
        $this->assertSame('0.00', (string) $order->adjustment_total);
        $this->assertSame('192.95', (string) $order->grand_total);
        $this->assertSame('Deliver before noon; PO #REV-2026-99', $order->notes);

        // Check Line 1 Snapshot
        $item1 = $order->items->firstWhere('product_id', $this->standardProduct->id);
        $this->assertNotNull($item1);
        $this->assertSame('Premium Hardware Tool Set', $item1->product_name_snapshot);
        $this->assertSame('GEN-STD-01', $item1->sku_snapshot);
        $this->assertSame('CASE', $item1->unit_snapshot);
        $this->assertSame(2, $item1->ordered_quantity);
        $this->assertSame('30.00', (string) $item1->unit_price);
        $this->assertSame('60.00', (string) $item1->taxable_amount);
        $this->assertSame('STD-825', $item1->tax_profile_code_snapshot);
        $this->assertSame('Standard Sales Tax 8.25%', $item1->tax_profile_name_snapshot);
        $this->assertSame('8.2500', (string) $item1->tax_rate_snapshot);
        $this->assertSame('4.95', (string) $item1->tax_amount);
        $this->assertSame('64.95', (string) $item1->line_total);

        // Check Line 2 Snapshot
        $item2 = $order->items->firstWhere('product_id', $this->reducedTaxProduct->id);
        $this->assertNotNull($item2);
        $this->assertSame('Packaged Organic Snacks', $item2->product_name_snapshot);
        $this->assertSame('GEN-RED-02', $item2->sku_snapshot);
        $this->assertSame('BOX', $item2->unit_snapshot);
        $this->assertSame(3, $item2->ordered_quantity);
        $this->assertSame('25.00', (string) $item2->unit_price);
        $this->assertSame('75.00', (string) $item2->taxable_amount);
        $this->assertSame('RED-400', $item2->tax_profile_code_snapshot);
        $this->assertSame('Reduced Grocery Tax 4.00%', $item2->tax_profile_name_snapshot);
        $this->assertSame('4.0000', (string) $item2->tax_rate_snapshot);
        $this->assertSame('3.00', (string) $item2->tax_amount);
        $this->assertSame('78.00', (string) $item2->line_total);

        // Check Line 3 Snapshot
        $item3 = $order->items->firstWhere('product_id', $this->exemptProduct->id);
        $this->assertNotNull($item3);
        $this->assertSame('Agricultural Feed Seeds', $item3->product_name_snapshot);
        $this->assertSame('GEN-EXM-03', $item3->sku_snapshot);
        $this->assertSame('BAG', $item3->unit_snapshot);
        $this->assertSame(5, $item3->ordered_quantity);
        $this->assertSame('10.00', (string) $item3->unit_price);
        $this->assertSame('50.00', (string) $item3->taxable_amount);
        $this->assertSame('EXEMPT-0', $item3->tax_profile_code_snapshot);
        $this->assertSame('0.0000', (string) $item3->tax_rate_snapshot);
        $this->assertSame('0.00', (string) $item3->tax_amount);
        $this->assertSame('50.00', (string) $item3->line_total);
    }

    // =========================================================================
    // 2. ROUND_HALF_UP BOUNDARY CASES & SUM OF ROUNDED LINE TAXES
    // =========================================================================

    public function test_tax_rounding_boundary_conditions_and_line_sum_parity(): void
    {
        // Product A: 1 unit @ $14.95 with 8.25% tax:
        // Taxable = 14.95, Raw tax = 14.95 * 0.0825 = 1.233375 -> Rounds to 1.23 (Line total = 16.18)
        // Product B: 1 unit @ $15.00 with 8.25% tax:
        // Taxable = 15.00, Raw tax = 15.00 * 0.0825 = 1.2375 -> Rounds to 1.24 (Line total = 16.24)
        // Subtotal = 29.95
        // Total Tax = 1.23 + 1.24 = 2.47
        // Grand Total = 29.95 + 2.47 = 32.42

        $prodA = Product::create([
            'sku' => 'ROUND-A',
            'name' => 'Product Round Down Test',
            'category_id' => $this->category->id,
            'unit' => 'EACH',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 10.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 14.95,
            'mrp' => 20.00,
            'tax_profile_id' => $this->standardTaxProfile->id,
        ]);

        $prodB = Product::create([
            'sku' => 'ROUND-B',
            'name' => 'Product Round Up Test',
            'category_id' => $this->category->id,
            'unit' => 'EACH',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 10.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 15.00,
            'mrp' => 20.00,
            'tax_profile_id' => $this->standardTaxProfile->id,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $prodA->id, 'quantity' => 1, 'unit_price' => '14.95'],
                ['product_id' => $prodB->id, 'quantity' => 1, 'unit_price' => '15.00'],
            ],
        ];

        $response = $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $response->assertRedirect();

        $order = Order::with('items')->latest('id')->first();
        $this->assertSame('29.95', (string) $order->subtotal);
        $this->assertSame('2.47', (string) $order->tax_total);
        $this->assertSame('32.42', (string) $order->grand_total);

        $itemA = $order->items->firstWhere('product_id', $prodA->id);
        $this->assertSame('1.23', (string) $itemA->tax_amount);
        $this->assertSame('16.18', (string) $itemA->line_total);

        $itemB = $order->items->firstWhere('product_id', $prodB->id);
        $this->assertSame('1.24', (string) $itemB->tax_amount);
        $this->assertSame('16.24', (string) $itemB->line_total);
    }

    // =========================================================================
    // 3. MASTER DATA DRIFT IMMUTABILITY TEST
    // =========================================================================

    public function test_subsequent_catalog_and_tax_profile_changes_do_not_mutate_committed_order_history(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->standardProduct->id,
                    'quantity' => 2,
                    'unit_price' => '30.00',
                ],
            ],
        ];

        $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $order = Order::with('items')->latest('id')->first();

        // Mutate Product Master Data
        $this->standardProduct->update([
            'name' => 'Completely Renamed Product',
            'sku' => 'ALTERED-SKU-99',
            'default_selling_price' => 99.00,
            'unit' => 'PALLET',
        ]);

        // Mutate Tax Profile Master Data
        $this->standardTaxProfile->update([
            'name' => 'Super High Rate Tax',
            'code' => 'TAX-2000',
            'rate' => '20.0000',
        ]);

        // Re-query order fresh from database
        $freshOrder = Order::with('items')->find($order->id);
        $freshItem = $freshOrder->items->first();

        $this->assertSame('60.00', (string) $freshOrder->subtotal);
        $this->assertSame('4.95', (string) $freshOrder->tax_total);
        $this->assertSame('64.95', (string) $freshOrder->grand_total);

        $this->assertSame('Premium Hardware Tool Set', $freshItem->product_name_snapshot);
        $this->assertSame('GEN-STD-01', $freshItem->sku_snapshot);
        $this->assertSame('CASE', $freshItem->unit_snapshot);
        $this->assertSame('30.00', (string) $freshItem->unit_price);
        $this->assertSame('STD-825', $freshItem->tax_profile_code_snapshot);
        $this->assertSame('Standard Sales Tax 8.25%', $freshItem->tax_profile_name_snapshot);
        $this->assertSame('8.2500', (string) $freshItem->tax_rate_snapshot);
        $this->assertSame('4.95', (string) $freshItem->tax_amount);
        $this->assertSame('64.95', (string) $freshItem->line_total);
    }

    // =========================================================================
    // 4. DRAFT PERSISTENCE & SUBMISSION PARITY
    // =========================================================================

    public function test_draft_order_persistence_and_submission_parity(): void
    {
        // 1. Save draft order
        $draftPayload = [
            'customer_id' => $this->customer->id,
            'notes' => 'Working draft',
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => '30.00'],
                ['product_id' => $this->reducedTaxProduct->id, 'quantity' => 2, 'unit_price' => '25.00'],
            ],
        ];

        $draftResponse = $this->actingAs($this->salesman)->postJson('/salesman/orders/drafts', $draftPayload);
        $draftResponse->assertOk()->assertJson(['success' => true]);

        $draftId = $draftResponse->json('draft.id');
        $draft = Order::with('items')->find($draftId);

        $this->assertSame(OrderStatus::DRAFT, $draft->status);
        $this->assertSame('80.00', (string) $draft->subtotal); // 30 + 50
        $this->assertSame('4.48', (string) $draft->tax_total); // 2.48 + 2.00
        $this->assertSame('84.48', (string) $draft->grand_total);

        // 2. Submit draft order
        $submitResponse = $this->actingAs($this->salesman)->post("/salesman/orders/drafts/{$draft->id}/submit", [
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $submitResponse->assertRedirect();

        $submitted = Order::with('items')->find($draft->id);
        $this->assertSame(OrderStatus::SUBMITTED, $submitted->status);
        $this->assertNotNull($submitted->order_number);
        $this->assertSame('80.00', (string) $submitted->subtotal);
        $this->assertSame('4.48', (string) $submitted->tax_total);
        $this->assertSame('84.48', (string) $submitted->grand_total);
    }

    // =========================================================================
    // 5. COST PRICE INVISIBILITY & SECURITY
    // =========================================================================

    public function test_salesman_cannot_view_or_receive_cost_price_in_order_flows(): void
    {
        // 1. Create page response
        $createResponse = $this->actingAs($this->salesman)->get('/salesman/orders/create');
        $createResponse->assertOk();
        $createResponse->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Create')
            ->where('products.data.0.cost_price', null)
        );

        // 2. Show page response
        $order = Order::create([
            'order_number' => 'ORD-2026-999999',
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'subtotal' => '30.00',
            'tax_total' => '2.48',
            'grand_total' => '32.48',
        ]);

        $order->items()->create([
            'product_id' => $this->standardProduct->id,
            'product_name_snapshot' => $this->standardProduct->name,
            'sku_snapshot' => $this->standardProduct->sku,
            'unit_snapshot' => $this->standardProduct->unit,
            'ordered_quantity' => 1,
            'unit_price' => '30.00',
            'taxable_amount' => '30.00',
            'tax_rate_snapshot' => '8.2500',
            'tax_amount' => '2.48',
            'line_total' => '32.48',
        ]);

        $showResponse = $this->actingAs($this->salesman)->get("/salesman/orders/{$order->id}");
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Show')
            ->missing('order.items.0.cost_price')
        );
    }

    // =========================================================================
    // 6. SCOPING & SENSITIVE RESOURCE ACCESS
    // =========================================================================

    public function test_salesman_cannot_submit_orders_for_other_salesman_customers(): void
    {
        $otherCustomer = Customer::create([
            'salesman_id' => $this->otherSalesman->id,
            'code' => 'CUST-OTHER-01',
            'name' => 'Competitor Mart',
            'contact_name' => 'Other Contact',
            'email' => 'other@competitor.test',
            'phone' => '+1 (555) 999-0000',
            'billing_address_line1' => '99 Other Way',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '99 Other Way',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'credit_limit' => 10000.00,
            'status' => CustomerStatus::ACTIVE,
        ]);

        $payload = [
            'customer_id' => $otherCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => '30.00'],
            ],
        ];

        $response = $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $response->assertSessionHasErrors('customer_id');
    }

    // =========================================================================
    // 7. PRICE BOUNDARY VALIDATION
    // =========================================================================

    public function test_price_boundary_violations_are_rejected_on_order_submission(): void
    {
        // Price below minimum_allowed_price ($25.00)
        $payloadLow = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => '20.00'],
            ],
        ];

        $responseLow = $this->actingAs($this->salesman)->post('/salesman/orders', $payloadLow);
        $responseLow->assertSessionHasErrors();

        // Price above MRP ($45.00)
        $payloadHigh = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => '50.00'],
            ],
        ];

        $responseHigh = $this->actingAs($this->salesman)->post('/salesman/orders', $payloadHigh);
        $responseHigh->assertSessionHasErrors();
    }
}
