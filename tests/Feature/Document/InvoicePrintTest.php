<?php

namespace Tests\Feature\Document;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerms;
use App\Enums\UserRole;
use App\Models\CompanyInformation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Invoices\InvoiceGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman1;
    protected User $salesman2;
    protected User $warehouseManager;
    protected User $deliveryPartner;
    protected Customer $customer1;
    protected Customer $customer2;
    protected TaxProfile $taxProfile;
    protected Product $product;
    protected Invoice $invoice1;
    protected Invoice $invoice2;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Services\System\CompanyInformationService::clearCache();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => 'ACTIVE',
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => 'ACTIVE',
        ]);

        $this->salesman1 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => 'ACTIVE',
        ]);

        $this->salesman2 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => 'ACTIVE',
        ]);

        $this->warehouseManager = User::factory()->create([
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => 'ACTIVE',
        ]);

        $this->deliveryPartner = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => 'ACTIVE',
        ]);

        $this->customer1 = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Acme Wholesale Corp',
            'contact_name' => 'Alice Smith',
            'email' => 'alice@acme.com',
            'phone' => '+1 555-0100',
            'billing_address_line1' => '100 Commerce Blvd',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '100 Warehouse Dock',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'tax_id' => 'TAX-112233',
            'payment_terms' => PaymentTerms::NET_30,
            'salesman_id' => $this->salesman1->id,
            'status' => 'ACTIVE',
        ]);

        $this->customer2 = Customer::create([
            'code' => 'CUST-002',
            'name' => 'Beta Logistics Inc',
            'contact_name' => 'Bob Jones',
            'email' => 'bob@betalogistics.com',
            'phone' => '+1 555-0200',
            'billing_address_line1' => '200 Logistics Way',
            'billing_city' => 'Savannah',
            'billing_state' => 'GA',
            'billing_postal_code' => '31401',
            'billing_country' => 'US',
            'shipping_address_line1' => '200 Port Way',
            'shipping_city' => 'Savannah',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '31401',
            'shipping_country' => 'US',
            'payment_terms' => PaymentTerms::NET_15,
            'salesman_id' => $this->salesman2->id,
            'status' => 'ACTIVE',
        ]);

        $this->taxProfile = TaxProfile::create([
            'code' => 'STD_TAX',
            'name' => 'Standard Tax Rate',
            'rate' => 0.0800,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-PRNT-01',
            'name' => 'Industrial Heavy Widget',
            'unit' => 'crate',
            'cost_price' => 50.00,
            'minimum_allowed_price' => 80.00,
            'default_selling_price' => 100.00,
            'mrp' => 120.00,
            'tax_profile_id' => $this->taxProfile->id,
            'status' => 'ACTIVE',
        ]);

        CompanyInformation::updateOrCreate(
            ['is_singleton' => true],
            [
                'legal_name' => 'Apex Distribution Corp',
                'dba_name' => 'Apex Wholesale',
                'address_line1' => '500 Logistics Way',
                'city' => 'Atlanta',
                'state' => 'GA',
                'postal_code' => '30301',
                'country' => 'US',
                'phone' => '+1 800-555-0199',
                'email' => 'invoicing@apexdist.com',
                'tax_id' => 'EIN-9988776',
                'state_tax_id' => 'GA-112233',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'invoice_footer_note' => 'Thank you for your business. Remit within agreed terms.',
            ]
        );

        $order1 = Order::create([
            'order_number' => 'ORD-2026-000001',
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'currency' => 'USD',
            'subtotal' => 200.00,
            'tax_total' => 16.00,
            'adjustment_total' => 0.00,
            'grand_total' => 216.00,
            'submitted_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'approved_by' => $this->admin->id,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => 'Industrial Heavy Widget',
            'sku_snapshot' => 'SKU-PRNT-01',
            'unit_snapshot' => 'crate',
            'ordered_quantity' => 2,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 2,
            'unit_price' => 100.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => 'STD_TAX',
            'tax_profile_name_snapshot' => 'Standard Tax Rate',
            'tax_rate_snapshot' => 0.0800,
            'taxable_amount' => 200.00,
            'tax_amount' => 16.00,
            'line_total' => 216.00,
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-2026-000002',
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer2->id,
            'salesman_id' => $this->salesman2->id,
            'created_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'currency' => 'USD',
            'subtotal' => 100.00,
            'tax_total' => 8.00,
            'adjustment_total' => 0.00,
            'grand_total' => 108.00,
            'submitted_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'approved_by' => $this->admin->id,
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => 'Industrial Heavy Widget',
            'sku_snapshot' => 'SKU-PRNT-01',
            'unit_snapshot' => 'crate',
            'ordered_quantity' => 1,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 1,
            'unit_price' => 100.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => 'STD_TAX',
            'tax_profile_name_snapshot' => 'Standard Tax Rate',
            'tax_rate_snapshot' => 0.0800,
            'taxable_amount' => 100.00,
            'tax_amount' => 8.00,
            'line_total' => 108.00,
        ]);

        $generator = app(InvoiceGeneratorService::class);
        $this->invoice1 = $generator->generateForOrder($order1, $this->admin);
        $this->invoice2 = $generator->generateForOrder($order2, $this->admin);
    }

    public function test_authorized_admin_can_access_printable_invoice(): void
    {
        $response = $this->actingAs($this->admin)->get(route('invoices.print', $this->invoice1));

        $response->assertStatus(200);
        $response->assertViewIs('documents.invoice');
        $response->assertSee('TAX INVOICE');
        $response->assertSee($this->invoice1->invoice_number);
    }

    public function test_accountant_can_access_printable_invoice(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('invoices.print', $this->invoice1));

        $response->assertStatus(200);
        $response->assertSee('TAX INVOICE');
    }

    public function test_salesman_can_access_printable_invoice_for_assigned_customer(): void
    {
        // Salesman 1 is assigned to Customer 1 (Invoice 1)
        $response = $this->actingAs($this->salesman1)->get(route('invoices.print', $this->invoice1));

        $response->assertStatus(200);
        $response->assertSee($this->invoice1->invoice_number);
        $response->assertSee('Acme Wholesale Corp');
    }

    public function test_salesman_cannot_access_printable_invoice_for_unassigned_customer_anti_idor(): void
    {
        // Salesman 1 is NOT assigned to Customer 2 (Invoice 2) -> must return 404
        $response = $this->actingAs($this->salesman1)->get(route('invoices.print', $this->invoice2));

        $response->assertStatus(404);
    }

    public function test_unauthorized_roles_receive_403_for_printable_invoice(): void
    {
        // Warehouse Manager does not have invoice.print permission
        $response1 = $this->actingAs($this->warehouseManager)->get(route('invoices.print', $this->invoice1));
        $response1->assertStatus(403);

        // Delivery Partner does not have invoice.print permission
        $response2 = $this->actingAs($this->deliveryPartner)->get(route('invoices.print', $this->invoice1));
        $response2->assertStatus(403);
    }

    /**
     * RULE-DOC-001 Invariant Test:
     * HARD RULE — ZERO product images, ZERO product thumbnails, ZERO product <img> tags.
     */
    public function test_rule_doc_001_strictly_no_product_images_in_printable_html(): void
    {
        $response = $this->actingAs($this->admin)->get(route('invoices.print', $this->invoice1));
        $response->assertStatus(200);

        $html = $response->getContent();

        // 1. Assert zero <img> tags in the entire output document
        $this->assertStringNotContainsString('<img', $html, 'Violation of RULE-DOC-001: <img> tag found in invoice output.');

        // 2. Assert zero product image or thumbnail references
        $this->assertStringNotContainsString('thumbnail', $html);
        $this->assertStringNotContainsString('.jpg', $html);
        $this->assertStringNotContainsString('.jpeg', $html);
        $this->assertStringNotContainsString('.png', $html);
        $this->assertStringNotContainsString('.webp', $html);
    }

    public function test_printable_invoice_contains_all_mandatory_financial_and_tax_details(): void
    {
        $response = $this->actingAs($this->admin)->get(route('invoices.print', $this->invoice1));
        $response->assertStatus(200);

        $response->assertSee('Apex Distribution Corp');
        $response->assertSee('EIN-9988776');
        $response->assertSee('GA-112233');
        $response->assertSee('Acme Wholesale Corp');
        $response->assertSee('CUST-001');
        $response->assertSee('SKU-PRNT-01');
        $response->assertSee('Industrial Heavy Widget');
        $response->assertSee('$200.00'); // Subtotal
        $response->assertSee('$16.00');  // Tax
        $response->assertSee('$216.00'); // Grand total
        $response->assertSee('Net 30 Days');
        $response->assertSee('Thank you for your business. Remit within agreed terms.');
    }

    public function test_printable_invoice_has_dedicated_print_css_and_hidden_chrome(): void
    {
        $response = $this->actingAs($this->admin)->get(route('invoices.print', $this->invoice1));
        $response->assertStatus(200);

        $response->assertSee('@page {', false);
        $response->assertSee('@media print', false);
        $response->assertSee('.no-print-bar', false);
    }
}
