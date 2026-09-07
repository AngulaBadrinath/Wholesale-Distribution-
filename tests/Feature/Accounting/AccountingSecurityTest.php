<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected User $warehouseManager;
    protected User $deliveryPartner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouseManager = User::factory()->create([
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->deliveryPartner = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);
    }

    public function test_authorized_roles_can_access_accounting_dashboard(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.accounting.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.accounting.index'))->assertOk();
        $this->actingAs($this->accountant)->get(route('admin.accounting.index'))->assertOk();
    }

    public function test_unauthorized_roles_are_forbidden(): void
    {
        $this->actingAs($this->salesman)->get(route('admin.accounting.index'))->assertForbidden();
        $this->actingAs($this->warehouseManager)->get(route('admin.accounting.index'))->assertForbidden();
        $this->actingAs($this->deliveryPartner)->get(route('admin.accounting.index'))->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.accounting.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_financial_reports_endpoints_are_protected(): void
    {
        // Accountant can access
        $this->actingAs($this->accountant)->get(route('admin.accounting.trial-balance'))->assertOk();
        $this->actingAs($this->accountant)->get(route('admin.accounting.profit-loss'))->assertOk();
        $this->actingAs($this->accountant)->get(route('admin.accounting.balance-sheet'))->assertOk();
        $this->actingAs($this->accountant)->get(route('admin.accounting.general-ledger'))->assertOk();

        // Salesman is forbidden
        $this->actingAs($this->salesman)->get(route('admin.accounting.trial-balance'))->assertForbidden();
        $this->actingAs($this->salesman)->get(route('admin.accounting.profit-loss'))->assertForbidden();
        $this->actingAs($this->salesman)->get(route('admin.accounting.balance-sheet'))->assertForbidden();
        $this->actingAs($this->salesman)->get(route('admin.accounting.general-ledger'))->assertForbidden();
    }
}
