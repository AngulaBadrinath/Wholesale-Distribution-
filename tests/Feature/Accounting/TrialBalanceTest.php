<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\JournalEntryType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalService $journalService;
    protected TrialBalanceService $trialBalanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
        $this->trialBalanceService = app(TrialBalanceService::class);
    }

    public function test_trial_balance_debit_equals_credit_for_multiple_journals(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');
        $sales = $this->accountService->resolveAccount('4010');
        $tax = $this->accountService->resolveAccount('2100');
        $ar = $this->accountService->resolveAccount('1100');
        $cogs = $this->accountService->resolveAccount('5010');
        $inventory = $this->accountService->resolveAccount('1200');

        // Journal 1: Capital injection 100,000.00
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-01', 'description' => 'Capital injection'],
            [
                ['account_id' => $cash->id, 'debit' => '100000.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '100000.00'],
            ],
            $this->accountant
        );

        // Journal 2: Invoice issued: AR 5900 (Sales 5000, Tax 900)
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-02', 'description' => 'Invoice issued'],
            [
                ['account_id' => $ar->id, 'debit' => '5900.00', 'credit' => '0.00'],
                ['account_id' => $sales->id, 'debit' => '0.00', 'credit' => '5000.00'],
                ['account_id' => $tax->id, 'debit' => '0.00', 'credit' => '900.00'],
            ],
            $this->accountant
        );

        // Journal 3: COGS: COGS 3000, Inventory 3000
        $this->journalService->createAndPostJournal(
            ['accounting_date' => '2026-09-02', 'description' => 'COGS recognition'],
            [
                ['account_id' => $cogs->id, 'debit' => '3000.00', 'credit' => '0.00'],
                ['account_id' => $inventory->id, 'debit' => '0.00', 'credit' => '3000.00'],
            ],
            $this->accountant
        );

        $tb = $this->trialBalanceService->getTrialBalance('2026-09-30');

        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals('0.00', $tb['difference']);
        $this->assertEquals('108900.00', $tb['total_debits']);
        $this->assertEquals('108900.00', $tb['total_credits']);
        $this->assertEquals($tb['total_net_debits'], $tb['total_net_credits']);
    }
}
