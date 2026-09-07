<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\ProfitAndLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitAndLossTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalService $journalService;
    protected ProfitAndLossService $pnlService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
        $this->pnlService = app(ProfitAndLossService::class);
    }

    public function test_profit_and_loss_calculates_gross_profit_and_net_income(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $ar = $this->accountService->resolveAccount('1100');
        $sales = $this->accountService->resolveAccount('4010'); // Revenue
        $discounts = $this->accountService->resolveAccount('4020'); // Contra-Revenue
        $cogs = $this->accountService->resolveAccount('5010'); // COGS
        $inventory = $this->accountService->resolveAccount('1200');
        $expense = $this->accountService->resolveAccount('5030'); // Operating Expense

        // 1. Sales revenue 20,000.00
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-01', 'description' => 'Sales'],
            [
                ['account_id' => $ar->id, 'debit' => '20000.00', 'credit' => '0.00'],
                ['account_id' => $sales->id, 'debit' => '0.00', 'credit' => '20000.00'],
            ],
            $this->accountant
        );

        // 2. Sales discount / credit note 1,000.00
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-05', 'description' => 'Sales discount'],
            [
                ['account_id' => $discounts->id, 'debit' => '1000.00', 'credit' => '0.00'],
                ['account_id' => $ar->id, 'debit' => '0.00', 'credit' => '1000.00'],
            ],
            $this->accountant
        );

        // 3. COGS 12,000.00
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-06', 'description' => 'COGS'],
            [
                ['account_id' => $cogs->id, 'debit' => '12000.00', 'credit' => '0.00'],
                ['account_id' => $inventory->id, 'debit' => '0.00', 'credit' => '12000.00'],
            ],
            $this->accountant
        );

        // 4. Operating expense 2,500.00
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-10', 'description' => 'Operating expense'],
            [
                ['account_id' => $expense->id, 'debit' => '2500.00', 'credit' => '0.00'],
                ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '2500.00'],
            ],
            $this->accountant
        );

        $pnl = $this->pnlService->getProfitAndLoss('2026-09-01', '2026-09-30');

        $this->assertEquals('20000.00', $pnl['operating_revenue']['total']);
        $this->assertEquals('1000.00', $pnl['contra_revenue']['total']);
        $this->assertEquals('19000.00', $pnl['net_revenue']);
        $this->assertEquals('12000.00', $pnl['cost_of_goods_sold']['total']);
        $this->assertEquals('7000.00', $pnl['gross_profit']);
        $this->assertEquals('2500.00', $pnl['total_operating_expenses']);
        $this->assertEquals('4500.00', $pnl['net_income']);
    }
}
