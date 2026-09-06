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
use App\Services\Payment\PaymentReversalService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentVerificationService;
use App\Services\Receivable\ReceivableLedgerService;
use App\Services\Refund\RefundWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReceivableLedgerTest extends TestCase
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
            'name' => 'Metro Retailers Inc',
            'code' => 'CUST-METRO-01',
            'contact_name' => 'John Metro',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '50000.00',
            'balance' => '0.00',
            'billing_address_line1' => '100 Main St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'US',
            'email' => 'finance@metroretail.com',
            'phone' => '212-555-0100',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Distribution Hub',
            'code' => 'WH-MAIN-01',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV-001',
            'is_active' => true,
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Beverage Tax',
            'code' => 'TAX-STD-BEV',
            'rate' => '0.0000',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Sparkling Spring Water 500ml',
            'sku' => 'BEV-WAT-001',
            'category_id' => $category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => '0.50',
            'default_selling_price' => '1.00',
            'minimum_allowed_price' => '0.80',
            'mrp' => '1.50',
            'unit' => 'bottle',
            'status' => \App\Enums\ProductStatus::ACTIVE,
        ]);

        $this->ledgerService = app(ReceivableLedgerService::class);
    }

    protected function createApprovedOrder(string $grandTotal = '1000.00'): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-'.uniqid(),
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
            'ordered_quantity' => (int) $grandTotal,
            'cancelled_quantity' => 0,
            'fulfilled_quantity' => 0,
            'unit_price' => '1.00',
            'tax_rate_snapshot' => '0.0000',
            'taxable_amount' => $grandTotal,
            'tax_amount' => '0.00',
            'line_total' => $grandTotal,
        ]);

        return $order;
    }

    public function test_invoice_generation_creates_ar_charge_debit(): void
    {
        $order = $this->createApprovedOrder('1000.00');

        $generator = app(InvoiceGeneratorService::class);
        $invoice = $generator->generateForOrder($order, $this->admin);

        // Verify AR transaction created
        $this->assertDatabaseHas('receivable_transactions', [
            'customer_id' => $this->customer->id,
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'source_number' => $invoice->invoice_number,
            'type' => ReceivableTransactionType::INVOICE_CHARGE->value,
            'debit_amount' => '1000.00',
            'credit_amount' => '0.00',
        ]);

        // Verify customer receivable balance
        $balance = $this->ledgerService->getCustomerReceivableBalance($this->customer);
        $this->assertSame('1000.00', $balance);
    }

    public function test_unverified_payment_does_not_create_ar_credit_until_verified(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->recordCashPayment([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => '700.00',
            'payment_date' => Carbon::now()->toDateString(),
        ], $this->salesman);

        // Payment is PENDING_VERIFICATION -> no AR credit should exist yet
        $this->assertDatabaseMissing('receivable_transactions', [
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'type' => ReceivableTransactionType::PAYMENT->value,
        ]);

        // Receivable balance is still $1000
        $this->assertSame('1000.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));

        // Now verify payment
        $verificationService = app(PaymentVerificationService::class);
        $verificationService->verifyPayment($payment, $this->accountant);

        // AR credit is posted
        $this->assertDatabaseHas('receivable_transactions', [
            'customer_id' => $this->customer->id,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'type' => ReceivableTransactionType::PAYMENT->value,
            'debit_amount' => '0.00',
            'credit_amount' => '700.00',
        ]);

        // Receivable balance reduces to $300.00
        $this->assertSame('300.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));
    }

    public function test_payment_reversal_posts_compensating_debit_and_restores_receivable(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        $payment = app(PaymentService::class)->recordCashPayment([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => '700.00',
            'payment_date' => Carbon::now()->toDateString(),
        ], $this->salesman);

        $verifiedPayment = app(PaymentVerificationService::class)->verifyPayment($payment, $this->accountant);
        $this->assertSame('300.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));

        // Now reverse payment
        app(PaymentReversalService::class)->reversePayment(
            $verifiedPayment,
            $this->accountant,
            \App\Enums\PaymentReversalReason::INSUFFICIENT_FUNDS,
            'Cheque bounced due to non-sufficient funds.'
        );

        // Compensating debit posted
        $this->assertDatabaseHas('receivable_transactions', [
            'customer_id' => $this->customer->id,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'type' => ReceivableTransactionType::PAYMENT_REVERSAL->value,
            'debit_amount' => '700.00',
            'credit_amount' => '0.00',
        ]);

        // Original payment row in ledger is UNTOUCHED (append-only)
        $this->assertDatabaseHas('receivable_transactions', [
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'type' => ReceivableTransactionType::PAYMENT->value,
            'credit_amount' => '700.00',
        ]);

        // Receivable balance restored to $1000.00 ($1000 - $700 + $700 = $1000)
        $this->assertSame('1000.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));
    }

    public function test_credit_note_reduces_receivable_and_creates_available_credit_balance(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        // Issue Credit Note of $200
        $creditNote = CreditNote::create([
            'credit_number' => 'CRN-TEST-001',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'invoice_id' => $invoice->id,
            'status' => CreditNoteStatus::ISSUED,
            'currency' => 'USD',
            'subtotal' => '200.00',
            'tax_total' => '0.00',
            'total_amount' => '200.00',
            'allocated_to_refunds' => '0.00',
            'remaining_balance' => '200.00',
            'reason' => 'Customer Return',
            'issued_by' => $this->admin->id,
            'issued_at' => Carbon::now(),
            'customer_name_snapshot' => $this->customer->name,
            'customer_code_snapshot' => $this->customer->code,
        ]);

        $this->ledgerService->recordCreditNote($creditNote, $this->admin);

        // AR Credit transaction exists
        $this->assertDatabaseHas('receivable_transactions', [
            'customer_id' => $this->customer->id,
            'source_type' => 'credit_note',
            'source_id' => $creditNote->id,
            'type' => ReceivableTransactionType::CREDIT_NOTE->value,
            'debit_amount' => '0.00',
            'credit_amount' => '200.00',
        ]);

        // Receivable is $800.00
        $this->assertSame('800.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));

        // Available Credit is $200.00
        $this->assertSame('200.00', $this->ledgerService->getCustomerCreditBalance($this->customer));
    }

    public function test_refund_consumes_credit_note_and_does_not_double_reduce_receivable(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        // Payment $700 -> Receivable = $300, Credit = $0
        $payment = app(PaymentService::class)->recordCashPayment([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => '700.00',
            'payment_date' => Carbon::now()->toDateString(),
        ], $this->salesman);
        app(PaymentVerificationService::class)->verifyPayment($payment, $this->accountant);

        $this->assertSame('300.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));
        $this->assertSame('0.00', $this->ledgerService->getCustomerCreditBalance($this->customer));

        // Credit Note $200 -> Receivable = $100, Credit = $200
        $creditNote = CreditNote::create([
            'credit_number' => 'CRN-TEST-002',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'invoice_id' => $invoice->id,
            'status' => CreditNoteStatus::ISSUED,
            'currency' => 'USD',
            'subtotal' => '200.00',
            'tax_total' => '0.00',
            'total_amount' => '200.00',
            'allocated_to_refunds' => '0.00',
            'remaining_balance' => '200.00',
            'reason' => 'Customer Return',
            'issued_by' => $this->admin->id,
            'issued_at' => Carbon::now(),
            'customer_name_snapshot' => $this->customer->name,
            'customer_code_snapshot' => $this->customer->code,
        ]);
        $this->ledgerService->recordCreditNote($creditNote, $this->admin);

        $this->assertSame('100.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));
        $this->assertSame('200.00', $this->ledgerService->getCustomerCreditBalance($this->customer));

        // Process Refund of $150 against Credit Note
        $refundWorkflow = app(RefundWorkflowService::class);
        $refundReq = $refundWorkflow->createRefundRequest($creditNote, $this->salesman, [
            'requested_amount' => '150.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Partial cash refund',
        ]);
        $refundWorkflow->approveRefund($refundReq, $this->admin, ['notes' => 'Approved']);
        $refundWorkflow->processRefund($refundReq, $this->accountant, [
            'transaction_reference' => 'CASH-DISBURSE-150',
            'disbursed_at' => Carbon::now()->toDateString(),
        ]);

        // CRITICAL CHECK:
        // Receivable balance MUST REMAIN $100.00 (not reduced to -$50.00)
        $this->assertSame('100.00', $this->ledgerService->getCustomerReceivableBalance($this->customer));

        // Available Credit balance MUST BE $50.00 ($200 - $150 = $50)
        $this->assertSame('50.00', $this->ledgerService->getCustomerCreditBalance($this->customer));
    }

    public function test_receivable_transaction_is_immutable_against_update_and_delete(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        $transaction = ReceivableTransaction::where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->firstOrFail();

        // Attempt direct Eloquent update -> Must throw LogicException
        $this->expectException(\LogicException::class);
        $transaction->update(['debit_amount' => '500.00']);
    }

    public function test_receivable_transaction_cannot_be_deleted(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        $transaction = ReceivableTransaction::where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->firstOrFail();

        $this->expectException(\LogicException::class);
        $transaction->delete();
    }

    public function test_duplicate_posting_prevention_guarantees_idempotency(): void
    {
        $order = $this->createApprovedOrder('1000.00');
        $invoice = app(InvoiceGeneratorService::class)->generateForOrder($order, $this->admin);

        // Attempt second record of same invoice
        $secondRecord = $this->ledgerService->recordInvoiceCharge($invoice, $this->admin);

        $this->assertCount(1, ReceivableTransaction::where('source_type', 'invoice')->where('source_id', $invoice->id)->get());
    }
}
