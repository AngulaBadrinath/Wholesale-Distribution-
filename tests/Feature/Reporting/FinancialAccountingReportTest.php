<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\ProfitAndLossService;
use App\Services\Accounting\TrialBalanceService;
use App\Services\Reporting\FinancialReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAccountingReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected AccountService $accountService;
    protected JournalService $journalService;
    protected TrialBalanceService $trialBalanceService;
    protected ProfitAndLossService $profitAndLossService;
    protected BalanceSheetService $balanceSheetService;
    protected FinancialReportService $financialReportService;

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

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
        $this->trialBalanceService = app(TrialBalanceService::class);
        $this->profitAndLossService = app(ProfitAndLossService::class);
        $this->balanceSheetService = app(BalanceSheetService::class);
        $this->financialReportService = app(FinancialReportService::class);

        // Populate sample accounting ledger
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');
        $sales = $this->accountService->resolveAccount('4010');
        $tax = $this->accountService->resolveAccount('2100');
        $ar = $this->accountService->resolveAccount('1100');
        $cogs = $this->accountService->resolveAccount('5010');
        $inventory = $this->accountService->resolveAccount('1200');

        // Capital injection
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-01', 'description' => 'Capital injection'],
            [
                ['account_id' => $cash->id, 'debit' => '50000.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '50000.00'],
            ],
            $this->accountant
        );

        // Sales Invoice
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-02', 'description' => 'Invoice issued'],
            [
                ['account_id' => $ar->id, 'debit' => '5500.00', 'credit' => '0.00'],
                ['account_id' => $sales->id, 'debit' => '0.00', 'credit' => '5000.00'],
                ['account_id' => $tax->id, 'debit' => '0.00', 'credit' => '500.00'],
            ],
            $this->accountant
        );

        // COGS
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-02', 'description' => 'COGS recognition'],
            [
                ['account_id' => $cogs->id, 'debit' => '2000.00', 'credit' => '0.00'],
                ['account_id' => $inventory->id, 'debit' => '0.00', 'credit' => '2000.00'],
            ],
            $this->accountant
        );
    }

    public function test_financial_report_reconciles_100_percent_with_trial_balance_service(): void
    {
        $directTB = $this->trialBalanceService->getTrialBalance('2026-09-30');
        $reportTB = $this->financialReportService->getTrialBalance(['as_of_date' => '2026-09-30'], $this->accountant);

        $this->assertEquals($directTB['total_debits'], $reportTB['total_debits']);
        $this->assertEquals($directTB['total_credits'], $reportTB['total_credits']);
        $this->assertEquals($directTB['is_balanced'], $reportTB['is_balanced']);
        $this->assertEquals($directTB['difference'], $reportTB['difference']);
        $this->assertEquals($directTB['accounts'], $reportTB['accounts']);
    }

    public function test_financial_report_reconciles_100_percent_with_pnl_service(): void
    {
        $directPnL = $this->profitAndLossService->getProfitAndLoss('2026-09-01', '2026-09-30');
        $reportPnL = $this->financialReportService->getProfitAndLoss([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ], $this->accountant);

        $this->assertEquals($directPnL['operating_revenue']['total'], $reportPnL['operating_revenue']['total']);
        $this->assertEquals($directPnL['cost_of_goods_sold']['total'], $reportPnL['cost_of_goods_sold']['total']);
        $this->assertEquals($directPnL['gross_profit'], $reportPnL['gross_profit']);
        $this->assertEquals($directPnL['net_income'], $reportPnL['net_income']);
        $this->assertEquals($directPnL['net_revenue'], $reportPnL['net_revenue']);
    }

    public function test_financial_report_reconciles_100_percent_with_balance_sheet_service(): void
    {
        $directBS = $this->balanceSheetService->getBalanceSheet('2026-09-30');
        $reportBS = $this->financialReportService->getBalanceSheet(['as_of_date' => '2026-09-30'], $this->accountant);

        $this->assertEquals($directBS['assets']['total_assets'], $reportBS['assets']['total_assets']);
        $this->assertEquals($directBS['liabilities']['total_liabilities'], $reportBS['liabilities']['total_liabilities']);
        $this->assertEquals($directBS['equity']['total_equity'], $reportBS['equity']['total_equity']);
        $this->assertEquals($directBS['is_balanced'], $reportBS['is_balanced']);
        $this->assertEquals($directBS['difference'], $reportBS['difference']);
    }

    public function test_financial_summary_executive_view_reconciles_aggregates(): void
    {
        $summary = $this->financialReportService->getFinancialSummary([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'as_of_date' => '2026-09-30',
        ], $this->admin);

        $this->assertEquals('5000.00', $summary['profit_and_loss']['net_sales_revenue']);
        $this->assertEquals('2000.00', $summary['profit_and_loss']['cogs']);
        $this->assertEquals('3000.00', $summary['profit_and_loss']['gross_profit']);
        $this->assertEquals('3000.00', $summary['profit_and_loss']['net_income']);

        $this->assertTrue($summary['balance_sheet']['is_balanced']);
        $this->assertTrue($summary['trial_balance']['is_balanced']);
    }

    public function test_unauthorized_role_is_denied_financial_reporting_access(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->financialReportService->getTrialBalance(['as_of_date' => '2026-09-30'], $this->salesman);
    }
}
