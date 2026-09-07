<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\BalanceType;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class AccountChartTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected AccountService $accountService;
    protected JournalService $journalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountService = app(AccountService::class);
        $this->journalService = app(JournalService::class);
    }

    public function test_default_gaap_chart_of_accounts_is_seeded(): void
    {
        $this->assertDatabaseHas('accounts', [
            'account_code' => '1010',
            'name' => 'Cash on Hand',
            'type' => 'ASSET',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('accounts', [
            'account_code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => 'ASSET',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('accounts', [
            'account_code' => '2010',
            'name' => 'Accounts Payable',
            'type' => 'LIABILITY',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('accounts', [
            'account_code' => '4010',
            'name' => 'Wholesale Sales Revenue',
            'type' => 'REVENUE',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('accounts', [
            'account_code' => '5010',
            'name' => 'Cost of Goods Sold (COGS)',
            'type' => 'EXPENSE',
            'is_system' => true,
        ]);
    }

    public function test_can_create_custom_account_with_valid_attributes(): void
    {
        $account = $this->accountService->createAccount([
            'account_code' => '1040',
            'name' => 'Payroll Checking Account',
            'type' => AccountType::ASSET,
            'category' => AccountCategory::CURRENT_ASSET,
            'is_reconcilable' => true,
            'description' => 'Dedicated commercial account for payroll disbursements',
        ], $this->admin);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'account_code' => '1040',
            'name' => 'Payroll Checking Account',
            'normal_balance' => BalanceType::DEBIT->value,
            'is_system' => false,
            'is_reconcilable' => true,
        ]);
    }

    public function test_rejects_duplicate_account_code(): void
    {
        $this->expectException(ValidationException::class);

        $this->accountService->createAccount([
            'account_code' => '1010', // Already exists
            'name' => 'Duplicate Cash',
            'type' => AccountType::ASSET,
            'category' => AccountCategory::CURRENT_ASSET,
        ], $this->admin);
    }

    public function test_rejects_parent_account_type_mismatch(): void
    {
        $assetAccount = Account::where('account_code', '1010')->firstOrFail();

        $this->expectException(ValidationException::class);

        // Attempt to create an EXPENSE account with an ASSET parent
        $this->accountService->createAccount([
            'account_code' => '5099',
            'name' => 'Invalid Child',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
            'parent_id' => $assetAccount->id,
        ], $this->admin);
    }

    public function test_prevents_circular_hierarchy_on_update(): void
    {
        $parent = $this->accountService->createAccount([
            'account_code' => '5100',
            'name' => 'Marketing Group',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
        ], $this->admin);

        $child = $this->accountService->createAccount([
            'account_code' => '5110',
            'name' => 'Online Ads',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
            'parent_id' => $parent->id,
        ], $this->admin);

        $this->expectException(ValidationException::class);

        // Attempt to set child as parent of parent (circular)
        $this->accountService->updateAccount($parent, [
            'parent_id' => $child->id,
        ], $this->admin);
    }

    public function test_system_accounts_cannot_be_deleted(): void
    {
        $systemAccount = Account::where('account_code', '1010')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->accountService->deleteAccount($systemAccount);
    }

    public function test_accounts_with_posted_transactions_cannot_be_deleted(): void
    {
        $customAccount = $this->accountService->createAccount([
            'account_code' => '5200',
            'name' => 'Temporary Supplies',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
        ], $this->admin);

        $cashAccount = Account::where('account_code', '1010')->firstOrFail();

        // Post a balanced journal referencing customAccount
        $this->journalService->createAndPostJournal([
            'description' => 'Test journal',
        ], [
            ['account_id' => $customAccount->id, 'debit' => '50.00', 'credit' => '0.00'],
            ['account_id' => $cashAccount->id, 'debit' => '0.00', 'credit' => '50.00'],
        ], $this->admin);

        $this->expectException(ValidationException::class);
        $this->accountService->deleteAccount($customAccount);
    }

    public function test_custom_account_without_transactions_can_be_deleted(): void
    {
        $customAccount = $this->accountService->createAccount([
            'account_code' => '5299',
            'name' => 'Unused Expense',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
        ], $this->admin);

        $deleted = $this->accountService->deleteAccount($customAccount);
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('accounts', ['id' => $customAccount->id]);
    }

    public function test_get_hierarchy_returns_tree_structure(): void
    {
        $parent = $this->accountService->createAccount([
            'account_code' => '6000',
            'name' => 'Administrative Overhead',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
        ], $this->admin);

        $child1 = $this->accountService->createAccount([
            'account_code' => '6010',
            'name' => 'Office Rent',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
            'parent_id' => $parent->id,
        ], $this->admin);

        $child2 = $this->accountService->createAccount([
            'account_code' => '6020',
            'name' => 'Utilities',
            'type' => AccountType::EXPENSE,
            'category' => AccountCategory::OPERATING_EXPENSE,
            'parent_id' => $parent->id,
        ], $this->admin);

        $hierarchy = $this->accountService->getHierarchy();
        $foundParent = $hierarchy->firstWhere('id', $parent->id);

        $this->assertNotNull($foundParent);
        $this->assertCount(2, $foundParent->children);
    }
}
