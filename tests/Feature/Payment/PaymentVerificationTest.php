<?php

namespace Tests\Feature\Payment;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected User $warehouseManager;
    protected Customer $customer;
    protected Order $order;
    protected Payment $pendingPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Arthur Accountant',
            'email' => 'accountant@wholesale.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'salesman@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouseManager = User::factory()->create([
            'name' => 'Wendy Warehouse',
            'email' => 'warehouse@wholesale.test',
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Apex Retailers',
            'code' => 'CUST-APEX',
            'contact_name' => 'Adam Apex',
            'phone' => '+1-555-0501',
            'email' => 'apex@wholesale.test',
            'billing_address_line1' => '100 Apex St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-VRF-01',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => \App\Enums\FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => \App\Enums\AdjustmentStatus::NONE,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-vrf-ord-1',
            'subtotal' => '1000.00',
            'tax_total' => '0.00',
            'adjustment_total' => '0.00',
            'grand_total' => '1000.00',
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $this->pendingPayment = Payment::create([
            'payment_number' => 'PAY-2026-VRF-001',
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '500.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesman->id,
        ]);
    }

    public function test_admin_can_access_payments_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.payments.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_verify_pending_payment(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.payments.verify', $this->pendingPayment)
        );

        $response->assertStatus(302);

        $this->pendingPayment->refresh();
        $this->assertEquals(PaymentTransactionStatus::VERIFIED, $this->pendingPayment->status);
        $this->assertEquals($this->admin->id, $this->pendingPayment->verified_by);
        $this->assertNotNull($this->pendingPayment->verified_at);

        // Order reconciled to PARTIALLY_PAID (500 out of 1000)
        $this->order->refresh();
        $this->assertEquals(PaymentStatus::PARTIALLY_PAID, $this->order->payment_status);
    }

    public function test_accountant_can_verify_pending_payment(): void
    {
        $response = $this->actingAs($this->accountant)->post(
            route('admin.payments.verify', $this->pendingPayment)
        );

        $response->assertStatus(302);

        $this->pendingPayment->refresh();
        $this->assertEquals(PaymentTransactionStatus::VERIFIED, $this->pendingPayment->status);
        $this->assertEquals($this->accountant->id, $this->pendingPayment->verified_by);
    }

    public function test_order_reconciliation_exact_and_multiple_payments(): void
    {
        // 1. First payment verified ($500) -> PARTIALLY_PAID
        $this->actingAs($this->admin)->post(route('admin.payments.verify', $this->pendingPayment));
        $this->order->refresh();
        $this->assertEquals(PaymentStatus::PARTIALLY_PAID, $this->order->payment_status);

        // 2. Second payment created ($500) and verified -> PAID
        $payment2 = Payment::create([
            'payment_number' => 'PAY-2026-VRF-002',
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '500.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesman->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment2));
        $this->order->refresh();
        $this->assertEquals(PaymentStatus::PAID, $this->order->payment_status);
    }

    public function test_order_reconciliation_overpayment(): void
    {
        $overPayment = Payment::create([
            'payment_number' => 'PAY-2026-VRF-003',
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '1500.00', // Greater than grand total 1000.00
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesman->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.payments.verify', $overPayment));
        $this->order->refresh();
        $this->assertEquals(PaymentStatus::OVERPAID, $this->order->payment_status);
    }

    public function test_pending_payment_does_not_affect_unpaid_order_status(): void
    {
        // Assert initial order payment status is UNPAID even with pending payment
        $this->assertEquals(PaymentStatus::UNPAID, $this->order->payment_status);
    }

    public function test_double_verification_is_rejected_with_409_conflict(): void
    {
        // First verification
        $this->actingAs($this->admin)->post(route('admin.payments.verify', $this->pendingPayment));

        // Second verification attempt
        $response = $this->actingAs($this->admin)->post(route('admin.payments.verify', $this->pendingPayment));

        $response->assertStatus(409);
    }

    public function test_verifying_rejected_payment_is_rejected_with_409_conflict(): void
    {
        $this->pendingPayment->update([
            'status' => PaymentTransactionStatus::REJECTED,
            'rejected_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.verify', $this->pendingPayment));

        $response->assertStatus(409);
    }

    public function test_salesman_cannot_verify_payment(): void
    {
        $response = $this->actingAs($this->salesman)->post(route('admin.payments.verify', $this->pendingPayment));

        $response->assertStatus(403);
    }

    public function test_warehouse_manager_cannot_verify_payment(): void
    {
        $response = $this->actingAs($this->warehouseManager)->post(route('admin.payments.verify', $this->pendingPayment));

        $response->assertStatus(403);
    }
}
