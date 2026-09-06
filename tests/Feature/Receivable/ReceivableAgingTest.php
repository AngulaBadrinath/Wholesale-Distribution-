<?php

declare(strict_types=1);

namespace Tests\Feature\Receivable;

use App\Enums\AccountStatus;
use App\Enums\CreditNoteStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Receivable\ReceivableAgingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableAgingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Order $order;
    protected ReceivableAgingService $agingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'name' => 'Apex Retailers',
            'code' => 'CUST-APEX-01',
            'contact_name' => 'Alice Apex',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '100000.00',
            'balance' => '0.00',
            'billing_address_line1' => '500 Commerce Way',
            'billing_city' => 'Apex City',
            'billing_state' => 'CA',
            'billing_postal_code' => '90001',
            'billing_country' => 'US',
            'email' => 'ap@apexretail.com',
            'phone' => '310-555-0200',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Apex Hub',
            'code' => 'WH-APEX-01',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-APEX-001',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal' => '1000.00',
            'tax_total' => '0.00',
            'adjustment_total' => '0.00',
            'grand_total' => '1000.00',
            'currency' => 'USD',
            'ordered_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
        ]);

        $this->agingService = app(ReceivableAgingService::class);
    }

    protected function createInvoice(string $grandTotal, string $amountPaid, string $dueDate, InvoiceStatus $status = InvoiceStatus::ISSUED): Invoice
    {
        $amountDue = max(0.00, round((float) $grandTotal - (float) $amountPaid, 2));

        $order = Order::create([
            'order_number' => 'ORD-INV-'.uniqid(),
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

        return Invoice::create([
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'invoice_date' => Carbon::parse($dueDate)->subDays(30)->toDateString(),
            'due_date' => $dueDate,
            'payment_terms' => PaymentTerms::NET_30,
            'currency' => 'USD',
            'subtotal' => $grandTotal,
            'tax_total' => '0.00',
            'adjustment_total' => '0.00',
            'grand_total' => $grandTotal,
            'amount_paid' => $amountPaid,
            'amount_due' => (string) $amountDue,
            'payment_status' => (float) $amountPaid >= (float) $grandTotal ? PaymentStatus::PAID : ((float) $amountPaid > 0 ? PaymentStatus::PARTIALLY_PAID : PaymentStatus::UNPAID),
            'customer_name_snapshot' => $this->customer->name,
            'customer_code_snapshot' => $this->customer->code,
            'billing_address_line1_snapshot' => '500 Commerce Way',
            'billing_city_snapshot' => 'Apex City',
            'billing_state_snapshot' => 'CA',
            'billing_postal_code_snapshot' => '90001',
            'billing_country_snapshot' => 'US',
            'shipping_address_line1_snapshot' => '500 Commerce Way',
            'shipping_city_snapshot' => 'Apex City',
            'shipping_state_snapshot' => 'CA',
            'shipping_postal_code_snapshot' => '90001',
            'shipping_country_snapshot' => 'US',
            'company_legal_name_snapshot' => 'Wholesale Distribution Corp',
            'company_address_snapshot' => '100 Distribution Hub, Apex City, CA 90001, US',
        ]);
    }

    public function test_aging_bucket_boundaries_are_strictly_deterministic(): void
    {
        $refDate = Carbon::parse('2026-09-30');

        // 1. Current: Due on or after reference date (e.g. Due 2026-09-30 or 2026-10-15) -> 0 days overdue
        $this->createInvoice('100.00', '0.00', '2026-09-30'); // 0 days overdue -> Current
        $this->createInvoice('50.00', '0.00', '2026-10-15');  // Future due -> Current

        // 2. 1-30 Days: Due 1 to 30 days before ref date
        $this->createInvoice('200.00', '0.00', '2026-09-29'); // 1 day overdue -> 1-30
        $this->createInvoice('300.00', '0.00', '2026-08-31'); // 30 days overdue -> 1-30

        // 3. 31-60 Days: Due 31 to 60 days before ref date
        $this->createInvoice('400.00', '0.00', '2026-08-30'); // 31 days overdue -> 31-60
        $this->createInvoice('500.00', '0.00', '2026-08-01'); // 60 days overdue -> 31-60

        // 4. 61-90 Days: Due 61 to 90 days before ref date
        $this->createInvoice('600.00', '0.00', '2026-07-31'); // 61 days overdue -> 61-90
        $this->createInvoice('700.00', '0.00', '2026-07-02'); // 90 days overdue -> 61-90

        // 5. 91+ Days: Due 91+ days before ref date
        $this->createInvoice('800.00', '0.00', '2026-07-01'); // 91 days overdue -> 91+
        $this->createInvoice('900.00', '0.00', '2026-05-01'); // 152 days overdue -> 91+

        $aging = $this->agingService->getAgingForCustomer($this->customer, $refDate);

        $this->assertSame('150.00', $aging['current']);
        $this->assertSame('500.00', $aging['days_1_30']);
        $this->assertSame('900.00', $aging['days_31_60']);
        $this->assertSame('1300.00', $aging['days_61_90']);
        $this->assertSame('1700.00', $aging['days_91_plus']);
        $this->assertSame('4550.00', $aging['total_receivable']);
    }

    public function test_partially_paid_invoices_age_only_outstanding_balance(): void
    {
        $refDate = Carbon::parse('2026-09-30');

        // Invoice of $1000 with $600 paid, due 2026-08-31 (30 days overdue) -> remaining $400 in 1-30 days
        $this->createInvoice('1000.00', '600.00', '2026-08-31', InvoiceStatus::ISSUED);

        $aging = $this->agingService->getAgingForCustomer($this->customer, $refDate);

        $this->assertSame('400.00', $aging['days_1_30']);
        $this->assertSame('400.00', $aging['total_receivable']);
    }

    public function test_fully_paid_invoices_do_not_participate_in_aging(): void
    {
        $refDate = Carbon::parse('2026-09-30');

        // Invoice of $1000 fully paid, due 2026-05-01 (overdue by 150 days)
        $this->createInvoice('1000.00', '1000.00', '2026-05-01', InvoiceStatus::PAID);

        $aging = $this->agingService->getAgingForCustomer($this->customer, $refDate);

        $this->assertSame('0.00', $aging['days_91_plus']);
        $this->assertSame('0.00', $aging['total_receivable']);
        $this->assertCount(0, $aging['invoices']);
    }

    public function test_customer_credit_balance_is_kept_separate_and_not_in_negative_aging_buckets(): void
    {
        $refDate = Carbon::parse('2026-09-30');

        // Invoice of $500 in 1-30 days
        $this->createInvoice('500.00', '0.00', '2026-09-15');

        // Customer has Credit Note of $800
        CreditNote::create([
            'credit_number' => 'CRN-TEST-AG-1',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'invoice_id' => null,
            'status' => CreditNoteStatus::ISSUED,
            'currency' => 'USD',
            'subtotal' => '800.00',
            'tax_total' => '0.00',
            'total_amount' => '800.00',
            'allocated_to_refunds' => '0.00',
            'remaining_balance' => '800.00',
            'reason' => 'Customer Return',
            'issued_by' => $this->admin->id,
            'issued_at' => Carbon::now(),
            'customer_name_snapshot' => $this->customer->name,
            'customer_code_snapshot' => $this->customer->code,
        ]);

        $aging = $this->agingService->getAgingForCustomer($this->customer, $refDate);

        // Days 1-30 remains $500 (NOT -$300!)
        $this->assertSame('500.00', $aging['days_1_30']);
        $this->assertSame('500.00', $aging['total_receivable']);

        // Available credit reported separately as $800.00
        $this->assertSame('800.00', $aging['available_credit']);
    }
}
