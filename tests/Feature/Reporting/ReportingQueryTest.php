<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\AccountStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reporting\CustomerReportService;
use App\Services\Reporting\InventoryReportService;
use App\Services\Reporting\SalesReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingQueryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Warehouse $warehouse;
    protected TaxProfile $taxProfile;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Hub',
                'address_line1' => '100 Hub St',
                'city' => 'Atlanta',
                'state' => 'GA',
                'postal_code' => '30301',
                'country_code' => 'US',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $this->taxProfile = TaxProfile::firstOrCreate(
            ['code' => 'STANDARD'],
            ['name' => 'Standard Tax', 'rate' => '10.00', 'is_active' => true]
        );

        $this->category = Category::create([
            'name' => 'General Goods',
            'code' => 'GEN-01',
            'is_active' => true,
        ]);
    }

    public function test_sales_report_executes_within_strict_query_budget(): void
    {
        $salesman = User::factory()->create(['role' => UserRole::SALESMAN, 'status' => AccountStatus::ACTIVE]);
        $customer = Customer::create([
            'code' => 'CUST-Q-01',
            'name' => 'Bulk Customer',
            'contact_name' => 'John Bulk',
            'email' => 'bulk@test.com',
            'phone' => '1234567890',
            'salesman_id' => $salesman->id,
            'credit_limit' => '50000.00',
            'payment_terms' => 'NET_30',
            'status' => 'ACTIVE',
            'billing_address_line1' => '100 Bulk Ave',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
        ]);

        $product = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Bulk Item',
            'sku' => 'SKU-BULK-01',
            'cost_price' => '10.00',
            'default_selling_price' => '20.00',
            'minimum_allowed_price' => '15.00',
            'mrp' => '25.00',
            'unit' => 'piece',
            'status' => ProductStatus::ACTIVE,
        ]);

        // Create 10 orders with order items
        for ($i = 1; $i <= 10; $i++) {
            $order = Order::create([
                'order_number' => 'ORD-Q-'.$i,
                'customer_id' => $customer->id,
                'salesman_id' => $salesman->id,
                'created_by' => $salesman->id,
                'status' => OrderStatus::APPROVED,
                'fulfillment_status' => FulfillmentStatus::DELIVERED,
                'payment_status' => PaymentStatus::PAID,
                'subtotal' => '200.00',
                'tax_total' => '20.00',
                'adjustment_total' => '0.00',
                'grand_total' => '220.00',
                'idempotency_key' => Str::uuid()->toString(),
                'draft_token' => Str::random(32),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'sku_snapshot' => $product->sku,
                'unit_snapshot' => 'piece',
                'ordered_quantity' => 10,
                'unit_price' => '20.00',
                'taxable_amount' => '200.00',
                'tax_rate' => '10.00',
                'tax_amount' => '20.00',
                'line_total' => '220.00',
                'is_price_overridden' => false,
            ]);
        }

        $service = app(SalesReportService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $summary = $service->getSalesSummary([]);
        $customerRows = $service->getSalesByCustomer([], null, 50);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 query for summary + 1 query for total units + 1 query for customer aggregation
        $this->assertLessThanOrEqual(5, $queryCount);
        $this->assertCount(1, $customerRows);
        $this->assertEquals('2000.00', $summary['gross_sales']);
    }

    public function test_inventory_valuation_eager_loading_avoids_n_plus_one(): void
    {
        // Create 10 products
        for ($i = 1; $i <= 10; $i++) {
            $product = Product::create([
                'category_id' => $this->category->id,
                'tax_profile_id' => $this->taxProfile->id,
                'name' => 'Product '.$i,
                'sku' => 'SKU-N1-'.$i,
                'cost_price' => '10.00',
                'default_selling_price' => '20.00',
                'minimum_allowed_price' => '15.00',
                'mrp' => '25.00',
                'unit' => 'piece',
                'status' => ProductStatus::ACTIVE,
            ]);

            InventoryBalance::updateOrCreate(
                ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id],
                [
                    'on_hand_quantity' => 20,
                    'reserved_quantity' => 0,
                    'available_quantity' => 20,
                    'damaged_quantity' => 0,
                    'is_active' => true,
                    'version' => 1,
                ]
            );
        }

        $service = app(InventoryReportService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $report = $service->getInventoryValuationReport([], $this->admin, 50);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count should be bounded (count query + select join + eager loads = ~4 queries max)
        $this->assertLessThanOrEqual(5, $queryCount);
        $this->assertCount(10, $report['data']);
    }
}
