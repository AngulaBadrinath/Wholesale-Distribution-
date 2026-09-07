<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalStatus;
use App\Enums\UserRole;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalReversalService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalReversalTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalService $journalService;
    protected JournalReversalService $reversalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
        $this->reversalService = app(JournalReversalService::class);
    }

    public function test_can_reverse_posted_journal_with_equal_and_opposite_lines(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');

        $original = $this->journalService->createAndPostJournal(
            [
                'entry_type' => JournalEntryType::MANUAL,
                'description' => 'Original deposit to be reversed',
            ],
            [
                ['account_id' => $cash->id, 'debit' => '25000.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '25000.00'],
            ],
            $this->accountant
        );

        $reversal = $this->reversalService->reverseJournal(
            $original,
            'Deposit entered by mistake',
            $this->accountant
        );

        $this->assertNotNull($reversal);
        $this->assertEquals(JournalEntryType::REVERSAL, $reversal->entry_type);
        $this->assertEquals(JournalStatus::POSTED, $reversal->status);
        $this->assertEquals('25000.00', $reversal->total_debit);
        $this->assertEquals('25000.00', $reversal->total_credit);

        // Check inverted lines
        $reversalCashLine = $reversal->lines->firstWhere('account_id', $cash->id);
        $reversalCapitalLine = $reversal->lines->firstWhere('account_id', $capital->id);

        $this->assertEquals('0.00', $reversalCashLine->debit);
        $this->assertEquals('25000.00', $reversalCashLine->credit);

        $this->assertEquals('25000.00', $reversalCapitalLine->debit);
        $this->assertEquals('0.00', $reversalCapitalLine->credit);

        // Original journal must be updated to REVERSED
        $original->refresh();
        $this->assertEquals(JournalStatus::REVERSED, $original->status);
        $this->assertEquals($reversal->id, $original->reversal_journal_id);
    }

    public function test_cannot_reverse_an_already_reversed_journal(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');

        $original = $this->journalService->createAndPostJournal(
            [
                'entry_type' => JournalEntryType::MANUAL,
                'description' => 'Original deposit',
            ],
            [
                ['account_id' => $cash->id, 'debit' => '1000.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '1000.00'],
            ],
            $this->accountant
        );

        $this->reversalService->reverseJournal($original, 'First reversal', $this->accountant);

        $this->expectException(ValidationException::class);

        $this->reversalService->reverseJournal($original, 'Second reversal attempt', $this->accountant);
    }
}
