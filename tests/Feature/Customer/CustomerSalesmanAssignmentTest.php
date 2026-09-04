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
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerSalesmanAssignmentTest extends TestCase
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
     * Helper to create a standard valid customer payload.
     */
    protected function validCustomerPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'CUST-00001',
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
            'tax_id' => 'TAX-GA-998877',
            'credit_limit' => 25000.00,
            'payment_terms' => PaymentTerms::NET_30->value,
            'status' => CustomerStatus::ACTIVE->value,
            'notes' => 'Priority wholesale buyer.',
        ], $overrides);
    }

    /**
     * CUS-SLM-001: Admin views assigned salesman information.
     */
    public function test_cus_slm_001_admin_views_assigned_salesman_information(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman->id,
        ]));

        // Check index view
        $indexResponse = $this->actingAs($admin)->get(route('customers.index'));
        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Index')
            ->has('customers.data.0.salesman')
            ->where('customers.data.0.salesman.id', $salesman->id)
            ->where('customers.data.0.salesman.name', $salesman->name)
        );

        // Check show view
        $showResponse = $this->actingAs($admin)->get(route('customers.show', $customer));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Show')
            ->has('customer.salesman')
            ->where('customer.salesman.id', $salesman->id)
            ->where('customer.salesman.name', $salesman->name)
        );
    }

    /**
     * CUS-SLM-002: Admin assigns active eligible salesman.
     */
    public function test_cus_slm_002_admin_assigns_active_eligible_salesman(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload());

        $this->assertNull($customer->salesman_id);

        $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesman->id,
            'reason' => 'Initial commercial territory allocation',
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'salesman_id' => $salesman->id,
        ]);
    }

    /**
     * CUS-SLM-003: Admin reassigns customer from Salesman A to Salesman B.
     */
    public function test_cus_slm_003_admin_reassigns_customer(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);

        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesmanA->id,
        ]));

        $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesmanB->id,
            'reason' => 'Portfolio rebalancing',
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertEquals($salesmanB->id, $customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-004: Admin unassigns customer.
     */
    public function test_cus_slm_004_admin_unassigns_customer(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman->id,
        ]));

        $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => null,
            'reason' => 'Account moved to corporate holding',
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertNull($customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-005: Unauthorized roles cannot assign.
     */
    public function test_cus_slm_005_unauthorized_roles_cannot_assign(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload());

        $unauthorizedRoles = [
            UserRole::ACCOUNTANT,
            UserRole::WAREHOUSE_MANAGER,
            UserRole::DELIVERY_PARTNER,
        ];

        foreach ($unauthorizedRoles as $role) {
            $user = $this->createUserWithRole($role);

            $this->actingAs($user)
                ->patch(route('customers.assign', $customer), [
                    'salesman_id' => $salesman->id,
                ])
                ->assertForbidden();
        }
    }

    /**
     * CUS-SLM-006: Salesman cannot assign.
     */
    public function test_cus_slm_006_salesman_cannot_assign(): void
    {
        $salesman1 = $this->createUserWithRole(UserRole::SALESMAN);
        $salesman2 = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman1->id,
        ]));

        $this->actingAs($salesman1)
            ->patch(route('customers.assign', $customer), [
                'salesman_id' => $salesman2->id,
            ])
            ->assertForbidden();
    }

    /**
     * CUS-SLM-007: Nonexistent salesman rejected.
     */
    public function test_cus_slm_007_nonexistent_salesman_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = Customer::create($this->validCustomerPayload());

        $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => 999999,
        ]);

        $response->assertSessionHasErrors('salesman_id');
        $this->assertNull($customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-008: Non-SALESMAN target rejected.
     */
    public function test_cus_slm_008_non_salesman_target_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = Customer::create($this->validCustomerPayload());

        $invalidTargets = [
            $this->createUserWithRole(UserRole::ADMIN),
            $this->createUserWithRole(UserRole::SUPER_ADMIN),
            $this->createUserWithRole(UserRole::ACCOUNTANT),
            $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER),
            $this->createUserWithRole(UserRole::DELIVERY_PARTNER),
        ];

        foreach ($invalidTargets as $invalidTarget) {
            $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
                'salesman_id' => $invalidTarget->id,
            ]);

            $response->assertSessionHasErrors('salesman_id');
            $this->assertNull($customer->fresh()->salesman_id);
        }
    }

    /**
     * CUS-SLM-009: Inactive salesman rejected.
     */
    public function test_cus_slm_009_inactive_salesman_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = Customer::create($this->validCustomerPayload());

        $inactiveStatuses = [
            AccountStatus::INVITED,
            AccountStatus::SUSPENDED,
            AccountStatus::DISABLED,
        ];

        foreach ($inactiveStatuses as $status) {
            $inactiveSalesman = $this->createUserWithRole(UserRole::SALESMAN, $status);

            $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
                'salesman_id' => $inactiveSalesman->id,
            ]);

            $response->assertSessionHasErrors('salesman_id');
            $this->assertNull($customer->fresh()->salesman_id);
        }
    }

    /**
     * CUS-SLM-010: Salesman list is scoped.
     */
    public function test_cus_slm_010_salesman_list_is_scoped(): void
    {
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);

        $customerA = Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00001',
            'name' => 'Alpha Assigned to A',
            'salesman_id' => $salesmanA->id,
        ]));

        $customerB = Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00002',
            'name' => 'Beta Assigned to B',
            'salesman_id' => $salesmanB->id,
        ]));

        $customerUnassigned = Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00003',
            'name' => 'Gamma Unassigned',
            'salesman_id' => null,
        ]));

        $responseA = $this->actingAs($salesmanA)->get(route('customers.index'));
        $responseA->assertOk();
        $responseA->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Index')
            ->where('customers.total', 1)
            ->where('customers.data.0.id', $customerA->id)
        );

        $responseB = $this->actingAs($salesmanB)->get(route('customers.index'));
        $responseB->assertOk();
        $responseB->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Index')
            ->where('customers.total', 1)
            ->where('customers.data.0.id', $customerB->id)
        );
    }

    /**
     * CUS-SLM-011: Salesman cannot access another salesman's customer by ID.
     */
    public function test_cus_slm_011_salesman_cannot_access_another_salesmans_customer_by_id(): void
    {
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);

        $customerB = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesmanB->id,
        ]));

        $this->actingAs($salesmanA)
            ->get(route('customers.show', $customerB))
            ->assertForbidden();
    }

    /**
     * CUS-SLM-012: Salesman search is scoped.
     */
    public function test_cus_slm_012_salesman_search_is_scoped(): void
    {
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);

        Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00001',
            'name' => 'Acme Mart Alpha',
            'salesman_id' => $salesmanA->id,
        ]));

        Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00002',
            'name' => 'Acme Mart Beta',
            'salesman_id' => $salesmanB->id,
        ]));

        $response = $this->actingAs($salesmanA)->get(route('customers.index', ['search' => 'Acme']));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Index')
            ->where('customers.total', 1)
            ->where('customers.data.0.code', 'CUST-00001')
        );
    }

    /**
     * CUS-SLM-013: Admin can filter by salesman.
     */
    public function test_cus_slm_013_admin_can_filter_by_salesman(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);

        $custA = Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00001',
            'salesman_id' => $salesmanA->id,
        ]));

        Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00002',
            'salesman_id' => $salesmanB->id,
        ]));

        $response = $this->actingAs($admin)->get(route('customers.index', ['salesman_id' => $salesmanA->id]));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Index')
            ->where('customers.total', 1)
            ->where('customers.data.0.id', $custA->id)
        );
    }

    /**
     * CUS-SLM-014: Admin can filter unassigned.
     */
    public function test_cus_slm_014_admin_can_filter_unassigned(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);

        Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00001',
            'salesman_id' => $salesman->id,
        ]));

        $unassignedCust = Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00002',
            'salesman_id' => null,
        ]));

        $response = $this->actingAs($admin)->get(route('customers.index', ['salesman_id' => 'UNASSIGNED']));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Index')
            ->where('customers.total', 1)
            ->where('customers.data.0.id', $unassignedCust->id)
        );
    }

    /**
     * CUS-SLM-015: Assignment audit.
     */
    public function test_cus_slm_015_assignment_audit(): void
    {
        Log::spy();

        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload());

        $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesman->id,
            'reason' => 'Assigned territory',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($customer, $salesman, $admin) {
            return str_contains($message, 'CUSTOMER_SALESMAN_ASSIGNED')
                && $context['action'] === 'CUSTOMER_SALESMAN_ASSIGNED'
                && $context['customer_id'] === $customer->id
                && $context['new_salesman_id'] === $salesman->id
                && is_null($context['previous_salesman_id'])
                && $context['actor_id'] === $admin->id;
        });
    }

    /**
     * CUS-SLM-016: Reassignment audit.
     */
    public function test_cus_slm_016_reassignment_audit(): void
    {
        Log::spy();

        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesmanA->id,
        ]));

        $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesmanB->id,
            'reason' => 'Reassignment',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($customer, $salesmanA, $salesmanB, $admin) {
            return str_contains($message, 'CUSTOMER_SALESMAN_REASSIGNED')
                && $context['action'] === 'CUSTOMER_SALESMAN_REASSIGNED'
                && $context['customer_id'] === $customer->id
                && $context['previous_salesman_id'] === $salesmanA->id
                && $context['new_salesman_id'] === $salesmanB->id
                && $context['actor_id'] === $admin->id;
        });
    }

    /**
     * CUS-SLM-017: Unassignment audit.
     */
    public function test_cus_slm_017_unassignment_audit(): void
    {
        Log::spy();

        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman->id,
        ]));

        $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => null,
            'reason' => 'Unassigning',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($customer, $salesman, $admin) {
            return str_contains($message, 'CUSTOMER_SALESMAN_UNASSIGNED')
                && $context['action'] === 'CUSTOMER_SALESMAN_UNASSIGNED'
                && $context['customer_id'] === $customer->id
                && $context['previous_salesman_id'] === $salesman->id
                && is_null($context['new_salesman_id'])
                && $context['actor_id'] === $admin->id;
        });
    }

    /**
     * CUS-SLM-018: Audit contains no secrets.
     */
    public function test_cus_slm_018_audit_contains_no_secrets(): void
    {
        Log::spy();

        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload());

        $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesman->id,
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $forbiddenKeys = ['password', 'secret', 'token', 'mfa', 'recovery_codes', 'remember_token'];
            foreach ($forbiddenKeys as $key) {
                if (array_key_exists($key, $context)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * CUS-SLM-019: Concurrent reassignment remains atomic.
     */
    public function test_cus_slm_019_concurrent_reassignment_remains_atomic(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload());

        DB::transaction(function () use ($customer, $salesman, $admin) {
            $updated = $this->service->assignSalesman($customer, $salesman->id, $admin, 'Atomic test', '127.0.0.1');
            $this->assertEquals($salesman->id, $updated->salesman_id);
        });

        $this->assertEquals($salesman->id, $customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-020: Customer identity remains unchanged.
     */
    public function test_cus_slm_020_customer_identity_remains_unchanged(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload());

        $originalCode = $customer->code;
        $originalName = $customer->name;
        $originalCreditLimit = $customer->credit_limit;
        $originalBillingCity = $customer->billing_city;

        $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesman->id,
        ]);

        $fresh = $customer->fresh();
        $this->assertEquals($salesman->id, $fresh->salesman_id);
        $this->assertEquals($originalCode, $fresh->code);
        $this->assertEquals($originalName, $fresh->name);
        $this->assertEquals($originalCreditLimit, $fresh->credit_limit);
        $this->assertEquals($originalBillingCity, $fresh->billing_city);
    }

    /**
     * CUS-SLM-021: Suspended salesman cannot access assigned portfolio.
     */
    public function test_cus_slm_021_suspended_salesman_cannot_access_assigned_portfolio(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN, AccountStatus::SUSPENDED);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman->id,
        ]));

        // Account status middleware should reject access
        $this->actingAs($salesman)
            ->get(route('customers.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($salesman)
            ->get(route('customers.show', $customer))
            ->assertRedirect(route('login'));
    }

    /**
     * CUS-SLM-022: Customer creation with assignment works and validates eligibility.
     */
    public function test_cus_slm_022_customer_creation_with_assignment_works(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);

        $payload = $this->validCustomerPayload([
            'code' => 'CUST-NEW-01',
            'salesman_id' => $salesman->id,
        ]);

        $response = $this->actingAs($admin)->post(route('customers.store'), $payload);
        $response->assertRedirect(route('customers.show', Customer::where('code', 'CUST-NEW-01')->first()));

        $created = Customer::where('code', 'CUST-NEW-01')->first();
        $this->assertNotNull($created);
        $this->assertEquals($salesman->id, $created->salesman_id);
    }

    /**
     * CUS-SLM-023: Customer update without assignment mutation preserves salesman.
     */
    public function test_cus_slm_023_customer_update_without_assignment_mutation_preserves_salesman(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);

        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman->id,
        ]));

        $updatePayload = array_merge($customer->toArray(), [
            'name' => 'Updated Acme Name',
        ]);

        $response = $this->actingAs($admin)->put(route('customers.update', $customer), $updatePayload);
        $response->assertRedirect(route('customers.show', $customer));

        $fresh = $customer->fresh();
        $this->assertEquals('Updated Acme Name', $fresh->name);
        $this->assertEquals($salesman->id, $fresh->salesman_id);
    }

    /**
     * CUS-SLM-024: Salesman deactivation does not destructively mutate customer assignment.
     */
    public function test_cus_slm_024_salesman_deactivation_does_not_destructively_mutate_customer_assignment(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman->id,
        ]));

        // Salesman is suspended
        $salesman->update(['status' => AccountStatus::SUSPENDED]);

        // Customer's salesman_id is preserved
        $this->assertEquals($salesman->id, $customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-025: Salesman attempting to filter other salesman ID is ignored / scoped.
     */
    public function test_cus_slm_025_salesman_attempting_to_filter_other_salesman_is_scoped(): void
    {
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);

        Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00001',
            'salesman_id' => $salesmanA->id,
        ]));

        Customer::create($this->validCustomerPayload([
            'code' => 'CUST-00002',
            'salesman_id' => $salesmanB->id,
        ]));

        // Salesman A attempts to query salesman_id=salesmanB
        $response = $this->actingAs($salesmanA)->get(route('customers.index', [
            'salesman_id' => $salesmanB->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Index')
            ->where('customers.total', 1)
            ->where('customers.data.0.code', 'CUST-00001')
        );
    }

    /**
     * CUS-SLM-026: Create-time salesman assignment uses the same eligibility rules.
     */
    public function test_cus_slm_026_create_time_salesman_assignment_uses_same_eligibility_rules(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $inactiveSalesman = $this->createUserWithRole(UserRole::SALESMAN, AccountStatus::SUSPENDED);
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);

        // Attempt creation with inactive salesman
        $payloadInactive = $this->validCustomerPayload([
            'code' => 'CUST-INACTIVE',
            'salesman_id' => $inactiveSalesman->id,
        ]);
        $this->actingAs($admin)->post(route('customers.store'), $payloadInactive)
            ->assertSessionHasErrors('salesman_id');
        $this->assertDatabaseMissing('customers', ['code' => 'CUST-INACTIVE']);

        // Attempt creation with non-salesman
        $payloadNonSalesman = $this->validCustomerPayload([
            'code' => 'CUST-NONSAL',
            'salesman_id' => $accountant->id,
        ]);
        $this->actingAs($admin)->post(route('customers.store'), $payloadNonSalesman)
            ->assertSessionHasErrors('salesman_id');
        $this->assertDatabaseMissing('customers', ['code' => 'CUST-NONSAL']);
    }

    /**
     * CUS-SLM-027: Update-time salesman assignment cannot bypass eligibility.
     */
    public function test_cus_slm_027_update_time_salesman_assignment_cannot_bypass_eligibility(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = Customer::create($this->validCustomerPayload());
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);

        $payload = array_merge($customer->toArray(), [
            'salesman_id' => $accountant->id,
        ]);

        $this->actingAs($admin)->put(route('customers.update', $customer), $payload)
            ->assertSessionHasErrors('salesman_id');

        $this->assertNull($customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-028: Admin cannot manipulate assignment by sending another user's role/identity.
     */
    public function test_cus_slm_028_admin_cannot_manipulate_actor_identity(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload());

        // Body sends actor_id=999 or user_id=999; server must use auth user ($admin)
        $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesman->id,
            'actor_id' => 99999,
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertEquals($salesman->id, $customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-029: Frontend assignment payload tampering cannot bypass server validation.
     */
    public function test_cus_slm_029_frontend_assignment_payload_tampering_cannot_bypass_server_validation(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = Customer::create($this->validCustomerPayload());

        $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => 'malicious_string_or_array',
        ]);

        $response->assertSessionHasErrors('salesman_id');
        $this->assertNull($customer->fresh()->salesman_id);
    }

    /**
     * CUS-SLM-030: No-op reassignment causes no unnecessary mutation or audit.
     */
    public function test_cus_slm_030_no_op_reassignment_causes_no_unnecessary_mutation_or_audit(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = Customer::create($this->validCustomerPayload([
            'salesman_id' => $salesman->id,
        ]));

        Log::spy();

        $response = $this->actingAs($admin)->patch(route('customers.assign', $customer), [
            'salesman_id' => $salesman->id,
            'reason' => 'Redundant assignment',
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertEquals($salesman->id, $customer->fresh()->salesman_id);

        Log::shouldNotHaveReceived('info', function ($message) {
            return str_contains($message, 'CUSTOMER_SALESMAN');
        });
    }
}
