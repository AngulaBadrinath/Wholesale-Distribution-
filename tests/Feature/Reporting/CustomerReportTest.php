<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Reporting\CustomerReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman1;
    protected User $salesman2;
    protected Customer $customer1;
    protected Customer $customer2;
    protected CustomerReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman1 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman2 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer1 = Customer::create([
            'salesman_id' => $this->salesman1->id,
            'name' => 'Acme Supplies',
            'code' => 'CUST-001',
            'contact_name' => 'John Acme',
            'phone' => '+1-555-0101',
            'email' => 'acme@test.com',
            'status' => CustomerStatus::ACTIVE,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '10000.00',
            'balance' => '0.00',
            'billing_address_line1' => '100 Acme Way',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
        ]);

        $this->customer2 = Customer::create([
            'salesman_id' => $this->salesman2->id,
            'name' => 'Global Retailers',
            'code' => 'CUST-002',
            'contact_name' => 'Jane Global',
            'phone' => '+1-555-0102',
            'email' => 'global@test.com',
            'status' => CustomerStatus::ACTIVE,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => '20000.00',
            'balance' => '0.00',
            'billing_address_line1' => '200 Global Rd',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30302',
            'billing_country' => 'US',
        ]);

        $this->reportService = app(CustomerReportService::class);
    }

    protected function createOrder(array $attributes): Order
    {
        $customCreatedAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $order = Order::create(array_merge([
            'order_number' => 'ORD-' . uniqid(),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'draft_token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'version' => 1,
            'currency' => 'USD',
            'subtotal' => '0.00',
            'tax_total' => '0.00',
            'adjustment_total' => '0.00',
            'grand_total' => '0.00',
        ], $attributes));

        if ($customCreatedAt) {
            $order->created_at = $customCreatedAt;
            $order->saveQuietly();
        }

        return $order;
    }

    protected function createInvoice(Customer $customer, string $grandTotal, string $amountPaid, string $dueDate, InvoiceStatus $status = InvoiceStatus::ISSUED): Invoice
    {
        $amountDue = max(0.00, round((float) $grandTotal - (float) $amountPaid, 2));

        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'salesman_id' => $customer->salesman_id,
            'created_by' => $customer->salesman_id,
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'order_id' => $order->id,
            'customer_id' => $customer->id,
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
            'amount_due' => number_format($amountDue, 2, '.', ''),
            'payment_status' => (float) $amountPaid >= (float) $grandTotal ? PaymentStatus::PAID : ((float) $amountPaid > 0 ? PaymentStatus::PARTIALLY_PAID : PaymentStatus::UNPAID),
            'customer_name_snapshot' => $customer->name,
            'customer_code_snapshot' => $customer->code,
            'company_legal_name_snapshot' => 'Wholesale Distribution Inc.',
            'company_address_snapshot' => '100 Main St, Atlanta, GA 30301',
            'company_phone_snapshot' => '+1-555-0000',
            'company_email_snapshot' => 'billing@wholesale.test',
            'billing_address_line1_snapshot' => '100 Acme Way',
            'billing_city_snapshot' => 'Atlanta',
            'billing_state_snapshot' => 'GA',
            'billing_postal_code_snapshot' => '30301',
            'billing_country_snapshot' => 'US',
            'shipping_address_line1_snapshot' => '100 Acme Way',
            'shipping_city_snapshot' => 'Atlanta',
            'shipping_state_snapshot' => 'GA',
            'shipping_postal_code_snapshot' => '30301',
            'shipping_country_snapshot' => 'US',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_customer_report_aging_and_receivables(): void
    {
        // Open Invoice 1 for Customer 1: Due in 10 days (Current)
        $this->createInvoice($this->customer1, '550.00', '0.00', '2026-09-17');

        // Open Invoice 2 for Customer 1: Overdue by 45 days (days_31_60)
        $this->createInvoice($this->customer1, '1100.00', '100.00', '2026-07-24');

        $report = $this->reportService->getCustomerReport([
            'as_of_date' => '2026-09-07',
        ], $this->admin);

        $this->assertEquals(2, $report['total']);
        $this->assertEquals('1550.00', $report['summary']['total_receivables']);

        $c1Data = collect($report['data'])->firstWhere('customer_id', $this->customer1->id);
        $this->assertNotNull($c1Data);
        $this->assertEquals('1550.00', $c1Data['total_receivable']);
        $this->assertEquals('550.00', $c1Data['aging_current']);
        $this->assertEquals('1000.00', $c1Data['aging_31_60']);
    }

    public function test_customer_purchase_frequency_and_cadence_calculation(): void
    {
        // 3 Orders on Sep 1, Sep 11, Sep 21 (Diff: 20 days / 2 gaps = 10 days cadence)
        $this->createOrder([
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'grand_total' => '100.00',
            'created_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);

        $this->createOrder([
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'grand_total' => '200.00',
            'created_at' => Carbon::parse('2026-09-11 10:00:00'),
        ]);

        $this->createOrder([
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::COMPLETED,
            'grand_total' => '300.00',
            'created_at' => Carbon::parse('2026-09-21 10:00:00'),
        ]);

        $stats = $this->reportService->getCustomerPurchaseStats($this->customer1->id);

        $this->assertEquals(3, $stats['order_count']);
        $this->assertEquals('2026-09-01', $stats['first_order_date']);
        $this->assertEquals('2026-09-21', $stats['last_order_date']);
        $this->assertEquals(10.0, $stats['average_frequency_days']);
        $this->assertEquals('600.00', $stats['total_spend']);
    }

    public function test_customer_report_salesman_scoping(): void
    {
        // Salesman 1 only sees customer 1
        $s1Report = $this->reportService->getCustomerReport([], $this->salesman1);
        $this->assertEquals(1, $s1Report['total']);
        $this->assertEquals($this->customer1->id, $s1Report['data'][0]['customer_id']);

        // Admin sees both customers
        $adminReport = $this->reportService->getCustomerReport([], $this->admin);
        $this->assertEquals(2, $adminReport['total']);
    }

    public function test_customer_report_web_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports/customers');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Reporting/Customers'));
    }
}
