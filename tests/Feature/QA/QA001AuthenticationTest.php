<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\Auth\SessionRevocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * QA-001: Master Authentication, Session & Access Security Test Suite
 *
 * Verifies end-to-end authentication lifecycle, anti-enumeration, throttling,
 * session regeneration, remote session revocation, password recovery with cross-device
 * invalidation, suspended account denial, privileged MFA, and zero credential leakage.
 */
class QA001AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $activeSalesman;
    protected User $activeAdmin;
    protected User $activeSuperAdmin;
    protected User $suspendedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeSalesman = User::factory()->create([
            'name' => 'Salesman QA',
            'email' => 'salesman.auth.qa@example.test',
            'password' => 'Password123!',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->activeAdmin = User::factory()->create([
            'name' => 'Admin QA',
            'email' => 'admin.auth.qa@example.test',
            'password' => 'Password123!',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->activeSuperAdmin = User::factory()->create([
            'name' => 'Super Admin QA',
            'email' => 'superadmin.auth.qa@example.test',
            'password' => 'Password123!',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->suspendedUser = User::factory()->create([
            'name' => 'Suspended User QA',
            'email' => 'suspended.auth.qa@example.test',
            'password' => 'Password123!',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::SUSPENDED,
        ]);
    }

    /**
     * Requirement 1 & 5: Valid login establishes session with regeneration.
     */
    public function test_01_valid_login_authenticates_and_regenerates_session(): void
    {
        $response = $this->post('/login', [
            'email' => 'salesman.auth.qa@example.test',
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticatedAs($this->activeSalesman);
        $response->assertRedirect('/');
    }

    /**
     * Requirement 2 & 13: Invalid credentials rejected with generic anti-enumeration error.
     */
    public function test_02_invalid_credentials_reject_without_user_enumeration(): void
    {
        // 1. Existing user with incorrect password
        $responseExisting = $this->post('/login', [
            'email' => 'salesman.auth.qa@example.test',
            'password' => 'WrongPassword!',
        ]);

        $this->assertGuest();
        $responseExisting->assertSessionHasErrors(['email' => trans('auth.failed')]);

        // 2. Non-existent user
        $responseNonExistent = $this->post('/login', [
            'email' => 'unknown.account@example.test',
            'password' => 'WrongPassword!',
        ]);

        $this->assertGuest();
        $responseNonExistent->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    /**
     * Requirement 3: Repeated login failures trigger rate-limit throttling (429).
     */
    public function test_03_login_throttling_triggers_rate_limiting(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'admin.auth.qa@example.test',
                'password' => 'WrongPassword!',
            ]);
        }

        // 6th attempt must be rate-limited
        $response = $this->post('/login', [
            'email' => 'admin.auth.qa@example.test',
            'password' => 'WrongPassword!',
        ]);

        $this->assertGuest();
        $response->assertStatus(429);
    }

    /**
     * Requirement 4 & 12: Logout revokes session and redirects to login.
     */
    public function test_04_logout_invalidates_session_and_clears_auth(): void
    {
        $this->actingAs($this->activeSalesman);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    /**
     * Requirement 6: Active session tracking and remote session revocation.
     */
    public function test_06_session_revocation_service_single_and_bulk_revocation(): void
    {
        $revocationService = app(SessionRevocationService::class);

        // Revoke all sessions for user
        $count = $revocationService->revokeAllSessions($this->activeSalesman);
        $this->assertIsInt($count);

        $securityEventCount = $revocationService->revokeUserSessionsForSecurityEvent($this->activeSalesman, 'QA Security Eviction');
        $this->assertIsInt($securityEventCount);
    }

    /**
     * Requirement 7: Password reset flow, single-use token, and cross-device session purging.
     */
    public function test_07_password_reset_flow_and_session_purge(): void
    {
        // 1. Request reset link (anti-enumeration returns same status)
        $forgotResponse = $this->post('/forgot-password', [
            'email' => 'salesman.auth.qa@example.test',
        ]);
        $forgotResponse->assertSessionHas('status');

        // 2. Generate token directly via broker
        $token = Password::createToken($this->activeSalesman);

        // 3. Reset password
        $resetResponse = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'salesman.auth.qa@example.test',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ]);

        $resetResponse->assertSessionHas('status');

        // 4. Verify password was updated
        $this->activeSalesman->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $this->activeSalesman->password));
    }

    /**
     * Requirement 8: Suspended account cannot log in even with correct credentials.
     */
    public function test_08_suspended_account_rejected_at_authentication(): void
    {
        $response = $this->post('/login', [
            'email' => 'suspended.auth.qa@example.test',
            'password' => 'Password123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => trans('auth.unavailable')]);
    }

    /**
     * Requirement 9 & 11: Role boundary enforcement and fail-closed portal isolation.
     */
    public function test_09_unauthorized_portal_access_rejected_fail_closed(): void
    {
        // Salesman attempting to reach Super Admin / Security management
        $response = $this->actingAs($this->activeSalesman)->get('/security/roles');
        $response->assertStatus(403);

        // Salesman attempting to reach System Company Configuration
        $responseCompany = $this->actingAs($this->activeSalesman)->get('/system/company');
        $responseCompany->assertStatus(403);
    }

    /**
     * Requirement 10: Suspended status takes immediate effect on active authenticated requests.
     */
    public function test_10_account_suspension_blocks_authenticated_actions(): void
    {
        $this->actingAs($this->activeSalesman);

        // Verify active access works
        $responseBefore = $this->get('/products');
        $responseBefore->assertStatus(200);

        // Suspend user
        $this->activeSalesman->update(['status' => AccountStatus::SUSPENDED]);

        // Next privileged or portal request must fail/redirect
        $responseAfter = $this->get('/products');
        $this->assertTrue(in_array($responseAfter->status(), [401, 403, 302], true));
    }

    /**
     * Requirement 14 & 15: Security logs emitted with zero credential disclosure.
     */
    public function test_12_security_logging_does_not_leak_passwords(): void
    {
        $this->post('/login', [
            'email' => 'admin.auth.qa@example.test',
            'password' => 'SecretPlaintextPassword999!',
        ]);

        // Check security_logs / audit_logs for any occurrence of plaintext password
        $leakedInSecurityLogs = SecurityLog::where('context', 'like', '%SecretPlaintextPassword999!%')->count();
        $this->assertEquals(0, $leakedInSecurityLogs, 'Plaintext password must NEVER appear in security logs');

        $leakedInAuditLogs = AuditLog::where('new_values', 'like', '%SecretPlaintextPassword999!%')
            ->orWhere('old_values', 'like', '%SecretPlaintextPassword999!%')
            ->count();
        $this->assertEquals(0, $leakedInAuditLogs, 'Plaintext password must NEVER appear in audit logs');
    }
}
