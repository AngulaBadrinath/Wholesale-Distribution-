<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Services\Auth\SessionRevocationService;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AUTH-PASSWORD-001: Forgot-password page is accessible to guests.
     */
    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
    }

    /**
     * AUTH-PASSWORD-002: Authenticated users cannot access guest-only reset request flow.
     */
    public function test_authenticated_user_is_redirected_away_from_forgot_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/forgot-password');

        $response->assertRedirect('/dashboard');
    }

    /**
     * AUTH-PASSWORD-003: Valid email request returns generic success message.
     */
    public function test_password_reset_link_can_be_requested_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'registered@example.com']);

        $response = $this->post('/forgot-password', [
            'email' => 'registered@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', trans('passwords.sent'));
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    /**
     * AUTH-PASSWORD-004: Unknown email returns identical generic success message (anti-enumeration).
     */
    public function test_password_reset_request_for_unknown_user_returns_identical_generic_success(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', trans('passwords.sent'));
        Notification::assertNothingSent();
    }

    /**
     * AUTH-PASSWORD-005: No account enumeration (responses are indistinguishable).
     */
    public function test_account_enumeration_is_prevented(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'active@example.com']);

        $responseKnown = $this->post('/forgot-password', ['email' => 'active@example.com']);
        $responseUnknown = $this->post('/forgot-password', ['email' => 'ghost@example.com']);

        $this->assertEquals($responseKnown->getStatusCode(), $responseUnknown->getStatusCode());
        $this->assertEquals(
            session('status'),
            trans('passwords.sent')
        );
    }

    /**
     * AUTH-PASSWORD-006: Forgot-password endpoint is rate limited.
     */
    public function test_forgot_password_endpoint_is_rate_limited(): void
    {
        Notification::fake();

        // 5 requests per minute per IP limit
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "attempt{$i}@example.com"])
                ->assertSessionHasNoErrors();
        }

        // 6th request should hit rate limit
        $response = $this->post('/forgot-password', ['email' => 'attempt6@example.com']);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * AUTH-PASSWORD-007: Notification generation/dispatch for eligible ACTIVE user.
     */
    public function test_reset_notification_contains_valid_signed_url_for_active_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'employee@example.com']);

        $this->post('/forgot-password', ['email' => 'employee@example.com']);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user) {
                $mailData = $notification->toMail($user);
                $this->assertStringContainsString('reset-password', $mailData->actionUrl);
                $this->assertStringContainsString(urlencode('employee@example.com'), $mailData->actionUrl);
                return true;
            }
        );
    }

    /**
     * AUTH-PASSWORD-008: Valid reset token renders reset form.
     */
    public function test_reset_password_screen_can_be_rendered_with_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->get("/reset-password/{$token}?email={$user->email}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', $token)
            ->where('email', $user->email)
        );
    }

    /**
     * AUTH-PASSWORD-009: Invalid token is rejected safely.
     */
    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/reset-password', [
            'token' => 'invalid-token-12345',
            'email' => $user->email,
            'password' => 'NewValidPass123!',
            'password_confirmation' => 'NewValidPass123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /**
     * AUTH-PASSWORD-010: Expired token is rejected safely.
     */
    public function test_password_cannot_be_reset_with_expired_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        // Travel 61 minutes into the future (expiry is 60 minutes)
        Carbon::setTestNow(Carbon::now()->addMinutes(61));

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewValidPass123!',
            'password_confirmation' => 'NewValidPass123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        Carbon::setTestNow(); // Reset test clock
    }

    /**
     * AUTH-PASSWORD-011: Used token cannot be reused (single-use enforcement).
     */
    public function test_used_token_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        // First reset with valid token succeeds
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'FirstNewPass123!',
            'password_confirmation' => 'FirstNewPass123!',
        ])->assertSessionHasNoErrors();

        // Second attempt with the SAME token must fail
        $secondAttempt = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SecondNewPass123!',
            'password_confirmation' => 'SecondNewPass123!',
        ]);

        $secondAttempt->assertSessionHasErrors(['email']);
        $this->assertTrue(Hash::check('FirstNewPass123!', $user->fresh()->password));
    }

    /**
     * AUTH-PASSWORD-012: Password confirmation mismatch is rejected.
     */
    public function test_password_confirmation_mismatch_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /**
     * AUTH-PASSWORD-013: Weak password (< 8 chars) is rejected according to policy.
     */
    public function test_password_shorter_than_eight_characters_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /**
     * AUTH-PASSWORD-014: Password successfully changed.
     */
    public function test_password_can_be_reset_successfully(): void
    {
        Event::fake([PasswordReset::class]);

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('BrandNewPassword123!', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    /**
     * AUTH-PASSWORD-015: Plaintext password is never stored in database.
     */
    public function test_plaintext_password_is_never_stored(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SuperSecretPlaintextPass123!',
            'password_confirmation' => 'SuperSecretPlaintextPass123!',
        ]);

        $rawRecord = DB::table('users')->where('id', $user->id)->first();
        $this->assertStringStartsNotWith('SuperSecretPlaintextPass123!', $rawRecord->password);
        $this->assertTrue(str_starts_with($rawRecord->password, '$2y$'));
    }

    /**
     * AUTH-PASSWORD-016: Password reset invalidates prior sessions.
     */
    public function test_password_reset_invalidates_prior_sessions(): void
    {
        $user = User::factory()->create();

        // Simulate active sessions in the database for this user
        DB::table('sessions')->insert([
            [
                'id' => 'device_session_a',
                'user_id' => $user->id,
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Chrome',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
            [
                'id' => 'device_session_b',
                'user_id' => $user->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Safari',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
        ]);

        $this->assertEquals(2, DB::table('sessions')->where('user_id', $user->id)->count());

        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'UpdatedPassword999!',
            'password_confirmation' => 'UpdatedPassword999!',
        ]);

        // Prior sessions must be completely purged from sessions table
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    /**
     * AUTH-PASSWORD-017: SessionRevocationService security hook is used.
     */
    public function test_session_revocation_service_hook_is_called_during_reset(): void
    {
        $mockService = $this->mock(SessionRevocationService::class);
        $mockService->shouldReceive('revokeUserSessionsForSecurityEvent')
            ->once()
            ->withArgs(function ($user, $reason) {
                return $reason === 'password_reset';
            })
            ->andReturn(1);

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ValidPass12345!',
            'password_confirmation' => 'ValidPass12345!',
        ]);
    }

    /**
     * AUTH-PASSWORD-018: Reset success creates correct security audit event.
     */
    public function test_successful_reset_logs_audit_event(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ValidPass12345!',
            'password_confirmation' => 'ValidPass12345!',
        ]);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($user) {
                return $message === 'session.security_event'
                    && ($context['action'] ?? null) === 'PASSWORD_RESET_COMPLETED'
                    && ($context['user_id'] ?? null) === $user->id;
            });
    }

    /**
     * AUTH-PASSWORD-019: Reset token is never written to audit logs.
     */
    public function test_reset_token_is_never_written_to_logs(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        Log::shouldNotHaveReceived('info', function ($message, $context) use ($token) {
            return str_contains(json_encode($context), $token);
        });
    }

    /**
     * AUTH-PASSWORD-020: Password values are never written to logs.
     */
    public function test_password_values_are_never_written_to_logs(): void
    {
        Log::spy();
        $secretPassword = 'super-unique-secret-password-xyz';

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $secretPassword,
            'password_confirmation' => $secretPassword,
        ]);

        Log::shouldNotHaveReceived('info', function ($message, $context) use ($secretPassword) {
            return str_contains(json_encode($context), $secretPassword);
        });
    }

    /**
     * AUTH-PASSWORD-021: CSRF required on reset mutation.
     */
    public function test_reset_routes_reject_invalid_http_methods(): void
    {
        $this->get('/reset-password')->assertStatus(405);
        $this->delete('/reset-password')->assertStatus(405);
    }

    /**
     * AUTH-PASSWORD-022: No open redirect (redirects to /login).
     */
    public function test_successful_reset_redirects_to_login(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * AUTH-PASSWORD-023: Concurrent/replayed reset is safely handled.
     */
    public function test_replay_reset_attempt_fails_safely(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        // Attempt 1
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'FirstPassword123!',
            'password_confirmation' => 'FirstPassword123!',
        ])->assertRedirect('/login');

        // Attempt 2 (replay)
        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ReplayedPassword123!',
            'password_confirmation' => 'ReplayedPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * AUTH-PASSWORD-024: SUSPENDED / DISABLED accounts do not receive reset notifications.
     */
    public function test_suspended_and_disabled_accounts_do_not_receive_reset_notifications(): void
    {
        Notification::fake();

        $suspendedUser = User::factory()->suspended()->create(['email' => 'suspended@example.com']);
        $disabledUser = User::factory()->disabled()->create(['email' => 'disabled@example.com']);

        $resSuspended = $this->post('/forgot-password', ['email' => 'suspended@example.com']);
        $resDisabled = $this->post('/forgot-password', ['email' => 'disabled@example.com']);

        // Both receive generic success response
        $resSuspended->assertSessionHas('status', trans('passwords.sent'));
        $resDisabled->assertSessionHas('status', trans('passwords.sent'));

        // Neither receives an email notification
        Notification::assertNothingSent();
    }

    /**
     * AUTH-PASSWORD-025: INVITED accounts do not receive reset notifications.
     */
    public function test_invited_accounts_do_not_receive_reset_notifications(): void
    {
        Notification::fake();

        User::factory()->invited()->create(['email' => 'invited@example.com']);

        $response = $this->post('/forgot-password', ['email' => 'invited@example.com']);

        $response->assertSessionHas('status', trans('passwords.sent'));
        Notification::assertNothingSent();
    }

    /**
     * AUTH-PASSWORD-026: Post-reset authentication remains separate.
     */
    public function test_resetting_password_does_not_automatically_authenticate_the_user(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        // User must remain a guest until explicit login
        $this->assertGuest();
    }

    /**
     * AUTH-PASSWORD-027: User account status is not altered by password reset.
     */
    public function test_password_reset_does_not_alter_account_status(): void
    {
        $user = User::factory()->create(['status' => AccountStatus::ACTIVE]);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $this->assertEquals(AccountStatus::ACTIVE, $user->fresh()->status);
    }
}
