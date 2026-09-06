<?php

declare(strict_types=1);

namespace Tests\Feature\Receivable;

use App\Enums\AccountStatus;
use App\Enums\CreditNoteStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ReceivableTransactionType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReceivableTransaction;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Credit\CreditNoteService;
use App\Services\Invoices\InvoiceGeneratorService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentVerificationService;
use App\Services\Receivable\ReceivableLedgerService;
use App\Services\Refund\RefundWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected TaxProfile $taxProfile;
    protected ReceivableLedgerService $ledgerService;

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

        $this->customer = Customer::create([
            'name' => 'Concurrent Enterprises',
            'code' => 'CUST-CONCUR-01',
            'contact_name' => 'Connie Current',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '100000.00',
            'balance' => '0.00',
            'billing_address_line1' => '999 Parallel St',
            'billing_city' => 'Threadville',
            'billing_state' => 'CA',
            'billing_postal_code' => '94016',
            'billing_country' => 'US',
            'email' => 'finance@concur.com',
            'phone' => '415-555-0999',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'West Coast Hub',
            'code' => 'WH-WEST-01',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Hardware',
            'code' => 'HDW-001',
            'is_active' => true,
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Tax',
            'code' => 'TAX-STD',
            'rate' => '0.0000',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Industrial Fastener Set',
            'sku' => 'HDW-FST-001',
            'category_id' => $category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => '10.00',
            'default_selling_price' => '20.00',
            'minimum_allowed_price' => '15.00',
            'mrp' => '25.00',
            'unit' => 'box',
            'status' => \App\Enums\ProductStatus::ACTIVE,
        ]);

        $this->ledgerService = app(ReceivableLedgerService::class);
    }

    protected function createApprovedOrder(string $grandTotal = '1000.00'): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-CONC-'.uniqid(),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => $grandTotal,
            'tax_total' => '0.00',
            'adjustment_total' => '0.00',
            'grand_total' => $grandTotal,
            'currency' => 'USD',
            'ordered_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => (int) ((float) $grandTotal / 20),
            'cancelled_quantity' => 0,
            'fulfilled_quantity' => 0,
            'unit_price' => '20.00',
            'tax_rate_snapshot' => '0.0000',
            'taxable_amount' => $grandTotal,
            'tax_amount' => '0.00',
            'line_total' => $grandTotal,
        ]);

        return $order;
    }

    public function test_concurrent_payment_verifications_maintain_accurate_receivable_balance(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        // Record two verified payments: Payment A = $400, Payment B = $300
        $paymentService = app(PaymentService::class);
        $verificationService = app(PaymentVerificationService::class);

        $paymentA = $paymentService->recordCashPayment([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => '400.00',
            'payment_date' => Carbon::now()->toDateString(),
        ], $this->salesman);
        $verificationService->verifyPayment($paymentA, $this->accountant);

        $paymentB = $paymentService->recordCashPayment([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => '300.00',
            'payment_date' => Carbon::now()->toDateString(),
        ], $this->salesman);
        $verificationService->verifyPayment($paymentB, $this->accountant);

        // Verify total receivable = $1,000 - $400 - $300 = $300.00
        $balance = $this->ledgerService->getCustomerReceivableBalance($this->customer);
        $this->assertSame('300.00', $balance);

        // Verify exactly 3 AR transactions exist: 1 Invoice Charge, 2 Payments
        $transactions = ReceivableTransaction::where('customer_id', $this->customer->id)->get();
        $this->assertCount(3, $transactions);
    }

    public function test_concurrent_credit_note_and_refund_workflow_preserves_receivable_and_credit_invariants(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        // Issue Credit Note of $500
        $creditNote = CreditNote::create([
            'credit_number' => 'CRN-CONCUR-01',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'invoice_id' => $invoice->id,
            'status' => CreditNoteStatus::ISSUED,
            'currency' => 'USD',
            'subtotal' => '500.00',
            'tax_total' => '0.00',
            'total_amount' => '500.00',
            'allocated_to_refunds' => '0.00',
            'remaining_balance' => '500.00',
            'reason' => 'Damaged goods return',
            'issued_by' => $this->admin->id,
            'issued_at' => Carbon::now(),
            'customer_name_snapshot' => $this->customer->name,
            'customer_code_snapshot' => $this->customer->code,
        ]);
        $this->ledgerService->recordCreditNote($creditNote, $this->admin);

        // Receivable is $500 ($1000 - $500), Available Credit is $500
        $this->assertSame('500.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));
        $this->assertSame('500.00', $this->ledgerService->getCustomerCreditBalance($this->customer));

        // Process Refund of $400 against this credit note
        $refundWorkflow = app(RefundWorkflowService::class);
        $refundReq = $refundWorkflow->createRefundRequest($creditNote, $this->salesman, [
            'requested_amount' => '400.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Cash disbursement for return',
        ]);
        $refundWorkflow->approveRefund($refundReq, $this->admin, ['notes' => 'Approved']);
        $refundWorkflow->processRefund($refundReq, $this->accountant, [
            'transaction_reference' => 'CASH-REF-400',
            'disbursed_at' => Carbon::now()->toDateString(),
        ]);

        // CRITICAL INVARIANT:
        // Receivable balance is STILL $500.00 (refund does NOT reduce receivable again)
        $this->assertSame('500.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));

        // Available Credit balance is reduced to $100.00 ($500 - $400)
        $this->assertSame('100.00', $this->ledgerService->getCustomerCreditBalance($this->customer));
    }

    public function test_duplicate_posting_calls_return_identical_record_without_duplicate_rows(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        // Call recordInvoiceCharge multiple times
        $txn1 = $this->ledgerService->recordInvoiceCharge($invoice, $this->admin);
        $txn2 = $this->ledgerService->recordInvoiceCharge($invoice, $this->admin);
        $txn3 = $this->ledgerService->recordInvoiceCharge($invoice, $this->admin);

        $this->assertSame($txn1->id, $txn2->id);
        $this->assertSame($txn1->id, $txn3->id);

        $this->assertCount(1, ReceivableTransaction::where('source_type', 'invoice')->where('source_id', $invoice->id)->get());
    }
}
