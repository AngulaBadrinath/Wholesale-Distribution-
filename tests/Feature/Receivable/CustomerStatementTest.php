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
use App\Services\Receivable\CustomerStatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected CustomerStatementService $statementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'name' => 'Zenith Distributors',
            'code' => 'CUST-ZENITH-01',
            'contact_name' => 'Zachary Zenith',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '75000.00',
            'balance' => '0.00',
            'billing_address_line1' => '777 Commerce Blvd',
            'billing_city' => 'Zenith City',
            'billing_state' => 'IL',
            'billing_postal_code' => '60007',
            'billing_country' => 'US',
            'email' => 'ar@zenithdist.com',
            'phone' => '312-555-0777',
        ]);

        $this->statementService = app(CustomerStatementService::class);
    }

    protected function createTxn(
        string $txnNumber,
        ReceivableTransactionType $type,
        string $debit,
        string $credit,
        string $txnDate,
        string $postingDate,
        string $desc
    ): ReceivableTransaction {
        return ReceivableTransaction::create([
            'transaction_number' => $txnNumber,
            'customer_id' => $this->customer->id,
            'type' => $type,
            'source_type' => 'manual',
            'source_id' => rand(1000, 9999),
            'source_number' => 'REF-'.$txnNumber,
            'amount' => max((float) $debit, (float) $credit),
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'transaction_date' => $txnDate,
            'posting_date' => $postingDate,
            'currency' => 'USD',
            'description' => $desc,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_statement_calculates_opening_balance_and_running_balance_chronologically(): void
    {
        // 1. Transactions prior to statement period (e.g. before 2026-09-01)
        // Invoice $1000 on 2026-08-10, Payment $400 on 2026-08-20 -> Opening balance should be $600.00
        $this->createTxn('AR-2026-000001', ReceivableTransactionType::INVOICE_CHARGE, '1000.00', '0.00', '2026-08-10', '2026-08-10', 'Invoice 1');
        $this->createTxn('AR-2026-000002', ReceivableTransactionType::PAYMENT, '0.00', '400.00', '2026-08-20', '2026-08-20', 'Payment 1');

        // 2. Transactions during statement period (2026-09-01 to 2026-09-30)
        // Txn A: Invoice $500 on 2026-09-05 -> Running balance: $600 + $500 = $1100
        $this->createTxn('AR-2026-000003', ReceivableTransactionType::INVOICE_CHARGE, '500.00', '0.00', '2026-09-05', '2026-09-05', 'Invoice 2');
        // Txn B: Payment $700 on 2026-09-12 -> Running balance: $1100 - $700 = $400
        $this->createTxn('AR-2026-000004', ReceivableTransactionType::PAYMENT, '0.00', '700.00', '2026-09-12', '2026-09-12', 'Payment 2');
        // Txn C: Credit Note $150 on 2026-09-20 -> Running balance: $400 - $150 = $250
        $this->createTxn('AR-2026-000005', ReceivableTransactionType::CREDIT_NOTE, '0.00', '150.00', '2026-09-20', '2026-09-20', 'Credit Note 1');

        $statement = $this->statementService->generateStatement(
            $this->customer,
            '2026-09-01',
            '2026-09-30'
        );

        // Verify Opening Balance
        $this->assertSame('600.00', $statement['opening_balance']);

        // Verify Period Totals
        $this->assertSame('500.00', $statement['total_debits']);
        $this->assertSame('850.00', $statement['total_credits']);

        // Verify Closing Balance: $600 + $500 - $850 = $250.00
        $this->assertSame('250.00', $statement['closing_balance']);

        // Verify Chronological Running Balances
        $this->assertCount(3, $statement['transactions']);
        $this->assertSame('1100.00', $statement['transactions'][0]['running_balance']);
        $this->assertSame('400.00', $statement['transactions'][1]['running_balance']);
        $this->assertSame('250.00', $statement['transactions'][2]['running_balance']);
    }

    public function test_statement_deterministic_ordering_with_same_date(): void
    {
        // Two transactions on the same date:
        $t1 = $this->createTxn('AR-2026-000010', ReceivableTransactionType::INVOICE_CHARGE, '800.00', '0.00', '2026-09-10', '2026-09-10', 'Invoice A');
        $t2 = $this->createTxn('AR-2026-000011', ReceivableTransactionType::PAYMENT, '0.00', '300.00', '2026-09-10', '2026-09-10', 'Payment B');

        $statement = $this->statementService->generateStatement($this->customer, '2026-09-01', '2026-09-30');

        $this->assertSame($t1->id, $statement['transactions'][0]['id']);
        $this->assertSame('800.00', $statement['transactions'][0]['running_balance']);

        $this->assertSame($t2->id, $statement['transactions'][1]['id']);
        $this->assertSame('500.00', $statement['transactions'][1]['running_balance']);
    }
}
