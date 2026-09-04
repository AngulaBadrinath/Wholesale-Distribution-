<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\Auth\SessionRevocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private SessionRevocationService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionService = app(SessionRevocationService::class);
    }

    /**
     * AUTH-SESSION-001: Current user can logout via POST /logout.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    /**
     * AUTH-SESSION-002: Logout invalidates the active session.
     */
    public function test_logout_invalidates_current_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $this->assertGuest();
        $this->assertNull(Auth::user());
    }

    /**
     * AUTH-SESSION-003: Logout regenerates CSRF token.
     */
    public function test_logout_regenerates_csrf_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $initialToken = session()->token();

        $this->post('/logout');

        $refreshedToken = session()->token();
        $this->assertNotEquals($initialToken, $refreshedToken);
    }

    /**
     * AUTH-SESSION-004: Authenticated user can view their active sessions.
     */
    public function test_authenticated_user_can_view_active_sessions(): void
    {
        $user = User::factory()->create();

        // Insert a simulated active session for this user
        DB::table('sessions')->insert([
            'id' => 'device_session_1',
            'user_id' => $user->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0.0.0',
            'payload' => serialize(['data' => 'test']),
            'last_activity' => Carbon::now()->timestamp,
        ]);

        $response = $this->actingAs($user)->get('/security/sessions');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Security/Sessions')
            ->has('sessions')
        );
    }

    /**
     * AUTH-SESSION-005: Current session is identifiable.
     */
    public function test_current_session_is_identifiable(): void
    {
        $user = User::factory()->create();

        $currentSessionId = 'current_session_id_xyz';
        $otherSessionId = 'other_session_id_abc';

        DB::table('sessions')->insert([
            [
                'id' => $currentSessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0.0.0',
                'payload' => serialize(['key' => 'curr']),
                'last_activity' => Carbon::now()->timestamp,
            ],
            [
                'id' => $otherSessionId,
                'user_id' => $user->id,
                'ip_address' => '10.0.0.2',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1',
                'payload' => serialize(['key' => 'oth']),
                'last_activity' => Carbon::now()->subMinutes(10)->timestamp,
            ],
        ]);

        $activeSessions = $this->sessionService->getActiveSessions($user, $currentSessionId);

        $current = $activeSessions->firstWhere('is_current', true);
        $other = $activeSessions->firstWhere('is_current', false);

        $this->assertNotNull($current);
        $this->assertNotNull($other);
        $this->assertEquals($this->sessionService->generateSessionToken($currentSessionId), $current['id']);
        $this->assertEquals($this->sessionService->generateSessionToken($otherSessionId), $other['id']);
    }

    /**
     * AUTH-SESSION-006: User can revoke one of their own sessions.
     */
    public function test_user_can_revoke_single_session(): void
    {
        $user = User::factory()->create();
        $targetSessionId = 'target_revocation_session';

        DB::table('sessions')->insert([
            'id' => $targetSessionId,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.10',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'payload' => serialize(['key' => 'val']),
            'last_activity' => Carbon::now()->timestamp,
        ]);

        $token = $this->sessionService->generateSessionToken($targetSessionId);

        $response = $this->actingAs($user)->post("/security/sessions/{$token}/revoke");

        $response->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => $targetSessionId]);
    }

    /**
     * AUTH-SESSION-007: Revoked session can no longer authenticate.
     */
    public function test_revoked_session_is_deleted_from_sessions_table(): void
    {
        $user = User::factory()->create();
        $sessionId = 'device_to_be_deleted';

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.20',
            'user_agent' => 'Chrome/128',
            'payload' => serialize(['auth' => $user->id]),
            'last_activity' => Carbon::now()->timestamp,
        ]);

        $token = $this->sessionService->generateSessionToken($sessionId);
        $this->sessionService->revokeSession($user, $token);

        // Record must no longer exist in sessions table
        $this->assertNull(DB::table('sessions')->where('id', $sessionId)->first());
    }

    /**
     * AUTH-SESSION-008: User can revoke all other sessions.
     */
    public function test_user_can_revoke_all_other_sessions(): void
    {
        $user = User::factory()->create();
        $currentSessionId = 'current_session_1';
        $otherSession1 = 'other_session_1';
        $otherSession2 = 'other_session_2';

        DB::table('sessions')->insert([
            [
                'id' => $currentSessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Desktop Chrome',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
            [
                'id' => $otherSession1,
                'user_id' => $user->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Mobile Safari',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
            [
                'id' => $otherSession2,
                'user_id' => $user->id,
                'ip_address' => '10.0.0.2',
                'user_agent' => 'Tablet Firefox',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
        ]);

        $this->sessionService->revokeOtherSessions($user, $currentSessionId);

        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSession1]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSession2]);
    }

    /**
     * AUTH-SESSION-009: Current session remains active after revoke-other-sessions.
     */
    public function test_current_session_remains_active_after_revoke_others(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/security/sessions/revoke-others');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    /**
     * AUTH-SESSION-010: Unauthorized user cannot revoke another user's session (IDOR protection).
     */
    public function test_user_cannot_revoke_another_users_session(): void
    {
        $userA = User::factory()->create(['email' => 'userA@example.com']);
        $userB = User::factory()->create(['email' => 'userB@example.com']);

        $userBSessionId = 'user_b_private_session';

        DB::table('sessions')->insert([
            'id' => $userBSessionId,
            'user_id' => $userB->id,
            'ip_address' => '192.168.1.99',
            'user_agent' => 'Mac Chrome',
            'payload' => serialize([]),
            'last_activity' => Carbon::now()->timestamp,
        ]);

        $tokenB = $this->sessionService->generateSessionToken($userBSessionId);

        // User A attempts to revoke User B's session using B's token
        $response = $this->actingAs($userA)->post("/security/sessions/{$tokenB}/revoke");

        $response->assertNotFound();
        // Session must STILL exist in database untouched
        $this->assertDatabaseHas('sessions', ['id' => $userBSessionId]);
    }

    /**
     * AUTH-SESSION-011: Session-management routes require authentication.
     */
    public function test_session_management_routes_require_authentication(): void
    {
        $this->get('/security/sessions')->assertRedirect('/login');
        $this->post('/security/sessions/any-token/revoke')->assertRedirect('/login');
        $this->post('/security/sessions/revoke-others')->assertRedirect('/login');
        $this->post('/security/sessions/revoke-all')->assertRedirect('/login');
    }

    /**
     * AUTH-SESSION-012: CSRF protection and HTTP method enforcement apply to session actions.
     */
    public function test_session_mutation_routes_reject_get_method(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/security/sessions/some-token/revoke')->assertStatus(405);
        $this->actingAs($user)->get('/security/sessions/revoke-others')->assertStatus(405);
        $this->actingAs($user)->get('/security/sessions/revoke-all')->assertStatus(405);
    }

    /**
     * AUTH-SESSION-013: Suspended/disabled accounts cannot access session management.
     */
    public function test_suspended_or_disabled_account_cannot_access_session_management(): void
    {
        $suspendedUser = User::factory()->suspended()->create();
        $disabledUser = User::factory()->disabled()->create();

        $this->actingAs($suspendedUser)->get('/security/sessions')->assertRedirect('/login');
        $this->actingAs($disabledUser)->get('/security/sessions')->assertRedirect('/login');
    }

    /**
     * AUTH-SESSION-014: Concurrent/repeated revocation is idempotent or safely handled.
     */
    public function test_repeated_revocation_of_already_revoked_session_returns_not_found_safely(): void
    {
        $user = User::factory()->create();
        $nonExistentToken = 'non_existent_or_already_revoked_token';

        $response = $this->actingAs($user)->post("/security/sessions/{$nonExistentToken}/revoke");

        $response->assertNotFound();
    }

    /**
     * AUTH-SESSION-015: Security audit events are logged without secrets.
     */
    public function test_security_audit_events_are_logged_without_secrets(): void
    {
        Log::shouldReceive('info')
            ->atLeast()
            ->once()
            ->withArgs(function ($message, $context) {
                if ($message === 'session.security_event') {
                    // Context must contain standard audit fields and NO raw passwords/hashes
                    return isset($context['action'], $context['user_id'])
                        && ! isset($context['password'])
                        && ! isset($context['password_hash']);
                }
                return true;
            });

        $user = User::factory()->create();
        $sessionId = 'audit_session_check';

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Chrome',
            'payload' => serialize([]),
            'last_activity' => Carbon::now()->timestamp,
        ]);

        $token = $this->sessionService->generateSessionToken($sessionId);
        $this->sessionService->revokeSession($user, $token);
    }

    /**
     * AUTH-SESSION-016: User can revoke all sessions (including current) and is signed out.
     */
    public function test_user_can_revoke_all_sessions_and_is_signed_out(): void
    {
        $user = User::factory()->create();
        $session1 = 'session_1_all';
        $session2 = 'session_2_all';

        DB::table('sessions')->insert([
            [
                'id' => $session1,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Device 1',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
            [
                'id' => $session2,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Device 2',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
        ]);

        $response = $this->actingAs($user)->post('/security/sessions/revoke-all');

        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => $session1]);
        $this->assertDatabaseMissing('sessions', ['id' => $session2]);
    }

    /**
     * AUTH-SESSION-017: Security invalidation hook revokes target user sessions on demand.
     */
    public function test_security_invalidation_hook_revokes_sessions(): void
    {
        $user = User::factory()->create();
        $session1 = 'sec_session_1';
        $session2 = 'sec_session_2';

        DB::table('sessions')->insert([
            [
                'id' => $session1,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Device 1',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
            [
                'id' => $session2,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Device 2',
                'payload' => serialize([]),
                'last_activity' => Carbon::now()->timestamp,
            ],
        ]);

        $revokedCount = $this->sessionService->revokeUserSessionsForSecurityEvent(
            $user,
            reason: 'account_suspended'
        );

        $this->assertEquals(2, $revokedCount);
        $this->assertDatabaseMissing('sessions', ['id' => $session1]);
        $this->assertDatabaseMissing('sessions', ['id' => $session2]);
    }

    /**
     * AUTH-SESSION-018: Raw database session ID is never exposed in session records.
     */
    public function test_raw_session_id_is_not_exposed_in_session_records(): void
    {
        $user = User::factory()->create();
        $rawSessionId = 'secret_raw_database_session_id_12345';

        DB::table('sessions')->insert([
            'id' => $rawSessionId,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Windows Chrome',
            'payload' => serialize([]),
            'last_activity' => Carbon::now()->timestamp,
        ]);

        $sessions = $this->sessionService->getActiveSessions($user, 'other_session');
        $sessionRecord = $sessions->first();

        $this->assertNotNull($sessionRecord);
        $this->assertNotEquals($rawSessionId, $sessionRecord['id']);
        $this->assertEquals(
            $this->sessionService->generateSessionToken($rawSessionId),
            $sessionRecord['id']
        );
    }
}
