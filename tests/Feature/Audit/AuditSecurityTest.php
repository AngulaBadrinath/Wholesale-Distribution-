<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $salesman;
    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);
    }

    public function test_admin_and_super_admin_can_view_activity_timeline(): void
    {
        $responseAdmin = $this->actingAs($this->admin)
            ->getJson('/admin/audit/timeline');
        $responseAdmin->assertOk();

        $responseSuper = $this->actingAs($this->superAdmin)
            ->getJson('/admin/audit/timeline');
        $responseSuper->assertOk();
    }

    public function test_unauthorized_roles_cannot_view_activity_timeline(): void
    {
        $responseSalesman = $this->actingAs($this->salesman)
            ->getJson('/admin/audit/timeline');
        $responseSalesman->assertStatus(403);
    }

    public function test_admin_and_super_admin_can_view_security_logs(): void
    {
        $responseAdmin = $this->actingAs($this->admin)
            ->getJson('/admin/audit/security');
        $responseAdmin->assertOk();

        $responseSuper = $this->actingAs($this->superAdmin)
            ->getJson('/admin/audit/security');
        $responseSuper->assertOk();
    }

    public function test_unauthorized_roles_cannot_view_security_logs(): void
    {
        $responseSalesman = $this->actingAs($this->salesman)
            ->getJson('/admin/audit/security');
        $responseSalesman->assertStatus(403);

        $responseAccountant = $this->actingAs($this->accountant)
            ->getJson('/admin/audit/security');
        $responseAccountant->assertStatus(403);
    }

    public function test_unauthenticated_request_is_denied(): void
    {
        $response = $this->getJson('/admin/audit/timeline');
        $response->assertStatus(401);
    }
}
