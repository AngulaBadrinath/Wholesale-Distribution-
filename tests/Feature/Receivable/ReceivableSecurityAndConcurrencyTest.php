<?php

declare(strict_types=1);

namespace Tests\Feature\Receivable;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReceivableTransactionType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\ReceivableTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableSecurityAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesmanA;
    protected User $salesmanB;
    protected User $warehouseManager;
    protected User $deliveryPartner;
    protected Customer $customerA;
    protected Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->warehouseManager = User::factory()->create([
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->deliveryPartner = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customerA = Customer::create([
            'name' => 'Customer Alpha',
            'code' => 'CUST-ALPHA-01',
            'contact_name' => 'Adam Alpha',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesmanA->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '50000.00',
            'balance' => '0.00',
            'billing_address_line1' => '100 Alpha St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'US',
            'email' => 'alpha@customer.com',
            'phone' => '212-555-0101',
        ]);

        $this->customerB = Customer::create([
            'name' => 'Customer Beta',
            'code' => 'CUST-BETA-01',
            'contact_name' => 'Bella Beta',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesmanB->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '50000.00',
            'balance' => '0.00',
            'billing_address_line1' => '200 Beta St',
            'billing_city' => 'Gotham',
            'billing_state' => 'NJ',
            'billing_postal_code' => '07001',
            'billing_country' => 'US',
            'email' => 'beta@customer.com',
            'phone' => '201-555-0202',
        ]);
    }

    public function test_salesman_can_access_assigned_customer_receivables_and_statement(): void
    {
        // Salesman A accesses Customer A -> 200 OK
        $response = $this->actingAs($this->salesmanA)->getJson(route('admin.receivables.show', $this->customerA->id));
        $response->assertOk();

        $statementResponse = $this->actingAs($this->salesmanA)->getJson(route('admin.receivables.statement', $this->customerA->id));
        $statementResponse->assertOk();
    }

    public function test_anti_idor_salesman_cannot_access_unassigned_customer_receivables_or_statement(): void
    {
        // Salesman A attempts to access Customer B -> 404 Fail Closed
        $response = $this->actingAs($this->salesmanA)->getJson(route('admin.receivables.show', $this->customerB->id));
        $response->assertNotFound();

        $statementResponse = $this->actingAs($this->salesmanA)->getJson(route('admin.receivables.statement', $this->customerB->id));
        $statementResponse->assertNotFound();
    }

    public function test_admin_and_accountant_can_access_all_customer_receivables(): void
    {
        // Admin
        $this->actingAs($this->admin)->getJson(route('admin.receivables.show', $this->customerA->id))->assertOk();
        $this->actingAs($this->admin)->getJson(route('admin.receivables.show', $this->customerB->id))->assertOk();

        // Accountant
        $this->actingAs($this->accountant)->getJson(route('admin.receivables.show', $this->customerA->id))->assertOk();
        $this->actingAs($this->accountant)->getJson(route('admin.receivables.show', $this->customerB->id))->assertOk();
    }

    public function test_unauthorized_roles_fail_closed_with_forbidden(): void
    {
        // Warehouse Manager
        $this->actingAs($this->warehouseManager)->getJson(route('admin.receivables.index'))->assertForbidden();
        $this->actingAs($this->warehouseManager)->getJson(route('admin.receivables.show', $this->customerA->id))->assertForbidden();

        // Delivery Partner
        $this->actingAs($this->deliveryPartner)->getJson(route('admin.receivables.index'))->assertForbidden();
        $this->actingAs($this->deliveryPartner)->getJson(route('admin.receivables.show', $this->customerA->id))->assertForbidden();
    }
}
