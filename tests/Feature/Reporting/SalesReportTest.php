<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Reporting\SalesReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman1;
    protected User $salesman2;
    protected Customer $customer1;
    protected Customer $customer2;
    protected Product $product1;
    protected Product $product2;
    protected SalesReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman1 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman2 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer1 = Customer::create([
            'salesman_id' => $this->salesman1->id,
            'name' => 'Acme Supplies',
            'code' => 'CUST-001',
            'contact_name' => 'John Acme',
            'phone' => '+1-555-0101',
            'email' => 'acme@test.com',
            'status' => CustomerStatus::ACTIVE,
            'billing_address_line1' => '100 Acme Way',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
        ]);

        $this->customer2 = Customer::create([
            'salesman_id' => $this->salesman2->id,
            'name' => 'Global Retailers',
            'code' => 'CUST-002',
            'contact_name' => 'Jane Global',
            'phone' => '+1-555-0102',
            'email' => 'global@test.com',
            'status' => CustomerStatus::ACTIVE,
            'billing_address_line1' => '200 Global Rd',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30302',
            'billing_country' => 'US',
        ]);

        $category = Category::create([
            'name' => 'General',
            'code' => 'GEN',
        ]);

        $tax = TaxProfile::create([
            'name' => 'Standard',
            'code' => 'STD',
            'rate' => 0.10,
        ]);

        $this->product1 = Product::create([
            'category_id' => $category->id,
            'tax_profile_id' => $tax->id,
            'name' => 'Widget A',
            'sku' => 'WIDGET-A',
            'cost_price' => '20.00',
            'default_selling_price' => '30.00',
            'minimum_allowed_price' => '25.00',
            'mrp' => '40.00',
            'unit' => 'piece',
            'status' => \App\Enums\ProductStatus::ACTIVE,
        ]);

        $this->product2 = Product::create([
            'category_id' => $category->id,
            'tax_profile_id' => $tax->id,
            'name' => 'Gadget B',
            'sku' => 'GADGET-B',
            'cost_price' => '50.00',
            'default_selling_price' => '80.00',
            'minimum_allowed_price' => '70.00',
            'mrp' => '100.00',
            'unit' => 'piece',
            'status' => \App\Enums\ProductStatus::ACTIVE,
        ]);

        $this->reportService = app(SalesReportService::class);
    }

    protected function createOrder(array $attributes): Order
    {
        $customCreatedAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $order = Order::create(array_merge([
            'order_number' => 'ORD-' . uniqid(),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'draft_token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'version' => 1,
            'currency' => 'USD',
            'subtotal' => '0.00',
            'tax_total' => '0.00',
            'adjustment_total' => '0.00',
            'grand_total' => '0.00',
        ], $attributes));

        if ($customCreatedAt) {
            $order->created_at = $customCreatedAt;
            $order->saveQuietly();
        }

        return $order;
    }

    public function test_sales_report_aggregates_approved_and_completed_orders_only(): void
    {
        // Order 1: Approved (Subtotal 300, Tax 30, Adjustment 10, Grand Total 320)
        $order1 = $this->createOrder([
            'order_number' => 'ORD-2026-0001',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '300.00',
            'tax_total' => '30.00',
            'adjustment_total' => '10.00',
            'grand_total' => '320.00',
            'created_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $this->product1->id,
            'product_name_snapshot' => $this->product1->name,
            'sku_snapshot' => $this->product1->sku,
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 10,
            'unit_price' => '30.00',
            'taxable_amount' => '300.00',
            'tax_amount' => '30.00',
            'line_total' => '330.00',
        ]);

        // Order 2: Completed (Subtotal 400, Tax 40, Adjustment 0, Grand Total 440)
        $order2 = $this->createOrder([
            'order_number' => 'ORD-2026-0002',
            'customer_id' => $this->customer2->id,
            'salesman_id' => $this->salesman2->id,
            'created_by' => $this->salesman2->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '400.00',
            'tax_total' => '40.00',
            'adjustment_total' => '0.00',
            'grand_total' => '440.00',
            'created_at' => Carbon::parse('2026-09-02 12:00:00'),
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $this->product2->id,
            'product_name_snapshot' => $this->product2->name,
            'sku_snapshot' => $this->product2->sku,
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 5,
            'unit_price' => '80.00',
            'taxable_amount' => '400.00',
            'tax_amount' => '40.00',
            'line_total' => '440.00',
        ]);

        // Order 3: Draft (Must be excluded from sales)
        $this->createOrder([
            'order_number' => 'ORD-2026-0003',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::DRAFT,
            'subtotal' => '1000.00',
            'grand_total' => '1000.00',
            'created_at' => Carbon::parse('2026-09-02 14:00:00'),
        ]);

        // Order 4: Cancelled (Must be excluded from sales)
        $this->createOrder([
            'order_number' => 'ORD-2026-0004',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::CANCELLED,
            'subtotal' => '500.00',
            'grand_total' => '500.00',
            'created_at' => Carbon::parse('2026-09-02 15:00:00'),
        ]);

        $summary = $this->reportService->getSalesSummary([], $this->admin);

        $this->assertEquals(2, $summary['total_orders']);
        $this->assertEquals('700.00', $summary['gross_sales']);
        $this->assertEquals('70.00', $summary['tax_total']);
        $this->assertEquals('10.00', $summary['discount_total']);
        $this->assertEquals('760.00', $summary['net_sales']);
        $this->assertEquals('380.00', $summary['average_order_value']);
        $this->assertEquals(15, $summary['total_units_sold']);
    }

    public function test_sales_report_date_filtering_boundaries(): void
    {
        $this->createOrder([
            'order_number' => 'ORD-DATE-1',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'subtotal' => '100.00',
            'grand_total' => '100.00',
            'created_at' => Carbon::parse('2026-08-31 23:59:59'),
        ]);

        $this->createOrder([
            'order_number' => 'ORD-DATE-2',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'subtotal' => '200.00',
            'grand_total' => '200.00',
            'created_at' => Carbon::parse('2026-09-01 00:00:00'),
        ]);

        $this->createOrder([
            'order_number' => 'ORD-DATE-3',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'subtotal' => '300.00',
            'grand_total' => '300.00',
            'created_at' => Carbon::parse('2026-09-05 23:59:59'),
        ]);

        $this->createOrder([
            'order_number' => 'ORD-DATE-4',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'subtotal' => '400.00',
            'grand_total' => '400.00',
            'created_at' => Carbon::parse('2026-09-06 00:00:01'),
        ]);

        $summary = $this->reportService->getSalesSummary([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-05',
        ], $this->admin);

        $this->assertEquals(2, $summary['total_orders']);
        $this->assertEquals('500.00', $summary['net_sales']);
    }

    public function test_sales_report_salesman_scoping(): void
    {
        $this->createOrder([
            'order_number' => 'ORD-SCOPE-1',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'subtotal' => '500.00',
            'grand_total' => '500.00',
        ]);

        $this->createOrder([
            'order_number' => 'ORD-SCOPE-2',
            'customer_id' => $this->customer2->id,
            'salesman_id' => $this->salesman2->id,
            'created_by' => $this->salesman2->id,
            'status' => OrderStatus::APPROVED,
            'subtotal' => '800.00',
            'grand_total' => '800.00',
        ]);

        // Salesman 1 only sees $500
        $s1Summary = $this->reportService->getSalesSummary([], $this->salesman1);
        $this->assertEquals(1, $s1Summary['total_orders']);
        $this->assertEquals('500.00', $s1Summary['net_sales']);

        // Admin sees total $1300
        $adminSummary = $this->reportService->getSalesSummary([], $this->admin);
        $this->assertEquals(2, $adminSummary['total_orders']);
        $this->assertEquals('1300.00', $adminSummary['net_sales']);
    }

    public function test_sales_report_web_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports/sales');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Reporting/Sales'));
    }
}
