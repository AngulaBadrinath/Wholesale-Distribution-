<?php

declare(strict_types=1);

namespace Tests\Feature\Refund;

use App\Enums\AccountStatus;
use App\Enums\CreditNoteStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
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
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Credit\CreditNoteService;
use App\Services\Refund\RefundWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundRequestTest extends TestCase
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
    protected ReturnRequest $returnRequest;
    protected CreditNote $creditNote;

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
            'code' => 'CUST-REF-001',
            'name' => 'Metro Retailers',
            'contact_name' => 'Jane Smith',
            'phone' => '+1 555-0299',
            'email' => 'jane@metroretail.com',
            'billing_address_line1' => '200 High Street',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '200 High Street',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'credit_limit' => '50000.00',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-REF-01',
            'name' => 'Central Warehouse',
            'address_line1' => '100 Depot Rd',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10002',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General Electronics',
            'code' => 'ELEC-01',
        ]);

        $taxProfile = TaxProfile::create([
            'name' => 'Standard VAT 10%',
            'code' => 'VAT10',
            'rate' => '0.1000',
            'is_exempt' => false,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-ELEC-01',
            'name' => 'Electronic Console',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => '150.00',
            'default_selling_price' => '200.00',
            'minimum_allowed_price' => '180.00',
            'mrp' => '250.00',
            'status' => 'ACTIVE',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-REF-001',
            'idempotency_key' => 'idemp-order-ref-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '1000.00',
            'tax_total' => '100.00',
            'grand_total' => '1100.00',
            'ordered_at' => Carbon::now(),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => 'Electronic Console',
            'sku_snapshot' => 'SKU-ELEC-01',
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 5,
            'fulfillable_quantity' => 5,
            'picked_quantity' => 5,
            'dispatched_quantity' => 5,
            'delivered_quantity' => 5,
            'returned_quantity' => 0,
            'unit_price' => '200.00',
            'tax_profile_id' => $taxProfile->id,
            'tax_profile_code_snapshot' => 'VAT10',
            'tax_profile_name_snapshot' => 'Standard VAT 10%',
            'tax_rate' => '0.1000',
            'tax_rate_snapshot' => '0.1000',
            'taxable_amount' => '1000.00',
            'tax_amount' => '100.00',
            'line_total' => '1100.00',
        ]);

        $this->returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-REF-001',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'estimated_refund_subtotal' => '400.00',
            'estimated_refund_tax' => '40.00',
            'estimated_refund_total' => '440.00',
            'is_credit_processed' => false,
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $this->returnRequest->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'requested_quantity' => 2,
            'received_quantity' => 2,
            'accepted_good_quantity' => 2,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'reason_code' => ReturnReasonCode::DEFECTIVE->value,
            'unit_price_snapshot' => '200.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_amount_snapshot' => '40.00',
            'line_total' => '440.00',
        ]);

        /** @var CreditNoteService $creditService */
        $creditService = app(CreditNoteService::class);
        $this->creditNote = $creditService->generateCreditNote($this->returnRequest, $this->admin);
    }

    public function test_can_create_valid_refund_request_against_credit_note(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $refund = $service->createRefundRequest($this->creditNote, $this->admin, [
            'requested_amount' => '200.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Customer requested cash disbursement for return.',
            'notes' => 'Authorized payout.',
        ]);

        $this->assertInstanceOf(RefundRequest::class, $refund);
        $this->assertStringStartsWith('REF-2026-', $refund->refund_number);
        $this->assertEquals(RefundStatus::REQUESTED, $refund->status);
        $this->assertEquals('200.00', (string) $refund->requested_amount);
        $this->assertEquals(PaymentMethod::CASH, $refund->payment_method);
        $this->assertEquals($this->customer->id, $refund->customer_id);
        $this->assertEquals($this->creditNote->id, $refund->credit_note_id);
        $this->assertEquals($this->admin->id, $refund->requested_by);

        // Verify initial lifecycle event
        $this->assertCount(1, $refund->events);
        $event = $refund->events->first();
        $this->assertEquals('REQUESTED', $event->event);
        $this->assertEquals($this->admin->id, $event->user_id);
    }

    public function test_cannot_request_refund_exceeding_available_credit(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createRefundRequest($this->creditNote, $this->admin, [
            'requested_amount' => '500.00', // Credit total is 440.00
            'payment_method' => PaymentMethod::CHEQUE->value,
            'reason' => 'Excess refund request.',
        ]);
    }

    public function test_refund_request_creation_is_idempotent(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $ref1 = $service->createRefundRequest($this->creditNote, $this->admin, [
            'requested_amount' => '100.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Duplicate test.',
            'idempotency_key' => 'idemp-ref-test-01',
        ]);

        $ref2 = $service->createRefundRequest($this->creditNote, $this->admin, [
            'requested_amount' => '100.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Duplicate test.',
            'idempotency_key' => 'idemp-ref-test-01',
        ]);

        $this->assertEquals($ref1->id, $ref2->id);
        $this->assertEquals($ref1->refund_number, $ref2->refund_number);
        $this->assertEquals(1, RefundRequest::count());
    }

    public function test_can_cancel_active_refund_request(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $refund = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '150.00',
            'payment_method' => PaymentMethod::CHEQUE->value,
            'reason' => 'Cheque disbursement requested.',
        ]);

        $cancelled = $service->cancelRefund($refund, $this->salesman, [
            'reason' => 'Customer opted for account credit instead.',
        ]);

        $this->assertEquals(RefundStatus::CANCELLED, $cancelled->status);
        $this->assertEquals($this->salesman->id, $cancelled->cancelled_by);
        $this->assertEquals('Customer opted for account credit instead.', $cancelled->cancellation_reason);

        // Check events
        $this->assertCount(2, $cancelled->events);
        $this->assertEquals('CANCELLED', $cancelled->events->last()->event);
    }

    public function test_admin_refund_request_controller_endpoints(): void
    {
        // Store via POST /admin/refunds
        $response = $this->actingAs($this->admin)->postJson('/admin/refunds', [
            'credit_note_id' => $this->creditNote->id,
            'requested_amount' => '120.00',
            'payment_method' => 'MONEY_ORDER',
            'reason' => 'Money order refund request.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['amount' => '120.00']);
        $refundId = $response->json('refund_request.id');

        // Index via GET /admin/refunds
        $response = $this->actingAs($this->admin)->getJson('/admin/refunds');
        $response->assertOk();

        // Show via GET /admin/refunds/{id}
        $response = $this->actingAs($this->admin)->getJson("/admin/refunds/{$refundId}");
        $response->assertOk();
        $response->assertJsonFragment(['amount' => '120.00']);

        // Cancel via POST /admin/refunds/{id}/cancel
        $response = $this->actingAs($this->admin)->postJson("/admin/refunds/{$refundId}/cancel", [
            'reason' => 'Mistake in amount.',
        ]);
        $response->assertOk();
        $response->assertJsonFragment(['status' => RefundStatus::CANCELLED->value]);
    }

    public function test_salesman_resource_scoping_and_anti_idor(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $refund = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '50.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Petty cash disbursement.',
        ]);

        // Assigned salesman can view
        $response = $this->actingAs($this->salesman)->getJson("/admin/refunds/{$refund->id}");
        $response->assertOk();

        // Foreign salesman cannot access foreign customer's refund (fails closed 404)
        $response = $this->actingAs($this->otherSalesman)->getJson("/admin/refunds/{$refund->id}");
        $response->assertNotFound();
    }
}
