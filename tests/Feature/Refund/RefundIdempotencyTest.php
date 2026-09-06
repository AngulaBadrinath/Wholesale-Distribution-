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

class RefundIdempotencyTest extends TestCase
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
            'code' => 'CUST-IDEMP-001',
            'name' => 'Idempotent Solutions',
            'contact_name' => 'Ian Dempo',
            'phone' => '+1 555-0699',
            'email' => 'ian@idempotent.com',
            'billing_address_line1' => '600 Commerce Lane',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '600 Commerce Lane',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'credit_limit' => '50000.00',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-IDEMP-01',
            'name' => 'Idempotency Warehouse',
            'address_line1' => '600 Depot Rd',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10002',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Precision Gear',
            'code' => 'GEAR-01',
        ]);

        $taxProfile = TaxProfile::create([
            'name' => 'Standard VAT 10%',
            'code' => 'VAT10',
            'rate' => '0.1000',
            'is_exempt' => false,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-GEAR-01',
            'name' => 'Precision Gear Box',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => '250.00',
            'default_selling_price' => '300.00',
            'minimum_allowed_price' => '280.00',
            'mrp' => '350.00',
            'status' => 'ACTIVE',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-IDEMP-001',
            'idempotency_key' => 'idemp-order-idemp-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '600.00',
            'tax_total' => '60.00',
            'grand_total' => '660.00',
            'ordered_at' => Carbon::now(),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => 'Precision Gear Box',
            'sku_snapshot' => 'SKU-GEAR-01',
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 2,
            'fulfillable_quantity' => 2,
            'picked_quantity' => 2,
            'dispatched_quantity' => 2,
            'delivered_quantity' => 2,
            'returned_quantity' => 0,
            'unit_price' => '300.00',
            'tax_profile_id' => $taxProfile->id,
            'tax_profile_code_snapshot' => 'VAT10',
            'tax_profile_name_snapshot' => 'Standard VAT 10%',
            'tax_rate' => '0.1000',
            'tax_rate_snapshot' => '0.1000',
            'taxable_amount' => '600.00',
            'tax_amount' => '60.00',
            'line_total' => '660.00',
        ]);

        $this->returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-IDEMP-001',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
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
            'requested_quantity' => 1,
            'received_quantity' => 1,
            'accepted_good_quantity' => 1,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'reason_code' => ReturnReasonCode::DEFECTIVE->value,
            'unit_price_snapshot' => '300.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_amount_snapshot' => '30.00',
            'line_total' => '330.00',
        ]);

        /** @var CreditNoteService $creditService */
        $creditService = app(CreditNoteService::class);
        $this->creditNote = $creditService->generateCreditNote($this->returnRequest, $this->admin);
    }

    public function test_refund_processing_is_idempotent_with_same_key(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $req = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '330.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Idempotent disbursement test',
        ]);
        $approved = $service->approveRefund($req, $this->admin);

        // First call
        $txn1 = $service->processRefund($approved, $this->accountant, [
            'idempotency_key' => 'idemp-process-key-99',
            'reference_number' => 'CASH-001',
        ]);

        // Second call with same idempotency key
        $txn2 = $service->processRefund($approved, $this->accountant, [
            'idempotency_key' => 'idemp-process-key-99',
            'reference_number' => 'CASH-001',
        ]);

        $this->assertEquals($txn1->id, $txn2->id);
        $this->assertEquals($txn1->transaction_number, $txn2->transaction_number);
        $this->assertEquals(1, RefundTransaction::count());

        $this->creditNote->refresh();
        $this->assertEquals('330.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('0.00', (string) $this->creditNote->remaining_balance);
        $this->assertEquals(CreditNoteStatus::FULLY_REFUNDED, $this->creditNote->status);
    }

    public function test_reprocessing_already_processed_refund_returns_existing_transaction(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        $req = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '200.00',
            'payment_method' => PaymentMethod::CHEQUE->value,
            'reason' => 'Partial disbursement',
        ]);
        $approved = $service->approveRefund($req, $this->admin);

        $txn1 = $service->processRefund($approved, $this->accountant);

        // Call again on the now-PROCESSED request
        $txn2 = $service->processRefund($approved->fresh(), $this->accountant);

        $this->assertEquals($txn1->id, $txn2->id);
        $this->assertEquals(1, RefundTransaction::count());

        $this->creditNote->refresh();
        $this->assertEquals('200.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('130.00', (string) $this->creditNote->remaining_balance);
    }
}
