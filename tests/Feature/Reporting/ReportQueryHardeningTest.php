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

class ReportQueryHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Product $product;
    protected SalesReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
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

        $category = Category::create([
            'name' => 'General',
            'code' => 'GEN',
        ]);

        $tax = TaxProfile::create([
            'name' => 'Standard',
            'code' => 'STD',
            'rate' => 10.00,
        ]);

        $this->product = Product::create([
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

        $order = Order::create([
            'order_number' => 'ORD-HARDEN-001',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'draft_token' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'version' => 1,
            'currency' => 'USD',
            'subtotal' => '300.00',
            'tax_total' => '30.00',
            'adjustment_total' => '0.00',
            'grand_total' => '330.00',
            'created_at' => Carbon::now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 10,
            'unit_price' => '30.00',
            'taxable_amount' => '300.00',
            'tax_amount' => '30.00',
            'line_total' => '330.00',
        ]);

        $this->reportService = app(SalesReportService::class);
    }

    public function test_sales_by_customer_with_salesman_filter_does_not_fail_on_ambiguous_column(): void
    {
        // Calling getSalesByCustomer with salesman_id filter
        $results = $this->reportService->getSalesByCustomer([
            'salesman_id' => $this->salesman->id,
        ], $this->admin);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertEquals($this->customer->id, $results[0]['customer_id']);
        $this->assertEquals('330.00', $results[0]['net_sales']);
    }

    public function test_sales_report_scoped_for_salesman_user_does_not_fail_on_joined_queries(): void
    {
        // When accessed by salesman, ResourceScopeService scopes the orders by salesman_id
        $results = $this->reportService->getSalesByCustomer([], $this->salesman);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertEquals($this->customer->id, $results[0]['customer_id']);
    }

    public function test_sales_report_endpoint_with_salesman_query_param_succeeds(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports/sales?salesman_id=' . $this->salesman->id);
        $response->assertOk();
    }
}
