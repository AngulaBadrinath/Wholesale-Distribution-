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
use App\Enums\RefundTransactionStatus;
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
use App\Models\RefundTransaction;
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

class RefundProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Order $order;
    protected OrderItem $orderItem;
    protected ReturnRequest $returnRequest;
    protected CreditNote $creditNote;
    protected RefundRequest $approvedRefund;

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
            'code' => 'CUST-PROC-001',
            'name' => 'Prime Distribution',
            'contact_name' => 'Alice Green',
            'phone' => '+1 555-0499',
            'email' => 'alice@primedist.com',
            'billing_address_line1' => '400 Commerce St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '400 Commerce St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'credit_limit' => '50000.00',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-PROC-01',
            'name' => 'Prime Warehouse',
            'address_line1' => '400 Depot Rd',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10002',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Prime Products',
            'code' => 'PRIME-01',
        ]);

        $taxProfile = TaxProfile::create([
            'name' => 'Standard VAT 10%',
            'code' => 'VAT10',
            'rate' => '0.1000',
            'is_exempt' => false,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-PRIME-01',
            'name' => 'Prime Unit',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => '200.00',
            'default_selling_price' => '250.00',
            'minimum_allowed_price' => '220.00',
            'mrp' => '300.00',
            'status' => 'ACTIVE',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-PROC-001',
            'idempotency_key' => 'idemp-order-proc-001',
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
            'product_name_snapshot' => 'Prime Unit',
            'sku_snapshot' => 'SKU-PRIME-01',
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 4,
            'fulfillable_quantity' => 4,
            'picked_quantity' => 4,
            'dispatched_quantity' => 4,
            'delivered_quantity' => 4,
            'returned_quantity' => 0,
            'unit_price' => '250.00',
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
            'return_number' => 'RET-2026-PROC-001',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'estimated_refund_subtotal' => '500.00',
            'estimated_refund_tax' => '50.00',
            'estimated_refund_total' => '550.00',
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
            'unit_price_snapshot' => '250.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_amount_snapshot' => '50.00',
            'line_total' => '550.00',
        ]);

        /** @var CreditNoteService $creditService */
        $creditService = app(CreditNoteService::class);
        $this->creditNote = $creditService->generateCreditNote($this->returnRequest, $this->admin);

        /** @var RefundWorkflowService $refundService */
        $refundService = app(RefundWorkflowService::class);
        $req = $refundService->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '250.00',
            'payment_method' => PaymentMethod::CHEQUE->value,
            'reason' => 'Partial cash settlement for return.',
        ]);

        $this->approvedRefund = $refundService->approveRefund($req, $this->admin);
    }

    public function test_can_process_approved_refund_and_reduce_credit_balance(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $transaction = $service->processRefund($this->approvedRefund, $this->accountant, [
            'reference_number' => 'CHQ-987654',
            'notes' => 'Cheque handed to customer.',
        ]);

        $this->assertInstanceOf(RefundTransaction::class, $transaction);
        $this->assertStringStartsWith('TXN-REF-2026-', $transaction->transaction_number);
        $this->assertEquals(RefundTransactionStatus::COMPLETED, $transaction->status);
        $this->assertEquals('250.00', (string) $transaction->amount);
        $this->assertEquals('CHQ-987654', $transaction->reference_number);
        $this->assertEquals($this->accountant->id, $transaction->processed_by);

        // Verify RefundRequest status updated to PROCESSED
        $this->approvedRefund->refresh();
        $this->assertEquals(RefundStatus::PROCESSED, $this->approvedRefund->status);

        // Verify CreditNote balance reduction and status
        $this->creditNote->refresh();
        $this->assertEquals('250.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('300.00', (string) $this->creditNote->remaining_balance);
        $this->assertEquals(CreditNoteStatus::PARTIALLY_REFUNDED, $this->creditNote->status);

        // Verify financial conservation: total = allocated + remaining
        $this->assertEquals(
            (string) $this->creditNote->total_amount,
            bcadd((string) $this->creditNote->allocated_to_refunds, (string) $this->creditNote->remaining_balance, 2)
        );
    }

    public function test_full_refund_settlement_marks_credit_note_fully_refunded(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        // 1. Process first refund ($250 of $550)
        $service->processRefund($this->approvedRefund, $this->accountant);

        // 2. Request and approve second refund for exact remaining $300
        $req2 = $service->createRefundRequest($this->creditNote->fresh(), $this->salesman, [
            'requested_amount' => '300.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Final settlement.',
        ]);
        $appr2 = $service->approveRefund($req2, $this->admin);

        // 3. Process second refund
        $service->processRefund($appr2, $this->accountant);

        $this->creditNote->refresh();
        $this->assertEquals('550.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('0.00', (string) $this->creditNote->remaining_balance);
        $this->assertEquals(CreditNoteStatus::FULLY_REFUNDED, $this->creditNote->status);
    }

    public function test_cannot_process_unapproved_refund_request(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $unapprovedReq = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '100.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Pending request.',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $service->processRefund($unapprovedReq, $this->accountant);
    }

    public function test_refund_transaction_is_historically_immutable(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);
        $transaction = $service->processRefund($this->approvedRefund, $this->accountant);

        // Attempting to update amount throws LogicException
        $this->expectException(\LogicException::class);
        $transaction->update(['amount' => '999.00']);
    }

    public function test_refund_transaction_cannot_be_deleted(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);
        $transaction = $service->processRefund($this->approvedRefund, $this->accountant);

        $this->expectException(\LogicException::class);
        $transaction->delete();
    }

    public function test_admin_refund_processing_controller_endpoint(): void
    {
        $response = $this->actingAs($this->accountant)->postJson("/admin/refunds/{$this->approvedRefund->id}/process", [
            'reference_number' => 'CHQ-112233',
            'notes' => 'Payout processed via controller.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => RefundTransactionStatus::COMPLETED->value]);
        $response->assertJsonFragment(['reference_number' => 'CHQ-112233']);
    }
}
