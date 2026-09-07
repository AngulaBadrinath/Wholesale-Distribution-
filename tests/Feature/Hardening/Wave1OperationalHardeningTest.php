<?php

namespace Tests\Feature\Hardening;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave1OperationalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $accountant;
    protected User $salesmanA;
    protected User $salesmanB;
    protected User $driver;
    protected Customer $customerA;
    protected Customer $customerB;

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

        $this->salesmanA = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesmanB = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->driver = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customerA = Customer::create([
            'salesman_id' => $this->salesmanA->id,
            'name' => 'Customer A',
            'code' => 'CUST-A-01',
            'contact_name' => 'Alice A',
            'phone' => '+1-555-0101',
            'email' => 'customera@wholesale.test',
            'billing_address_line1' => '100 Alpha St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->customerB = Customer::create([
            'salesman_id' => $this->salesmanB->id,
            'name' => 'Customer B',
            'code' => 'CUST-B-01',
            'contact_name' => 'Bob B',
            'phone' => '+1-555-0202',
            'email' => 'customerb@wholesale.test',
            'billing_address_line1' => '200 Beta St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10002',
            'billing_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    /**
     * Test BUG-001: PaymentVerificationService badge counts are scoped to salesman's portfolio.
     */
    public function test_payment_badge_counts_are_scoped_per_salesman(): void
    {
        // 2 pending payments for Customer A (Salesman A)
        Payment::create([
            'payment_number' => 'PAY-2026-000001',
            'customer_id' => $this->customerA->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '150.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesmanA->id,
        ]);

        Payment::create([
            'payment_number' => 'PAY-2026-000002',
            'customer_id' => $this->customerA->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '250.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesmanA->id,
        ]);

        // 3 pending payments for Customer B (Salesman B)
        Payment::create([
            'payment_number' => 'PAY-2026-000003',
            'customer_id' => $this->customerB->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '300.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesmanB->id,
        ]);

        Payment::create([
            'payment_number' => 'PAY-2026-000004',
            'customer_id' => $this->customerB->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '400.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesmanB->id,
        ]);

        Payment::create([
            'payment_number' => 'PAY-2026-000005',
            'customer_id' => $this->customerB->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '500.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesmanB->id,
        ]);

        // Global admin sees all 5
        $adminCounts = PaymentVerificationService::getBadgeCounts($this->admin);
        $this->assertSame(5, $adminCounts['pending_verification']);
        $this->assertSame(5, $adminCounts['all']);

        // Salesman A sees only their 2 payments
        $salesmanACounts = PaymentVerificationService::getBadgeCounts($this->salesmanA);
        $this->assertSame(2, $salesmanACounts['pending_verification']);
        $this->assertSame(2, $salesmanACounts['all']);

        // Salesman B sees only their 3 payments
        $salesmanBCounts = PaymentVerificationService::getBadgeCounts($this->salesmanB);
        $this->assertSame(3, $salesmanBCounts['pending_verification']);
        $this->assertSame(3, $salesmanBCounts['all']);
    }

    /**
     * Test BUG-002: Credit Notes workspace access control.
     */
    public function test_credit_notes_workspace_authorization_rules(): void
    {
        // Super Admin, Admin, Accountant can access /admin/credits
        $this->actingAs($this->superAdmin)->get(route('admin.credits.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.credits.index'))->assertOk();
        $this->actingAs($this->accountant)->get(route('admin.credits.index'))->assertOk();

        // Driver is blocked with 403
        $this->actingAs($this->driver)->get(route('admin.credits.index'))->assertForbidden();
    }

    /**
     * Test BUG-004: General Ledger and Trial Balance endpoints load correctly for authorized financial roles.
     */
    public function test_general_ledger_and_trial_balance_endpoints_respond_with_inertia(): void
    {
        $this->actingAs($this->accountant)
            ->get('/admin/accounting/trial-balance')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/admin/accounting/general-ledger')
            ->assertOk();
    }
}
