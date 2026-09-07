<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\JournalEntryType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalService $journalService;
    protected GeneralLedgerService $glService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
        $this->glService = app(GeneralLedgerService::class);
    }

    public function test_can_retrieve_account_ledger_with_running_balances(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010'); // Debit normal
        $capitalAccount = $this->accountService->resolveAccount('3010'); // Credit normal
        $salesAccount = $this->accountService->resolveAccount('4010'); // Credit normal

        // Entry 1: Capital deposit 50,000.00 on 2026-09-01
        $this->journalService->createAndPostJournal(
            [
                'entry_type' => JournalEntryType::MANUAL,
                'accounting_date' => '2026-09-01',
                'description' => 'Initial capital',
            ],
            [
                ['account_id' => $cashAccount->id, 'debit' => '50000.00', 'credit' => '0.00'],
                ['account_id' => $capitalAccount->id, 'debit' => '0.00', 'credit' => '50000.00'],
            ],
            $this->accountant
        );

        // Entry 2: Cash sale 1,500.00 on 2026-09-05
        $this->journalService->createAndPostJournal(
            [
                'entry_type' => JournalEntryType::SYSTEM,
                'accounting_date' => '2026-09-05',
                'description' => 'Cash sale',
            ],
            [
                ['account_id' => $cashAccount->id, 'debit' => '1500.00', 'credit' => '0.00'],
                ['account_id' => $salesAccount->id, 'debit' => '0.00', 'credit' => '1500.00'],
            ],
            $this->accountant
        );

        // Fetch Cash Account Statement
        $ledger = $this->glService->getAccountLedger($cashAccount, '2026-09-01', '2026-09-30');

        $this->assertEquals('1010', $ledger['account']['account_code']);
        $this->assertEquals('0.00', $ledger['opening_balance']);
        $this->assertEquals('51500.00', $ledger['period_debits']);
        $this->assertEquals('0.00', $ledger['period_credits']);
        $this->assertEquals('51500.00', $ledger['closing_balance']);
        $this->assertCount(2, $ledger['transactions']);
        $this->assertEquals('50000.00', $ledger['transactions'][0]['running_balance']);
        $this->assertEquals('51500.00', $ledger['transactions'][1]['running_balance']);
    }

    public function test_opening_balance_calculated_correctly_for_filtered_dates(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010');
        $capitalAccount = $this->accountService->resolveAccount('3010');

        // Prior entry in August
        $this->journalService->createAndPostJournal(
            [
                'accounting_date' => '2026-08-15',
                'description' => 'August capital',
            ],
            [
                ['account_id' => $cashAccount->id, 'debit' => '10000.00', 'credit' => '0.00'],
                ['account_id' => $capitalAccount->id, 'debit' => '0.00', 'credit' => '10000.00'],
            ],
            $this->accountant
        );

        // Query starting from September
        $ledger = $this->glService->getAccountLedger($cashAccount, '2026-09-01', '2026-09-30');

        $this->assertEquals('10000.00', $ledger['opening_balance']);
        $this->assertEquals('10000.00', $ledger['closing_balance']);
        $this->assertCount(0, $ledger['transactions']);
    }
}
