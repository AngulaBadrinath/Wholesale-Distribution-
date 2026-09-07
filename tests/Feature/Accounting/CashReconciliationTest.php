<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\CashReconciliationService;
use App\Services\Accounting\JournalMappingService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalService $journalService;
    protected JournalMappingService $mappingService;
    protected CashReconciliationService $reconciliationService;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'name' => 'Cash Customer',
            'code' => 'CUST-CASH-01',
            'contact_name' => 'Jane Cash',
            'phone' => '1234567890',
            'email' => 'jane@cash.com',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => '50000.00',
            'payment_terms' => PaymentTerms::NET_30,
            'billing_address_line1' => '123 Main St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
        $this->mappingService = app(JournalMappingService::class);
        $this->reconciliationService = app(CashReconciliationService::class);
    }

    public function test_can_reconcile_cash_account_with_matching_gl_and_operations(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010');

        $payment = Payment::create([
            'payment_number' => 'PAY-2026-REC-01',
            'customer_id' => $this->customer->id,
            'amount' => '2500.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::VERIFIED,
            'recorded_by' => $this->accountant->id,
            'verified_by' => $this->accountant->id,
        ]);

        // Post into GL
        $this->mappingService->postPaymentVerified($payment, $this->accountant);

        $summary = $this->reconciliationService->getReconciliationSummary($cashAccount);

        $this->assertTrue($summary['is_reconciled']);
        $this->assertEquals('2500.00', $summary['gl_balance']);
        $this->assertEquals('2500.00', $summary['operational_receipts']);
        $this->assertEquals('0.00', $summary['difference']);

        // Create official reconciliation record
        $record = $this->reconciliationService->createReconciliationSession($cashAccount, [
            'statement_date' => now()->toDateString(),
            'ending_balance' => '2500.00',
            'notes' => 'Month-end cash drawer reconciliation',
        ], $this->accountant);

        $this->assertNotNull($record);
        $this->assertEquals(ReconciliationStatus::RECONCILED, $record->status);
        $this->assertEquals('0.00', $record->difference);
    }

    public function test_identifies_unreconciled_discrepancy(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010');

        // Payment verified in operations but NOT posted to GL
        Payment::create([
            'payment_number' => 'PAY-2026-REC-02',
            'customer_id' => $this->customer->id,
            'amount' => '1000.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::VERIFIED,
            'recorded_by' => $this->accountant->id,
            'verified_by' => $this->accountant->id,
        ]);

        $summary = $this->reconciliationService->getReconciliationSummary($cashAccount);

        $this->assertFalse($summary['is_reconciled']);
        $this->assertEquals('0.00', $summary['gl_balance']);
        $this->assertEquals('1000.00', $summary['operational_receipts']);
        $this->assertEquals('-1000.00', $summary['difference']);
    }
}
