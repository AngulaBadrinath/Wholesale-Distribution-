<?php

namespace Tests\Feature\Payment;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReversalTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $accountant;
    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Order $order;
    protected Payment $verifiedPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@wholesale.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Arthur Accountant',
            'email' => 'accountant@wholesale.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'salesman@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Zenith Distributors',
            'code' => 'CUST-ZENITH',
            'contact_name' => 'Zack Zenith',
            'phone' => '+1-555-0701',
            'email' => 'zenith@wholesale.test',
            'billing_address_line1' => '100 Zenith Way',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-REV-01',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => \App\Enums\FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::PAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => \App\Enums\AdjustmentStatus::NONE,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-rev-ord-1',
            'subtotal' => '1000.00',
            'tax_total' => '0.00',
            'adjustment_total' => '0.00',
            'grand_total' => '1000.00',
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $this->verifiedPayment = Payment::create([
            'payment_number' => 'PAY-2026-REV-001',
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'payment_method' => PaymentMethod::CHEQUE,
            'status' => PaymentTransactionStatus::VERIFIED,
            'amount' => '1000.00',
            'payment_date' => now()->subDays(2)->toDateString(),
            'bank_name' => 'First National Bank',
            'cheque_number' => 'CHQ-777888',
            'cheque_date' => now()->subDays(3)->toDateString(),
            'recorded_by' => $this->salesman->id,
            'verified_by' => $this->accountant->id,
            'verified_at' => now()->subDays(2),
            'version' => 1,
        ]);
    }

    public function test_accountant_can_reverse_verified_payment_with_bounced_cheque_reason(): void
    {
        $payload = [
            'reversal_reason_code' => PaymentReversalReason::BOUNCED_CHEQUE->value,
            'reversal_notes' => 'Cheque returned unpaid by clearing house due to NSF.',
        ];

        $response = $this->actingAs($this->accountant)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $response->assertStatus(302);

        $this->verifiedPayment->refresh();
        $this->assertEquals(PaymentTransactionStatus::REVERSED, $this->verifiedPayment->status);
        $this->assertEquals($this->accountant->id, $this->verifiedPayment->reversed_by);
        $this->assertEquals(PaymentReversalReason::BOUNCED_CHEQUE, $this->verifiedPayment->reversal_reason_code);
        $this->assertEquals('Cheque returned unpaid by clearing house due to NSF.', $this->verifiedPayment->reversal_notes);
        $this->assertNotNull($this->verifiedPayment->reversed_at);
        $this->assertEquals(2, $this->verifiedPayment->version);

        // Linked order payment status rolls back to UNPAID
        $this->order->refresh();
        $this->assertEquals(PaymentStatus::UNPAID, $this->order->payment_status);
    }

    public function test_super_admin_can_reverse_verified_payment(): void
    {
        $payload = [
            'reversal_reason_code' => PaymentReversalReason::ADMIN_CORRECTION->value,
            'reversal_notes' => 'Administrative correction by Super Admin.',
        ];

        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $response->assertStatus(302);

        $this->verifiedPayment->refresh();
        $this->assertEquals(PaymentTransactionStatus::REVERSED, $this->verifiedPayment->status);
        $this->assertEquals($this->superAdmin->id, $this->verifiedPayment->reversed_by);
    }

    public function test_order_reconciliation_on_partial_reversal(): void
    {
        // Add a second verified payment of $500 to an order with total $1,500
        $this->order->update(['grand_total' => '1500.00', 'payment_status' => PaymentStatus::PAID]);

        $secondVerifiedPayment = Payment::create([
            'payment_number' => 'PAY-2026-REV-002',
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::VERIFIED,
            'amount' => '500.00',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->salesman->id,
            'verified_by' => $this->accountant->id,
            'verified_at' => now(),
            'version' => 1,
        ]);

        // Reverse the $1,000 cheque -> Remaining verified is $500 -> Order becomes PARTIALLY_PAID
        $payload = [
            'reversal_reason_code' => PaymentReversalReason::INSUFFICIENT_FUNDS->value,
            'reversal_notes' => 'Customer account had insufficient funds.',
        ];

        $this->actingAs($this->accountant)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $this->order->refresh();
        $this->assertEquals(PaymentStatus::PARTIALLY_PAID, $this->order->payment_status);
    }

    public function test_admin_cannot_reverse_payment_without_payment_reverse_permission(): void
    {
        // Admin role does NOT have payment.reverse permission (only Accountant & Super Admin)
        $payload = [
            'reversal_reason_code' => PaymentReversalReason::BOUNCED_CHEQUE->value,
            'reversal_notes' => 'Admin attempting reversal.',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $response->assertStatus(403);
    }

    public function test_salesman_cannot_reverse_payment(): void
    {
        $payload = [
            'reversal_reason_code' => PaymentReversalReason::BOUNCED_CHEQUE->value,
            'reversal_notes' => 'Salesman attempting reversal.',
        ];

        $response = $this->actingAs($this->salesman)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $response->assertStatus(403);
    }

    public function test_cannot_reverse_already_reversed_payment(): void
    {
        $this->verifiedPayment->update([
            'status' => PaymentTransactionStatus::REVERSED,
            'reversed_by' => $this->accountant->id,
        ]);

        $payload = [
            'reversal_reason_code' => PaymentReversalReason::BOUNCED_CHEQUE->value,
            'reversal_notes' => 'Double reversal attempt.',
        ];

        $response = $this->actingAs($this->accountant)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $response->assertStatus(409);
    }

    public function test_cannot_reverse_pending_payment(): void
    {
        $this->verifiedPayment->update([
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
        ]);

        $payload = [
            'reversal_reason_code' => PaymentReversalReason::BOUNCED_CHEQUE->value,
            'reversal_notes' => 'Attempting to reverse pending.',
        ];

        $response = $this->actingAs($this->accountant)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $response->assertStatus(409);
    }

    public function test_cannot_reverse_rejected_payment(): void
    {
        $this->verifiedPayment->update([
            'status' => PaymentTransactionStatus::REJECTED,
        ]);

        $payload = [
            'reversal_reason_code' => PaymentReversalReason::BOUNCED_CHEQUE->value,
            'reversal_notes' => 'Attempting to reverse rejected.',
        ];

        $response = $this->actingAs($this->accountant)->post(
            route('admin.payments.reverse', $this->verifiedPayment),
            $payload
        );

        $response->assertStatus(409);
    }
}
