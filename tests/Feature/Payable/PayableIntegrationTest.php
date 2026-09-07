<?php

declare(strict_types=1);

namespace Tests\Feature\Payable;

use App\Enums\AccountStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Payable\PayableLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected PayableLedgerService $ledgerService;

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

        $this->ledgerService = app(PayableLedgerService::class);
    }

    public function test_admin_can_create_supplier_via_http(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/payables/suppliers', [
            'name' => 'Global Logistics Inc',
            'contact_person' => 'Alice Smith',
            'email' => 'alice@globallogistics.com',
            'phone' => '+1 555-4321',
            'payment_terms_days' => 45,
            'tax_id' => 'TAX-998877',
        ]);

        $supplier = Supplier::where('name', 'Global Logistics Inc')->first();
        $this->assertNotNull($supplier);
        $this->assertEquals('SUP-000001', $supplier->supplier_code);

        $response->assertRedirect(route('admin.payables.show', $supplier->id));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Global Logistics Inc',
            'supplier_code' => 'SUP-000001',
            'payment_terms_days' => 45,
        ]);
    }

    public function test_accountant_can_record_bill_and_post_via_http(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Prime Distribution'], $this->admin);

        $response = $this->actingAs($this->accountant)->post('/admin/payables/bills', [
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'INV-2026-001',
            'bill_date' => '2026-09-05',
            'due_date' => '2026-10-05',
            'subtotal' => '3000.00',
            'tax_total' => '300.00',
            'total_amount' => '3300.00',
            'description' => 'Packaging supplies Q3',
            'post_immediately' => true,
        ]);

        $response->assertRedirect();

        $bill = SupplierBill::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($bill);
        $this->assertEquals(SupplierBillStatus::POSTED, $bill->status);
        $this->assertEquals('3300.00', $bill->total_amount);

        // Verify subledger transaction created
        $this->assertDatabaseHas('payable_transactions', [
            'supplier_id' => $supplier->id,
            'supplier_bill_id' => $bill->id,
            'amount' => '3300.00',
            'credit_amount' => '3300.00',
        ]);
    }

    public function test_accountant_can_record_supplier_payment_via_http(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Mega Corp'], $this->admin);
        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '5000.00',
            'tax_total' => '0.00',
            'total_amount' => '5000.00',
        ], $this->accountant);
        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $response = $this->actingAs($this->accountant)->post('/admin/payables/payments', [
            'supplier_id' => $supplier->id,
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-06',
            'amount' => '2000.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER->value,
            'reference_number' => 'WIRE-9988',
            'notes' => 'Partial advance wire',
        ]);

        $response->assertRedirect();

        $bill->refresh();
        $this->assertEquals(SupplierBillStatus::PARTIALLY_PAID, $bill->status);
        $this->assertEquals('2000.00', $bill->amount_paid);
        $this->assertEquals('3000.00', $bill->amount_due);

        $payment = SupplierPayment::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('2000.00', $payment->amount);
        $this->assertEquals(SupplierPaymentStatus::COMPLETED, $payment->status);

        $this->assertEquals('3000.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));
    }

    public function test_accountant_can_reverse_supplier_payment_via_http(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Reversal HTTP Corp'], $this->admin);
        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '4000.00',
            'tax_total' => '0.00',
            'total_amount' => '4000.00',
        ], $this->accountant);
        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $payment = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-04',
            'amount' => '4000.00',
            'payment_method' => SupplierPaymentMethod::CHEQUE->value,
            'reference_number' => 'CHQ-5566',
        ], $this->accountant);

        $response = $this->actingAs($this->accountant)->post("/admin/payables/payments/{$payment->id}/reverse", [
            'reversal_reason' => 'Cheque signature mismatch returned by bank',
        ]);

        $response->assertRedirect();

        $payment->refresh();
        $bill->refresh();

        $this->assertEquals(SupplierPaymentStatus::REVERSED, $payment->status);
        $this->assertEquals(SupplierBillStatus::POSTED, $bill->status);
        $this->assertEquals('4000.00', $bill->amount_due);
        $this->assertEquals('4000.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));
    }

    public function test_index_json_endpoint_returns_aggregated_summary(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Alpha Suppliers'], $this->admin);
        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '1500.00',
            'tax_total' => '0.00',
            'total_amount' => '1500.00',
        ], $this->accountant);
        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $response = $this->actingAs($this->accountant)->getJson('/admin/payables');

        $response->assertOk()
            ->assertJsonStructure([
                'suppliers' => ['data', 'total'],
                'summary' => ['total_ap_outstanding', 'total_suppliers', 'active_bills_count', 'total_paid_bills'],
            ]);

        $this->assertEquals('1500.00', $response->json('summary.total_ap_outstanding'));
        $this->assertEquals(1, $response->json('summary.total_suppliers'));
        $this->assertEquals(1, $response->json('summary.active_bills_count'));
    }

    public function test_show_json_endpoint_returns_complete_subledger(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Show Supplier'], $this->admin);
        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '2000.00',
            'tax_total' => '0.00',
            'total_amount' => '2000.00',
        ], $this->accountant);
        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $response = $this->actingAs($this->accountant)->getJson("/admin/payables/{$supplier->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'supplier',
                'outstanding_balance',
                'bills' => ['data'],
                'payments' => ['data'],
                'transactions' => ['data'],
            ]);

        $this->assertEquals('2000.00', $response->json('outstanding_balance'));
        $this->assertCount(1, $response->json('transactions.data'));
    }
}
