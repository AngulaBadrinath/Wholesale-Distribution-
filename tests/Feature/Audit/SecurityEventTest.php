<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\Audit\SecurityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SecurityLogService $securityLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->securityLogService = app(SecurityLogService::class);
    }

    public function test_can_log_security_event_with_severity_and_actor(): void
    {
        $log = $this->securityLogService->logSecurityEvent(
            eventType: 'LOGIN_FAILED',
            severity: 'WARNING',
            actor: null,
            email: 'attacker@example.com',
            context: [
                'reason' => 'invalid_password',
                'attempt' => 3,
            ]
        );

        $this->assertInstanceOf(SecurityLog::class, $log);
        $this->assertDatabaseHas('security_logs', [
            'id' => $log->id,
            'event_type' => 'LOGIN_FAILED',
            'severity' => 'WARNING',
            'actor_email' => 'attacker@example.com',
        ]);
        $this->assertEquals('invalid_password', $log->fresh()->context['reason']);
    }

    public function test_security_context_redacts_passwords_and_secrets(): void
    {
        $log = $this->securityLogService->logSecurityEvent(
            eventType: 'MFA_CHALLENGE_FAILED',
            severity: 'CRITICAL',
            actor: $this->user,
            context: [
                'provided_secret' => '123456',
                'password' => 'AttemptedPassword',
                'mfa_secret' => 'TOTPSECRET',
                'failure_count' => 5,
            ]
        );

        $context = $log->fresh()->context;

        $this->assertEquals(5, $context['failure_count']);
        $this->assertEquals('[REDACTED]', $context['provided_secret']);
        $this->assertEquals('[REDACTED]', $context['password']);
        $this->assertEquals('[REDACTED]', $context['mfa_secret']);
    }

    public function test_can_query_filtered_security_logs(): void
    {
        $this->securityLogService->logSecurityEvent(
            eventType: 'PASSWORD_RESET_REQUESTED',
            severity: 'INFO',
            actor: $this->user
        );

        $this->securityLogService->logSecurityEvent(
            eventType: 'PERMISSION_DENIED',
            severity: 'CRITICAL',
            actor: $this->user
        );

        $criticalLogs = $this->securityLogService->getSecurityLogs(['severity' => 'CRITICAL']);

        $this->assertEquals(1, $criticalLogs->total());
        $this->assertEquals('PERMISSION_DENIED', $criticalLogs->items()[0]->event_type);
    }
}
