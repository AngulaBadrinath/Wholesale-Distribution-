<?php

namespace Tests\Feature\Payment;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentRejectionCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Payment $pendingPayment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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
            'name' => 'Starlight Mart',
            'code' => 'CUST-STAR',
            'contact_name' => 'Stella Star',
            'phone' => '+1-555-0601',
            'email' => 'star@wholesale.test',
            'billing_address_line1' => '100 Star Blvd',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->pendingPayment = Payment::create([
            'payment_number' => 'PAY-2026-REJ-01',
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentMethod::CHEQUE,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '800.00',
            'payment_date' => now()->toDateString(),
            'bank_name' => 'City Bank',
            'cheque_number' => 'CHQ-001122',
            'cheque_date' => now()->toDateString(),
            'recorded_by' => $this->salesman->id,
            'version' => 1,
        ]);
    }

    public function test_admin_can_reject_pending_payment(): void
    {
        $payload = [
            'rejection_reason_code' => PaymentRejectionReason::ILLEGIBLE_EVIDENCE->value,
            'rejection_notes' => 'Cheque image is completely blurry and signature cannot be read.',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.payments.reject', $this->pendingPayment),
            $payload
        );

        $response->assertStatus(302);

        $this->pendingPayment->refresh();
        $this->assertEquals(PaymentTransactionStatus::REJECTED, $this->pendingPayment->status);
        $this->assertEquals($this->admin->id, $this->pendingPayment->rejected_by);
        $this->assertEquals(PaymentRejectionReason::ILLEGIBLE_EVIDENCE, $this->pendingPayment->rejection_reason_code);
        $this->assertEquals('Cheque image is completely blurry and signature cannot be read.', $this->pendingPayment->rejection_notes);
        $this->assertNotNull($this->pendingPayment->rejected_at);
        $this->assertEquals(2, $this->pendingPayment->version);
    }

    public function test_cannot_reject_already_verified_payment(): void
    {
        $this->pendingPayment->update([
            'status' => PaymentTransactionStatus::VERIFIED,
            'verified_by' => $this->admin->id,
        ]);

        $payload = [
            'rejection_reason_code' => PaymentRejectionReason::AMOUNT_MISMATCH->value,
            'rejection_notes' => 'Amount mismatch discovered.',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.payments.reject', $this->pendingPayment),
            $payload
        );

        $response->assertStatus(409);
    }

    public function test_salesman_cannot_reject_payment(): void
    {
        $payload = [
            'rejection_reason_code' => PaymentRejectionReason::ILLEGIBLE_EVIDENCE->value,
            'rejection_notes' => 'Attempting to reject.',
        ];

        $response = $this->actingAs($this->salesman)->post(
            route('admin.payments.reject', $this->pendingPayment),
            $payload
        );

        $response->assertStatus(403);
    }

    public function test_can_correct_and_resubmit_rejected_payment(): void
    {
        // Reject payment first
        $this->pendingPayment->update([
            'status' => PaymentTransactionStatus::REJECTED,
            'rejected_by' => $this->admin->id,
            'rejection_reason_code' => PaymentRejectionReason::ILLEGIBLE_EVIDENCE,
            'rejection_notes' => 'Need sharper scan',
            'version' => 2,
        ]);

        $newScan = UploadedFile::fake()->image('sharp_cheque_scan.jpg', 1000, 700)->size(250);

        $correctionPayload = [
            'cheque_number' => 'CHQ-001122-CORR',
            'evidence' => $newScan,
            'notes' => 'Uploaded high resolution scan of the cheque.',
        ];

        $response = $this->actingAs($this->salesman)->post(
            route('salesman.payments.correct', $this->pendingPayment),
            $correctionPayload
        );

        $response->assertStatus(302);

        $this->pendingPayment->refresh();
        $this->assertEquals(PaymentTransactionStatus::PENDING_VERIFICATION, $this->pendingPayment->status);
        $this->assertEquals('CHQ-001122-CORR', $this->pendingPayment->cheque_number);
        $this->assertEquals('sharp_cheque_scan.jpg', $this->pendingPayment->evidence_original_name);
        $this->assertEquals(3, $this->pendingPayment->version);

        // Now Admin can successfully verify the corrected payment
        $verifyResponse = $this->actingAs($this->admin)->post(
            route('admin.payments.verify', $this->pendingPayment)
        );
        $verifyResponse->assertStatus(302);

        $this->pendingPayment->refresh();
        $this->assertEquals(PaymentTransactionStatus::VERIFIED, $this->pendingPayment->status);
    }

    public function test_cannot_correct_verified_payment(): void
    {
        $this->pendingPayment->update([
            'status' => PaymentTransactionStatus::VERIFIED,
            'verified_by' => $this->admin->id,
        ]);

        $correctionPayload = [
            'notes' => 'Attempting to modify verified transaction',
        ];

        $response = $this->actingAs($this->salesman)->post(
            route('salesman.payments.correct', $this->pendingPayment),
            $correctionPayload
        );

        $response->assertStatus(409);
    }
}
