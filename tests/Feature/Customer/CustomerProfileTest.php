<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentTerms;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerService::class);
    }

    /**
     * Helper to create active users with specific roles and statuses.
     */
    protected function createUserWithRole(UserRole $role, AccountStatus $status = AccountStatus::ACTIVE): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => $status,
            'password' => bcrypt('ValidPassword123!'),
        ]);
    }

    /**
     * Helper to create a standard valid customer.
     */
    protected function createCustomer(array $overrides = []): Customer
    {
        static $counter = 1000;
        $counter++;

        return Customer::create(array_merge([
            'code' => 'CUST-' . str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'name' => 'Acme Wholesale Grocers',
            'contact_name' => 'John Doe',
            'email' => 'buyer@acmegrocers.com',
            'phone' => '+1 (555) 123-4567',
            'billing_address_line1' => '100 Commerce Blvd',
            'billing_address_line2' => 'Suite 400',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
            'shipping_address_line1' => '200 Logistics Pkwy',
            'shipping_address_line2' => 'Dock 5',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30302',
            'shipping_country' => 'US',
            'tax_id' => 'US-123456789',
            'credit_limit' => 50000.00,
            'payment_terms' => PaymentTerms::NET_30,
            'status' => CustomerStatus::ACTIVE,
            'notes' => 'Priority wholesale distributor account.',
            'salesman_id' => null,
        ], $overrides));
    }

    // =========================================================================
    // 1. Role Access & Scope Tests (CUS-PROFILE-001 to CUS-PROFILE-009)
    // =========================================================================

    public function test_super_admin_can_access_any_customer_profile(): void
    {
        $superAdmin = $this->createUserWithRole(UserRole::SUPER_ADMIN);
        $customer = $this->createCustomer();

        $response = $this->actingAs($superAdmin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Show')
            ->has('customer')
            ->where('customer.id', $customer->id)
            ->where('customer.code', $customer->code)
            ->where('can.update', true)
            ->where('can.assign', true)
        );
    }

    public function test_admin_can_access_any_customer_profile(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer();

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Show')
            ->where('customer.id', $customer->id)
            ->where('can.update', true)
            ->where('can.assign', true)
        );
    }

    public function test_accountant_can_access_customer_profile_read_only(): void
    {
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);
        $customer = $this->createCustomer();

        $response = $this->actingAs($accountant)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Show')
            ->where('customer.id', $customer->id)
            ->where('can.update', false)
            ->where('can.assign', false)
        );
    }

    public function test_salesman_can_access_assigned_customer_profile(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = $this->createCustomer(['salesman_id' => $salesman->id]);

        $response = $this->actingAs($salesman)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Show')
            ->where('customer.id', $customer->id)
            ->where('customer.salesman_id', $salesman->id)
            ->where('can.update', false)
            ->where('can.assign', false)
        );
    }

    public function test_salesman_cannot_access_another_salesmans_customer_profile_idor(): void
    {
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);
        $customerB = $this->createCustomer(['salesman_id' => $salesmanB->id]);

        $response = $this->actingAs($salesmanA)->get(route('customers.show', $customerB));

        $response->assertForbidden();
    }

    public function test_salesman_cannot_access_unassigned_customer_profile_idor(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $unassignedCustomer = $this->createCustomer(['salesman_id' => null]);

        $response = $this->actingAs($salesman)->get(route('customers.show', $unassignedCustomer));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $customer = $this->createCustomer();

        $response = $this->get(route('customers.show', $customer));

        $response->assertRedirect(route('login'));
    }

    public function test_warehouse_manager_and_delivery_partner_denied_access(): void
    {
        $warehouse = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);
        $driver = $this->createUserWithRole(UserRole::DELIVERY_PARTNER);
        $customer = $this->createCustomer();

        $this->actingAs($warehouse)->get(route('customers.show', $customer))->assertForbidden();
        $this->actingAs($driver)->get(route('customers.show', $customer))->assertForbidden();
    }

    public function test_inactive_account_cannot_access_profile(): void
    {
        $inactiveAdmin = $this->createUserWithRole(UserRole::ADMIN, AccountStatus::SUSPENDED);
        $customer = $this->createCustomer();

        $response = $this->actingAs($inactiveAdmin)->get(route('customers.show', $customer));

        $response->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2. Profile Data Integrity & Commercial Terms (CUS-PROFILE-010 to CUS-PROFILE-015)
    // =========================================================================

    public function test_profile_renders_authoritative_customer_identity(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer([
            'code' => 'CUST-00999',
            'name' => 'Metro Supermarkets Inc',
            'contact_name' => 'Jane Smith',
            'email' => 'purchasing@metro.com',
            'phone' => '+1 (555) 987-6543',
            'notes' => 'Regional retail chain with 12 locations.',
        ]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customer.code', 'CUST-00999')
            ->where('customer.name', 'Metro Supermarkets Inc')
            ->where('customer.contact_name', 'Jane Smith')
            ->where('customer.email', 'purchasing@metro.com')
            ->where('customer.phone', '+1 (555) 987-6543')
            ->where('customer.notes', 'Regional retail chain with 12 locations.')
        );
    }

    public function test_profile_renders_billing_and_shipping_addresses(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer([
            'billing_address_line1' => '500 Corporate Pkwy',
            'billing_address_line2' => 'Bldg 2',
            'billing_city' => 'Chicago',
            'billing_state' => 'IL',
            'billing_postal_code' => '60601',
            'billing_country' => 'US',
            'shipping_address_line1' => '900 Logistics Rd',
            'shipping_address_line2' => 'Bay 12',
            'shipping_city' => 'Joliet',
            'shipping_state' => 'IL',
            'shipping_postal_code' => '60431',
            'shipping_country' => 'US',
        ]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customer.billing_address_line1', '500 Corporate Pkwy')
            ->where('customer.billing_city', 'Chicago')
            ->where('customer.formatted_billing_address', "500 Corporate Pkwy\nBldg 2\nChicago, IL 60601\nUS")
            ->where('customer.shipping_address_line1', '900 Logistics Rd')
            ->where('customer.shipping_city', 'Joliet')
            ->where('customer.formatted_shipping_address', "900 Logistics Rd\nBay 12\nJoliet, IL 60431\nUS")
        );
    }

    public function test_profile_renders_credit_limit_and_payment_terms(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer([
            'credit_limit' => 75000.00,
            'payment_terms' => PaymentTerms::NET_60,
        ]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customer.credit_limit', fn ($val) => (float) $val === 75000.00)
            ->where('customer.payment_terms', 'NET_60')
            ->where('customer.payment_terms_label', 'Net 60 Days')
        );
    }

    public function test_profile_renders_customer_lifecycle_status_and_can_order(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $activeCustomer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);
        $onHoldCustomer = $this->createCustomer(['status' => CustomerStatus::ON_HOLD]);
        $inactiveCustomer = $this->createCustomer(['status' => CustomerStatus::INACTIVE]);

        $this->actingAs($admin)->get(route('customers.show', $activeCustomer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.status', 'ACTIVE')
                ->where('customer.status_label', 'Active')
                ->where('customer.can_order', true)
            );

        $this->actingAs($admin)->get(route('customers.show', $onHoldCustomer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.status', 'ON_HOLD')
                ->where('customer.status_label', 'On Hold')
                ->where('customer.can_order', false)
            );

        $this->actingAs($admin)->get(route('customers.show', $inactiveCustomer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.status', 'INACTIVE')
                ->where('customer.status_label', 'Inactive')
                ->where('customer.can_order', false)
            );
    }

    public function test_profile_eager_loads_assigned_salesman(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = $this->createCustomer(['salesman_id' => $salesman->id]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customer.salesman_id', $salesman->id)
            ->where('customer.salesman.id', $salesman->id)
            ->where('customer.salesman.name', $salesman->name)
            ->where('customer.salesman.email', $salesman->email)
            ->where('customer.salesman.status', 'ACTIVE')
        );
    }

    public function test_unassigned_customer_displays_null_salesman_safely(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['salesman_id' => null]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customer.salesman_id', null)
            ->where('customer.salesman', null)
        );
    }

    // =========================================================================
    // 3. Financial Summary Deferred State Contracts (CUS-PROFILE-016 to CUS-PROFILE-022)
    // =========================================================================

    public function test_financial_summary_reports_deferred_and_non_authoritative_state(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['credit_limit' => 50000.00]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customer.financial_summary.status', 'DEFERRED')
            ->where('customer.financial_summary.is_authoritative', false)
            ->where('customer.financial_summary.credit_limit', fn ($val) => (float) $val === 50000.00)
            ->where('customer.financial_summary.outstanding_balance', null)
            ->where('customer.financial_summary.available_credit', null)
            ->where('customer.financial_summary.credit_utilization_pct', null)
            ->where('customer.financial_summary.aging.current', null)
            ->where('customer.financial_summary.aging.days_1_30', null)
            ->where('customer.financial_summary.aging.days_31_60', null)
            ->where('customer.financial_summary.aging.days_61_90', null)
            ->where('customer.financial_summary.aging.days_90_plus', null)
            ->has('customer.financial_summary.source_notice')
        );
    }

    public function test_outstanding_balance_is_not_fabricated_as_authoritative_zero(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer();

        $profile = $this->service->getProfile($customer, $admin);

        $this->assertNull($profile['financial_summary']['outstanding_balance']);
        $this->assertFalse($profile['financial_summary']['is_authoritative']);
    }

    public function test_available_credit_is_not_fabricated_as_authoritative_credit_limit(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['credit_limit' => 100000.00]);

        $profile = $this->service->getProfile($customer, $admin);

        $this->assertNull($profile['financial_summary']['available_credit']);
        $this->assertEquals(100000.00, $profile['financial_summary']['credit_limit']);
    }

    public function test_aging_values_are_not_fabricated_as_authoritative_zero_balances(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer();

        $profile = $this->service->getProfile($customer, $admin);

        $aging = $profile['financial_summary']['aging'];
        $this->assertNull($aging['current']);
        $this->assertNull($aging['days_1_30']);
        $this->assertNull($aging['days_31_60']);
        $this->assertNull($aging['days_61_90']);
        $this->assertNull($aging['days_90_plus']);
    }

    public function test_no_order_or_payment_database_tables_exist_in_current_phase(): void
    {
        $tables = DB::connection()->getSchemaBuilder()->getTableListing();

        $this->assertNotContains('orders', $tables);
        $this->assertNotContains('order_items', $tables);
        $this->assertNotContains('payments', $tables);
        $this->assertNotContains('invoices', $tables);
        $this->assertNotContains('receivables', $tables);
        $this->assertNotContains('ledger_entries', $tables);
    }

    // =========================================================================
    // 4. Security, IDOR, and Sensitive Data Protection (CUS-PROFILE-023 to CUS-PROFILE-026)
    // =========================================================================

    public function test_no_sensitive_authentication_data_leaks_in_profile_response(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = $this->createCustomer(['salesman_id' => $salesman->id]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('customer.salesman.password')
            ->missing('customer.salesman.two_factor_secret')
            ->missing('customer.salesman.two_factor_recovery_codes')
            ->missing('customer.salesman.remember_token')
        );
    }

    public function test_tax_id_is_returned_in_profile_context(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['tax_id' => 'TX-99887766']);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customer.tax_id', 'TX-99887766')
        );
    }

    public function test_direct_id_tampering_with_non_existent_customer_returns_404(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $response = $this->actingAs($admin)->get('/customers/999999');

        $response->assertNotFound();
    }

    // =========================================================================
    // 5. Query Performance (CUS-PROFILE-027)
    // =========================================================================

    public function test_profile_query_count_is_efficient_without_n_plus_one(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = $this->createCustomer(['salesman_id' => $salesman->id]);

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $queries = DB::getQueryLog();

        // 1 query for user auth, 1 query for customer route model binding with eager-loaded salesman
        $this->assertLessThanOrEqual(5, count($queries));
    }
}
