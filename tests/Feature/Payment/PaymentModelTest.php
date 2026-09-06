<?php

namespace Tests\Feature\Payment;

use App\Enums\AccountStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentReversalReason;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected User $accountant;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->accountant = User::factory()->create([
            'name' => 'Arthur Accountant',
            'email' => 'accountant@wholesale.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Acme Corporation',
            'code' => 'CUST-ACME-01',
            'contact_name' => 'John Doe',
            'phone' => '+1-555-0199',
            'email' => 'acme@wholesale.test',
            'billing_address_line1' => '100 Main St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Main St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => \App\Enums\CustomerStatus::ACTIVE,
        ]);
    }

    public function test_can_create_payment_with_defaults(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-2026-0001',
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentMethod::CASH,
            'amount' => '2500.50',
            'payment_date' => '2026-09-06',
            'recorded_by' => $this->salesman->id,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_number' => 'PAY-2026-0001',
            'customer_id' => $this->customer->id,
            'payment_method' => 'CASH',
            'status' => 'PENDING_VERIFICATION',
            'version' => 1,
        ]);

        $this->assertEquals(PaymentTransactionStatus::PENDING_VERIFICATION, $payment->status);
        $this->assertEquals(PaymentMethod::CASH, $payment->payment_method);
        $this->assertEquals('2500.50', $payment->amount);
        $this->assertTrue($payment->isPending());
        $this->assertFalse($payment->isVerified());
        $this->assertFalse($payment->hasEvidence());
    }

    public function test_payment_relationships(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-TEST-01',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => \App\Enums\OrderStatus::APPROVED,
            'fulfillment_status' => \App\Enums\FulfillmentStatus::UNALLOCATED,
            'payment_status' => \App\Enums\PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => \App\Enums\AdjustmentStatus::NONE,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-ord-test-01',
            'subtotal' => '250.00',
            'tax_total' => '45.00',
            'adjustment_total' => '0.00',
            'grand_total' => '295.00',
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-2026-0002',
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::CHEQUE,
            'status' => PaymentTransactionStatus::VERIFIED,
            'amount' => '5000.00',
            'payment_date' => '2026-09-06',
            'cheque_number' => 'CHQ-987654',
            'bank_name' => 'Apex National Bank',
            'cheque_date' => '2026-09-05',
            'evidence_object_key' => 'payments/2026/09/cheque-1.jpg',
            'evidence_original_name' => 'cheque.jpg',
            'evidence_mime_type' => 'image/jpeg',
            'evidence_size_bytes' => 204800,
            'evidence_uploaded_at' => now(),
            'recorded_by' => $this->salesman->id,
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
        ]);

        $this->assertInstanceOf(Customer::class, $payment->customer);
        $this->assertEquals($this->customer->id, $payment->customer->id);

        $this->assertInstanceOf(Order::class, $payment->order);
        $this->assertEquals($order->id, $payment->order->id);

        $this->assertInstanceOf(User::class, $payment->recordedBy);
        $this->assertEquals($this->salesman->id, $payment->recordedBy->id);

        $this->assertInstanceOf(User::class, $payment->verifiedBy);
        $this->assertEquals($this->admin->id, $payment->verifiedBy->id);

        $this->assertTrue($payment->isVerified());
        $this->assertTrue($payment->hasEvidence());

        // Verify inverse relationships on Customer and Order
        $this->assertTrue($this->customer->payments->contains($payment));
        $this->assertTrue($order->payments->contains($payment));
    }

    public function test_payment_rejection_and_reversal_casts_and_relations(): void
    {
        $rejectedPayment = Payment::create([
            'payment_number' => 'PAY-2026-0003',
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentMethod::MONEY_ORDER,
            'status' => PaymentTransactionStatus::REJECTED,
            'amount' => '1200.00',
            'payment_date' => '2026-09-06',
            'money_order_number' => 'MO-112233',
            'issuer_name' => 'National Post',
            'evidence_object_key' => 'payments/2026/09/mo-1.jpg',
            'recorded_by' => $this->salesman->id,
            'rejected_by' => $this->admin->id,
            'rejection_reason_code' => PaymentRejectionReason::ILLEGIBLE_EVIDENCE,
            'rejection_notes' => 'Image resolution too low',
            'rejected_at' => now(),
        ]);

        $this->assertTrue($rejectedPayment->isRejected());
        $this->assertEquals(PaymentRejectionReason::ILLEGIBLE_EVIDENCE, $rejectedPayment->rejection_reason_code);
        $this->assertInstanceOf(User::class, $rejectedPayment->rejectedBy);

        $reversedPayment = Payment::create([
            'payment_number' => 'PAY-2026-0004',
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentMethod::CHEQUE,
            'status' => PaymentTransactionStatus::REVERSED,
            'amount' => '3000.00',
            'payment_date' => '2026-09-06',
            'cheque_number' => 'CHQ-112233',
            'bank_name' => 'Metro Bank',
            'recorded_by' => $this->salesman->id,
            'reversed_by' => $this->accountant->id,
            'reversal_reason_code' => PaymentReversalReason::BOUNCED_CHEQUE,
            'reversal_notes' => 'Cheque returned unpaid (NSF)',
            'reversed_at' => now(),
        ]);

        $this->assertTrue($reversedPayment->isReversed());
        $this->assertEquals(PaymentReversalReason::BOUNCED_CHEQUE, $reversedPayment->reversal_reason_code);
        $this->assertInstanceOf(User::class, $reversedPayment->reversedBy);
    }

    public function test_payment_scopes_and_filtering(): void
    {
        $p1 = Payment::create([
            'payment_number' => 'PAY-P1',
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '100.00',
            'payment_date' => '2026-09-01',
            'recorded_by' => $this->salesman->id,
        ]);

        $p2 = Payment::create([
            'payment_number' => 'PAY-P2',
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentMethod::CHEQUE,
            'cheque_number' => 'CHQ-778899',
            'bank_name' => 'City Bank',
            'status' => PaymentTransactionStatus::VERIFIED,
            'amount' => '200.00',
            'payment_date' => '2026-09-02',
            'recorded_by' => $this->salesman->id,
        ]);

        $this->assertCount(1, Payment::pending()->get());
        $this->assertEquals($p1->id, Payment::pending()->first()->id);

        $this->assertCount(1, Payment::verified()->get());
        $this->assertEquals($p2->id, Payment::verified()->first()->id);

        $this->assertCount(1, Payment::filterByMethod('CHEQUE')->get());
        $this->assertCount(1, Payment::filterByStatus('PENDING_VERIFICATION')->get());
        $this->assertCount(1, Payment::search('778899')->get());
    }

    public function test_payment_factory_states(): void
    {
        $cashPayment = Payment::factory()->cash()->create([
            'customer_id' => $this->customer->id,
            'recorded_by' => $this->salesman->id,
        ]);
        $this->assertEquals(PaymentMethod::CASH, $cashPayment->payment_method);

        $chequePayment = Payment::factory()->cheque()->verified($this->admin)->create([
            'customer_id' => $this->customer->id,
            'recorded_by' => $this->salesman->id,
        ]);
        $this->assertEquals(PaymentMethod::CHEQUE, $chequePayment->payment_method);
        $this->assertTrue($chequePayment->isVerified());

        $moPayment = Payment::factory()->moneyOrder()->rejected($this->admin)->create([
            'customer_id' => $this->customer->id,
            'recorded_by' => $this->salesman->id,
        ]);
        $this->assertEquals(PaymentMethod::MONEY_ORDER, $moPayment->payment_method);
        $this->assertTrue($moPayment->isRejected());

        $revPayment = Payment::factory()->cheque()->reversed($this->accountant)->create([
            'customer_id' => $this->customer->id,
            'recorded_by' => $this->salesman->id,
        ]);
        $this->assertEquals(PaymentMethod::CHEQUE, $revPayment->payment_method);
        $this->assertTrue($revPayment->isReversed());
    }
}
