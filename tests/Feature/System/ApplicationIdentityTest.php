<?php

namespace Tests\Feature\System;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\System\ApplicationIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationIdentityTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationIdentityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApplicationIdentityService::class);
    }

    /**
     * SYS-IDENTITY-001: Authoritative identity source returns deterministic values.
     */
    public function test_authoritative_identity_source_returns_deterministic_values(): void
    {
        $identity = $this->service->get();

        $this->assertNotEmpty($identity->name);
        $this->assertNotEmpty($identity->company_name);
        $this->assertNotEmpty($identity->tagline);
        $this->assertNotEmpty($identity->support_email);
        $this->assertNotEmpty($identity->support_phone);
        $this->assertNotEmpty($identity->logo_path);
        $this->assertNotEmpty($identity->favicon_path);
        $this->assertNotEmpty($identity->footer_text);

        $this->assertSame($this->service->getAppName(), $identity->name);
        $this->assertSame($this->service->getCompanyName(), $identity->company_name);
        $this->assertSame($this->service->getTagline(), $identity->tagline);
        $this->assertSame($this->service->getSupportEmail(), $identity->support_email);
        $this->assertSame($this->service->getSupportPhone(), $identity->support_phone);
        $this->assertSame($this->service->getLogoPath(), $identity->logo_path);
        $this->assertSame($this->service->getFaviconPath(), $identity->favicon_path);
        $this->assertSame($this->service->getFooterText(), $identity->footer_text);
    }

    /**
     * SYS-IDENTITY-002: Default identity is available when configuration is missing or null.
     */
    public function test_default_identity_is_available_when_configuration_is_missing(): void
    {
        config([
            'app_identity.name' => null,
            'app_identity.company_name' => '',
            'app_identity.tagline' => '   ',
            'app_identity.support_email' => null,
            'app_identity.support_phone' => null,
            'app_identity.logo_path' => null,
            'app_identity.favicon_path' => null,
            'app_identity.footer_text' => null,
            'app.name' => null,
        ]);

        $identity = $this->service->get();

        $this->assertSame(ApplicationIdentityService::DEFAULT_NAME, $identity->name);
        $this->assertSame(ApplicationIdentityService::DEFAULT_COMPANY_NAME, $identity->company_name);
        $this->assertSame(ApplicationIdentityService::DEFAULT_TAGLINE, $identity->tagline);
        $this->assertSame(ApplicationIdentityService::DEFAULT_SUPPORT_EMAIL, $identity->support_email);
        $this->assertSame(ApplicationIdentityService::DEFAULT_SUPPORT_PHONE, $identity->support_phone);
        $this->assertSame(ApplicationIdentityService::DEFAULT_LOGO_PATH, $identity->logo_path);
        $this->assertSame(ApplicationIdentityService::DEFAULT_FAVICON_PATH, $identity->favicon_path);
        $this->assertSame(ApplicationIdentityService::DEFAULT_FOOTER_TEXT, $identity->footer_text);
    }

    /**
     * SYS-IDENTITY-003: Identity is shared safely through Inertia.
     */
    public function test_identity_is_shared_safely_through_inertia(): void
    {
        config([
            'app_identity.name' => 'Apex Distribution Platform',
            'app_identity.company_name' => 'Apex Wholesale Logistics LLC',
        ]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('appName')
            ->where('appName', 'Apex Distribution Platform')
            ->has('identity', fn (Assert $identity) => $identity
                ->where('name', 'Apex Distribution Platform')
                ->where('company_name', 'Apex Wholesale Logistics LLC')
                ->hasAll([
                    'name',
                    'company_name',
                    'tagline',
                    'support_email',
                    'support_phone',
                    'logo_path',
                    'favicon_path',
                    'footer_text',
                ])
            )
        );
    }

    /**
     * SYS-IDENTITY-004: Frontend receives only intended public identity fields.
     */
    public function test_frontend_receives_only_intended_public_identity_fields(): void
    {
        $publicIdentity = $this->service->getPublicIdentity();

        $expectedKeys = [
            'name',
            'company_name',
            'tagline',
            'support_email',
            'support_phone',
            'logo_path',
            'favicon_path',
            'footer_text',
        ];

        $this->assertSame($expectedKeys, array_keys($publicIdentity));
    }

    /**
     * SYS-IDENTITY-005: Dynamic config changes reflect without modifying code.
     */
    public function test_dynamic_config_changes_reflect_immediately(): void
    {
        config([
            'app_identity.name' => 'Pinnacle Supply Chain',
            'app_identity.company_name' => 'Pinnacle Goods Corp',
            'app_identity.tagline' => 'Next-Gen Distribution Solutions',
            'app_identity.support_email' => 'help@pinnacle.com',
            'app_identity.footer_text' => 'Pinnacle Supply Chain 2026',
        ]);

        $identity = $this->service->get();

        $this->assertSame('Pinnacle Supply Chain', $identity->name);
        $this->assertSame('Pinnacle Goods Corp', $identity->company_name);
        $this->assertSame('Next-Gen Distribution Solutions', $identity->tagline);
        $this->assertSame('help@pinnacle.com', $identity->support_email);
        $this->assertSame('Pinnacle Supply Chain 2026', $identity->footer_text);

        $response = $this->get('/login');
        $response->assertInertia(fn (Assert $page) => $page
            ->where('appName', 'Pinnacle Supply Chain')
            ->where('identity.name', 'Pinnacle Supply Chain')
            ->where('identity.company_name', 'Pinnacle Goods Corp')
            ->where('identity.tagline', 'Next-Gen Distribution Solutions')
            ->where('identity.support_email', 'help@pinnacle.com')
            ->where('identity.footer_text', 'Pinnacle Supply Chain 2026')
        );
    }

    /**
     * SYS-IDENTITY-006: Identity values render correctly on login view.
     */
    public function test_identity_renders_correctly_on_login_view(): void
    {
        config(['app_identity.name' => 'Custom Wholesale Hub']);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('appName', 'Custom Wholesale Hub')
            ->where('identity.name', 'Custom Wholesale Hub')
        );
    }

    /**
     * SYS-IDENTITY-007: Identity values render correctly on forgot password view.
     */
    public function test_identity_renders_correctly_on_forgot_password_view(): void
    {
        config(['app_identity.name' => 'Enterprise Distribution Portal']);

        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ForgotPassword')
            ->where('appName', 'Enterprise Distribution Portal')
            ->where('identity.name', 'Enterprise Distribution Portal')
        );
    }

    /**
     * SYS-IDENTITY-008: Identity values render correctly on reset password view.
     */
    public function test_identity_renders_correctly_on_reset_password_view(): void
    {
        config(['app_identity.name' => 'Enterprise Distribution Portal']);

        $response = $this->get('/reset-password/sample-token?email=user@example.com');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('appName', 'Enterprise Distribution Portal')
            ->where('identity.name', 'Enterprise Distribution Portal')
        );
    }

    /**
     * SYS-IDENTITY-009: Identity values render correctly in the main application shell for authenticated user.
     */
    public function test_identity_renders_correctly_in_application_shell(): void
    {
        $user = User::factory()->create(['status' => AccountStatus::ACTIVE]);

        config([
            'app_identity.name' => 'Apex Distribution Corp',
            'app_identity.footer_text' => 'Apex Distribution Corp 2026',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('appName', 'Apex Distribution Corp')
            ->where('identity.name', 'Apex Distribution Corp')
            ->where('identity.footer_text', 'Apex Distribution Corp 2026')
        );
    }

    /**
     * SYS-IDENTITY-010: XSS safety: identity strings are plain text and escaped by Blade.
     */
    public function test_xss_payloads_in_identity_are_escaped_in_blade_html(): void
    {
        config([
            'app_identity.name' => '<script>alert("XSS")</script>',
            'app_identity.favicon_path' => '/favicon.ico"><script>alert("xss")</script>',
        ]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        // HTML in title must be escaped by Blade e() helper
        $response->assertSee('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert("XSS")</script>', false);
    }

    /**
     * SYS-IDENTITY-011: Zero DB queries executed for identity resolution.
     */
    public function test_zero_db_queries_executed_for_identity_resolution(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service->get();
        $this->service->getAppName();
        $this->service->getCompanyName();
        $this->service->getTagline();
        $this->service->getSupportEmail();
        $this->service->getSupportPhone();
        $this->service->getLogoPath();
        $this->service->getFaviconPath();
        $this->service->getFooterText();
        $this->service->getPublicIdentity();

        $queries = DB::getQueryLog();
        $this->assertCount(0, $queries, 'ApplicationIdentityService should execute zero database queries.');
    }

    /**
     * SYS-IDENTITY-012: Blade template includes dynamic title and favicon tag.
     */
    public function test_blade_template_includes_dynamic_title_and_favicon(): void
    {
        config([
            'app_identity.name' => 'Global Commerce Network',
            'app_identity.favicon_path' => '/custom-favicon.png',
        ]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<title inertia>Global Commerce Network</title>', false);
        $response->assertSee('<link rel="icon" href="/custom-favicon.png">', false);
    }
}
