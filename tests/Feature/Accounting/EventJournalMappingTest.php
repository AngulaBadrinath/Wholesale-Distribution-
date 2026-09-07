<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventJournalMappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected AccountService $accountService;
    protected JournalMappingService $mappingService;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'name' => 'Acme Wholesale Customer',
            'code' => 'CUST-ACME-01',
            'contact_name' => 'John Doe',
            'phone' => '1234567890',
            'email' => 'acme@test.com',
            'salesman_id' => $salesman->id,
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => '100000.00',
            'payment_terms' => PaymentTerms::NET_30,
            'billing_address_line1' => '123 Main St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-TEST-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $salesman->id,
            'created_by' => $salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => DeliveryStatus::PENDING_ASSIGNMENT,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-test-ord-001',
            'subtotal' => '1000.00',
            'tax_total' => '180.00',
            'adjustment_total' => '0.00',
            'grand_total' => '1180.00',
            'submitted_at' => now(),
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Global Imports Ltd',
            'supplier_code' => 'SUP-GLOBAL',
            'email' => 'global@imports.com',
            'phone' => '9876543210',
            'status' => SupplierStatus::ACTIVE,
            'created_by' => $this->accountant->id,
        ]);

        $this->accountService = app(AccountService::class);
        $this->mappingService = app(JournalMappingService::class);
    }

    protected function createInvoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'invoice_number' => 'INV-2026-'.uniqid(),
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'subtotal' => '1000.00',
            'tax_total' => '180.00',
            'adjustment_total' => '0.00',
            'grand_total' => '1180.00',
            'amount_paid' => '0.00',
            'amount_due' => '1180.00',
            'currency' => 'USD',
            'payment_terms' => PaymentTerms::NET_30,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => InvoiceStatus::ISSUED,
            'payment_status' => PaymentStatus::UNPAID,
            'customer_name_snapshot' => $this->customer->name,
            'customer_code_snapshot' => $this->customer->code,
            'billing_address_line1_snapshot' => '123 Main St',
            'billing_city_snapshot' => 'Metropolis',
            'billing_state_snapshot' => 'NY',
            'billing_postal_code_snapshot' => '10001',
            'billing_country_snapshot' => 'USA',
            'shipping_address_line1_snapshot' => '123 Main St',
            'shipping_city_snapshot' => 'Metropolis',
            'shipping_state_snapshot' => 'NY',
            'shipping_postal_code_snapshot' => '10001',
            'shipping_country_snapshot' => 'USA',
            'company_legal_name_snapshot' => 'Wholesale Corp',
            'company_address_snapshot' => '100 Corporate Blvd',
            'created_by' => $this->accountant->id,
        ], $overrides));
    }

    public function test_post_invoice_creates_balanced_revenue_journal(): void
    {
        $invoice = $this->createInvoice();

        $journal = $this->mappingService->postInvoiceIssued($invoice, $this->accountant);

        $this->assertNotNull($journal);
        $this->assertEquals(JournalStatus::POSTED, $journal->status);
        $this->assertEquals('1180.00', $journal->total_debit);
        $this->assertEquals('1180.00', $journal->total_credit);
        $this->assertEquals('invoice', $journal->source_type);
        $this->assertEquals($invoice->id, $journal->source_id);

        $lines = $journal->lines;
        $this->assertCount(3, $lines);

        $arAccount = $this->accountService->resolveAccount('1100');
        $salesAccount = $this->accountService->resolveAccount('4010');
        $taxAccount = $this->accountService->resolveAccount('2100');

        $arLine = $lines->firstWhere('account_id', $arAccount->id);
        $salesLine = $lines->firstWhere('account_id', $salesAccount->id);
        $taxLine = $lines->firstWhere('account_id', $taxAccount->id);

        $this->assertEquals('1180.00', $arLine->debit);
        $this->assertEquals('0.00', $arLine->credit);

        $this->assertEquals('0.00', $salesLine->debit);
        $this->assertEquals('1000.00', $salesLine->credit);

        $this->assertEquals('0.00', $taxLine->debit);
        $this->assertEquals('180.00', $taxLine->credit);
    }

    public function test_post_customer_payment_credits_ar_and_debits_cash(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-2026-000001',
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'amount' => '500.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::VERIFIED,
            'recorded_by' => $this->accountant->id,
            'verified_by' => $this->accountant->id,
        ]);

        $journal = $this->mappingService->postPaymentVerified($payment, $this->accountant);

        $this->assertNotNull($journal);
        $this->assertEquals('500.00', $journal->total_debit);
        $this->assertEquals('500.00', $journal->total_credit);

        $cashAccount = $this->accountService->resolveAccount('1010');
        $arAccount = $this->accountService->resolveAccount('1100');

        $lines = $journal->lines;
        $cashLine = $lines->firstWhere('account_id', $cashAccount->id);
        $arLine = $lines->firstWhere('account_id', $arAccount->id);

        $this->assertEquals('500.00', $cashLine->debit);
        $this->assertEquals('0.00', $cashLine->credit);

        $this->assertEquals('0.00', $arLine->debit);
        $this->assertEquals('500.00', $arLine->credit);
    }

    public function test_post_supplier_bill_creates_ap_liability(): void
    {
        $bill = SupplierBill::create([
            'bill_number' => 'BILL-2026-000001',
            'supplier_invoice_number' => 'SUP-INV-999',
            'supplier_id' => $this->supplier->id,
            'subtotal' => '3200.00',
            'tax_total' => '0.00',
            'total_amount' => '3200.00',
            'amount_paid' => '0.00',
            'amount_due' => '3200.00',
            'status' => SupplierBillStatus::POSTED,
            'bill_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'created_by' => $this->accountant->id,
        ]);

        $journal = $this->mappingService->postSupplierBillPosted($bill, $this->accountant);

        $this->assertNotNull($journal);
        $this->assertEquals('3200.00', $journal->total_debit);
        $this->assertEquals('3200.00', $journal->total_credit);

        $apAccount = $this->accountService->resolveAccount('2010');
        $expenseAccount = $this->accountService->resolveAccount('5030');

        $lines = $journal->lines;
        $expenseLine = $lines->firstWhere('account_id', $expenseAccount->id);
        $apLine = $lines->firstWhere('account_id', $apAccount->id);

        $this->assertEquals('3200.00', $expenseLine->debit);
        $this->assertEquals('0.00', $expenseLine->credit);

        $this->assertEquals('0.00', $apLine->debit);
        $this->assertEquals('3200.00', $apLine->credit);
    }

    public function test_post_supplier_payment_debits_ap_and_credits_bank(): void
    {
        $payment = SupplierPayment::create([
            'payment_number' => 'SPAY-2026-000001',
            'supplier_id' => $this->supplier->id,
            'amount' => '1500.00',
            'payment_method' => SupplierPaymentMethod::BANK_TRANSFER,
            'status' => SupplierPaymentStatus::COMPLETED,
            'payment_date' => '2026-09-02',
            'created_by' => $this->accountant->id,
        ]);

        $journal = $this->mappingService->postSupplierPaymentCompleted($payment, $this->accountant);

        $this->assertNotNull($journal);
        $this->assertEquals('1500.00', $journal->total_debit);
        $this->assertEquals('1500.00', $journal->total_credit);

        $apAccount = $this->accountService->resolveAccount('2010');
        $bankAccount = $this->accountService->resolveAccount('1030');

        $lines = $journal->lines;
        $apLine = $lines->firstWhere('account_id', $apAccount->id);
        $bankLine = $lines->firstWhere('account_id', $bankAccount->id);

        $this->assertEquals('1500.00', $apLine->debit);
        $this->assertEquals('0.00', $apLine->credit);

        $this->assertEquals('0.00', $bankLine->debit);
        $this->assertEquals('1500.00', $bankLine->credit);
    }

    public function test_idempotent_event_mapping_does_not_duplicate_journals(): void
    {
        $invoice = $this->createInvoice([
            'invoice_number' => 'INV-2026-000002',
            'subtotal' => '2000.00',
            'tax_total' => '0.00',
            'grand_total' => '2000.00',
            'amount_due' => '2000.00',
        ]);

        $first = $this->mappingService->postInvoiceIssued($invoice, $this->accountant);
        $second = $this->mappingService->postInvoiceIssued($invoice, $this->accountant);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->count());
    }
}
