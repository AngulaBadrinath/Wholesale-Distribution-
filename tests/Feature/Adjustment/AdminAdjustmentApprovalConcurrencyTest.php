<?php

namespace Tests\Feature\Adjustment;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentReasonCode;
use App\Enums\AdjustmentStatus;
use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderAdjustmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderAdjustmentItem;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Adjustment\OrderAdjustmentService;
use App\Services\Adjustment\OrderAdjustmentWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class AdminAdjustmentApprovalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminA;
    protected User $adminB;
    protected User $salesman;

    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;

    protected OrderAdjustmentWorkflowService $workflowService;
    protected OrderAdjustmentService $requestService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflowService = app(OrderAdjustmentWorkflowService::class);
        $this->requestService = app(OrderAdjustmentService::class);

        $this->adminA = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'alice@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->adminB = User::factory()->create([
            'name' => 'Bob Admin',
            'email' => 'bob@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Acme Corporation',
            'code' => 'CUST-ACME-01',
            'contact_name' => 'John Doe',
            'phone' => '+1-555-0199',
            'email' => 'acme@wholesale.test',
            'billing_address_line1' => '100 Main St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Main St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->category = Category::create([
            'name' => 'Dry Goods',
            'code' => 'CAT-DRY',
            'is_active' => true,
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Tax',
            'code' => 'TAX-STD',
            'rate' => 18.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Premium Olive Oil 1L',
            'sku' => 'SKU-OIL-1L',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '25.00',
            'mrp' => '30.00',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    protected function createScenario(
        int $orderedQty = 10,
        int $allocatedQty = 0,
        int $pickedQty = 0,
        int $reductionQty = 2
    ): array {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => AdjustmentStatus::REQUESTED,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-ord-' . uniqid(),
            'subtotal' => bcmul('25.00', (string) $orderedQty, 2),
            'tax_total' => bcmul('4.50', (string) $orderedQty, 2),
            'adjustment_total' => '0.00',
            'grand_total' => bcmul('29.50', (string) $orderedQty, 2),
            'version' => 1,
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => $orderedQty,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => $pickedQty,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => '25.00',
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => bcmul('25.00', (string) $orderedQty, 2),
            'tax_amount' => bcmul('4.50', (string) $orderedQty, 2),
            'line_total' => bcmul('29.50', (string) $orderedQty, 2),
        ]);

        $allocation = null;
        if ($allocatedQty > 0) {
            $allocation = OrderItemAllocation::create([
                'allocation_number' => 'ALC-' . uniqid(),
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'allocated_quantity' => $allocatedQty,
                'reserved_quantity' => $allocatedQty,
                'picked_quantity' => $pickedQty,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => $pickedQty > 0 ? AllocationStatus::PICKED : AllocationStatus::ALLOCATED,
                'allocated_by' => $this->adminA->id,
                'allocated_at' => Carbon::now()->subHour(),
            ]);
        }

        $unallocated = max(0, $orderedQty - $allocatedQty);
        $affectedAlloc = max(0, $reductionQty - $unallocated);

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-CONC-' . uniqid(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => bcmul('25.00', (string) $orderedQty, 2),
            'order_tax_total_snapshot' => bcmul('4.50', (string) $orderedQty, 2),
            'order_grand_total_snapshot' => bcmul('29.50', (string) $orderedQty, 2),
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::SUBMITTED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Concurrency test notes.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'projected_subtotal_reduction' => bcmul('25.00', (string) $reductionQty, 2),
            'projected_tax_reduction' => bcmul('4.50', (string) $reductionQty, 2),
            'projected_grand_total_reduction' => bcmul('29.50', (string) $reductionQty, 2),
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-' . uniqid()),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => $orderedQty,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => $orderedQty,
            'allocated_quantity_snapshot' => $allocatedQty,
            'unallocated_quantity_snapshot' => $unallocated,
            'requested_quantity_reduction' => $reductionQty,
            'projected_fulfillable_quantity' => $orderedQty - $reductionQty,
            'projected_cancelled_quantity' => $reductionQty,
            'affected_allocation_quantity' => $affectedAlloc,
            'projected_taxable_amount_reduction' => bcmul('25.00', (string) $reductionQty, 2),
            'projected_tax_amount_reduction' => bcmul('4.50', (string) $reductionQty, 2),
            'projected_line_total_reduction' => bcmul('29.50', (string) $reductionQty, 2),
        ]);

        return [$order, $adj, $item, $allocation];
    }

    /**
     * A. Concurrent Approval vs Approval:
     *    Admin A and Admin B both attempt to approve. Exactly one succeeds, second gets 409 Conflict.
     */
    public function test_concurrent_approval_vs_approval_serializes_and_second_gets_409(): void
    {
        [$order, $adj] = $this->createScenario();

        $successCount = 0;
        $conflictCount = 0;

        $actors = [$this->adminA, $this->adminB];

        foreach ($actors as $actor) {
            try {
                $this->workflowService->approveAdjustment($actor, $order, $adj);
                $successCount++;
            } catch (ConflictHttpException $e) {
                $conflictCount++;
            }
        }

        $this->assertEquals(1, $successCount, 'Exactly one approval must succeed.');
        $this->assertEquals(1, $conflictCount, 'The competing approval must be rejected with 409 Conflict.');
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);
    }

    /**
     * B. Concurrent Approval vs Rejection:
     *    Admin A approves while Admin B rejects. Exactly one decision commits.
     */
    public function test_concurrent_approval_vs_rejection_serializes_and_loser_gets_409(): void
    {
        [$order, $adj] = $this->createScenario();

        $successCount = 0;
        $conflictCount = 0;

        // First action: Admin A approves
        try {
            $this->workflowService->approveAdjustment($this->adminA, $order, $adj);
            $successCount++;
        } catch (ConflictHttpException $e) {
            $conflictCount++;
        }

        // Second action: Admin B tries to reject the now-approved adjustment
        try {
            $this->workflowService->rejectAdjustment($this->adminB, $order, $adj, 'Rejecting after approval attempt.');
            $successCount++;
        } catch (ConflictHttpException $e) {
            $conflictCount++;
        }

        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, $conflictCount);
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);
    }

    /**
     * C. Concurrent Approval vs Requester Withdrawal:
     *    Requester withdraws request while Admin attempts approval.
     */
    public function test_concurrent_approval_vs_requester_withdrawal_serializes_and_loser_gets_409(): void
    {
        [$order, $adj] = $this->createScenario();

        // Requester withdraws the request
        $this->requestService->withdrawAdjustmentRequest($this->salesman, $adj, 'Requester withdrawal.');
        $this->assertEquals(OrderAdjustmentStatus::CANCELLED, $adj->fresh()->status);

        // Competing approval MUST fail with 409 Conflict
        $this->expectException(ConflictHttpException::class);
        $this->workflowService->approveAdjustment($this->adminA, $order, $adj);
    }

    /**
     * D. Concurrent Rejection vs Requester Withdrawal:
     *    Admin rejects, then requester attempts to withdraw.
     */
    public function test_concurrent_rejection_vs_requester_withdrawal_serializes(): void
    {
        [$order, $adj] = $this->createScenario();

        // Admin rejects first
        $this->workflowService->rejectAdjustment($this->adminA, $order, $adj, 'Admin rejection before withdrawal.');
        $this->assertEquals(OrderAdjustmentStatus::REJECTED, $adj->fresh()->status);

        // Requester attempting to withdraw a rejected adjustment MUST fail with 409 Conflict
        $this->expectException(ConflictHttpException::class);
        $this->requestService->withdrawAdjustmentRequest($this->salesman, $adj, 'Attempting to withdraw already rejected.');
    }

    /**
     * E. Approval vs Allocation Progression:
     *    Warehouse picks units such that requested reduction now encroaches on picked stock.
     */
    public function test_approval_aborts_if_warehouse_picks_encroaching_stock_before_lock(): void
    {
        // 10 ordered, 8 allocated, 5 reduction (Case B: 3 affected).
        // Initial picked is 0 (unpicked = 8, 3 <= 8, so valid Case B).
        [$order, $adj, $item, $allocation] = $this->createScenario(10, 8, 0, 5);

        // Warehouse progresses: picks 7 units (unpicked is now 1, but affected is 3 => encroachment!)
        $allocation->picked_quantity = 7;
        $allocation->status = AllocationStatus::PICKED;
        $allocation->save();

        $item->picked_quantity = 7;
        $item->save();

        // Approval attempt with Case B acknowledgment MUST still fail with 409 due to picking encroachment under lock
        $this->expectException(ConflictHttpException::class);
        $this->workflowService->approveAdjustment(
            $this->adminA,
            $order,
            $adj,
            ['acknowledge_allocation_impact' => true]
        );
    }

    /**
     * F. Approval vs Order Version Mutation:
     *    Order version changed between snapshot and approval lock acquisition.
     */
    public function test_approval_aborts_if_order_version_increments_before_lock(): void
    {
        [$order, $adj] = $this->createScenario();

        // Order updated in background, version increments to 2
        $order->version = 2;
        $order->save();

        $this->expectException(ConflictHttpException::class);
        $this->workflowService->approveAdjustment($this->adminA, $order, $adj);
    }

    /**
     * G. Duplicate HTTP Submit / Double-Click Retries:
     *    First request commits, second gets HTTP 409 Conflict.
     */
    public function test_duplicate_browser_submit_or_network_retry_returns_409(): void
    {
        [$order, $adj] = $this->createScenario();

        // First click
        $first = $this->actingAs($this->adminA)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $first->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);

        // Immediate double-click / network retry
        $second = $this->actingAs($this->adminA)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $second->assertStatus(409);
    }
}
