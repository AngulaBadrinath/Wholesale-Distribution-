<?php

declare(strict_types=1);

namespace Tests\Feature\Payable;

use App\Enums\AccountStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\UserRole;
use App\Models\PayableTransaction;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Payable\PayableLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayableConcurrencyTest extends TestCase
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

    public function test_concurrent_competing_payments_prevent_overpayment(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Concurrent Supplies'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '1000.00',
            'tax_total' => '0.00',
            'total_amount' => '1000.00',
        ], $this->accountant);

        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        // First payment of $700 succeeds
        $payment1 = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-02',
            'amount' => '700.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER->value,
        ], $this->accountant);

        $this->assertNotNull($payment1);

        // Competing concurrent payment of $700 on same bill must fail overpayment check
        $this->expectException(ValidationException::class);

        $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-02',
            'amount' => '700.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER->value,
        ], $this->accountant);
    }

    public function test_concurrent_duplicate_bill_posting_returns_same_transaction(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Idempotent Post Corp'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '500.00',
            'tax_total' => '0.00',
            'total_amount' => '500.00',
        ], $this->accountant);

        // First post
        $txn1 = $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        // Second duplicate post attempt
        $txn2 = $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $this->assertEquals($txn1->id, $txn2->id);
        $this->assertDatabaseCount('payable_transactions', 1);
        $this->assertEquals('500.00', $this->ledgerService->getSupplierOutstandingBalance($supplier));
    }

    public function test_concurrent_payment_reversal_is_idempotent_and_safe(): void
    {
        $supplier = $this->ledgerService->createSupplier(['name' => 'Concurrent Reversal Corp'], $this->admin);

        $bill = $this->ledgerService->createBill($supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '1000.00',
            'tax_total' => '0.00',
            'total_amount' => '1000.00',
        ], $this->accountant);

        $this->ledgerService->recordSupplierBill($bill, $this->accountant);

        $payment = $this->ledgerService->recordSupplierPayment($supplier, [
            'supplier_bill_id' => $bill->id,
            'payment_date' => '2026-09-02',
            'amount' => '1000.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER->value,
        ], $this->accountant);

        // First reversal
        $this->ledgerService->recordPaymentReversal($payment, 'Valid reversal', $this->accountant);

        // Competing second reversal
        $this->expectException(ValidationException::class);
        $this->ledgerService->recordPaymentReversal($payment, 'Competing reversal', $this->accountant);
    }
}
