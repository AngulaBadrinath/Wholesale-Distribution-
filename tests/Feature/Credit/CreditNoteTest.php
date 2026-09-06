<?php

declare(strict_types=1);

namespace Tests\Feature\Credit;

use App\Enums\AccountStatus;
use App\Enums\CreditNoteStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Credit\CreditNoteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected User $otherSalesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Order $order;
    protected OrderItem $orderItem;
    protected ReturnRequest $approvedReturn;

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

        $this->otherSalesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-ACME-001',
            'name' => 'Acme Supplies Ltd',
            'contact_name' => 'John Doe',
            'phone' => '+1 555-0199',
            'email' => 'acme@example.com',
            'billing_address_line1' => '100 Commerce Blvd',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Commerce Blvd',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'credit_limit' => '50000.00',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-MAIN',
            'name' => 'Main Logistics Center',
            'address_line1' => '456 Warehouse Way',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10002',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Industrial Equipment',
            'code' => 'IND-01',
        ]);

        $taxProfile = TaxProfile::create([
            'name' => 'Standard VAT 10%',
            'code' => 'VAT10',
            'rate' => '0.1000',
            'is_exempt' => false,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-WIDGET-A',
            'name' => 'Industrial Widget A',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => '80.00',
            'default_selling_price' => '100.00',
            'minimum_allowed_price' => '90.00',
            'mrp' => '120.00',
            'status' => 'ACTIVE',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-000001',
            'idempotency_key' => 'idemp-order-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '500.00',
            'tax_total' => '50.00',
            'grand_total' => '550.00',
            'ordered_at' => Carbon::now(),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => 'Industrial Widget A',
            'sku_snapshot' => 'SKU-WIDGET-A',
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 5,
            'fulfillable_quantity' => 5,
            'picked_quantity' => 5,
            'dispatched_quantity' => 5,
            'delivered_quantity' => 5,
            'returned_quantity' => 0,
            'unit_price' => '100.00',
            'tax_profile_id' => $taxProfile->id,
            'tax_profile_code_snapshot' => 'VAT10',
            'tax_profile_name_snapshot' => 'Standard VAT 10%',
            'tax_rate' => '0.1000',
            'tax_rate_snapshot' => '0.1000',
            'taxable_amount' => '500.00',
            'tax_amount' => '50.00',
            'line_total' => '550.00',
        ]);

        $this->approvedReturn = ReturnRequest::create([
            'return_number' => 'RET-2026-000001',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'estimated_refund_subtotal' => '200.00',
            'estimated_refund_tax' => '20.00',
            'estimated_refund_total' => '220.00',
            'is_credit_processed' => false,
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $this->approvedReturn->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'requested_quantity' => 2,
            'received_quantity' => 2,
            'accepted_good_quantity' => 2,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'reason_code' => ReturnReasonCode::EXCESS_STOCK->value,
            'unit_price_snapshot' => '100.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_amount_snapshot' => '20.00',
            'line_total' => '220.00',
        ]);
    }

    public function test_can_generate_credit_note_from_approved_return(): void
    {
        /** @var CreditNoteService $service */
        $service = app(CreditNoteService::class);

        $creditNote = $service->generateCreditNote($this->approvedReturn, $this->admin, [
            'reason' => 'Customer return satisfaction',
        ]);

        $this->assertInstanceOf(CreditNote::class, $creditNote);
        $this->assertStringStartsWith('CR-2026-', $creditNote->credit_number);
        $this->assertEquals(CreditNoteStatus::ISSUED, $creditNote->status);
        $this->assertEquals('200.00', (string) $creditNote->subtotal);
        $this->assertEquals('20.00', (string) $creditNote->tax_total);
        $this->assertEquals('220.00', (string) $creditNote->total_amount);
        $this->assertEquals('0.00', (string) $creditNote->allocated_to_refunds);
        $this->assertEquals('220.00', (string) $creditNote->remaining_balance);
        $this->assertEquals('Acme Supplies Ltd', $creditNote->customer_name_snapshot);
        $this->assertEquals('CUST-ACME-001', $creditNote->customer_code_snapshot);
        $this->assertEquals('100 Commerce Blvd', $creditNote->billing_address_line1_snapshot);
        $this->assertEquals('Customer return satisfaction', $creditNote->reason);

        // Verify items
        $this->assertCount(1, $creditNote->items);
        $item = $creditNote->items->first();
        $this->assertEquals('Industrial Widget A', $item->product_name_snapshot);
        $this->assertEquals('SKU-WIDGET-A', $item->sku_snapshot);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals('100.00', (string) $item->unit_price_snapshot);
        $this->assertEquals('0.1000', (string) $item->tax_rate_snapshot);
        $this->assertEquals('20.00', (string) $item->tax_amount_snapshot);
        $this->assertEquals('200.00', (string) $item->line_subtotal);
        $this->assertEquals('220.00', (string) $item->line_total);

        // Verify return request updated
        $this->approvedReturn->refresh();
        $this->assertTrue($this->approvedReturn->is_credit_processed);
        $this->assertEquals($creditNote->id, $this->approvedReturn->credit_note_id);
    }

    public function test_credit_note_and_items_are_immutable(): void
    {
        $service = app(CreditNoteService::class);
        $creditNote = $service->generateCreditNote($this->approvedReturn, $this->admin);

        // Attempting to mutate financial totals directly must throw LogicException
        $this->expectException(\LogicException::class);
        $creditNote->update([
            'total_amount' => '999.00',
        ]);
    }

    public function test_credit_note_cannot_be_deleted(): void
    {
        $service = app(CreditNoteService::class);
        $creditNote = $service->generateCreditNote($this->approvedReturn, $this->admin);

        $this->expectException(\LogicException::class);
        $creditNote->delete();
    }

    public function test_credit_note_item_cannot_be_modified(): void
    {
        $service = app(CreditNoteService::class);
        $creditNote = $service->generateCreditNote($this->approvedReturn, $this->admin);
        $item = $creditNote->items->first();

        $this->expectException(\LogicException::class);
        $item->update([
            'unit_price_snapshot' => '500.00',
        ]);
    }

    public function test_credit_note_generation_is_idempotent(): void
    {
        $service = app(CreditNoteService::class);

        $cn1 = $service->generateCreditNote($this->approvedReturn, $this->admin, [
            'idempotency_key' => 'idemp-key-12345',
        ]);

        $cn2 = $service->generateCreditNote($this->approvedReturn, $this->admin, [
            'idempotency_key' => 'idemp-key-12345',
        ]);

        $this->assertEquals($cn1->id, $cn2->id);
        $this->assertEquals($cn1->credit_number, $cn2->credit_number);
        $this->assertEquals(1, CreditNote::count());
    }

    public function test_cannot_generate_credit_note_for_unapproved_return(): void
    {
        $unapprovedReturn = ReturnRequest::create([
            'return_number' => 'RET-2026-000002',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => ReturnStatus::REQUESTED,
            'created_by' => $this->salesman->id,
            'requested_at' => Carbon::now(),
            'is_credit_processed' => false,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/admin/credits', [
            'return_request_id' => $unapprovedReturn->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_credit_note_controller_endpoints(): void
    {
        $service = app(CreditNoteService::class);
        $creditNote = $service->generateCreditNote($this->approvedReturn, $this->admin);

        // Index
        $response = $this->actingAs($this->admin)->getJson('/admin/credits');
        $response->assertOk();
        $response->assertJsonFragment(['credit_number' => $creditNote->credit_number]);

        // Show
        $response = $this->actingAs($this->admin)->getJson("/admin/credits/{$creditNote->id}");
        $response->assertOk();
        $response->assertJsonFragment(['credit_number' => $creditNote->credit_number]);

        // Eligibility calculation endpoint
        $newReturn = ReturnRequest::create([
            'return_number' => 'RET-2026-000003',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'is_credit_processed' => false,
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $newReturn->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'requested_quantity' => 1,
            'received_quantity' => 1,
            'accepted_good_quantity' => 1,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'reason_code' => ReturnReasonCode::DEFECTIVE->value,
            'unit_price_snapshot' => '100.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_amount_snapshot' => '10.00',
            'line_total' => '110.00',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/admin/returns/{$newReturn->id}/credit-eligibility");
        $response->assertOk();
        $response->assertJsonFragment(['eligible_total' => '110.00']);
    }

    public function test_salesman_resource_scoping_and_anti_idor(): void
    {
        $service = app(CreditNoteService::class);
        $creditNote = $service->generateCreditNote($this->approvedReturn, $this->admin);

        // Assigned salesman can access their customer's credit note
        $response = $this->actingAs($this->salesman)->getJson("/admin/credits/{$creditNote->id}");
        $response->assertOk();

        // Other salesman cannot access foreign credit note (fails closed 404)
        $response = $this->actingAs($this->otherSalesman)->getJson("/admin/credits/{$creditNote->id}");
        $response->assertNotFound();
    }
}
