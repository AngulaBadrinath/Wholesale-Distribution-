<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->auditLogService = app(AuditLogService::class);
    }

    public function test_timeline_filters_by_module(): void
    {
        $this->auditLogService->log(
            eventType: 'ORDER_APPROVED',
            module: 'ORDER',
            action: 'ORDER_APPROVED',
            actor: $this->admin
        );

        $this->auditLogService->log(
            eventType: 'STOCK_ADJUSTED',
            module: 'INVENTORY',
            action: 'STOCK_ADJUSTED',
            actor: $this->admin
        );

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/audit/timeline?module=ORDER');

        $response->assertOk()
            ->assertJsonPath('logs.total', 1)
            ->assertJsonPath('logs.data.0.module', 'ORDER');
    }

    public function test_timeline_filters_by_search_term(): void
    {
        $this->auditLogService->log(
            eventType: 'PAYMENT_VERIFIED',
            module: 'PAYMENT',
            action: 'PAYMENT_VERIFIED',
            actor: $this->admin,
            referenceNumber: 'PAY-UNIQUE-9988',
            description: 'Cheque verified against bank balance'
        );

        $this->auditLogService->log(
            eventType: 'ORDER_SUBMITTED',
            module: 'ORDER',
            action: 'ORDER_SUBMITTED',
            actor: $this->admin,
            referenceNumber: 'ORD-5544'
        );

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/audit/timeline?search=PAY-UNIQUE-9988');

        $response->assertOk()
            ->assertJsonPath('logs.total', 1)
            ->assertJsonPath('logs.data.0.reference_number', 'PAY-UNIQUE-9988');
    }

    public function test_timeline_filters_by_date_range(): void
    {
        // Log 1: 5 days ago
        Carbon::setTestNow(Carbon::now()->subDays(5));
        $this->auditLogService->log(
            eventType: 'OLD_EVENT',
            module: 'CUSTOMER',
            action: 'OLD_ACTION',
            actor: $this->admin
        );

        // Log 2: Today
        Carbon::setTestNow(null);
        $this->auditLogService->log(
            eventType: 'TODAY_EVENT',
            module: 'CUSTOMER',
            action: 'TODAY_ACTION',
            actor: $this->admin
        );

        $todayStr = Carbon::today()->toDateString();
        $response = $this->actingAs($this->admin)
            ->getJson("/admin/audit/timeline?date_from={$todayStr}");

        $response->assertOk()
            ->assertJsonPath('logs.total', 1)
            ->assertJsonPath('logs.data.0.action', 'TODAY_ACTION');
    }

    public function test_can_retrieve_entity_history_endpoint(): void
    {
        $this->auditLogService->log(
            eventType: 'ORDER_CREATED',
            module: 'ORDER',
            action: 'ORDER_CREATED',
            actor: $this->admin,
            entityType: 'ORDER',
            entityId: 777
        );

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/audit/entities/ORDER/777');

        $response->assertOk()
            ->assertJsonPath('entity_type', 'ORDER')
            ->assertJsonPath('entity_id', 777)
            ->assertJsonPath('history.total', 1);
    }
}
