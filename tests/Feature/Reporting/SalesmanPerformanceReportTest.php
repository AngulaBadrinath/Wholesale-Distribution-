<?php

namespace Tests\Feature\Reporting;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Reporting\SalesmanPerformanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesmanPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected SalesmanPerformanceReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SalesmanPerformanceReportService::class);
    }

    private function createSalesman(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => UserRole::SALESMAN,
        ]);
    }

    private function createCustomer(User $salesman, string $name): Customer
    {
        return Customer::create([
            'code' => 'CUST-'.Str::upper(Str::random(6)),
            'name' => $name,
            'contact_name' => 'Contact '.$name,
            'email' => strtolower(str_replace(' ', '', $name)).'@example.com',
            'phone' => '1234567890',
            'salesman_id' => $salesman->id,
            'credit_limit' => '10000.00',
            'payment_terms' => 'NET_30',
            'status' => 'ACTIVE',
            'billing_address_line1' => '100 Main St',
            'billing_city' => 'Anytown',
            'billing_state' => 'CA',
            'billing_postal_code' => '90001',
            'billing_country' => 'US',
        ]);
    }

    private function createProduct(string $sku, string $name, string $price): Product
    {
        $taxProfile = TaxProfile::firstOrCreate(
            ['code' => 'STANDARD'],
            ['name' => 'Standard Tax', 'rate' => '10.00', 'is_active' => true]
        );

        return Product::create([
            'sku' => $sku,
            'name' => $name,
            'unit_of_measure' => 'UNIT',
            'default_selling_price' => $price,
            'minimum_allowed_price' => bcmul($price, '0.8', 2),
            'mrp' => bcmul($price, '1.2', 2),
            'cost_price' => bcmul($price, '0.6', 2),
            'tax_profile_id' => $taxProfile->id,
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    public function test_salesman_performance_metrics_aggregated_correctly(): void
    {
        $salesman = $this->createSalesman('Alice Sales', 'alice@distro.test');
        $customer = $this->createCustomer($salesman, 'Alpha Corp');
        $product = $this->createProduct('SKU-100', 'Widget Pro', '100.00');

        // Order 1: Approved, 2 items, $200 gross, $20 tax, $220 net, delivered
        $order1 = Order::create([
            'order_number' => 'ORD-001',
            'customer_id' => $customer->id,
            'salesman_id' => $salesman->id,
            'created_by' => $salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => '200.00',
            'tax_total' => '20.00',
            'adjustment_total' => '0.00',
            'grand_total' => '220.00',
            'idempotency_key' => Str::uuid()->toString(),
            'draft_token' => Str::random(32),
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => 'UNIT',
            'ordered_quantity' => 2,
            'unit_price' => '100.00',
            'taxable_amount' => '200.00',
            'tax_rate' => '10.00',
            'tax_amount' => '20.00',
            'line_total' => '220.00',
            'is_price_overridden' => false,
        ]);

        // Order 2: Approved, 1 item price overridden, $80 gross, $8 tax, $88 net
        $order2 = Order::create([
            'order_number' => 'ORD-002',
            'customer_id' => $customer->id,
            'salesman_id' => $salesman->id,
            'created_by' => $salesman->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '80.00',
            'tax_total' => '8.00',
            'adjustment_total' => '0.00',
            'grand_total' => '88.00',
            'idempotency_key' => Str::uuid()->toString(),
            'draft_token' => Str::random(32),
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => 'UNIT',
            'ordered_quantity' => 1,
            'unit_price' => '80.00',
            'taxable_amount' => '80.00',
            'tax_rate' => '10.00',
            'tax_amount' => '8.00',
            'line_total' => '88.00',
            'is_price_overridden' => true,
        ]);

        $report = $this->service->getSalesmanPerformanceReport([]);

        $this->assertCount(1, $report['data']);
        $aliceData = $report['data'][0];

        $this->assertEquals($salesman->id, $aliceData['salesman_id']);
        $this->assertEquals(2, $aliceData['orders_approved']);
        $this->assertEquals(2, $aliceData['orders_fulfilled']);
        $this->assertEquals('280.00', $aliceData['gross_sales']);
        $this->assertEquals('28.00', $aliceData['tax_total']);
        $this->assertEquals('308.00', $aliceData['net_sales']);
        $this->assertEquals('154.00', $aliceData['average_order_value']);
        $this->assertEquals(1, $aliceData['price_overrides_count']);
        $this->assertStringContainsString('Unconfigured in V1', $aliceData['commission_status']);
    }

    public function test_historical_salesman_attribution_persists_after_customer_reassignment(): void
    {
        $salesman1 = $this->createSalesman('Salesman One', 'one@distro.test');
        $salesman2 = $this->createSalesman('Salesman Two', 'two@distro.test');

        $customer = $this->createCustomer($salesman1, 'Beta Logistics');

        // Order placed by salesman1
        Order::create([
            'order_number' => 'ORD-HIST-1',
            'customer_id' => $customer->id,
            'salesman_id' => $salesman1->id,
            'created_by' => $salesman1->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => '500.00',
            'tax_total' => '50.00',
            'adjustment_total' => '0.00',
            'grand_total' => '550.00',
            'idempotency_key' => Str::uuid()->toString(),
            'draft_token' => Str::random(32),
        ]);

        // Customer is now reassigned to salesman2
        $customer->update(['salesman_id' => $salesman2->id]);

        $report = $this->service->getSalesmanPerformanceReport([]);

        $row1 = collect($report['data'])->firstWhere('salesman_id', $salesman1->id);
        $row2 = collect($report['data'])->firstWhere('salesman_id', $salesman2->id);

        // Salesman 1 retains attribution for the order
        $this->assertEquals(1, $row1['orders_approved']);
        $this->assertEquals('550.00', $row1['net_sales']);
        $this->assertEquals(0, $row1['assigned_customers_count']);

        // Salesman 2 has the customer assigned but 0 historical orders
        $this->assertEquals(0, $row2['orders_approved']);
        $this->assertEquals('0.00', $row2['net_sales']);
        $this->assertEquals(1, $row2['assigned_customers_count']);
    }

    public function test_salesman_user_scoping_restricts_to_own_record(): void
    {
        $salesman1 = $this->createSalesman('Salesman One', 'one@distro.test');
        $salesman2 = $this->createSalesman('Salesman Two', 'two@distro.test');

        $report = $this->service->getSalesmanPerformanceReport([], $salesman1);

        $this->assertCount(1, $report['data']);
        $this->assertEquals($salesman1->id, $report['data'][0]['salesman_id']);
    }
}
