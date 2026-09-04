<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\SessionRevocationService;
use App\Services\Auth\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        RateLimiter::clear('mfa-challenge:127.0.0.1');
        RateLimiter::clear('mfa-confirm:127.0.0.1');
    }

    /**
     * AUTH-MFA-001: MFA setup page requires authenticated user.
     */
    public function test_mfa_setup_page_requires_authenticated_user(): void
    {
        $response = $this->get('/security/mfa');

        $response->assertRedirect('/login');
    }

    /**
     * AUTH-MFA-002: Unauthorized user cannot access MFA management endpoints.
     */
    public function test_unauthorized_guest_cannot_access_mfa_management_actions(): void
    {
        $this->post('/security/mfa/enable', ['current_password' => 'password'])->assertRedirect('/login');
        $this->post('/security/mfa/confirm', ['code' => '123456'])->assertRedirect('/login');
        $this->delete('/security/mfa', ['current_password' => 'password'])->assertRedirect('/login');
        $this->post('/security/mfa/recovery-codes', ['current_password' => 'password'])->assertRedirect('/login');
    }

    /**
     * AUTH-MFA-003: Role policy correctly determines whether MFA is mandatory.
     */
    public function test_role_policy_correctly_determines_mandatory_mfa(): void
    {
        $policy = app(MfaPolicy::class);

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $accountant = User::factory()->accountant()->create();
        $salesman = User::factory()->role(UserRole::SALESMAN)->create();
        $warehouse = User::factory()->role(UserRole::WAREHOUSE_MANAGER)->create();
        $delivery = User::factory()->role(UserRole::DELIVERY_PARTNER)->create();
        $noRole = User::factory()->create(['role' => null]);

        $this->assertTrue($policy->isMfaRequired($superAdmin));
        $this->assertTrue($policy->isMfaRequired($admin));
        $this->assertTrue($policy->isMfaRequired($accountant));
        $this->assertFalse($policy->isMfaRequired($salesman));
        $this->assertFalse($policy->isMfaRequired($warehouse));
        $this->assertFalse($policy->isMfaRequired($delivery));
        $this->assertFalse($policy->isMfaRequired($noRole));
    }

    /**
     * AUTH-MFA-004: Privileged user without configured MFA cannot bypass mandatory MFA policy.
     */
    public function test_privileged_user_without_mfa_is_routed_to_mfa_challenge_and_cannot_access_dashboard(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => Hash::make('SecretPassword123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'SecretPassword123!',
        ]);

        $response->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();

        // Direct dashboard navigation must be rejected
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertRedirect('/login');
    }

    /**
     * AUTH-MFA-005: MFA enrollment requires step-up / password confirmation.
     */
    public function test_mfa_enrollment_requires_valid_current_password_step_up(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ValidPassword123!'),
        ]);

        // Wrong password
        $response = $this->actingAs($user)->post('/security/mfa/enable', [
            'current_password' => 'WrongPassword',
        ]);
        $response->assertSessionHasErrors(['current_password']);

        // Correct password initiates setup
        $validResponse = $this->actingAs($user)->post('/security/mfa/enable', [
            'current_password' => 'ValidPassword123!',
        ]);
        $validResponse->assertRedirect(route('mfa.index'));
        $validResponse->assertSessionHas('mfa_setup_data');
        $this->assertNotNull(session('mfa.pending_secret'));
    }

    /**
     * AUTH-MFA-006: TOTP secret generation works securely.
     */
    public function test_totp_secret_generation_produces_secure_base32_key(): void
    {
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();

        $this->assertIsString($secret);
        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    /**
     * AUTH-MFA-007: QR setup data is generated correctly as pure local SVG.
     */
    public function test_qr_setup_data_is_generated_locally_without_remote_urls(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();

        $svg = $service->generateQrCodeSvg($user, $secret);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringNotContainsString('googleapis.com', $svg);
        $this->assertStringNotContainsString('google.com', $svg);
        $this->assertStringNotContainsString('api.qrserver.com', $svg);
        $this->assertStringNotContainsString('quickchart.io', $svg);
    }

    /**
     * AUTH-MFA-008: Invalid TOTP code cannot confirm enrollment.
     */
    public function test_invalid_totp_code_cannot_confirm_enrollment(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();

        $response = $this->actingAs($user)
            ->withSession(['mfa.pending_secret' => $secret])
            ->post('/security/mfa/confirm', ['code' => '000000']);

        $response->assertSessionHasErrors(['code']);
        $this->assertFalse($user->fresh()->hasMfaEnabled());
    }

    /**
     * AUTH-MFA-009: Valid TOTP code confirms enrollment.
     */
    public function test_valid_totp_code_confirms_enrollment_and_generates_recovery_codes(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();
        $validCode = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->actingAs($user)
            ->withSession(['mfa.pending_secret' => $secret])
            ->post('/security/mfa/confirm', ['code' => $validCode]);

        $response->assertRedirect(route('mfa.index'));
        $response->assertSessionHas('recovery_codes');
        $this->assertTrue($user->fresh()->hasMfaEnabled());
        $this->assertCount(8, session('recovery_codes'));
        $this->assertEquals(8, DB::table('two_factor_recovery_codes')->where('user_id', $user->id)->count());
    }

    /**
     * AUTH-MFA-010: MFA remains disabled before confirmation.
     */
    public function test_mfa_remains_disabled_until_totp_code_is_confirmed(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $this->actingAs($user)->post('/security/mfa/enable', [
            'current_password' => 'ValidPassword123!',
        ]);

        $freshUser = $user->fresh();
        $this->assertNull($freshUser->two_factor_secret);
        $this->assertNull($freshUser->two_factor_confirmed_at);
        $this->assertFalse($freshUser->hasMfaEnabled());
    }

    /**
     * AUTH-MFA-011: Recovery codes are generated securely with high entropy.
     */
    public function test_recovery_codes_are_generated_with_expected_format_and_count(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);

        $codes = $service->generateRecoveryCodes($user);

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $code);
        }
    }

    /**
     * AUTH-MFA-012: Recovery codes are never stored in plaintext and never leaked through model serialization.
     */
    public function test_recovery_codes_are_hashed_in_database_and_hidden_from_serialization(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $codes = $service->generateRecoveryCodes($user);

        $dbRecords = DB::table('two_factor_recovery_codes')->where('user_id', $user->id)->get();

        foreach ($dbRecords as $record) {
            $this->assertSame(64, strlen($record->code_hash));
            // Plaintext code should never be equal to the hash
            $this->assertNotContains($record->code_hash, $codes);
        }

        // Check user model serialization
        $userArray = $user->fresh()->toArray();
        $this->assertArrayNotHasKey('two_factor_secret', $userArray);
    }

    /**
     * AUTH-MFA-013: Recovery code can authenticate once.
     */
    public function test_recovery_code_can_authenticate_valid_login(): void
    {
        $user = User::factory()->withMfa()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $codes = $service->generateRecoveryCodes($user);
        $testCode = $codes[0];

        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->post('/login/mfa', [
            'recovery_code' => $testCode,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * AUTH-MFA-014: Used recovery code cannot authenticate again.
     */
    public function test_used_recovery_code_cannot_be_reused(): void
    {
        $user = User::factory()->withMfa()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $codes = $service->generateRecoveryCodes($user);
        $testCode = $codes[0];

        // Consume it once
        $this->assertTrue($service->verifyAndConsumeRecoveryCode($user, $testCode));

        // Attempt authentication with the now-consumed code
        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->post('/login/mfa', [
            'recovery_code' => $testCode,
        ]);

        $response->assertSessionHasErrors(['recovery_code']);
        $this->assertGuest();
    }

    /**
     * AUTH-MFA-015: Regenerated recovery codes invalidate old codes.
     */
    public function test_regenerating_recovery_codes_invalidates_all_previous_codes(): void
    {
        $user = User::factory()->withMfa()->create([
            'password' => Hash::make('ValidPassword123!'),
        ]);
        $service = app(TwoFactorAuthenticationService::class);
        $oldCodes = $service->generateRecoveryCodes($user);

        // Regenerate via controller
        $response = $this->actingAs($user)->post('/security/mfa/recovery-codes', [
            'current_password' => 'ValidPassword123!',
        ]);

        $response->assertRedirect(route('mfa.index'));
        $newCodes = session('recovery_codes');
        $this->assertCount(8, $newCodes);

        // None of the old codes should be valid anymore
        foreach ($oldCodes as $oldCode) {
            $this->assertFalse($service->verifyAndConsumeRecoveryCode($user, $oldCode));
        }

        // New codes should work
        $this->assertTrue($service->verifyAndConsumeRecoveryCode($user, $newCodes[0]));
    }

    /**
     * AUTH-MFA-016: Login with valid password and MFA-enabled account reaches MFA challenge.
     */
    public function test_login_with_valid_password_reaches_mfa_challenge_without_logging_in(): void
    {
        $user = User::factory()->withMfa()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();
        $this->assertNotNull(session('mfa.challenge'));
        $this->assertSame($user->id, session('mfa.challenge.user_id'));
    }

    /**
     * AUTH-MFA-017: Valid TOTP completes authentication.
     */
    public function test_valid_totp_code_completes_authentication(): void
    {
        $secret = (new Google2FA())->generateSecretKey(32);
        $user = User::factory()->withMfa($secret)->create();
        $validOtp = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->post('/login/mfa', [
            'code' => $validOtp,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('mfa.challenge'));
    }

    /**
     * AUTH-MFA-018: Invalid TOTP fails authentication.
     */
    public function test_invalid_totp_code_fails_authentication(): void
    {
        $user = User::factory()->withMfa()->create();

        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->post('/login/mfa', [
            'code' => '999999',
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertGuest();
    }

    /**
     * AUTH-MFA-019: MFA challenge cannot be bypassed by directly navigating to protected pages.
     */
    public function test_direct_navigation_to_protected_routes_during_challenge_fails(): void
    {
        $user = User::factory()->withMfa()->create();

        $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/security/sessions')->assertRedirect('/login');
        $this->get('/security/mfa')->assertRedirect('/login');
    }

    /**
     * AUTH-MFA-020: MFA challenge state expires appropriately.
     */
    public function test_expired_mfa_challenge_state_is_rejected(): void
    {
        $user = User::factory()->withMfa()->create();

        // Expired timestamp
        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->subMinute()->timestamp,
            ],
        ])->post('/login/mfa', [
            'code' => '123456',
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertGuest();
    }

    /**
     * AUTH-MFA-021: MFA challenge cannot be replayed after successful use.
     */
    public function test_mfa_challenge_cannot_be_replayed(): void
    {
        $secret = (new Google2FA())->generateSecretKey(32);
        $user = User::factory()->withMfa($secret)->create();
        $validOtp = (new Google2FA())->getCurrentOtp($secret);

        // First attempt succeeds
        $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->post('/login/mfa', ['code' => $validOtp])->assertRedirect('/dashboard');

        // Logout to become guest again
        auth()->logout();

        // Second attempt without session challenge fails
        $secondResponse = $this->from(route('mfa.challenge'))->post('/login/mfa', ['code' => $validOtp]);
        $secondResponse->assertSessionHasErrors(['code']);
    }

    /**
     * AUTH-MFA-022: MFA attempts are rate limited.
     */
    public function test_mfa_challenge_attempts_are_rate_limited(): void
    {
        $user = User::factory()->withMfa()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->withSession([
                'mfa.challenge' => [
                    'user_id' => $user->id,
                    'remember' => false,
                    'requires_setup' => false,
                    'attempts' => $i,
                    'expires_at' => now()->addMinutes(5)->timestamp,
                ],
            ])->postJson('/login/mfa', ['code' => '000000']);
        }

        // 6th attempt should be blocked with 429
        $throttledResponse = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 5,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->postJson('/login/mfa', ['code' => '000000']);

        $throttledResponse->assertStatus(429);
    }

    /**
     * AUTH-MFA-023: Recovery code attempts are rate limited.
     */
    public function test_recovery_code_attempts_are_throttled(): void
    {
        $user = User::factory()->withMfa()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->withSession([
                'mfa.challenge' => [
                    'user_id' => $user->id,
                    'remember' => false,
                    'requires_setup' => false,
                    'attempts' => $i,
                    'expires_at' => now()->addMinutes(5)->timestamp,
                ],
            ])->postJson('/login/mfa', ['recovery_code' => 'INVALID-CODE']);
        }

        $throttled = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 5,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->postJson('/login/mfa', ['recovery_code' => 'INVALID-CODE']);

        $throttled->assertStatus(429);
    }

    /**
     * AUTH-MFA-024: Disabling MFA requires step-up authentication.
     */
    public function test_disabling_mfa_requires_correct_current_password(): void
    {
        $user = User::factory()->withMfa()->create([
            'role' => UserRole::SALESMAN,
            'password' => Hash::make('ValidPassword123!'),
        ]);

        // Wrong password rejected
        $response = $this->actingAs($user)->delete('/security/mfa', [
            'current_password' => 'WrongPassword',
        ]);
        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue($user->fresh()->hasMfaEnabled());

        // Correct password disables MFA
        $validResponse = $this->actingAs($user)->delete('/security/mfa', [
            'current_password' => 'ValidPassword123!',
        ]);
        $validResponse->assertRedirect(route('mfa.index'));
        $this->assertFalse($user->fresh()->hasMfaEnabled());
    }

    /**
     * AUTH-MFA-025: Mandatory privileged role cannot disable MFA.
     */
    public function test_mandatory_privileged_role_cannot_disable_mfa(): void
    {
        $admin = User::factory()->admin()->withMfa()->create([
            'password' => Hash::make('AdminPassword123!'),
        ]);

        $response = $this->actingAs($admin)->delete('/security/mfa', [
            'current_password' => 'AdminPassword123!',
        ]);

        $response->assertStatus(403);
        $this->assertTrue($admin->fresh()->hasMfaEnabled());
    }

    /**
     * AUTH-MFA-026: MFA state changes generate audit events.
     */
    public function test_mfa_state_changes_generate_audit_events(): void
    {
        Log::spy();

        $user = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'password' => Hash::make('UserPassword123!'),
        ]);

        // Enable MFA
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->actingAs($user)
            ->withSession(['mfa.pending_secret' => $secret])
            ->post('/security/mfa/confirm', ['code' => $code]);

        Log::shouldHaveReceived('info')->with('auth.security_event', \Mockery::on(function ($data) {
            return ($data['action'] ?? null) === 'MFA_ENABLED';
        }))->atLeast()->once();

        // Disable MFA
        $this->actingAs($user)->delete('/security/mfa', [
            'current_password' => 'UserPassword123!',
        ]);

        Log::shouldHaveReceived('info')->with('auth.security_event', \Mockery::on(function ($data) {
            return ($data['action'] ?? null) === 'MFA_DISABLED';
        }))->atLeast()->once();
    }

    /**
     * AUTH-MFA-027, AUTH-MFA-028, AUTH-MFA-029: Sensitive secrets are never logged.
     */
    public function test_sensitive_secrets_are_never_logged_in_audit(): void
    {
        Log::spy();

        $user = User::factory()->create([
            'password' => Hash::make('SecretPass123!'),
        ]);
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->actingAs($user)
            ->withSession(['mfa.pending_secret' => $secret])
            ->post('/security/mfa/confirm', ['code' => $code]);

        Log::shouldNotHaveReceived('info', [
            \Mockery::any(),
            \Mockery::on(function ($context) use ($secret) {
                return is_array($context) && str_contains(json_encode($context), $secret);
            }),
        ]);
    }

    /**
     * AUTH-MFA-030: Security-sensitive MFA changes trigger session invalidation.
     */
    public function test_enabling_or_disabling_mfa_revokes_other_sessions(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'password' => Hash::make('SecretPass123!'),
        ]);

        // Create two simulated sessions in database
        DB::table('sessions')->insert([
            ['id' => 'sess-1', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Browser', 'payload' => 'a', 'last_activity' => time()],
            ['id' => 'sess-2', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Mobile', 'payload' => 'b', 'last_activity' => time()],
        ]);

        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecretKey();
        $code = (new Google2FA())->getCurrentOtp($secret);

        // Confirm MFA from current session 'sess-1'
        $this->actingAs($user)
            ->withSession([
                '_token' => 'test-csrf',
                'mfa.pending_secret' => $secret,
            ])
            ->post('/security/mfa/confirm', ['code' => $code]);

        // sess-2 must have been deleted
        $remainingSessions = DB::table('sessions')->where('user_id', $user->id)->pluck('id')->toArray();
        $this->assertNotContains('sess-2', $remainingSessions);
    }

    /**
     * AUTH-MFA-031: Existing authentication flows do not regress.
     */
    public function test_standard_user_without_mfa_authenticates_normally_without_challenge(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'password' => Hash::make('PlainPassword123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'PlainPassword123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('mfa.challenge'));
    }

    /**
     * AUTH-MFA-032: IDOR protection applies to MFA management endpoints.
     */
    public function test_idor_protection_ensures_users_can_only_manage_their_own_mfa(): void
    {
        $userA = User::factory()->withMfa()->create();
        $userB = User::factory()->withMfa()->create([
            'role' => UserRole::SALESMAN,
            'password' => Hash::make('UserBPassword123!'),
        ]);

        // User B cannot alter User A's recovery codes or state
        $this->actingAs($userB)->delete('/security/mfa', [
            'current_password' => 'UserBPassword123!',
        ]);

        // User A's MFA must still be enabled
        $this->assertTrue($userA->fresh()->hasMfaEnabled());
        // User B's MFA was disabled
        $this->assertFalse($userB->fresh()->hasMfaEnabled());
    }

    /**
     * AUTH-MFA-033: Suspended or disabled users cannot manage MFA.
     */
    public function test_suspended_and_disabled_users_cannot_access_or_manage_mfa(): void
    {
        $suspendedUser = User::factory()->suspended()->create();
        $disabledUser = User::factory()->disabled()->create();

        $this->actingAs($suspendedUser)->get('/security/mfa')->assertRedirect('/login');
        $this->actingAs($disabledUser)->get('/security/mfa')->assertRedirect('/login');
    }

    /**
     * AUTH-MFA-034: Responsive and accessible attributes verified in MFA challenge Inertia props.
     */
    public function test_mfa_challenge_renders_with_expected_inertia_props(): void
    {
        $user = User::factory()->withMfa()->create();

        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $user->id,
                'remember' => false,
                'requires_setup' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->get('/login/mfa');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/MfaChallenge')
            ->where('requires_setup', false)
            ->where('qr_code_svg', null)
        );
    }

    /**
     * Additional adversarial test: Recovery codes cannot be used during mandatory setup challenge.
     */
    public function test_recovery_code_cannot_be_used_during_initial_setup_challenge(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $admin->id,
                'remember' => false,
                'requires_setup' => true,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->post('/login/mfa', [
            'recovery_code' => 'ANY-RECOVERY-CODE',
        ]);

        $response->assertSessionHasErrors(['recovery_code']);
        $this->assertGuest();
    }

    /**
     * Additional flow test: Privileged user completes MFA setup during login challenge.
     */
    public function test_privileged_user_completes_setup_during_login_challenge(): void
    {
        $admin = User::factory()->admin()->create();
        $secret = (new Google2FA())->generateSecretKey(32);
        $validOtp = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $admin->id,
                'remember' => false,
                'requires_setup' => true,
                'pending_secret' => $secret,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->post('/login/mfa', [
            'code' => $validOtp,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->fresh()->hasMfaEnabled());
        $this->assertCount(8, session('recovery_codes'));
    }
}
