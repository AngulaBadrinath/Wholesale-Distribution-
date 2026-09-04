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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLifecycleTest extends TestCase
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
     * Helper to create a customer.
     */
    protected function createCustomer(array $overrides = []): Customer
    {
        static $counter = 2000;
        $counter++;

        return Customer::create(array_merge([
            'code' => 'CUST-' . str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'name' => 'Acme Supermarket',
            'contact_name' => 'John Doe',
            'email' => 'buyer@acme.com',
            'phone' => '+1 (555) 123-4567',
            'billing_address_line1' => '100 Commerce Blvd',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
            'shipping_country' => 'US',
            'credit_limit' => 50000.00,
            'payment_terms' => PaymentTerms::NET_30,
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => null,
        ], $overrides));
    }

    // =========================================================================
    // 1. Order Eligibility Contract Tests (CUS-LIFE-001 to CUS-LIFE-006)
    // =========================================================================

    public function test_cus_life_001_active_customer_can_place_orders(): void
    {
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        $this->assertTrue($customer->canPlaceOrders());
        $this->assertTrue($customer->isActive());
    }

    public function test_cus_life_002_on_hold_customer_cannot_place_orders(): void
    {
        $customer = $this->createCustomer(['status' => CustomerStatus::ON_HOLD]);

        $this->assertFalse($customer->canPlaceOrders());
        $this->assertFalse($customer->isActive());
    }

    public function test_cus_life_003_inactive_customer_cannot_place_orders(): void
    {
        $customer = $this->createCustomer(['status' => CustomerStatus::INACTIVE]);

        $this->assertFalse($customer->canPlaceOrders());
        $this->assertFalse($customer->isActive());
    }

    public function test_cus_life_004_ensure_can_place_orders_passes_for_active_customer(): void
    {
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        // Should execute without throwing exception
        $customer->ensureCanPlaceOrders();
        $this->assertTrue(true);
    }

    public function test_cus_life_005_ensure_can_place_orders_throws_for_on_hold_customer(): void
    {
        $customer = $this->createCustomer(['status' => CustomerStatus::ON_HOLD]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Customer account is currently on hold and cannot participate in new sales orders.');

        $customer->ensureCanPlaceOrders();
    }

    public function test_cus_life_006_ensure_can_place_orders_throws_for_inactive_customer(): void
    {
        $customer = $this->createCustomer(['status' => CustomerStatus::INACTIVE]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Customer account is deactivated. New order creation is prohibited.');

        $customer->ensureCanPlaceOrders();
    }

    // =========================================================================
    // 2. Authoritative Lifecycle State Transitions (CUS-LIFE-007 to CUS-LIFE-012)
    // =========================================================================

    public function test_cus_life_007_super_admin_can_transition_active_to_on_hold(): void
    {
        $superAdmin = $this->createUserWithRole(UserRole::SUPER_ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        $response = $this->actingAs($superAdmin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ON_HOLD->value,
            'reason' => 'Pending credit review after high order volume',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ON_HOLD, $customer->status);
        $this->assertFalse($customer->canPlaceOrders());
    }

    public function test_cus_life_008_super_admin_can_transition_active_to_inactive(): void
    {
        $superAdmin = $this->createUserWithRole(UserRole::SUPER_ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        $response = $this->actingAs($superAdmin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::INACTIVE->value,
            'reason' => 'Customer ceased trading',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::INACTIVE, $customer->status);
        $this->assertFalse($customer->canPlaceOrders());
    }

    public function test_cus_life_009_admin_can_transition_on_hold_to_active(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ON_HOLD]);

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ACTIVE->value,
            'reason' => 'Credit review cleared',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ACTIVE, $customer->status);
        $this->assertTrue($customer->canPlaceOrders());
    }

    public function test_cus_life_010_admin_can_transition_on_hold_to_inactive(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ON_HOLD]);

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::INACTIVE->value,
            'reason' => 'Account closed after review',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::INACTIVE, $customer->status);
    }

    public function test_cus_life_011_admin_can_reactivate_inactive_to_active(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::INACTIVE]);

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ACTIVE->value,
            'reason' => 'Reactivating seasonal retail partner',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ACTIVE, $customer->status);
        $this->assertTrue($customer->canPlaceOrders());
    }

    public function test_cus_life_012_admin_can_transition_inactive_to_on_hold(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::INACTIVE]);

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ON_HOLD->value,
            'reason' => 'Reopening under probationary terms',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ON_HOLD, $customer->status);
    }

    // =========================================================================
    // 3. Role Authorization & Inactive Actor Security (CUS-LIFE-013 to CUS-LIFE-016)
    // =========================================================================

    public function test_cus_life_013_accountant_cannot_modify_customer_status(): void
    {
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        $response = $this->actingAs($accountant)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ON_HOLD->value,
        ]);

        $response->assertForbidden();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ACTIVE, $customer->status);
    }

    public function test_cus_life_014_salesman_cannot_modify_customer_status_even_for_assigned_customer(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = $this->createCustomer([
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $salesman->id,
        ]);

        $response = $this->actingAs($salesman)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ON_HOLD->value,
        ]);

        $response->assertForbidden();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ACTIVE, $customer->status);
    }

    public function test_cus_life_015_warehouse_and_delivery_roles_denied_status_modification(): void
    {
        $warehouse = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);
        $delivery = $this->createUserWithRole(UserRole::DELIVERY_PARTNER);
        $customer = $this->createCustomer();

        $this->actingAs($warehouse)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::INACTIVE->value,
        ])->assertForbidden();

        $this->actingAs($delivery)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::INACTIVE->value,
        ])->assertForbidden();
    }

    public function test_cus_life_016_inactive_admin_cannot_modify_status(): void
    {
        $suspendedAdmin = $this->createUserWithRole(UserRole::ADMIN, AccountStatus::SUSPENDED);
        $customer = $this->createCustomer();

        $response = $this->actingAs($suspendedAdmin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ON_HOLD->value,
        ]);

        $response->assertRedirect(route('login'));
    }

    // =========================================================================
    // 4. Validation, No-Ops, and Audit Classification (CUS-LIFE-017 to CUS-LIFE-024)
    // =========================================================================

    public function test_cus_life_017_no_op_transition_does_not_mutate_or_throw(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ACTIVE->value,
            'reason' => 'Redundant active request',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ACTIVE, $customer->status);
    }

    public function test_cus_life_018_invalid_status_value_rejected_with_422(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer();

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => 'DELETED',
        ]);

        $response->assertSessionHasErrors(['status']);
    }

    public function test_cus_life_019_reason_up_to_500_chars_is_accepted(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer();
        $validReason = str_repeat('A', 500);

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ON_HOLD->value,
            'reason' => $validReason,
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertEquals(CustomerStatus::ON_HOLD, $customer->status);
    }

    public function test_cus_life_020_reason_exceeding_500_chars_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer();
        $excessiveReason = str_repeat('A', 501);

        $response = $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::ON_HOLD->value,
            'reason' => $excessiveReason,
        ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_cus_life_021_audit_logs_customer_activated_action(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ON_HOLD]);

        $loggedAction = null;
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use (&$loggedAction) {
                $loggedAction = $context['action'] ?? null;
                return $context['action'] === 'CUSTOMER_ACTIVATED'
                    && $context['new_status'] === CustomerStatus::ACTIVE->value;
            });

        $this->service->updateStatus($customer, CustomerStatus::ACTIVE, $admin, 'Reactivated');

        $this->assertEquals('CUSTOMER_ACTIVATED', $loggedAction);
    }

    public function test_cus_life_022_audit_logs_customer_placed_on_hold_action(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        $loggedAction = null;
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use (&$loggedAction) {
                $loggedAction = $context['action'] ?? null;
                return $context['action'] === 'CUSTOMER_PLACED_ON_HOLD'
                    && $context['new_status'] === CustomerStatus::ON_HOLD->value;
            });

        $this->service->updateStatus($customer, CustomerStatus::ON_HOLD, $admin, 'Credit freeze');

        $this->assertEquals('CUSTOMER_PLACED_ON_HOLD', $loggedAction);
    }

    public function test_cus_life_023_audit_logs_customer_deactivated_action(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $customer = $this->createCustomer(['status' => CustomerStatus::ACTIVE]);

        $loggedAction = null;
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use (&$loggedAction) {
                $loggedAction = $context['action'] ?? null;
                return $context['action'] === 'CUSTOMER_DEACTIVATED'
                    && $context['new_status'] === CustomerStatus::INACTIVE->value;
            });

        $this->service->updateStatus($customer, CustomerStatus::INACTIVE, $admin, 'Deactivated');

        $this->assertEquals('CUSTOMER_DEACTIVATED', $loggedAction);
    }

    // =========================================================================
    // 5. Salesman Assignment & Scoping Invariants (CUS-LIFE-025 to CUS-LIFE-028)
    // =========================================================================

    public function test_cus_life_025_deactivation_preserves_salesman_assignment(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = $this->createCustomer([
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $salesman->id,
        ]);

        $this->actingAs($admin)->patch(route('customers.status', $customer), [
            'status' => CustomerStatus::INACTIVE->value,
            'reason' => 'Business closure',
        ]);

        $customer->refresh();
        $this->assertEquals(CustomerStatus::INACTIVE, $customer->status);
        $this->assertEquals($salesman->id, $customer->salesman_id);
    }

    public function test_cus_life_026_salesman_can_view_assigned_inactive_customer(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $customer = $this->createCustomer([
            'status' => CustomerStatus::INACTIVE,
            'salesman_id' => $salesman->id,
        ]);

        $response = $this->actingAs($salesman)->get(route('customers.show', $customer));

        $response->assertOk();
    }

    public function test_cus_life_027_salesman_cannot_view_another_salesmans_inactive_customer_idor(): void
    {
        $salesmanA = $this->createUserWithRole(UserRole::SALESMAN);
        $salesmanB = $this->createUserWithRole(UserRole::SALESMAN);
        $customerB = $this->createCustomer([
            'status' => CustomerStatus::INACTIVE,
            'salesman_id' => $salesmanB->id,
        ]);

        $response = $this->actingAs($salesmanA)->get(route('customers.show', $customerB));

        $response->assertForbidden();
    }

    public function test_cus_life_028_directory_status_filtering_works_accurately(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $this->createCustomer(['status' => CustomerStatus::ACTIVE]);
        $this->createCustomer(['status' => CustomerStatus::ON_HOLD]);
        $this->createCustomer(['status' => CustomerStatus::INACTIVE]);

        $activeList = $this->service->list(['status' => 'ACTIVE'], 15, $admin);
        $onHoldList = $this->service->list(['status' => 'ON_HOLD'], 15, $admin);
        $inactiveList = $this->service->list(['status' => 'INACTIVE'], 15, $admin);

        $this->assertGreaterThanOrEqual(1, $activeList->total());
        $this->assertGreaterThanOrEqual(1, $onHoldList->total());
        $this->assertGreaterThanOrEqual(1, $inactiveList->total());
    }

    // =========================================================================
    // 6. Enum Helper Methods (CUS-LIFE-031)
    // =========================================================================

    public function test_cus_life_031_customer_status_enum_descriptions_and_transitions(): void
    {
        $this->assertNotEmpty(CustomerStatus::ACTIVE->description());
        $this->assertNotEmpty(CustomerStatus::ON_HOLD->description());
        $this->assertNotEmpty(CustomerStatus::INACTIVE->description());

        $this->assertTrue(CustomerStatus::ACTIVE->canTransitionTo(CustomerStatus::ON_HOLD));
        $this->assertTrue(CustomerStatus::ACTIVE->canTransitionTo(CustomerStatus::INACTIVE));
        $this->assertFalse(CustomerStatus::ACTIVE->canTransitionTo(CustomerStatus::ACTIVE));

        $this->assertTrue(CustomerStatus::ON_HOLD->canTransitionTo(CustomerStatus::ACTIVE));
        $this->assertTrue(CustomerStatus::ON_HOLD->canTransitionTo(CustomerStatus::INACTIVE));
        $this->assertFalse(CustomerStatus::ON_HOLD->canTransitionTo(CustomerStatus::ON_HOLD));

        $this->assertTrue(CustomerStatus::INACTIVE->canTransitionTo(CustomerStatus::ACTIVE));
        $this->assertTrue(CustomerStatus::INACTIVE->canTransitionTo(CustomerStatus::ON_HOLD));
        $this->assertFalse(CustomerStatus::INACTIVE->canTransitionTo(CustomerStatus::INACTIVE));
    }
}
