<?php

declare(strict_types=1);

namespace Tests\Feature\Payable;

use App\Enums\AccountStatus;
use App\Enums\PayableTransactionType;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\PayableTransaction;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Payable\PayableLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PayableLedgerTest extends TestCase
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

    public function test_can_create_supplier_with_sequential_code(): void
    {
        $supplier1 = $this->ledgerService->createSupplier([
            'name' => 'Acme Supplies',
            'contact_person' => 'John Doe',
            'email' => 'john@acme.com',
            'phone' => '+1 555-0100',
            'payment_terms_days' => 30,
        ], $this->admin);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier1->id,
            'name' => 'Acme Supplies',
            'supplier_code' => 'SUP-000001',
            'status' => SupplierStatus::ACTIVE->value,
            'payment_terms_days' => 30,
        ]);

        $supplier2 = $this->ledgerService->createSupplier([
            'name' => 'Beta Global',
            'payment_terms_days' => 45,
        ], $this->admin);

        $this->assertEquals('SUP-000002', $supplier2->supplier_code);
    }

    public function test_draft_bill_does_not_create_payable_transaction(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Delta Tech'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '1000.00',
            'tax_total' => '100.00',
            'total_amount' => '1100.00',
            'description' => 'Server hardware delivery',
        ], $this->accountant);

        $this->assertEquals(SupplierBillStatus::DRAFT, $bill->status);
        $this->assertEquals('1100.00', $bill->total_amount);
        $this->assertEquals('1100.00', $bill->amount_due);
        $this->assertEquals('0.00', $bill->amount_paid);

        // No transaction in subledger yet
        $this->assertDatabaseCount('payable_transactions', 0);
        $this->assertEquals('0.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));
    }

    public function test_posting_supplier_bill_creates_liability_transaction(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Apex Parts'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '2500.00',
            'tax_total' => '250.00',
            'total_amount' => '2750.00',
        ], $this->accountant);

        $txn = $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $bill->refresh();
        $this->assertEquals(SupplierBillStatus::POSTED, $bill->status);
        $this->assertNotNull($bill->posted_at);

        $this->assertDatabaseHas('payable_transactions', [
            'id' => $txn->id,
            'supplier_id' => $supplier->id,
            'supplier_bill_id' => $bill->id,
            'source_type' => 'SUPPLIER_BILL',
            'source_id' => $bill->id,
            'type' => PayableTransactionType::SUPPLIER_BILL->value,
            'amount' => '2750.00',
            'credit_amount' => '2750.00',
            'debit_amount' => '0.00',
            'running_balance' => '2750.00',
        ]);

        $this->assertEquals('2750.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));
    }

    public function test_recording_partial_payment_reduces_outstanding(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Zenith Corp'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '10000.00',
            'tax_total' => '0.00',
            'total_amount' => '10000.00',
        ], $this->accountant);

        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        // First partial payment: $4,000
        $payment1 = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-05',
            'amount' => '4000.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER->value,
            'reference_number' => 'NEFT-8891',
        ], $this->accountant);

        $bill->refresh();
        $this->assertEquals(SupplierBillStatus::PARTIALLY_PAID, $bill->status);
        $this->assertEquals('4000.00', $bill->amount_paid);
        $this->assertEquals('6000.00', $bill->amount_due);
        $this->assertEquals('6000.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));

        // Second partial payment: $3,000
        $payment2 = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-10',
            'amount' => '3000.00',
            'payment_method' => SupplierPaymentMethod::CHEQUE->value,
            'reference_number' => 'CHQ-1002',
        ], $this->accountant);

        $bill->refresh();
        $this->assertEquals(SupplierBillStatus::PARTIALLY_PAID, $bill->status);
        $this->assertEquals('7000.00', $bill->amount_paid);
        $this->assertEquals('3000.00', $bill->amount_due);
        $this->assertEquals('3000.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));

        // Final payment: $3,000 -> full settlement
        $payment3 = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-15',
            'amount' => '3000.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER->value,
        ], $this->accountant);

        $bill->refresh();
        $this->assertEquals(SupplierBillStatus::PAID, $bill->status);
        $this->assertEquals('10000.00', $bill->amount_paid);
        $this->assertEquals('0.00', $bill->amount_due);
        $this->assertEquals('0.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));

        // 4 total transactions in ledger (1 bill + 3 payments)
        $this->assertDatabaseCount('payable_transactions', 4);
    }

    public function test_overpayment_is_strictly_rejected(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Overpay Ltd'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '1000.00',
            'tax_total' => '0.00',
            'total_amount' => '1000.00',
        ], $this->accountant);

        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $this->expectException(ValidationException::class);

        // Attempting to pay $1,200 on a $1,000 bill
        $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-02',
            'amount' => '1200.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER->value,
        ], $this->accountant);
    }

    public function test_payment_reversal_reopens_bill_and_restores_liability(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Reverse Demo Corp'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '5000.00',
            'tax_total' => '0.00',
            'total_amount' => '5000.00',
        ], $this->accountant);

        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $payment = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-05',
            'amount' => '3000.00',
            'payment_method' => SupplierPaymentMethod::CHEQUE->value,
            'reference_number' => 'CHQ-999',
        ], $this->accountant);

        $this->assertEquals('2000.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));

        // Reverse the $3,000 cheque payment
        $reversalTxn = $this->ledgerService->recordPaymentReversal(
            $payment,
            'Cheque returned unpaid by drawee bank',
            $this->accountant
        );

        $payment->refresh();
        $bill->refresh();

        $this->assertEquals(SupplierPaymentStatus::REVERSED, $payment->status);
        $this->assertEquals('Cheque returned unpaid by drawee bank', $payment->reversal_reason);

        // Bill restored
        $this->assertEquals(SupplierBillStatus::POSTED, $bill->status);
        $this->assertEquals('0.00', $bill->amount_paid);
        $this->assertEquals('5000.00', $bill->amount_due);

        // Outstanding restored to $5,000
        $this->assertEquals('5000.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));

        $this->assertDatabaseHas('payable_transactions', [
            'id' => $reversalTxn->id,
            'source_type' => 'PAYMENT_REVERSAL',
            'source_id' => $payment->id,
            'type' => PayableTransactionType::PAYMENT_REVERSAL->value,
            'credit_amount' => '3000.00',
            'debit_amount' => '0.00',
            'running_balance' => '5000.00',
        ]);
    }

    public function test_duplicate_payment_reversal_is_blocked(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Dup Reverse Corp'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '2000.00',
            'tax_total' => '0.00',
            'total_amount' => '2000.00',
        ], $this->accountant);

        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $payment = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-02',
            'amount' => '2000.00',
            'payment_method' => SupplierPaymentMethod::CASH->value,
        ], $this->accountant);

        $this->ledgerService->recordPaymentReversal($payment, 'Duplicate entry correction', $this->accountant);

        $this->expectException(ValidationException::class);

        // Second reversal attempt on same payment must fail
        $this->ledgerService->recordPaymentReversal($payment, 'Duplicate entry correction again', $this->accountant);
    }

    public function test_posted_payable_transaction_is_immutable(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Immutable Corp'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '1500.00',
            'tax_total' => '0.00',
            'total_amount' => '1500.00',
        ], $this->accountant);

        $txn = $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        // Attempting to alter financial amount via Eloquent throws LogicException
        $this->expectException(LogicException::class);
        $txn->amount = '9999.00';
        $txn->save();
    }

    public function test_posted_payable_transaction_cannot_be_deleted(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Permanent Corp'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '800.00',
            'tax_total' => '0.00',
            'total_amount' => '800.00',
        ], $this->accountant);

        $txn = $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $this->expectException(LogicException::class);
        $txn->delete();
    }
}
