<?php

declare(strict_types=1);

namespace Tests\Feature\Payable;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\PayableTransaction;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Payable\PayableLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayablePostgresConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected PayableLedgerService $ledgerService;
    protected Supplier $supplier;
    protected SupplierBill $bill;
    protected PayableTransaction $transaction;

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
        $this->supplier = $this->ledgerService->createSupplier(['name' => 'Constraint Test Supplier'], $this->admin);

        $this->bill = $this->ledgerService->createBill($this->supplier, [
            'bill_date' => '2026-09-01',
            'subtotal' => '500.00',
            'tax_total' => '0.00',
            'total_amount' => '500.00',
        ], $this->accountant);

        $this->transaction = $this->ledgerService->recordSupplierBill($this->bill, $this->accountant);
    }

    public function test_postgres_trigger_prevents_raw_sql_delete(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger test requires pgsql driver.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Payable transactions are permanent immutable financial ledger records and cannot be deleted.');

        DB::statement('DELETE FROM payable_transactions WHERE id = ?', [$this->transaction->id]);
    }

    public function test_postgres_trigger_prevents_raw_sql_update_of_financial_amount(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger test requires pgsql driver.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Payable transactions are immutable financial records.');

        DB::statement('UPDATE payable_transactions SET amount = 9999.00 WHERE id = ?', [$this->transaction->id]);
    }

    public function test_postgres_unique_constraint_blocks_duplicate_source_posting(): void
    {
        $this->expectException(QueryException::class);

        // Attempt raw SQL insert of identical source_type, source_id, and type
        DB::table('payable_transactions')->insert([
            'transaction_number' => 'AP-2026-999999',
            'supplier_id' => $this->supplier->id,
            'supplier_bill_id' => $this->bill->id,
            'source_type' => 'SUPPLIER_BILL',
            'source_id' => $this->bill->id,
            'source_number' => $this->bill->bill_number,
            'type' => 'SUPPLIER_BILL',
            'amount' => 500.00,
            'debit_amount' => 0.00,
            'credit_amount' => 500.00,
            'transaction_date' => '2026-09-01',
            'posting_date' => '2026-09-01',
            'currency' => 'USD',
            'description' => 'Duplicate attempt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgres_fk_restrict_prevents_supplier_deletion(): void
    {
        $this->expectException(QueryException::class);

        // Cannot delete supplier that has posted transactions
        DB::statement('DELETE FROM suppliers WHERE id = ?', [$this->supplier->id]);
    }
}
