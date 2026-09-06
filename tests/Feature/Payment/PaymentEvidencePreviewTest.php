<?php

namespace Tests\Feature\Payment;

use App\Enums\AccountStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentEvidencePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $accountant;
    protected User $salesman1;
    protected User $salesman2;
    protected User $inactiveAdmin;
    protected Customer $customer1;
    protected Customer $customer2;
    protected Payment $paymentWithEvidence;
    protected Payment $paymentWithoutEvidence;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@wholesale.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

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

        $this->salesman1 = User::factory()->create([
            'name' => 'Sam Salesman One',
            'email' => 'sales1@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman2 = User::factory()->create([
            'name' => 'Sally Salesman Two',
            'email' => 'sales2@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->inactiveAdmin = User::factory()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::SUSPENDED,
        ]);

        $this->customer1 = Customer::create([
            'salesman_id' => $this->salesman1->id,
            'name' => 'Customer One Corp',
            'code' => 'CUST-001',
            'contact_name' => 'Client One',
            'phone' => '+1-555-0101',
            'email' => 'c1@wholesale.test',
            'billing_address_line1' => '100 First St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'status' => \App\Enums\CustomerStatus::ACTIVE,
        ]);

        $this->customer2 = Customer::create([
            'salesman_id' => $this->salesman2->id,
            'name' => 'Customer Two Corp',
            'code' => 'CUST-002',
            'contact_name' => 'Client Two',
            'phone' => '+1-555-0102',
            'email' => 'c2@wholesale.test',
            'billing_address_line1' => '200 Second St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10002',
            'billing_country' => 'USA',
            'status' => \App\Enums\CustomerStatus::ACTIVE,
        ]);

        // Upload fake evidence to storage
        $file = UploadedFile::fake()->image('cheque_001.jpg', 600, 400)->size(150);
        $evidenceService = app(PaymentEvidenceService::class);
        $stored = $evidenceService->validateAndStoreEvidence($file);

        $this->paymentWithEvidence = Payment::create([
            'payment_number' => 'PAY-CHQ-001',
            'customer_id' => $this->customer1->id,
            'payment_method' => PaymentMethod::CHEQUE,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '3500.00',
            'payment_date' => '2026-09-06',
            'cheque_number' => 'CHQ-556677',
            'bank_name' => 'National Treasury Bank',
            'cheque_date' => '2026-09-05',
            'evidence_object_key' => $stored['evidence_object_key'],
            'evidence_original_name' => $stored['evidence_original_name'],
            'evidence_mime_type' => $stored['evidence_mime_type'],
            'evidence_size_bytes' => $stored['evidence_size_bytes'],
            'evidence_uploaded_at' => $stored['evidence_uploaded_at'],
            'recorded_by' => $this->salesman1->id,
        ]);

        $this->paymentWithoutEvidence = Payment::create([
            'payment_number' => 'PAY-CSH-001',
            'customer_id' => $this->customer1->id,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => '1000.00',
            'payment_date' => '2026-09-06',
            'recorded_by' => $this->salesman1->id,
        ]);
    }

    public function test_admin_can_retrieve_evidence_preview_url(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            route('admin.payments.evidence.url', $this->paymentWithEvidence)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'url',
            'expires_at',
            'mime_type',
            'original_name',
        ]);
    }

    public function test_accountant_can_retrieve_evidence_preview_url(): void
    {
        $response = $this->actingAs($this->accountant)->getJson(
            route('admin.payments.evidence.url', $this->paymentWithEvidence)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['url', 'expires_at']);
    }

    public function test_super_admin_can_retrieve_evidence_preview_url(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson(
            route('admin.payments.evidence.url', $this->paymentWithEvidence)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['url', 'expires_at']);
    }

    public function test_assigned_salesman_can_retrieve_evidence_preview_url(): void
    {
        $response = $this->actingAs($this->salesman1)->getJson(
            route('admin.payments.evidence.url', $this->paymentWithEvidence)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['url', 'expires_at']);
    }

    public function test_out_of_scope_salesman_is_rejected_with_anti_idor_protection(): void
    {
        // Salesman 2 attempting to view evidence for customer 1's payment
        $response = $this->actingAs($this->salesman2)->getJson(
            route('admin.payments.evidence.url', $this->paymentWithEvidence)
        );

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_is_rejected(): void
    {
        $response = $this->getJson(
            route('admin.payments.evidence.url', $this->paymentWithEvidence)
        );

        $response->assertStatus(401);
    }

    public function test_inactive_user_is_rejected(): void
    {
        $response = $this->actingAs($this->inactiveAdmin)->getJson(
            route('admin.payments.evidence.url', $this->paymentWithEvidence)
        );

        $response->assertStatus(403);
    }

    public function test_payment_with_no_evidence_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            route('admin.payments.evidence.url', $this->paymentWithoutEvidence)
        );

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'This payment record has no visual evidence attached.',
        ]);
    }

    public function test_stream_evidence_returns_binary_stream_for_authorized_user(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.payments.evidence.stream', $this->paymentWithEvidence)
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_stream_evidence_rejects_out_of_scope_salesman(): void
    {
        $response = $this->actingAs($this->salesman2)->get(
            route('admin.payments.evidence.stream', $this->paymentWithEvidence)
        );

        $response->assertStatus(403);
    }
}
