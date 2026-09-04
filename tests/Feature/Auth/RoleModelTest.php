<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\RoleAssignmentService;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    /**
     * RBAC-ROLE-001: All six canonical roles exist.
     */
    public function test_all_six_canonical_roles_exist(): void
    {
        $expectedRoles = [
            'SUPER_ADMIN',
            'ADMIN',
            'ACCOUNTANT',
            'SALESMAN',
            'WAREHOUSE_MANAGER',
            'DELIVERY_PARTNER',
        ];

        $cases = array_map(fn (UserRole $role) => $role->value, UserRole::cases());
        $this->assertSame($expectedRoles, $cases);
        $this->assertSame($expectedRoles, UserRole::values());
    }

    /**
     * RBAC-ROLE-002: User role is strongly typed through UserRole.
     */
    public function test_user_role_is_strongly_typed_through_user_role(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertSame(UserRole::SUPER_ADMIN, $user->role);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasRole(UserRole::SUPER_ADMIN));
        $this->assertTrue($user->hasRole('SUPER_ADMIN'));
    }

    /**
     * RBAC-ROLE-003: Invalid role cannot be persisted.
     */
    public function test_invalid_role_cannot_be_persisted(): void
    {
        $this->expectException(\ValueError::class);

        $user = User::factory()->create();
        $user->role = 'NON_EXISTENT_ROLE'; // @phpstan-ignore-line
        $user->save();
    }

    /**
     * RBAC-ROLE-004: MFA policy consumes authoritative UserRole.
     */
    public function test_mfa_policy_consumes_authoritative_user_role(): void
    {
        $mfaPolicy = app(MfaPolicy::class);

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $accountant = User::factory()->accountant()->create();
        $salesman = User::factory()->salesman()->create();

        $this->assertTrue($mfaPolicy->isMfaRequired($superAdmin));
        $this->assertTrue($mfaPolicy->isMfaRequired($admin));
        $this->assertTrue($mfaPolicy->isMfaRequired($accountant));
        $this->assertFalse($mfaPolicy->isMfaRequired($salesman));
    }

    /**
     * RBAC-ROLE-005: SUPER_ADMIN is privileged.
     */
    public function test_super_admin_is_privileged(): void
    {
        $this->assertTrue(UserRole::SUPER_ADMIN->isPrivileged());
    }

    /**
     * RBAC-ROLE-006: ADMIN is privileged.
     */
    public function test_admin_is_privileged(): void
    {
        $this->assertTrue(UserRole::ADMIN->isPrivileged());
    }

    /**
     * RBAC-ROLE-007: ACCOUNTANT is privileged.
     */
    public function test_accountant_is_privileged(): void
    {
        $this->assertTrue(UserRole::ACCOUNTANT->isPrivileged());
    }

    /**
     * RBAC-ROLE-008: SALESMAN is not privileged.
     */
    public function test_salesman_is_not_privileged(): void
    {
        $this->assertFalse(UserRole::SALESMAN->isPrivileged());
    }

    /**
     * RBAC-ROLE-009: WAREHOUSE_MANAGER is not privileged.
     */
    public function test_warehouse_manager_is_not_privileged(): void
    {
        $this->assertFalse(UserRole::WAREHOUSE_MANAGER->isPrivileged());
    }

    /**
     * RBAC-ROLE-010: DELIVERY_PARTNER is not privileged.
     */
    public function test_delivery_partner_is_not_privileged(): void
    {
        $this->assertFalse(UserRole::DELIVERY_PARTNER->isPrivileged());
    }

    /**
     * RBAC-ROLE-011: Authorized role assignment succeeds.
     */
    public function test_authorized_role_assignment_succeeds(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        $response = $this->actingAs($admin)->put(route('users.role.update', $target), [
            'role' => UserRole::ACCOUNTANT->value,
            'reason' => 'Transfer to finance team',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(UserRole::ACCOUNTANT, $target->fresh()->role);
    }

    /**
     * RBAC-ROLE-012: Unauthorized actor cannot change role.
     */
    public function test_unauthorized_actor_cannot_change_role(): void
    {
        $salesman = User::factory()->salesman()->create();
        $target = User::factory()->salesman()->create();

        $response = $this->actingAs($salesman)->put(route('users.role.update', $target), [
            'role' => UserRole::ADMIN->value,
        ]);

        $response->assertForbidden();
        $this->assertSame(UserRole::SALESMAN, $target->fresh()->role);
    }

    /**
     * RBAC-ROLE-013: Self-role modification is rejected.
     */
    public function test_self_role_modification_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('users.role.update', $admin), [
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $response->assertForbidden();
        $this->assertSame(UserRole::ADMIN, $admin->fresh()->role);
    }

    /**
     * RBAC-ROLE-014: Invalid role assignment returns validation failure.
     */
    public function test_invalid_role_assignment_returns_validation_failure(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        $response = $this->actingAs($admin)->put(route('users.role.update', $target), [
            'role' => 'INVALID_ROLE_STRING',
        ]);

        $response->assertSessionHasErrors(['role']);
        $this->assertSame(UserRole::SALESMAN, $target->fresh()->role);
    }

    /**
     * RBAC-ROLE-015: Role change produces audit event.
     */
    public function test_role_change_produces_audit_event(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        Log::spy();

        $this->actingAs($admin)->put(route('users.role.update', $target), [
            'role' => UserRole::ACCOUNTANT->value,
            'reason' => 'Audit test promotion',
        ]);

        Log::shouldHaveReceived('info')
            ->with('auth.security_event', \Mockery::on(function ($context) use ($admin, $target) {
                return ($context['action'] ?? null) === 'ROLE_ASSIGNED'
                    && ($context['actor_id'] ?? null) === $admin->id
                    && ($context['target_user_id'] ?? null) === $target->id
                    && ($context['previous_role'] ?? null) === UserRole::SALESMAN->value
                    && ($context['new_role'] ?? null) === UserRole::ACCOUNTANT->value;
            }));
    }

    /**
     * RBAC-ROLE-016: Audit contains previous and new role.
     */
    public function test_audit_contains_previous_and_new_role(): void
    {
        $service = app(RoleAssignmentService::class);
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        $loggedData = null;
        Log::shouldReceive('info')->andReturnUsing(function ($event, $context) use (&$loggedData) {
            if ($event === 'auth.security_event' && ($context['action'] ?? null) === 'ROLE_ASSIGNED') {
                $loggedData = $context;
            }
        });

        $service->assignRole($admin, $target, UserRole::WAREHOUSE_MANAGER, 'Role change');

        $this->assertNotNull($loggedData);
        $this->assertSame(UserRole::SALESMAN->value, $loggedData['previous_role']);
        $this->assertSame(UserRole::WAREHOUSE_MANAGER->value, $loggedData['new_role']);
    }

    /**
     * RBAC-ROLE-017: Audit contains no secrets.
     */
    public function test_audit_contains_no_secrets(): void
    {
        $service = app(RoleAssignmentService::class);
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->withMfa('TESTSECRET123456')->create();

        $loggedContext = null;
        Log::shouldReceive('info')->andReturnUsing(function ($event, $context) use (&$loggedContext) {
            if ($event === 'auth.security_event') {
                $loggedContext = $context;
            }
        });

        $service->assignRole($admin, $target, UserRole::ACCOUNTANT, 'Testing secrecy');

        $this->assertNotNull($loggedContext);
        $this->assertArrayNotHasKey('password', $loggedContext);
        $this->assertArrayNotHasKey('two_factor_secret', $loggedContext);
        $this->assertArrayNotHasKey('token', $loggedContext);
        $this->assertArrayNotHasKey('cookie', $loggedContext);
        $this->assertArrayNotHasKey('session_id', $loggedContext);
    }

    /**
     * RBAC-ROLE-018: Role change triggers session security invalidation.
     */
    public function test_role_change_triggers_session_security_invalidation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        // Create an active session for the target in the database
        DB::table('sessions')->insert([
            'id' => 'target-session-token-123',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Chrome/120.0',
            'payload' => serialize(['test' => 'data']),
            'last_activity' => now()->timestamp,
        ]);

        $this->assertDatabaseHas('sessions', ['user_id' => $target->id]);

        $this->actingAs($admin)->put(route('users.role.update', $target), [
            'role' => UserRole::ACCOUNTANT->value,
        ]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    /**
     * RBAC-ROLE-019: Old target sessions cannot retain stale privilege.
     */
    public function test_old_target_sessions_cannot_retain_stale_privilege(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $targetAdmin = User::factory()->admin()->create();

        // Seed target session
        DB::table('sessions')->insert([
            'id' => 'admin-stale-session-456',
            'user_id' => $targetAdmin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => serialize(['test' => 'data']),
            'last_activity' => now()->timestamp,
        ]);

        // Super Admin demotes Admin to Delivery Partner
        $this->actingAs($superAdmin)->put(route('users.role.update', $targetAdmin), [
            'role' => UserRole::DELIVERY_PARTNER->value,
            'reason' => 'Demoted for policy breach',
        ]);

        $this->assertSame(UserRole::DELIVERY_PARTNER, $targetAdmin->fresh()->role);
        // Stale session must be erased from database
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-stale-session-456']);
    }

    /**
     * RBAC-ROLE-020: IDOR attempts fail.
     */
    public function test_idor_attempts_fail(): void
    {
        $salesman = User::factory()->salesman()->create();
        $target = User::factory()->admin()->create();

        // Direct PUT with guessed user ID by unauthorized actor
        $response = $this->actingAs($salesman)->put("/security/users/{$target->id}/role", [
            'role' => UserRole::SALESMAN->value,
        ]);

        $response->assertForbidden();
        $this->assertSame(UserRole::ADMIN, $target->fresh()->role);
    }

    /**
     * RBAC-ROLE-021: Concurrent role changes remain consistent.
     */
    public function test_concurrent_role_changes_remain_consistent(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        $service = app(RoleAssignmentService::class);

        // First transition
        $service->assignRole($admin, $target, UserRole::ACCOUNTANT);
        $this->assertSame(UserRole::ACCOUNTANT, $target->fresh()->role);

        // Second transition
        $service->assignRole($admin, $target, UserRole::WAREHOUSE_MANAGER);
        $this->assertSame(UserRole::WAREHOUSE_MANAGER, $target->fresh()->role);
    }

    /**
     * RBAC-ROLE-022: Login regression passes.
     */
    public function test_login_regression_passes(): void
    {
        $salesman = User::factory()->salesman()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $salesman->email,
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($salesman);
    }

    /**
     * RBAC-ROLE-023: MFA regression passes.
     */
    public function test_mfa_regression_passes(): void
    {
        $admin = User::factory()->admin()->withMfa('TESTSECRET123456')->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();
    }

    /**
     * RBAC-ROLE-024: Password reset regression passes.
     */
    public function test_password_reset_regression_passes(): void
    {
        $user = User::factory()->salesman()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    /**
     * RBAC-ROLE-025: Session revocation regression passes.
     */
    public function test_session_revocation_regression_passes(): void
    {
        $user = User::factory()->salesman()->create();
        $service = app(SessionRevocationService::class);

        DB::table('sessions')->insert([
            'id' => 'sample-test-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => serialize(['test' => 'data']),
            'last_activity' => now()->timestamp,
        ]);

        $revoked = $service->revokeUserSessionsForSecurityEvent($user, 'unit_test');
        $this->assertSame(1, $revoked);
        $this->assertDatabaseMissing('sessions', ['id' => 'sample-test-session-id']);
    }

    /**
     * RBAC-ROLE-026: All six factory role states work.
     */
    public function test_all_six_factory_role_states_work(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $accountant = User::factory()->accountant()->create();
        $salesman = User::factory()->salesman()->create();
        $warehouseManager = User::factory()->warehouseManager()->create();
        $deliveryPartner = User::factory()->deliveryPartner()->create();

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($accountant->isAccountant());
        $this->assertTrue($salesman->isSalesman());
        $this->assertTrue($warehouseManager->isWarehouseManager());
        $this->assertTrue($deliveryPartner->isDeliveryPartner());
    }

    /**
     * RBAC-ROLE-027: ADMIN cannot grant SUPER_ADMIN.
     */
    public function test_admin_cannot_grant_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        $response = $this->actingAs($admin)->put(route('users.role.update', $target), [
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $response->assertForbidden();
        $this->assertSame(UserRole::SALESMAN, $target->fresh()->role);
    }

    /**
     * RBAC-ROLE-028: ADMIN cannot modify SUPER_ADMIN.
     */
    public function test_admin_cannot_modify_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin1 = User::factory()->superAdmin()->create();
        $superAdmin2 = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->put(route('users.role.update', $superAdmin1), [
            'role' => UserRole::ADMIN->value,
        ]);

        $response->assertForbidden();
        $this->assertSame(UserRole::SUPER_ADMIN, $superAdmin1->fresh()->role);
    }

    /**
     * RBAC-ROLE-029: Last SUPER_ADMIN cannot be demoted.
     */
    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $onlySuperAdmin = User::factory()->superAdmin()->create();
        $adminActor = User::factory()->admin()->create();

        $service = app(RoleAssignmentService::class);

        $this->expectException(ValidationException::class);
        $service->assignRole($adminActor, $onlySuperAdmin, UserRole::ADMIN);
    }

    /**
     * RBAC-ROLE-029B: Demoting target when no other SUPER_ADMIN exists throws ValidationException.
     */
    public function test_last_super_admin_cannot_be_demoted_service_validation(): void
    {
        $service = app(RoleAssignmentService::class);

        $superAdmin1 = User::factory()->superAdmin()->create();
        $superAdmin2 = User::factory()->superAdmin()->create();

        // Demote superAdmin2: allowed because superAdmin1 remains
        $service->assignRole($superAdmin1, $superAdmin2, UserRole::ADMIN);
        $this->assertSame(UserRole::ADMIN, $superAdmin2->fresh()->role);

        // Now superAdmin1 is the ONLY Super Admin left.
        // Any demotion attempt throws ValidationException
        $this->expectException(ValidationException::class);
        $service->assignRole($superAdmin2, $superAdmin1, UserRole::ADMIN);
    }

    /**
     * RBAC-ROLE-029C: ADMIN cannot modify SUPER_ADMIN even in service layer.
     */
    public function test_admin_cannot_modify_super_admin_service(): void
    {
        $service = app(RoleAssignmentService::class);

        $admin = User::factory()->admin()->create();
        $superAdmin1 = User::factory()->superAdmin()->create();
        $superAdmin2 = User::factory()->superAdmin()->create();

        $this->expectException(AuthorizationException::class);
        $service->assignRole($admin, $superAdmin1, UserRole::ACCOUNTANT);
    }

    /**
     * RBAC-ROLE-030: SUPER_ADMIN can grant SUPER_ADMIN.
     */
    public function test_super_admin_can_grant_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->salesman()->create();

        $response = $this->actingAs($superAdmin)->put(route('users.role.update', $target), [
            'role' => UserRole::SUPER_ADMIN->value,
            'reason' => 'Appointed new co-director',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(UserRole::SUPER_ADMIN, $target->fresh()->role);
    }

    /**
     * RBAC-ROLE-031: SUPER_ADMIN can modify another SUPER_ADMIN when multiple exist.
     */
    public function test_super_admin_can_modify_another_super_admin(): void
    {
        $superAdmin1 = User::factory()->superAdmin()->create();
        $superAdmin2 = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin1)->put(route('users.role.update', $superAdmin2), [
            'role' => UserRole::ADMIN->value,
            'reason' => 'Demoting to general administrator',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(UserRole::ADMIN, $superAdmin2->fresh()->role);
    }

    /**
     * RBAC-ROLE-032: Non-active actor cannot assign roles.
     */
    public function test_non_active_actor_cannot_assign_roles(): void
    {
        $suspendedAdmin = User::factory()->admin()->suspended()->create();
        $target = User::factory()->salesman()->create();

        $service = app(RoleAssignmentService::class);

        $this->expectException(AuthorizationException::class);
        $service->assignRole($suspendedAdmin, $target, UserRole::ACCOUNTANT);
    }

    /**
     * RBAC-ROLE-033: Role no-op does not unnecessarily revoke sessions or audit.
     */
    public function test_role_no_op_does_not_revoke_sessions_or_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->salesman()->create();

        // Seed target session
        DB::table('sessions')->insert([
            'id' => 'no-op-session-123',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => serialize(['test' => 'data']),
            'last_activity' => now()->timestamp,
        ]);

        $service = app(RoleAssignmentService::class);

        // Assign same role
        $updated = $service->assignRole($admin, $target, UserRole::SALESMAN);

        $this->assertSame(UserRole::SALESMAN, $updated->role);
        // Session should NOT be deleted
        $this->assertDatabaseHas('sessions', ['id' => 'no-op-session-123']);
    }

    /**
     * RBAC-ROLE-034: Role change does not mutate password, account status, or MFA secret.
     */
    public function test_role_change_does_not_mutate_other_attributes(): void
    {
        $admin = User::factory()->admin()->create();
        $password = Hash::make('SecretPass123!');
        $target = User::factory()->salesman()->withMfa('MYSECRET123456')->create([
            'password' => $password,
            'status' => AccountStatus::ACTIVE,
            'email' => 'target.preserved@example.com',
        ]);

        $service = app(RoleAssignmentService::class);
        $service->assignRole($admin, $target, UserRole::ACCOUNTANT);

        $fresh = $target->fresh();
        $this->assertSame(UserRole::ACCOUNTANT, $fresh->role);
        $this->assertSame('target.preserved@example.com', $fresh->email);
        $this->assertSame($password, $fresh->password);
        $this->assertSame(AccountStatus::ACTIVE, $fresh->status);
        $this->assertSame('MYSECRET123456', $fresh->two_factor_secret);
        $this->assertNotNull($fresh->two_factor_confirmed_at);
    }

    /**
     * RBAC-ROLE-035: Role management index page requires privileged user.
     */
    public function test_role_management_index_requires_privileged_user(): void
    {
        $salesman = User::factory()->salesman()->create();
        $admin = User::factory()->admin()->create();

        // Guest is redirected to login
        $this->get(route('roles.index'))->assertRedirect('/login');

        // Salesman is forbidden (403)
        $this->actingAs($salesman)->get(route('roles.index'))->assertForbidden();

        // Admin can view the page
        $this->actingAs($admin)->get(route('roles.index'))->assertOk();
    }
}
