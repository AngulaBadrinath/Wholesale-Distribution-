<?php

declare(strict_types=1);

namespace Tests\Feature\Receivable;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReceivableTransactionType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\ReceivableTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReceivablePostgresConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'name' => 'Constraint Testing Corp',
            'code' => 'CUST-CONST-01',
            'contact_name' => 'Carl Constraint',
            'status' => CustomerStatus::ACTIVE,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '50000.00',
            'balance' => '0.00',
            'billing_address_line1' => '100 Constraint Way',
            'billing_city' => 'Postgres City',
            'billing_state' => 'CA',
            'billing_postal_code' => '94016',
            'billing_country' => 'US',
            'email' => 'finance@constraint.com',
            'phone' => '415-555-0100',
        ]);
    }

    protected function createDirectTransaction(
        string $txnNumber,
        string $sourceType,
        int $sourceId,
        ReceivableTransactionType $type,
        string $amount,
        string $debit,
        string $credit
    ): ReceivableTransaction {
        return ReceivableTransaction::create([
            'transaction_number' => $txnNumber,
            'customer_id' => $this->customer->id,
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_number' => 'REF-'.$txnNumber,
            'amount' => $amount,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'transaction_date' => Carbon::now()->toDateString(),
            'posting_date' => Carbon::now()->toDateString(),
            'currency' => 'USD',
            'description' => 'Postgres constraint direct transaction test',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_unique_constraint_on_source_type_source_id_and_type_blocks_duplicates(): void
    {
        $this->createDirectTransaction(
            'AR-2026-900001',
            'invoice',
            101,
            ReceivableTransactionType::INVOICE_CHARGE,
            '1000.00',
            '1000.00',
            '0.00'
        );

        // Attempt second direct DB insertion with identical source_type, source_id, and type
        $this->expectException(QueryException::class);

        $this->createDirectTransaction(
            'AR-2026-900002',
            'invoice',
            101,
            ReceivableTransactionType::INVOICE_CHARGE,
            '1000.00',
            '1000.00',
            '0.00'
        );
    }

    public function test_unique_transaction_number_constraint(): void
    {
        $this->createDirectTransaction(
            'AR-2026-900010',
            'invoice',
            201,
            ReceivableTransactionType::INVOICE_CHARGE,
            '500.00',
            '500.00',
            '0.00'
        );

        // Attempt second direct DB insertion with identical transaction_number
        $this->expectException(QueryException::class);

        $this->createDirectTransaction(
            'AR-2026-900010',
            'invoice',
            202,
            ReceivableTransactionType::INVOICE_CHARGE,
            '600.00',
            '600.00',
            '0.00'
        );
    }

    public function test_foreign_key_restricts_customer_deletion_when_transactions_exist(): void
    {
        $this->createDirectTransaction(
            'AR-2026-900020',
            'invoice',
            301,
            ReceivableTransactionType::INVOICE_CHARGE,
            '750.00',
            '750.00',
            '0.00'
        );

        // Attempting to delete customer must fail due to FK RESTRICT
        $this->expectException(QueryException::class);
        $this->customer->delete();
    }

    public function test_postgresql_trigger_or_eloquent_blocks_mutation_of_immutable_financial_fields(): void
    {
        $txn = $this->createDirectTransaction(
            'AR-2026-900030',
            'invoice',
            401,
            ReceivableTransactionType::INVOICE_CHARGE,
            '800.00',
            '800.00',
            '0.00'
        );

        // Test at raw DB level if PostgreSQL driver is active
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->expectException(QueryException::class);
            DB::statement('UPDATE receivable_transactions SET debit_amount = 400.00 WHERE id = ?', [$txn->id]);
        } else {
            $this->expectException(\LogicException::class);
            $txn->update(['debit_amount' => '400.00']);
        }
    }

    public function test_postgresql_trigger_or_eloquent_blocks_deletion_of_posted_transaction(): void
    {
        $txn = $this->createDirectTransaction(
            'AR-2026-900040',
            'invoice',
            501,
            ReceivableTransactionType::INVOICE_CHARGE,
            '900.00',
            '900.00',
            '0.00'
        );

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->expectException(QueryException::class);
            DB::statement('DELETE FROM receivable_transactions WHERE id = ?', [$txn->id]);
        } else {
            $this->expectException(\LogicException::class);
            $txn->delete();
        }
    }
}
