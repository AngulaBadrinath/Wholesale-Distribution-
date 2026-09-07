<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalService $journalService;
    protected BalanceSheetService $balanceSheetService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
        $this->balanceSheetService = app(BalanceSheetService::class);
    }

    public function test_balance_sheet_satisfies_accounting_equation(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');
        $ar = $this->accountService->resolveAccount('1100');
        $sales = $this->accountService->resolveAccount('4010');
        $tax = $this->accountService->resolveAccount('2100');
        $cogs = $this->accountService->resolveAccount('5010');
        $inventory = $this->accountService->resolveAccount('1200');

        // 1. Owner invests 50,000.00
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-01', 'description' => 'Capital investment'],
            [
                ['account_id' => $cash->id, 'debit' => '50000.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '50000.00'],
            ],
            $this->accountant
        );

        // 2. Invoice issued: AR 11,800.00 (Sales 10,000.00, Tax 1,800.00)
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-02', 'description' => 'Invoice issued'],
            [
                ['account_id' => $ar->id, 'debit' => '11800.00', 'credit' => '0.00'],
                ['account_id' => $sales->id, 'debit' => '0.00', 'credit' => '10000.00'],
                ['account_id' => $tax->id, 'debit' => '0.00', 'credit' => '1800.00'],
            ],
            $this->accountant
        );

        // 3. COGS: 6,000.00
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-03', 'description' => 'COGS'],
            [
                ['account_id' => $cogs->id, 'debit' => '6000.00', 'credit' => '0.00'],
                ['account_id' => $inventory->id, 'debit' => '0.00', 'credit' => '6000.00'],
            ],
            $this->accountant
        );

        $bs = $this->balanceSheetService->getBalanceSheet('2026-09-30');

        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals('0.00', $bs['difference']);
        // Assets: Cash (50,000) + AR (11,800) + Inventory (-6,000) = 55,800.00
        // Liabilities: Tax (1,800.00)
        // Equity: Capital (50,000.00) + Net Earnings (10,000 - 6,000 = 4,000.00) = 54,000.00
        // Liabilities + Equity = 1,800 + 54,000 = 55,800.00
        $this->assertEquals('55800.00', $bs['assets']['total_assets']);
        $this->assertEquals('1800.00', $bs['liabilities']['total_liabilities']);
        $this->assertEquals('54000.00', $bs['equity']['total_equity']);
        $this->assertEquals('55800.00', $bs['total_liabilities_and_equity']);
    }
}
