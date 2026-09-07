<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAuditEventTest extends TestCase
{
    use RefreshDatabase;

    protected User $actor;
    protected AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->auditLogService = app(AuditLogService::class);
    }

    public function test_can_record_structured_business_audit_event(): void
    {
        $log = $this->auditLogService->log(
            eventType: 'ORDER_APPROVED',
            module: 'ORDER',
            action: 'ORDER_APPROVED',
            actor: $this->actor,
            entityType: 'ORDER',
            entityId: 10045,
            referenceNumber: 'ORD-10045',
            description: 'Order approved by administrator.',
            metadata: [
                'order_id' => 10045,
                'total_amount' => 12500.50,
                'customer_id' => 54,
            ]
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'event_type' => 'ORDER_APPROVED',
            'module' => 'ORDER',
            'action' => 'ORDER_APPROVED',
            'actor_id' => $this->actor->id,
            'actor_email' => $this->actor->email,
            'entity_type' => 'ORDER',
            'entity_id' => 10045,
            'reference_number' => 'ORD-10045',
        ]);
    }

    public function test_sensitive_keys_are_strictly_redacted_from_audit_metadata(): void
    {
        $log = $this->auditLogService->log(
            eventType: 'USER_CREATED',
            module: 'USER',
            action: 'USER_CREATED',
            actor: $this->actor,
            entityType: 'USER',
            entityId: 99,
            metadata: [
                'email' => 'newuser@example.com',
                'password' => 'SuperSecret123!',
                'password_confirmation' => 'SuperSecret123!',
                'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                'token' => 'abc-xyz-token',
                'nested' => [
                    'secret' => 'confidential-value',
                    'safe_key' => 'visible-value',
                ],
            ]
        );

        $metadata = $log->fresh()->metadata;

        $this->assertEquals('newuser@example.com', $metadata['email']);
        $this->assertEquals('[REDACTED]', $metadata['password']);
        $this->assertEquals('[REDACTED]', $metadata['password_confirmation']);
        $this->assertEquals('[REDACTED]', $metadata['mfa_secret']);
        $this->assertEquals('[REDACTED]', $metadata['token']);
        $this->assertEquals('[REDACTED]', $metadata['nested']['secret']);
        $this->assertEquals('visible-value', $metadata['nested']['safe_key']);
    }

    public function test_can_retrieve_entity_audit_history(): void
    {
        $this->auditLogService->log(
            eventType: 'ORDER_CREATED',
            module: 'ORDER',
            action: 'ORDER_CREATED',
            actor: $this->actor,
            entityType: 'ORDER',
            entityId: 501
        );

        $this->auditLogService->log(
            eventType: 'ORDER_APPROVED',
            module: 'ORDER',
            action: 'ORDER_APPROVED',
            actor: $this->actor,
            entityType: 'ORDER',
            entityId: 501
        );

        $this->auditLogService->log(
            eventType: 'ORDER_CREATED',
            module: 'ORDER',
            action: 'ORDER_CREATED',
            actor: $this->actor,
            entityType: 'ORDER',
            entityId: 999 // Different entity
        );

        $history = $this->auditLogService->getEntityHistory('ORDER', 501);

        $this->assertEquals(2, $history->total());
        $this->assertEquals('ORDER_APPROVED', $history->items()[0]->action);
        $this->assertEquals('ORDER_CREATED', $history->items()[1]->action);
    }
}
