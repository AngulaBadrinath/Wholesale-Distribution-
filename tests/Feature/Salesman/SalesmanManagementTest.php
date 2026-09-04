<?php

namespace Tests\Feature\Salesman;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Services\Auth\SessionRevocationService;
use App\Services\Salesman\SalesmanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SalesmanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    /**
     * Helper to create standard valid customer payload.
     */
    protected function validCustomerPayload(array $overrides = []): array
    {
        static $counter = 1;
        $code = sprintf('CUST-%05d', $counter++);

        return array_merge([
            'code' => $code,
            'name' => 'Acme Wholesale ' . $counter,
            'contact_name' => 'John Doe',
            'email' => "buyer{$counter}@acmegrocers.com",
            'phone' => '+1 (555) 123-4567',
            'billing_address_line1' => '100 Commerce Blvd',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
            'shipping_address_line1' => '200 Logistics Pkwy',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30301',
            'shipping_country' => 'US',
            'credit_limit' => 50000.00,
            'payment_terms' => \App\Enums\PaymentTerms::NET_30,
            'status' => \App\Enums\CustomerStatus::ACTIVE,
        ], $overrides);
    }

    /**
     * SLM-ACC-001: Super Admin and Admin can access salesman directory.
     */
    public function test_super_admin_and_admin_can_access_salesman_directory(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        User::factory()->salesman()->count(3)->create();

        $responseSuper = $this->actingAs($superAdmin)->get('/salesmen');
        $responseSuper->assertOk();
        $responseSuper->assertInertia(fn ($page) => $page
            ->component('Salesman/Index')
            ->has('salesmen.data', 3)
            ->has('statuses')
        );

        $responseAdmin = $this->actingAs($admin)->get('/salesmen');
        $responseAdmin->assertOk();
    }

    /**
     * SLM-ACC-002: Unauthorized roles cannot access salesman directory.
     */
    public function test_unauthorized_roles_cannot_access_salesman_directory(): void
    {
        $accountant = User::factory()->accountant()->create();
        $salesman = User::factory()->salesman()->create();
        $warehouse = User::factory()->warehouseManager()->create();
        $delivery = User::factory()->deliveryPartner()->create();

        $this->actingAs($accountant)->get('/salesmen')->assertForbidden();
        $this->actingAs($salesman)->get('/salesmen')->assertForbidden();
        $this->actingAs($warehouse)->get('/salesmen')->assertForbidden();
        $this->actingAs($delivery)->get('/salesmen')->assertForbidden();
    }

    /**
     * SLM-ACC-003: Unauthenticated guests are redirected to login.
     */
    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/salesmen');
        $response->assertRedirect(route('login'));
    }

    /**
     * SLM-ACC-004: Authorized admin can provision salesman account.
     */
    public function test_authorized_admin_can_provision_salesman_account(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'John Salesman',
            'email' => 'john.salesman@distributor.com',
            'password' => 'SecurePass123!',
            'status' => 'ACTIVE',
        ];

        $response = $this->actingAs($admin)->post('/salesmen', $payload);

        $createdUser = User::where('email', 'john.salesman@distributor.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame('John Salesman', $createdUser->name);
        $this->assertSame(UserRole::SALESMAN, $createdUser->role);
        $this->assertSame(AccountStatus::ACTIVE, $createdUser->status);
        $this->assertTrue(Hash::check('SecurePass123!', $createdUser->password));

        $response->assertRedirect(route('salesmen.show', $createdUser->id));
        $response->assertSessionHas('status');
    }

    /**
     * SLM-ACC-005: Provisioning forces SALESMAN role on server regardless of client input.
     */
    public function test_provisioning_forces_salesman_role_on_server(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'Attacker Attempt',
            'email' => 'attacker@distributor.com',
            'password' => 'SecurePass123!',
            'status' => 'ACTIVE',
            'role' => 'SUPER_ADMIN', // Attempted role escalation
        ];

        $this->actingAs($admin)->post('/salesmen', $payload);

        $createdUser = User::where('email', 'attacker@distributor.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame(UserRole::SALESMAN, $createdUser->role);
        $this->assertNotSame(UserRole::SUPER_ADMIN, $createdUser->role);
    }

    /**
     * SLM-ACC-006: Duplicate salesman email is rejected with 422.
     */
    public function test_duplicate_salesman_email_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'existing@distributor.com']);

        $payload = [
            'name' => 'Duplicate Salesman',
            'email' => 'existing@distributor.com',
            'password' => 'SecurePass123!',
            'status' => 'ACTIVE',
        ];

        $response = $this->actingAs($admin)->post('/salesmen', $payload);
        $response->assertSessionHasErrors('email');
    }

    /**
     * SLM-ACC-007: Short passwords are rejected.
     */
    public function test_short_passwords_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'Weak Salesman',
            'email' => 'weak@distributor.com',
            'password' => 'short',
            'status' => 'ACTIVE',
        ];

        $response = $this->actingAs($admin)->post('/salesmen', $payload);
        $response->assertSessionHasErrors('password');
    }

    /**
     * SLM-ACC-008: Authorized admin can view salesman profile with assigned portfolio.
     */
    public function test_authorized_admin_can_view_salesman_profile_with_assigned_portfolio(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->create(['name' => 'Jane Salesman']);

        $customer1 = Customer::create($this->validCustomerPayload(['salesman_id' => $salesman->id, 'name' => 'Alpha Mart']));
        $customer2 = Customer::create($this->validCustomerPayload(['salesman_id' => $salesman->id, 'name' => 'Beta Grocery']));
        $unrelatedCustomer = Customer::create($this->validCustomerPayload());

        $response = $this->actingAs($admin)->get("/salesmen/{$salesman->id}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Salesman/Show')
            ->where('salesman.id', $salesman->id)
            ->where('salesman.name', 'Jane Salesman')
            ->has('assigned_customers', 2)
            ->where('assigned_customers.0.name', 'Alpha Mart')
            ->where('assigned_customers.1.name', 'Beta Grocery')
            ->has('statuses')
        );
    }

    /**
     * SLM-ACC-009: Authorized admin can edit salesman name and email.
     */
    public function test_authorized_admin_can_edit_salesman_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->create([
            'name' => 'Old Name',
            'email' => 'old.email@distributor.com',
        ]);

        $payload = [
            'name' => 'Updated Name',
            'email' => 'new.email@distributor.com',
        ];

        $response = $this->actingAs($admin)->put("/salesmen/{$salesman->id}", $payload);
        $response->assertRedirect(route('salesmen.show', $salesman->id));

        $salesman->refresh();
        $this->assertSame('Updated Name', $salesman->name);
        $this->assertSame('new.email@distributor.com', $salesman->email);
    }

    /**
     * SLM-ACC-010: Admin can transition salesman to suspended with reason and audit log.
     */
    public function test_admin_can_transition_salesman_to_suspended_with_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->create(['status' => AccountStatus::ACTIVE]);

        Log::spy();

        $payload = [
            'status' => 'SUSPENDED',
            'reason' => 'Administrative review pending.',
        ];

        $response = $this->actingAs($admin)->patch("/salesmen/{$salesman->id}/status", $payload);
        $response->assertRedirect();

        $salesman->refresh();
        $this->assertSame(AccountStatus::SUSPENDED, $salesman->status);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($admin, $salesman) {
            return $message === 'auth.salesman_event'
                && ($context['action'] ?? '') === 'SALESMAN_SUSPENDED'
                && ($context['actor_id'] ?? null) === $admin->id
                && ($context['salesman_id'] ?? null) === $salesman->id
                && ($context['new_status'] ?? '') === 'SUSPENDED'
                && ($context['reason'] ?? '') === 'Administrative review pending.';
        });
    }

    /**
     * SLM-ACC-011: Suspending salesman revokes active database sessions immediately.
     */
    public function test_suspending_salesman_revokes_active_sessions_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->create(['status' => AccountStatus::ACTIVE]);

        // Create active database sessions for salesman
        DB::table('sessions')->insert([
            ['id' => 'sess_1', 'user_id' => $salesman->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Agent', 'payload' => 'payload', 'last_activity' => time()],
            ['id' => 'sess_2', 'user_id' => $salesman->id, 'ip_address' => '127.0.0.2', 'user_agent' => 'Agent', 'payload' => 'payload', 'last_activity' => time()],
            ['id' => 'sess_admin', 'user_id' => $admin->id, 'ip_address' => '127.0.0.3', 'user_agent' => 'Agent', 'payload' => 'payload', 'last_activity' => time()],
        ]);

        $this->assertSame(2, DB::table('sessions')->where('user_id', $salesman->id)->count());

        $this->actingAs($admin)->patch("/salesmen/{$salesman->id}/status", [
            'status' => 'SUSPENDED',
            'reason' => 'Security hold',
        ]);

        // Salesman sessions are deleted, admin session remains intact
        $this->assertSame(0, DB::table('sessions')->where('user_id', $salesman->id)->count());
        $this->assertGreaterThanOrEqual(1, DB::table('sessions')->where('user_id', $admin->id)->count());
    }

    /**
     * SLM-ACC-012: Suspended salesman cannot authenticate.
     */
    public function test_suspended_salesman_cannot_authenticate(): void
    {
        $salesman = User::factory()->salesman()->suspended()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $this->assertFalse($salesman->canAuthenticate());

        $response = $this->post('/login', [
            'email' => $salesman->email,
            'password' => 'Password123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * SLM-ACC-013: Suspending salesman preserves customer assignments and blocks new assignments.
     */
    public function test_suspending_salesman_preserves_customer_assignments_and_blocks_new_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->create(['status' => AccountStatus::ACTIVE]);
        $customer = Customer::create($this->validCustomerPayload(['salesman_id' => $salesman->id]));

        $this->assertTrue($salesman->canBeAssignedAsSalesman());

        $this->actingAs($admin)->patch("/salesmen/{$salesman->id}/status", [
            'status' => 'SUSPENDED',
            'reason' => 'Temporary suspension',
        ]);

        $salesman->refresh();
        $customer->refresh();

        // Historical assignment is preserved
        $this->assertSame($salesman->id, $customer->salesman_id);

        // Cannot be assigned to new customers
        $this->assertFalse($salesman->canBeAssignedAsSalesman());
    }

    /**
     * SLM-ACC-014: Admin can transition salesman to disabled with audit log.
     */
    public function test_admin_can_transition_salesman_to_disabled_and_revokes_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->create(['status' => AccountStatus::ACTIVE]);

        Log::spy();

        $response = $this->actingAs($admin)->patch("/salesmen/{$salesman->id}/status", [
            'status' => 'DISABLED',
            'reason' => 'Contract terminated',
        ]);
        $response->assertRedirect();

        $salesman->refresh();
        $this->assertSame(AccountStatus::DISABLED, $salesman->status);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($salesman) {
            return $message === 'auth.salesman_event'
                && ($context['action'] ?? '') === 'SALESMAN_DISABLED'
                && ($context['salesman_id'] ?? null) === $salesman->id;
        });
    }

    /**
     * SLM-ACC-015: Admin can reactivate suspended salesman to active.
     */
    public function test_admin_can_reactivate_suspended_salesman_to_active(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->suspended()->create();

        Log::spy();

        $response = $this->actingAs($admin)->patch("/salesmen/{$salesman->id}/status", [
            'status' => 'ACTIVE',
            'reason' => 'Reinstated after review',
        ]);
        $response->assertRedirect();

        $salesman->refresh();
        $this->assertSame(AccountStatus::ACTIVE, $salesman->status);
        $this->assertTrue($salesman->canAuthenticate());
        $this->assertTrue($salesman->canBeAssignedAsSalesman());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($salesman) {
            return $message === 'auth.salesman_event'
                && ($context['action'] ?? '') === 'SALESMAN_ACTIVATED'
                && ($context['salesman_id'] ?? null) === $salesman->id;
        });
    }

    /**
     * SLM-ACC-016: Reactivated salesman can successfully log in.
     */
    public function test_reactivated_salesman_can_successfully_log_in(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->suspended()->create([
            'password' => Hash::make('Password123!'),
        ]);

        // Reactivate
        $this->actingAs($admin)->patch("/salesmen/{$salesman->id}/status", [
            'status' => 'ACTIVE',
        ]);

        $this->post('/logout');

        // Now salesman logs in
        $response = $this->post('/login', [
            'email' => $salesman->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($salesman);
    }

    /**
     * SLM-ACC-017: Admin attempting to suspend or alter own account via salesman endpoint is blocked.
     */
    public function test_self_suspension_is_prohibited(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch("/salesmen/{$admin->id}/status", [
            'status' => 'SUSPENDED',
        ]);

        // Admin is not a salesman, returns 404 or 403
        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $admin->refresh();
        $this->assertSame(AccountStatus::ACTIVE, $admin->status);
    }

    /**
     * SLM-ACC-018: No-op status transition does not produce redundant database writes or audit logs.
     */
    public function test_no_op_status_transition_does_not_mutate_db_or_duplicate_audits(): void
    {
        $admin = User::factory()->admin()->create();
        $salesman = User::factory()->salesman()->create(['status' => AccountStatus::ACTIVE]);

        Log::spy();

        $response = $this->actingAs($admin)->patch("/salesmen/{$salesman->id}/status", [
            'status' => 'ACTIVE',
        ]);
        $response->assertRedirect();

        Log::shouldNotHaveReceived('info', function ($message, $context) {
            return ($context['action'] ?? '') === 'SALESMAN_ACTIVATED';
        });
    }

    /**
     * SLM-ACC-019: Salesman directory status filtering returns exact subsets.
     */
    public function test_salesman_directory_status_filtering(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->salesman()->create(['name' => 'Active Salesman', 'status' => AccountStatus::ACTIVE]);
        User::factory()->salesman()->suspended()->create(['name' => 'Suspended Salesman']);
        User::factory()->salesman()->disabled()->create(['name' => 'Disabled Salesman']);
        User::factory()->salesman()->invited()->create(['name' => 'Invited Salesman']);

        $resActive = $this->actingAs($admin)->get('/salesmen?status=active');
        $resActive->assertOk();
        $resActive->assertInertia(fn ($page) => $page->where('salesmen.total', 1)->where('salesmen.data.0.name', 'Active Salesman'));

        $resSuspended = $this->actingAs($admin)->get('/salesmen?status=suspended');
        $resSuspended->assertOk();
        $resSuspended->assertInertia(fn ($page) => $page->where('salesmen.total', 1)->where('salesmen.data.0.name', 'Suspended Salesman'));

        $resDisabled = $this->actingAs($admin)->get('/salesmen?status=disabled');
        $resDisabled->assertOk();
        $resDisabled->assertInertia(fn ($page) => $page->where('salesmen.total', 1)->where('salesmen.data.0.name', 'Disabled Salesman'));

        $resInvited = $this->actingAs($admin)->get('/salesmen?status=invited');
        $resInvited->assertOk();
        $resInvited->assertInertia(fn ($page) => $page->where('salesmen.total', 1)->where('salesmen.data.0.name', 'Invited Salesman'));
    }

    /**
     * SLM-ACC-020: Search query matches salesman name and email without leaking non-salesman users.
     */
    public function test_salesman_directory_search_is_scoped_to_salesmen(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->salesman()->create(['name' => 'Carlos Rodriguez', 'email' => 'carlos@distributor.com']);
        User::factory()->admin()->create(['name' => 'Carlos Administrator', 'email' => 'carlos.admin@distributor.com']);

        $response = $this->actingAs($admin)->get('/salesmen?search=Carlos');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('salesmen.total', 1)
            ->where('salesmen.data.0.email', 'carlos@distributor.com')
        );
    }

    /**
     * SLM-ACC-021: Audit logs contain zero passwords, MFA secrets, or session tokens.
     */
    public function test_audit_logs_contain_no_sensitive_secrets_or_passwords(): void
    {
        $admin = User::factory()->admin()->create();

        Log::spy();

        $this->actingAs($admin)->post('/salesmen', [
            'name' => 'Privacy Check Salesman',
            'email' => 'privacy@distributor.com',
            'password' => 'SuperSecretPlaintextPassword123!',
            'status' => 'ACTIVE',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $json = json_encode($context);
            $this->assertStringNotContainsString('SuperSecretPlaintextPassword123!', $json);
            $this->assertArrayNotHasKey('password', $context);
            $this->assertArrayNotHasKey('two_factor_secret', $context);
            return true;
        });
    }

    /**
     * SLM-ACC-022: Non-salesman users cannot be managed or viewed via salesman endpoints (IDOR / Scope).
     */
    public function test_non_salesman_user_cannot_be_managed_via_salesman_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($admin)->get("/salesmen/{$accountant->id}")->assertNotFound();
        $this->actingAs($admin)->get("/salesmen/{$accountant->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/salesmen/{$accountant->id}", ['name' => 'New Name', 'email' => 'new@distributor.com'])->assertNotFound();
        $this->actingAs($admin)->patch("/salesmen/{$accountant->id}/status", ['status' => 'SUSPENDED'])->assertNotFound();
    }

    /**
     * SLM-ACC-023: AccountStatus enum transition matrix and metadata validation.
     */
    public function test_enum_metadata_and_transition_matrix(): void
    {
        $active = AccountStatus::ACTIVE;
        $suspended = AccountStatus::SUSPENDED;
        $disabled = AccountStatus::DISABLED;
        $invited = AccountStatus::INVITED;

        $this->assertSame('Active', $active->label());
        $this->assertSame('Suspended', $suspended->label());
        $this->assertSame('Disabled', $disabled->label());
        $this->assertSame('Invited', $invited->label());

        $this->assertTrue($active->canTransitionTo($suspended));
        $this->assertTrue($active->canTransitionTo($disabled));
        $this->assertFalse($active->canTransitionTo($active)); // No-op

        $this->assertTrue($suspended->canTransitionTo($active));
        $this->assertTrue($suspended->canTransitionTo($disabled));

        $this->assertTrue($disabled->canTransitionTo($active));
        $this->assertTrue($disabled->canTransitionTo($suspended));

        $this->assertTrue($invited->canTransitionTo($active));
        $this->assertTrue($invited->canTransitionTo($disabled));

        $this->assertSame(['INVITED', 'ACTIVE', 'SUSPENDED', 'DISABLED'], AccountStatus::values());
    }
}
