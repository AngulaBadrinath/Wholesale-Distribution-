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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class RefundConcurrencyTest extends TestCase
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
            'code' => 'CUST-CONC-001',
            'name' => 'Concurrency Enterprises',
            'contact_name' => 'Charles Babbage',
            'phone' => '+1 555-0599',
            'email' => 'charles@concurrency.com',
            'billing_address_line1' => '500 Tech Parkway',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '500 Tech Parkway',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'credit_limit' => '50000.00',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-CONC-01',
            'name' => 'Concurrency Center',
            'address_line1' => '500 Depot Rd',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10002',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Hardware Modules',
            'code' => 'HDW-01',
        ]);

        $taxProfile = TaxProfile::create([
            'name' => 'Zero Tax 0%',
            'code' => 'VAT0',
            'rate' => '0.0000',
            'is_exempt' => true,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-CONC-01',
            'name' => 'Hardware Module 500',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => '400.00',
            'default_selling_price' => '500.00',
            'minimum_allowed_price' => '450.00',
            'mrp' => '600.00',
            'status' => 'ACTIVE',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-CONC-001',
            'idempotency_key' => 'idemp-order-conc-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '500.00',
            'tax_total' => '0.00',
            'grand_total' => '500.00',
            'ordered_at' => Carbon::now(),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => 'Hardware Module 500',
            'sku_snapshot' => 'SKU-CONC-01',
            'unit_snapshot' => 'piece',
            'ordered_quantity' => 1,
            'fulfillable_quantity' => 1,
            'picked_quantity' => 1,
            'dispatched_quantity' => 1,
            'delivered_quantity' => 1,
            'returned_quantity' => 0,
            'unit_price' => '500.00',
            'tax_profile_id' => $taxProfile->id,
            'tax_profile_code_snapshot' => 'VAT0',
            'tax_profile_name_snapshot' => 'Zero Tax 0%',
            'tax_rate' => '0.0000',
            'tax_rate_snapshot' => '0.0000',
            'taxable_amount' => '500.00',
            'tax_amount' => '0.00',
            'line_total' => '500.00',
        ]);

        $this->returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-CONC-001',
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
            'estimated_refund_tax' => '0.00',
            'estimated_refund_total' => '500.00',
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
            'unit_price_snapshot' => '500.00',
            'tax_rate_snapshot' => '0.0000',
            'tax_amount_snapshot' => '0.00',
            'line_total' => '500.00',
        ]);

        /** @var CreditNoteService $creditService */
        $creditService = app(CreditNoteService::class);
        $this->creditNote = $creditService->generateCreditNote($this->returnRequest, $this->admin);
    }

    /**
     * Required Concurrency Test Case:
     * Initial credit: $500.00
     * Refund A: $400.00
     * Refund B: $400.00
     * Expected: Total successful <= $500.00 (One succeeds, one fails with ConflictHttpException).
     */
    public function test_double_refund_prevention_on_competing_requests(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        // 1. Create Refund A ($400) and approve it
        $reqA = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '400.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Refund A',
        ]);
        $apprA = $service->approveRefund($reqA, $this->admin);

        // 2. Create Refund B ($400) and approve it (while remaining was initially $500)
        $reqB = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '400.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Refund B',
        ]);
        $apprB = $service->approveRefund($reqB, $this->admin);

        // 3. Process Refund A -> Succeeds
        $txnA = $service->processRefund($apprA, $this->accountant);
        $this->assertEquals('400.00', (string) $txnA->amount);

        $this->creditNote->refresh();
        $this->assertEquals('400.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('100.00', (string) $this->creditNote->remaining_balance);

        // 4. Process Refund B -> Must FAIL because only $100 is remaining
        $failed = false;
        try {
            $service->processRefund($apprB, $this->accountant);
        } catch (ConflictHttpException $e) {
            $failed = true;
            $this->assertStringContainsString('Double-refund prevention violation', $e->getMessage());
        }

        $this->assertTrue($failed, 'Expected second competing refund to be blocked by double-refund prevention.');

        // Verify total disbursed is exactly $400.00 <= $500.00 (Never $800.00)
        $this->creditNote->refresh();
        $this->assertEquals('400.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('100.00', (string) $this->creditNote->remaining_balance);
    }

    /**
     * Multi-Partial Refund Exhaustion Scenario:
     * Credit: $500.00
     * Request 1: $300.00 -> Success ($200.00 remaining)
     * Request 2: $200.00 -> Success ($0.00 remaining, FULLY_REFUNDED)
     * Request 3: $100.00 -> Blocked ($0.00 remaining)
     */
    public function test_multi_partial_refund_exact_conservation(): void
    {
        /** @var RefundWorkflowService $service */
        $service = app(RefundWorkflowService::class);

        // Refund 1: $300
        $req1 = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '300.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Step 1',
        ]);
        $appr1 = $service->approveRefund($req1, $this->admin);
        $service->processRefund($appr1, $this->accountant);

        $this->creditNote->refresh();
        $this->assertEquals('300.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('200.00', (string) $this->creditNote->remaining_balance);
        $this->assertEquals(CreditNoteStatus::PARTIALLY_REFUNDED, $this->creditNote->status);

        // Refund 2: $200
        $req2 = $service->createRefundRequest($this->creditNote, $this->salesman, [
            'requested_amount' => '200.00',
            'payment_method' => PaymentMethod::CHEQUE->value,
            'reason' => 'Step 2',
        ]);
        $appr2 = $service->approveRefund($req2, $this->admin);
        $service->processRefund($appr2, $this->accountant);

        $this->creditNote->refresh();
        $this->assertEquals('500.00', (string) $this->creditNote->allocated_to_refunds);
        $this->assertEquals('0.00', (string) $this->creditNote->remaining_balance);
        $this->assertEquals(CreditNoteStatus::FULLY_REFUNDED, $this->creditNote->status);

        // Refund 3: $100 against exhausted credit -> fails at request creation
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createRefundRequest($this->creditNote->fresh(), $this->salesman, [
            'requested_amount' => '100.00',
            'payment_method' => PaymentMethod::CASH->value,
            'reason' => 'Step 3 against exhausted credit',
        ]);
    }
}
