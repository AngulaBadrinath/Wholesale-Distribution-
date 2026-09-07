<?php

declare(strict_types=1);

namespace Tests\Feature\Payable;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Payable\PayableLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected User $warehouseManager;
    protected User $deliveryPartner;
    protected PayableLedgerService $ledgerService;
    protected Supplier $supplier;

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

        $this->ledgerService = app(PayableLedgerService::class);
        $this->supplier = $this->ledgerService->createSupplier(['name' => 'Security Demo Supplier'], $this->admin);
    }

    public function test_authorized_roles_can_access_payables(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin/payables')->assertOk();
        $this->actingAs($this->admin)->get('/admin/payables')->assertOk();
        $this->actingAs($this->accountant)->get('/admin/payables')->assertOk();

        $this->actingAs($this->superAdmin)->get("/admin/payables/{$this->supplier->id}")->assertOk();
        $this->actingAs($this->admin)->get("/admin/payables/{$this->supplier->id}")->assertOk();
        $this->actingAs($this->accountant)->get("/admin/payables/{$this->supplier->id}")->assertOk();
    }

    public function test_unauthorized_roles_are_forbidden(): void
    {
        $this->actingAs($this->salesman)->get('/admin/payables')->assertForbidden();
        $this->actingAs($this->warehouseManager)->get('/admin/payables')->assertForbidden();
        $this->actingAs($this->deliveryPartner)->get('/admin/payables')->assertForbidden();

        $this->actingAs($this->salesman)->get("/admin/payables/{$this->supplier->id}")->assertForbidden();
        $this->actingAs($this->warehouseManager)->get("/admin/payables/{$this->supplier->id}")->assertForbidden();
        $this->actingAs($this->deliveryPartner)->get("/admin/payables/{$this->supplier->id}")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $this->get('/admin/payables')->assertRedirect(route('login'));
        $this->get("/admin/payables/{$this->supplier->id}")->assertRedirect(route('login'));
    }

    public function test_anti_idor_non_existent_supplier_returns_404(): void
    {
        $this->actingAs($this->admin)->get('/admin/payables/999999')->assertNotFound();
    }

    public function test_unauthorized_roles_cannot_create_supplier_or_bill(): void
    {
        $this->actingAs($this->salesman)->post('/admin/payables/suppliers', [
            'name' => 'Hacker Supplier',
        ])->assertForbidden();

        $this->actingAs($this->salesman)->post('/admin/payables/bills', [
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-09-01',
            'subtotal' => '100.00',
            'tax_total' => '0.00',
            'total_amount' => '100.00',
        ])->assertForbidden();
    }
}
