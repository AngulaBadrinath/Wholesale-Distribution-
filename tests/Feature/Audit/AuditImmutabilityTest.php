<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\SecurityLogService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected AuditLogService $auditLogService;
    protected SecurityLogService $securityLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->auditLogService = app(AuditLogService::class);
        $this->securityLogService = app(SecurityLogService::class);
    }

    public function test_eloquent_update_on_audit_log_is_blocked_by_domain_exception(): void
    {
        $log = $this->auditLogService->log(
            eventType: 'PRICE_OVERRIDE',
            module: 'PRICING',
            action: 'OVERRIDE_AUTHORIZED',
            actor: $this->user,
            entityType: 'PRODUCT',
            entityId: 101
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Audit log records are strictly immutable and cannot be updated.');

        $log->update(['description' => 'Tampered description']);
    }

    public function test_eloquent_delete_on_audit_log_is_blocked_by_domain_exception(): void
    {
        $log = $this->auditLogService->log(
            eventType: 'PAYMENT_RECEIVED',
            module: 'PAYMENT',
            action: 'PAYMENT_RECORDED',
            actor: $this->user
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Audit log records are strictly immutable and cannot be deleted.');

        $log->delete();
    }

    public function test_eloquent_update_on_security_log_is_blocked_by_domain_exception(): void
    {
        $log = $this->securityLogService->logSecurityEvent(
            eventType: 'FAILED_LOGIN',
            severity: 'WARNING',
            actor: $this->user
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Security log records are strictly immutable and cannot be updated.');

        $log->update(['severity' => 'INFO']);
    }

    public function test_eloquent_delete_on_security_log_is_blocked_by_domain_exception(): void
    {
        $log = $this->securityLogService->logSecurityEvent(
            eventType: 'SUSPICIOUS_ACTIVITY',
            severity: 'CRITICAL',
            actor: $this->user
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Security log records are strictly immutable and cannot be deleted.');

        $log->delete();
    }

    public function test_raw_sql_update_on_audit_log_is_blocked_by_postgresql_trigger(): void
    {
        $log = $this->auditLogService->log(
            eventType: 'RAW_SQL_TEST',
            module: 'TEST',
            action: 'CREATE',
            actor: $this->user
        );

        $this->expectException(QueryException::class);

        DB::statement("UPDATE audit_logs SET description = 'Tampered via raw SQL' WHERE id = {$log->id}");
    }

    public function test_raw_sql_delete_on_audit_log_is_blocked_by_postgresql_trigger(): void
    {
        $log = $this->auditLogService->log(
            eventType: 'RAW_SQL_TEST',
            module: 'TEST',
            action: 'CREATE',
            actor: $this->user
        );

        $this->expectException(QueryException::class);

        DB::statement("DELETE FROM audit_logs WHERE id = {$log->id}");
    }

    public function test_raw_sql_update_on_security_log_is_blocked_by_postgresql_trigger(): void
    {
        $log = $this->securityLogService->logSecurityEvent(
            eventType: 'RAW_SQL_TEST',
            severity: 'INFO',
            actor: $this->user
        );

        $this->expectException(QueryException::class);

        DB::statement("UPDATE security_logs SET severity = 'TAMPERED' WHERE id = {$log->id}");
    }

    public function test_raw_sql_delete_on_security_log_is_blocked_by_postgresql_trigger(): void
    {
        $log = $this->securityLogService->logSecurityEvent(
            eventType: 'RAW_SQL_TEST',
            severity: 'INFO',
            actor: $this->user
        );

        $this->expectException(QueryException::class);

        DB::statement("DELETE FROM security_logs WHERE id = {$log->id}");
    }
}
