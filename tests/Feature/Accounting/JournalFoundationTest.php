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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalFoundationTest extends TestCase
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

    public function test_can_create_and_post_balanced_journal_entry(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010'); // Cash on Hand (Asset)
        $capitalAccount = $this->accountService->resolveAccount('3010'); // Owner Equity

        $journal = $this->journalService->createAndPostJournal(
            [
                'entry_type' => JournalEntryType::MANUAL,
                'posting_date' => '2026-09-01',
                'accounting_date' => '2026-09-01',
                'description' => 'Initial capital investment',
                'source_type' => 'MANUAL_DEPOSIT',
                'source_id' => 101,
                'source_event' => 'CAPITAL_INJECTION_101',
            ],
            [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => '50000.00',
                    'credit' => '0.00',
                    'description' => 'Cash received from investor',
                ],
                [
                    'account_id' => $capitalAccount->id,
                    'debit' => '0.00',
                    'credit' => '50000.00',
                    'description' => 'Owner capital credit',
                ],
            ],
            $this->accountant
        );

        $this->assertNotNull($journal->id);
        $this->assertMatchesRegularExpression('/^JE-\d{4}-\d{6}$/', $journal->journal_number);
        $this->assertEquals(JournalStatus::POSTED, $journal->status);
        $this->assertEquals('50000.00', $journal->total_debit);
        $this->assertEquals('50000.00', $journal->total_credit);
        $this->assertEquals($this->accountant->id, $journal->created_by);
        $this->assertEquals($this->accountant->id, $journal->posted_by);
        $this->assertCount(2, $journal->lines);
    }

    public function test_rejects_unbalanced_journal_entry(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010');
        $capitalAccount = $this->accountService->resolveAccount('3010');

        $this->expectException(ValidationException::class);

        $this->journalService->createAndPostJournal(
            [
                'entry_type' => JournalEntryType::MANUAL,
                'description' => 'Unbalanced entry test',
            ],
            [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => '50000.00',
                    'credit' => '0.00',
                ],
                [
                    'account_id' => $capitalAccount->id,
                    'debit' => '0.00',
                    'credit' => '40000.00', // Unbalanced by 10,000
                ],
            ],
            $this->accountant
        );
    }

    public function test_rejects_journal_with_less_than_two_lines(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010');

        $this->expectException(ValidationException::class);

        $this->journalService->createAndPostJournal(
            [
                'description' => 'Single line entry test',
            ],
            [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => '1000.00',
                    'credit' => '0.00',
                ],
            ],
            $this->accountant
        );
    }

    public function test_rejects_line_with_both_debit_and_credit_or_both_zero(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010');
        $capitalAccount = $this->accountService->resolveAccount('3010');

        $this->expectException(ValidationException::class);

        $this->journalService->createAndPostJournal(
            [
                'description' => 'Invalid line amounts',
            ],
            [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => '100.00',
                    'credit' => '100.00', // Both debit and credit
                ],
                [
                    'account_id' => $capitalAccount->id,
                    'debit' => '0.00',
                    'credit' => '0.00',
                ],
            ],
            $this->accountant
        );
    }

    public function test_idempotency_prevents_duplicate_journal_for_same_source_event(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010');
        $capitalAccount = $this->accountService->resolveAccount('3010');

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'TEST_EVENT',
            'source_id' => 999,
            'source_event' => 'TEST_EVENT_999_POST',
            'description' => 'Idempotent transaction',
        ];

        $lines = [
            [
                'account_id' => $cashAccount->id,
                'debit' => '250.00',
                'credit' => '0.00',
            ],
            [
                'account_id' => $capitalAccount->id,
                'debit' => '0.00',
                'credit' => '250.00',
            ],
        ];

        $firstJournal = $this->journalService->createAndPostJournal($header, $lines, $this->accountant);
        $secondJournal = $this->journalService->createAndPostJournal($header, $lines, $this->accountant);

        $this->assertEquals($firstJournal->id, $secondJournal->id);
        $this->assertEquals(1, JournalEntry::where('source_event', 'TEST_EVENT_999_POST')->count());
    }

    public function test_multi_line_split_journal_balances_correctly(): void
    {
        $cashAccount = $this->accountService->resolveAccount('1010'); // Asset
        $salesAccount = $this->accountService->resolveAccount('4010'); // Revenue
        $taxAccount = $this->accountService->resolveAccount('2100'); // Tax Liability

        // Cash received: 1180.00, Sales: 1000.00, GST: 180.00
        $journal = $this->journalService->createAndPostJournal(
            [
                'description' => 'Multi-line invoice split',
            ],
            [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => '1180.00',
                    'credit' => '0.00',
                    'description' => 'Total cash received',
                ],
                [
                    'account_id' => $salesAccount->id,
                    'debit' => '0.00',
                    'credit' => '1000.00',
                    'description' => 'Base revenue',
                ],
                [
                    'account_id' => $taxAccount->id,
                    'debit' => '0.00',
                    'credit' => '180.00',
                    'description' => 'Output GST liability',
                ],
            ],
            $this->accountant
        );

        $this->assertEquals('1180.00', $journal->total_debit);
        $this->assertEquals('1180.00', $journal->total_credit);
        $this->assertCount(3, $journal->lines);
    }
}
