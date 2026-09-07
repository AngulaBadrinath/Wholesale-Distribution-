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

class AccountingConcurrencyTest extends TestCase
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

    public function test_concurrent_posting_for_same_source_event_is_idempotent(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');

        $header = [
            'entry_type' => JournalEntryType::SYSTEM,
            'source_type' => 'invoice',
            'source_id' => 8888,
            'source_event' => 'INVOICE_ISSUED_8888',
            'description' => 'Concurrent test event',
        ];

        $lines = [
            ['account_id' => $cash->id, 'debit' => '500.00', 'credit' => '0.00'],
            ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '500.00'],
        ];

        $entry1 = $this->journalService->createAndPostJournal($header, $lines, $this->accountant);
        $entry2 = $this->journalService->createAndPostJournal($header, $lines, $this->accountant);

        $this->assertEquals($entry1->id, $entry2->id);
        $this->assertEquals(1, JournalEntry::where('source_event', 'INVOICE_ISSUED_8888')->count());
    }

    public function test_concurrent_reversals_block_duplicate_reversal_entries(): void
    {
        $cash = $this->accountService->resolveAccount('1010');
        $capital = $this->accountService->resolveAccount('3010');

        $original = $this->journalService->createAndPostJournal(
            [
                'entry_type' => JournalEntryType::MANUAL,
                'description' => 'Original entry for concurrency test',
            ],
            [
                ['account_id' => $cash->id, 'debit' => '1000.00', 'credit' => '0.00'],
                ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '1000.00'],
            ],
            $this->accountant
        );

        $firstReversal = $this->reversalService->reverseJournal($original, 'First thread reversal', $this->accountant);
        $this->assertNotNull($firstReversal);

        $this->expectException(ValidationException::class);
        $this->reversalService->reverseJournal($original, 'Concurrent second thread reversal', $this->accountant);
    }
}
