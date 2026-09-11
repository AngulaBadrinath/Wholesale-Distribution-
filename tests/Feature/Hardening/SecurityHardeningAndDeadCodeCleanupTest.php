<?php

namespace Tests\Feature\Hardening;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\System\ApplicationIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningAndDeadCodeCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clearResolvedInstances();
    }

    /**
     * SEC-001: Route-level permission defense-in-depth rejects unauthorized roles with 403.
     */
    public function test_sec_001_unauthorized_roles_receive_403_on_credit_and_refund_routes(): void
    {
        $salesman = User::factory()->create(['role' => UserRole::SALESMAN, 'status' => AccountStatus::ACTIVE]);
        $driver = User::factory()->create(['role' => UserRole::DELIVERY_PARTNER, 'status' => AccountStatus::ACTIVE]);
        $warehouse = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER, 'status' => AccountStatus::ACTIVE]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'status' => AccountStatus::ACTIVE]);

        $unauthorizedUsers = [$salesman, $driver, $warehouse];

        foreach ($unauthorizedUsers as $user) {
            $this->actingAs($user)->get('/admin/credits')->assertStatus(403);
            $this->actingAs($user)->get('/admin/credits/1')->assertStatus(403);
            $this->actingAs($user)->get('/admin/returns/1/credit-eligibility')->assertStatus(403);
            $this->actingAs($user)->get('/admin/refunds')->assertStatus(403);
            $this->actingAs($user)->get('/admin/refunds/1')->assertStatus(403);
        }

        // Admin retains authorized access
        $this->actingAs($admin)->get('/admin/credits')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/refunds')->assertStatus(200);
    }

    /**
     * SEC-002: Category-specific rate limiters enforce configured thresholds.
     */
    public function test_sec_002_login_rate_limiter_blocks_after_5_attempts(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('ValidPassword123!'),
            'status' => AccountStatus::ACTIVE,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'WrongPassword!',
            ]);
        }

        // 6th attempt must receive 429
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(429);
    }

    /**
     * SEC-002: Password reset rate limiter limits to 3 requests per 10 minutes.
     */
    public function test_sec_002_password_reset_rate_limiter_blocks_excessive_requests(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', [
                'email' => 'victim@example.com',
            ]);
        }

        // 4th attempt must be rate-limited with 429
        $response = $this->post('/forgot-password', [
            'email' => 'victim@example.com',
        ]);

        $response->assertStatus(429);
    }

    /**
     * SEC-003: MFA challenge throttle key is scoped to user session + IP.
     */
    public function test_sec_003_mfa_throttling_is_scoped_by_user_session_and_ip(): void
    {
        $userA = User::factory()->create([
            'status' => AccountStatus::ACTIVE,
            'two_factor_secret' => 'SECRET_A',
            'two_factor_confirmed_at' => now(),
        ]);

        $userB = User::factory()->create([
            'status' => AccountStatus::ACTIVE,
            'two_factor_secret' => 'SECRET_B',
            'two_factor_confirmed_at' => now(),
        ]);

        // Exhaust User A's attempts with same IP
        for ($i = 0; $i < 5; $i++) {
            $this->withSession([
                'mfa.challenge' => [
                    'user_id' => $userA->id,
                    'attempts' => $i,
                    'expires_at' => now()->addMinutes(5)->timestamp,
                ],
            ])->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
                ->post('/login/mfa', ['code' => '000000']);
        }

        // User A must be rate-limited
        $respA = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $userA->id,
                'attempts' => 5,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->post('/login/mfa', ['code' => '000000']);

        $respA->assertStatus(429);

        // User B from the SAME IP must NOT be locked out by User A's failures
        $respB = $this->withSession([
            'mfa.challenge' => [
                'user_id' => $userB->id,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ])->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->post('/login/mfa', ['code' => '000000']);

        // User B should get 422/invalid code, NOT 429 rate limit
        $this->assertNotEquals(429, $respB->getStatusCode());
    }

    /**
     * SEC-004: HTTP security headers and tailored Content-Security-Policy are present.
     */
    public function test_sec_004_security_headers_middleware_attaches_headers(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
        $this->assertStringContainsString('https://*.amazonaws.com', $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    /**
     * DEAD-001: Obsolete /foundation scaffold route is completely removed.
     */
    public function test_dead_001_foundation_route_returns_404(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'status' => AccountStatus::ACTIVE]);

        $this->actingAs($admin)->get('/foundation')->assertStatus(404);
    }

    /**
     * DEAD-002: Legacy create routes redirect 301 to canonical create routes.
     */
    public function test_dead_002_legacy_create_routes_redirect_to_canonical(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'status' => AccountStatus::ACTIVE]);

        $this->actingAs($admin)->get('/customers-create')->assertRedirect('/customers/create');
        $this->actingAs($admin)->get('/salesmen-create')->assertRedirect('/salesmen/create');
        $this->actingAs($admin)->get('/products-create')->assertRedirect('/products/create');
        $this->actingAs($admin)->get('/categories-create')->assertRedirect('/categories/create');
        $this->actingAs($admin)->get('/tax-profiles-create')->assertRedirect('/tax-profiles/create');
    }

    /**
     * DEAD-002: Canonical create routes respond with 200 for authorized users.
     */
    public function test_dead_002_canonical_create_routes_render(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'status' => AccountStatus::ACTIVE]);

        $this->actingAs($admin)->get('/customers/create')->assertStatus(200);
        $this->actingAs($admin)->get('/salesmen/create')->assertStatus(200);
        $this->actingAs($admin)->get('/products/create')->assertStatus(200);
        $this->actingAs($admin)->get('/categories/create')->assertStatus(200);
        $this->actingAs($admin)->get('/tax-profiles/create')->assertStatus(200);
    }

    /**
     * UI-002: Unique Distributors branding defaults are enforced.
     */
    public function test_ui_002_unique_distributors_branding_defaults(): void
    {
        $service = app(ApplicationIdentityService::class);
        $identity = $service->get();

        $this->assertSame('Unique Distributors', $identity->name);
        $this->assertSame('Unique Distributors Inc.', $identity->company_name);
        $this->assertSame('Unique Distributors', $identity->footer_text);
    }
}
