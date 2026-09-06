<?php

declare(strict_types=1);

namespace Tests\Feature\Refund;

use App\Enums\AccountStatus;
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

class RefundApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminApprover;
    protected User $accountant;
    protected User $salesman;
    protected User $otherSalesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Order $order;
    protected OrderItem $orderItem;
    protected ReturnRequest $returnRequest;
    protected CreditNote $creditNote;
    protected RefundRequest $refundRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->adminApprover = User::factory()->create([
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

        $this->otherSalesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-APPR-001',
            'name' => 'Apex Retailers',
            'contact_name' => 'Bob Builder',
            'phone' => '+1 555-0399',
            'email' => 'bob@apexretail.com',
            'billing_address_line1' => '300 Broadway',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '300 Broadway',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'credit_limit' => '50000.00',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-APPR-01',
            'name' => 'Apex Central Warehouse',
            'address_line1' => '300 Depot Rd',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10002',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Apex Parts',
            'code' => 'PRT-01',
        ]);

        $taxProfile = TaxProfile::create([
            'name' => 'Standard VAT 10%',
            'code' => 'VAT10',
            'rate' => '0.1000',
            'is_exempt' => false,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-APEX-01',
            'name' => 'Apex Valve',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => '100.00',
            'default_selling_price' => '150.00',
            'minimum_allowed_price' => '130.00',
            'mrp' => '180.00',
            'status' => 'ACTIVE',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-APPR-001',
            'idempotency_key' => 'idemp-order-appr-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '750.00',
            'tax_total' => '75.00',
            'grand_total' => '825.00',
            'ordered_at' => Carbon::now(),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => 'Apex Valve',
            'sku_snapshot' => 'SKU-APEX-01',
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 5,
            'fulfillable_quantity' => 5,
            'picked_quantity' => 5,
            'dispatched_quantity' => 5,
            'delivered_quantity' => 5,
            'returned_quantity' => 0,
            'unit_price' => '150.00',
            'tax_profile_id' => $taxProfile->id,
            'tax_profile_code_snapshot' => 'VAT10',
            'tax_profile_name_snapshot' => 'Standard VAT 10%',
            'tax_rate' => '0.1000',
            'tax_rate_snapshot' => '0.1000',
            'taxable_amount' => '750.00',
            'tax_amount' => '75.00',
            'line_total' => '825.00',
        ]);

        $this->returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-APPR-001',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->adminApprover->id,
            'requested_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'estimated_refund_subtotal' => '300.00',
            'estimated_refund_tax' => '30.00',
            'estimated_refund_total' => '330.00',
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
            'unit_price_snapshot' => '150.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_amount_snapshot' => '30.00',
            'line_total' => '330.00',
        ]);

        /** @var CreditNoteService $creditService */
        $creditService = app(CreditNoteService::class);
        $this->creditNote = $creditService->generateCreditNote($this->returnRequest, $this->adminApprover);

        /** @var RefundWorkflowService $refundService */
        $refundService = app(RefundWorkflowService::class);
        $this->refundRequest = $refundService->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '200.00',
            'payment_method' => PaymentMethod::CHEQUE->value,
            'reason' => 'Customer requested payout.',
        ]);
    }

    public function test_can_review_and_approve_refund_request(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        // 1. Move to UNDER_REVIEW
        $reviewed = $service->reviewRefund($this->refundRequest, $this->accountant, [
            'notes' => 'Verified bank cheque details.',
        ]);
        $this->assertEquals(RefundStatus::UNDER_REVIEW, $reviewed->status);
        $this->assertEquals($this->accountant->id, $reviewed->reviewed_by);

        // 2. Approve by independent manager (Maker-Checker compliant)
        $approved = $service->approveRefund($reviewed, $this->adminApprover, [
            'notes' => 'Authorized for settlement.',
        ]);

        $this->assertEquals(RefundStatus::APPROVED, $approved->status);
        $this->assertEquals($this->adminApprover->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);

        // Verify events
        $this->assertCount(3, $approved->events);
        $this->assertEquals('APPROVED', $approved->events->last()->action);
    }

    public function test_self_approval_is_strictly_prevented_by_maker_checker(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        // Requester (salesman) attempts to approve own request -> throws exception
        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $this->expectExceptionMessage('Maker-Checker violation');

        $service->approveRefund($this->refundRequest, $this->salesman);
    }

    public function test_super_admin_emergency_override_can_approve_own_request(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        // Super Admin creates a refund request
        $superAdminRefund = $service->createRefundRequest($this->creditNote, $this->superAdmin, [
            'requested_amount' => '50.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Emergency administrative resolution.',
        ]);

        // Super Admin approves own request via emergency override
        $approved = $service->approveRefund($superAdminRefund, $this->superAdmin, [
            'notes' => 'Emergency override execution.',
        ]);

        $this->assertEquals(RefundStatus::APPROVED, $approved->status);
        $this->assertEquals($this->superAdmin->id, $approved->approved_by);
    }

    public function test_can_reject_refund_request_with_documented_reason(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $rejected = $service->rejectRefund($this->refundRequest, $this->adminApprover, [
            'reason' => 'Duplicate refund already settled via account balance.',
        ]);

        $this->assertEquals(RefundStatus::REJECTED, $rejected->status);
        $this->assertEquals($this->adminApprover->id, $rejected->rejected_by);
        $this->assertEquals('Duplicate refund already settled via account balance.', $rejected->rejection_reason);

        // Verify rejection event
        $this->assertEquals('REJECTED', $rejected->events->last()->action);
    }

    public function test_cannot_approve_already_rejected_or_cancelled_request(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $service->rejectRefund($this->refundRequest, $this->adminApprover, [
            'reason' => 'Invalid request.',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $service->approveRefund($this->refundRequest->fresh(), $this->adminApprover);
    }

    public function test_admin_refund_approval_controller_endpoints(): void
    {
        // 1. Review via POST /admin/refunds/{id}/review
        $response = $this->actingAs($this->adminApprover)->postJson("/admin/refunds/{$this->refundRequest->id}/review");
        $response->assertOk();
        $response->assertJsonFragment(['status' => RefundStatus::UNDER_REVIEW->value]);

        // 2. Self-approval via controller blocked (Policy Maker-Checker)
        $response = $this->actingAs($this->salesman)->postJson("/admin/refunds/{$this->refundRequest->id}/approve");
        $response->assertForbidden();

        // 3. Approve by authorized Admin
        $response = $this->actingAs($this->adminApprover)->postJson("/admin/refunds/{$this->refundRequest->id}/approve");
        $response->assertOk();
        $response->assertJsonFragment(['status' => RefundStatus::APPROVED->value]);
    }

    public function test_rejection_controller_endpoint(): void
    {
        $response = $this->actingAs($this->adminApprover)->postJson("/admin/refunds/{$this->refundRequest->id}/reject", [
            'reason' => 'Customer changed preference to store credit.',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['status' => RefundStatus::REJECTED->value]);
    }
}
