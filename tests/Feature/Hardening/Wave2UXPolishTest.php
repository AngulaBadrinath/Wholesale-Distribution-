<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentTerms;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Receivable\CustomerStatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave2UXPolishTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected Customer $customer;
    protected TaxProfile $taxProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'name' => 'Wave 2 Test Retailer',
            'code' => 'CUST-W2-01',
            'contact_name' => 'Jane Wave',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '50000.00',
            'balance' => '0.00',
            'billing_address_line1' => '100 Wave Way',
            'billing_city' => 'Chicago',
            'billing_state' => 'IL',
            'billing_postal_code' => '60601',
            'billing_country' => 'US',
            'email' => 'ar@wavetest.com',
            'phone' => '312-555-0199',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'State General Tax',
            'code' => 'TAX-ST-0825',
            'rate' => '8.2500',
            'description' => 'General state sales tax',
            'status' => TaxProfileStatus::ACTIVE,
        ]);
    }

    /**
     * Test BUG-006: Customer Statement endpoint responds with filtered date range presets.
     */
    public function test_customer_statement_date_filtering_presets(): void
    {
        // 1. This Month preset
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $response = $this->actingAs($this->accountant)
            ->get("/admin/receivables/{$this->customer->id}/statement?start_date={$startOfMonth}&end_date={$endOfMonth}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Receivables/Statement')
            ->where('filters.start_date', $startOfMonth)
            ->where('filters.end_date', $endOfMonth)
            ->has('statement.customer')
            ->has('statement.statement_period')
        );

        // 2. Last 30 Days preset
        $last30Start = Carbon::now()->subDays(30)->toDateString();
        $today = Carbon::now()->toDateString();

        $response30 = $this->actingAs($this->admin)
            ->get("/admin/receivables/{$this->customer->id}/statement?start_date={$last30Start}&end_date={$today}");

        $response30->assertStatus(200);
        $response30->assertInertia(fn ($page) => $page
            ->where('filters.start_date', $last30Start)
            ->where('filters.end_date', $today)
        );

        // 3. Year to Date preset
        $ytdStart = Carbon::now()->startOfYear()->toDateString();

        $responseYtd = $this->actingAs($this->accountant)
            ->get("/admin/receivables/{$this->customer->id}/statement?start_date={$ytdStart}&end_date={$today}");

        $responseYtd->assertStatus(200);
        $responseYtd->assertInertia(fn ($page) => $page
            ->where('filters.start_date', $ytdStart)
            ->where('filters.end_date', $today)
        );
    }

    /**
     * Test BUG-010: Tax Profile percentage precision and display representation.
     */
    public function test_tax_profile_rate_precision_and_retrieval(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/tax-profiles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('TaxProfile/Index')
            ->has('taxProfiles.data', 1)
            ->where('taxProfiles.data.0.code', 'TAX-ST-0825')
            ->where('taxProfiles.data.0.rate', '8.2500')
        );

        // Edit view returns clean rate
        $editResponse = $this->actingAs($this->admin)
            ->get("/tax-profiles/{$this->taxProfile->id}/edit");

        $editResponse->assertStatus(200);
        $editResponse->assertInertia(fn ($page) => $page
            ->component('TaxProfile/Edit')
            ->where('taxProfile.rate', '8.2500')
        );
    }

    /**
     * Test BUG-007 & BUG-009: Product Edit and Inventory Exceptions views are fully operational.
     */
    public function test_product_edit_and_inventory_exceptions_views_operational(): void
    {
        $product = Product::create([
            'sku' => 'PROD-W2-001',
            'name' => 'Wave 2 Test Widget',
            'description' => 'Test widget for UI verification',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '12.00',
            'default_selling_price' => '15.00',
            'mrp' => '20.00',
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        $responseProduct = $this->actingAs($this->admin)
            ->get("/products/{$product->id}/edit");

        $responseProduct->assertStatus(200);
        $responseProduct->assertInertia(fn ($page) => $page
            ->component('Product/Edit')
            ->has('product')
        );

        $responseExceptions = $this->actingAs($this->admin)
            ->get('/admin/inventory-exceptions');

        $responseExceptions->assertStatus(200);
        $responseExceptions->assertInertia(fn ($page) => $page
            ->component('Admin/Inventory/Exceptions')
            ->has('exceptions')
        );
    }
}
