<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingPostgresConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalService $journalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
    }

    public function test_unique_account_code_database_constraint(): void
    {
        $this->expectException(QueryException::class);

        // Attempt direct duplicate insert bypassing service layer
        DB::table('accounts')->insert([
            'account_code' => '1010', // Already seeded
            'name' => 'Duplicate Cash',
            'type' => 'ASSET',
            'category' => 'CURRENT_ASSET',
            'normal_balance' => 'DEBIT',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_journal_number_sequence_database_uniqueness(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');

        $journal1 = $this->journalService->createAndPostJournal(
            ['description' => 'Seq test 1'],
            [
                ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '100.00'],
            ],
            $this->accountant
        );

        $journal2 = $this->journalService->createAndPostJournal(
            ['description' => 'Seq test 2'],
            [
                ['account_id' => $cash->id, 'debit' => '200.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '200.00'],
            ],
            $this->accountant
        );

        $this->assertNotEquals($journal1->journal_number, $journal2->journal_number);
    }

    public function test_posted_journal_entry_deletion_is_blocked_at_database_level(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');

        $journal = $this->journalService->createAndPostJournal(
            ['description' => 'Immutability test'],
            [
                ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '100.00'],
            ],
            $this->accountant
        );

        // Attempt direct SQL DELETE
        $isPostgres = DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            $this->expectException(QueryException::class);
            DB::table('journal_entries')->where('id', $journal->id)->delete();
        } else {
            // Under SQLite unit test environment, Model-level deleting hook blocks Eloquent delete
            $this->expectException(\LogicException::class);
            $journal->delete();
        }
    }

    public function test_posted_journal_lines_deletion_is_blocked(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');

        $journal = $this->journalService->createAndPostJournal(
            ['description' => 'Line Immutability test'],
            [
                ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '100.00'],
            ],
            $this->accountant
        );

        $line = $journal->lines->first();

        $isPostgres = DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            $this->expectException(QueryException::class);
            DB::table('journal_lines')->where('id', $line->id)->delete();
        } else {
            $this->expectException(\LogicException::class);
            $line->delete();
        }
    }
}
