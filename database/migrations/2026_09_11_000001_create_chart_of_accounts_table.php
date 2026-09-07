<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create accounts table
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 32)->unique();
            $table->string('name', 255);
            $table->string('type', 32)->index(); // ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE
            $table->string('category', 64)->index(); // CURRENT_ASSET, NON_CURRENT_ASSET, CURRENT_LIABILITY, LONG_TERM_LIABILITY, EQUITY, OPERATING_REVENUE, CONTRA_REVENUE, COST_OF_GOODS_SOLD, OPERATING_EXPENSE, OTHER_EXPENSE
            $table->string('normal_balance', 10); // DEBIT, CREDIT
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_reconcilable')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['category', 'is_active']);
        });

        // 2. Seed Default Standard Chart of Accounts (GAAP Foundation)
        $defaultAccounts = [
            // ASSETS (1000s)
            [
                'account_code' => '1010',
                'name' => 'Cash on Hand',
                'type' => 'ASSET',
                'category' => 'CURRENT_ASSET',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => true,
                'description' => 'Physical cash received from customer collections and point-of-sale payments.',
            ],
            [
                'account_code' => '1020',
                'name' => 'Undeposited Cheques & Money Orders',
                'type' => 'ASSET',
                'category' => 'CURRENT_ASSET',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => true,
                'description' => 'Cheques and money orders collected from customers awaiting bank deposit.',
            ],
            [
                'account_code' => '1030',
                'name' => 'Operating Bank Account',
                'type' => 'ASSET',
                'category' => 'CURRENT_ASSET',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => true,
                'description' => 'Primary commercial checking and electronic bank transfer account.',
            ],
            [
                'account_code' => '1100',
                'name' => 'Accounts Receivable',
                'type' => 'ASSET',
                'category' => 'CURRENT_ASSET',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Trade receivables owed by wholesale customers for issued credit invoices.',
            ],
            [
                'account_code' => '1200',
                'name' => 'Inventory Asset',
                'type' => 'ASSET',
                'category' => 'CURRENT_ASSET',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Current inventory valuation of wholesale merchandise on hand.',
            ],

            // LIABILITIES (2000s)
            [
                'account_code' => '2010',
                'name' => 'Accounts Payable',
                'type' => 'LIABILITY',
                'category' => 'CURRENT_LIABILITY',
                'normal_balance' => 'CREDIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Trade liabilities owed to suppliers for posted supplier bills.',
            ],
            [
                'account_code' => '2100',
                'name' => 'Sales Tax Payable',
                'type' => 'LIABILITY',
                'category' => 'CURRENT_LIABILITY',
                'normal_balance' => 'CREDIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Sales taxes collected on customer invoices to be remitted to tax authorities.',
            ],
            [
                'account_code' => '2200',
                'name' => 'Customer Deposits / Unearned Revenue',
                'type' => 'LIABILITY',
                'category' => 'CURRENT_LIABILITY',
                'normal_balance' => 'CREDIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Advance customer payments or credit balances prior to order invoice realization.',
            ],

            // EQUITY (3000s)
            [
                'account_code' => '3010',
                'name' => "Owner's / Shareholder Capital",
                'type' => 'EQUITY',
                'category' => 'EQUITY',
                'normal_balance' => 'CREDIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Capital contributed by business owners and shareholders.',
            ],
            [
                'account_code' => '3020',
                'name' => 'Retained Earnings',
                'type' => 'EQUITY',
                'category' => 'EQUITY',
                'normal_balance' => 'CREDIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Accumulated net income retained from prior financial periods.',
            ],

            // REVENUE (4000s)
            [
                'account_code' => '4010',
                'name' => 'Wholesale Sales Revenue',
                'type' => 'REVENUE',
                'category' => 'OPERATING_REVENUE',
                'normal_balance' => 'CREDIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Gross revenue recognized from issued wholesale product invoices.',
            ],
            [
                'account_code' => '4020',
                'name' => 'Sales Discounts & Allowances',
                'type' => 'REVENUE',
                'category' => 'CONTRA_REVENUE',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Contra-revenue reductions for credit notes, returned goods, and sales adjustments.',
            ],
            [
                'account_code' => '4030',
                'name' => 'Delivery & Shipping Revenue',
                'type' => 'REVENUE',
                'category' => 'OPERATING_REVENUE',
                'normal_balance' => 'CREDIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Revenue recognized from logistics and delivery charges.',
            ],

            // EXPENSES (5000s)
            [
                'account_code' => '5010',
                'name' => 'Cost of Goods Sold (COGS)',
                'type' => 'EXPENSE',
                'category' => 'COST_OF_GOODS_SOLD',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Direct cost basis of wholesale goods delivered and fulfilled.',
            ],
            [
                'account_code' => '5020',
                'name' => 'Inventory Shrinkage & Damage Write-Off',
                'type' => 'EXPENSE',
                'category' => 'OPERATING_EXPENSE',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Expenses arising from damaged goods, stock adjustments, and inventory write-downs.',
            ],
            [
                'account_code' => '5030',
                'name' => 'Operating & Procurement Expenses',
                'type' => 'EXPENSE',
                'category' => 'OPERATING_EXPENSE',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Operational expenditures, supplier bill services, and wholesale administrative costs.',
            ],
            [
                'account_code' => '5040',
                'name' => 'Payment Processing & Bank Charges',
                'type' => 'EXPENSE',
                'category' => 'OPERATING_EXPENSE',
                'normal_balance' => 'DEBIT',
                'is_active' => true,
                'is_system' => true,
                'is_reconcilable' => false,
                'description' => 'Bank fees, reconciliation write-offs, and transaction processing costs.',
            ],
        ];

        $now = now();
        foreach ($defaultAccounts as $acc) {
            DB::table('accounts')->insert(array_merge($acc, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
