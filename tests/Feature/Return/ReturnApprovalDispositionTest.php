<?php

namespace Tests\Feature\Return;

use App\Enums\AllocationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Return\ReturnInspectionService;
use App\Services\Return\ReturnRequestService;
use App\Services\Return\ReturnWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ReturnApprovalDispositionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminRequester;
    protected User $adminApprover;
    protected User $warehouseManager;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;
    protected Order $deliveredOrder;
    protected OrderItem $itemA;
    protected OrderItem $itemB;
    protected ReturnRequest $returnRequest;
    protected ReturnWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => 'ACTIVE',
        ]);

        $this->adminRequester = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => 'ACTIVE',
        ]);

        $this->adminApprover = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => 'ACTIVE',
        ]);

        $this->warehouseManager = User::factory()->create([
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => 'ACTIVE',
        ]);

        $salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => 'ACTIVE',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-MAIN',
            'name' => 'Main Distribution Warehouse',
            'address_line1' => '100 Logistics Blvd',
            'city' => 'Atlanta',
            'state' => 'GA',
            'postal_code' => '30301',
            'country' => 'US',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Acme Supermarket',
            'contact_name' => 'Alice Smith',
            'email' => 'alice@acmesuper.com',
            'phone' => '+1 555-0100',
            'billing_address_line1' => '123 Market St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '123 Market St',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'tax_id' => 'TAX-12345',
            'payment_terms' => PaymentTerms::NET_30,
            'salesman_id' => $salesman->id,
            'status' => 'ACTIVE',
        ]);

        $this->taxProfile = TaxProfile::create([
            'code' => 'STD_GST',
            'name' => 'Standard GST 10%',
            'rate' => 0.1000,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'sku' => 'SKU-APP-A',
            'name' => 'Organic Orange Juice 1L',
            'unit' => 'BOTTLE',
            'cost_price' => 5.00,
            'minimum_allowed_price' => 8.00,
            'default_selling_price' => 10.00,
            'mrp' => 12.00,
            'status' => 'ACTIVE',
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        $this->productB = Product::create([
            'sku' => 'SKU-APP-B',
            'name' => 'Almond Milk 1L',
            'unit' => 'CARTON',
            'cost_price' => 8.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 15.00,
            'mrp' => 18.00,
            'status' => 'ACTIVE',
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        $this->deliveredOrder = Order::create([
            'order_number' => 'ORD-2026-900004',
            'idempotency_key' => 'IDEMP-ORD-2026-900004',
            'customer_id' => $this->customer->id,
            'salesman_id' => $salesman->id,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 250.00,
            'tax_amount' => 25.00,
            'total_amount' => 275.00,
            'created_by' => $this->adminRequester->id,
            'ordered_at' => now()->subDays(2),
        ]);

        $this->itemA = OrderItem::create([
            'order_id' => $this->deliveredOrder->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'unit_price' => 10.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => 0.1000,
            'taxable_amount' => 100.00,
            'tax_amount' => 10.00,
            'line_total' => 110.00,
        ]);

        $this->itemB = OrderItem::create([
            'order_id' => $this->deliveredOrder->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => $this->productB->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'unit_price' => 15.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => 0.1000,
            'taxable_amount' => 150.00,
            'tax_amount' => 15.00,
            'line_total' => 165.00,
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-004-A',
            'order_id' => $this->deliveredOrder->id,
            'order_item_id' => $this->itemA->id,
            'product_id' => $this->productA->id,
            'allocated_quantity' => 10,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'status' => AllocationStatus::DELIVERED,
            'warehouse_code' => $this->warehouse->code,
            'allocated_by' => $this->adminRequester->id,
            'allocated_at' => now()->subDays(2),
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-004-B',
            'order_id' => $this->deliveredOrder->id,
            'order_item_id' => $this->itemB->id,
            'product_id' => $this->productB->id,
            'allocated_quantity' => 10,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'status' => AllocationStatus::DELIVERED,
            'warehouse_code' => $this->warehouse->code,
            'allocated_by' => $this->adminRequester->id,
            'allocated_at' => now()->subDays(2),
        ]);

        // Create return request by adminRequester
        $reqService = app(ReturnRequestService::class);
        $this->returnRequest = $reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 6,
                    'reason_code' => ReturnReasonCode::DEFECTIVE->value,
                ],
                [
                    'order_item_id' => $this->itemB->id,
                    'requested_quantity' => 4,
                    'reason_code' => ReturnReasonCode::DAMAGED_IN_TRANSIT->value,
                ],
            ],
        ], $this->adminRequester);

        // Record inspection (Received: 5 for Item A, 4 for Item B)
        $inspService = app(ReturnInspectionService::class);
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        $this->returnRequest = $inspService->recordInspection($this->returnRequest, [
            'inspection_notes' => 'Received 5 of Item A, 4 of Item B',
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'received_quantity' => 5,
                ],
                [
                    'item_id' => $itemB_req->id,
                    'received_quantity' => 4,
                ],
            ],
        ], $this->warehouseManager);

        $this->service = app(ReturnWorkflowService::class);
    }

    public function test_maker_checker_prevents_requester_from_approving_own_return(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        $this->expectException(ValidationException::class);

        $this->service->approveReturn($this->returnRequest, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'accepted_good_quantity' => 3,
                    'accepted_damaged_quantity' => 2,
                    'rejected_quantity' => 0,
                ],
                [
                    'item_id' => $itemB_req->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->adminRequester); // Same user as creator
    }

    public function test_super_admin_can_bypass_maker_checker_in_emergency(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        // Even if superAdmin created it, superAdmin can approve
        $approved = $this->service->approveReturn($this->returnRequest, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'accepted_good_quantity' => 3,
                    'accepted_damaged_quantity' => 2,
                    'rejected_quantity' => 0,
                ],
                [
                    'item_id' => $itemB_req->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->superAdmin);

        $this->assertEquals(ReturnStatus::APPROVED, $approved->status);
        $this->assertEquals($this->superAdmin->id, $approved->approved_by);
    }

    public function test_distinct_admin_approver_approves_cleanly_with_disposition_reconciliation(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        // Received for A is 5 (Good: 3, Damaged: 1, Rejected: 1) -> sum = 5
        // Received for B is 4 (Good: 4, Damaged: 0, Rejected: 0) -> sum = 4
        $approved = $this->service->approveReturn($this->returnRequest, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'accepted_good_quantity' => 3,
                    'accepted_damaged_quantity' => 1,
                    'rejected_quantity' => 1,
                ],
                [
                    'item_id' => $itemB_req->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->adminApprover);

        $this->assertEquals(ReturnStatus::APPROVED, $approved->status);
        $this->assertEquals($this->adminApprover->id, $approved->approved_by);

        // Approved units: Item A = 4 (3 good + 1 damaged), Item B = 4 (4 good + 0 damaged)
        // Financial eligibility:
        // Item A subtotal: 4 * 10 = 40.00, Tax: 4.00, Total: 44.00
        // Item B subtotal: 4 * 15 = 60.00, Tax: 6.00, Total: 66.00
        // Aggregate Total: 110.00
        $this->assertEquals('100.00', (string) $approved->estimated_refund_subtotal);
        $this->assertEquals('10.00', (string) $approved->estimated_refund_tax);
        $this->assertEquals('110.00', (string) $approved->estimated_refund_total);
    }

    public function test_rejects_approval_when_disposition_sum_does_not_equal_received_quantity(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        // Item A received was 5, but disposition sum is 3 + 1 + 0 = 4 (undispositioned 1 unit)
        $this->expectException(ValidationException::class);

        $this->service->approveReturn($this->returnRequest, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'accepted_good_quantity' => 3,
                    'accepted_damaged_quantity' => 1,
                    'rejected_quantity' => 0, // Sum = 4 != 5
                ],
                [
                    'item_id' => $itemB_req->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->adminApprover);
    }

    public function test_admin_can_reject_return_with_documented_reason(): void
    {
        $rejected = $this->service->rejectReturn($this->returnRequest, [
            'rejection_reason' => 'Goods past 30-day return policy and damaged by customer negligence.',
        ], $this->adminApprover);

        $this->assertEquals(ReturnStatus::REJECTED, $rejected->status);
        $this->assertStringContainsString('Goods past 30-day return policy', $rejected->rejection_reason);

        // Items rejected quantity updated
        $this->assertEquals(5, $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id)->fresh()->rejected_quantity);
    }

    public function test_requester_can_cancel_return_before_inspection(): void
    {
        // Create new draft return
        $reqService = app(ReturnRequestService::class);
        $draftReturn = $reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 2,
                ],
            ],
        ], $this->adminRequester);

        $cancelled = $this->service->cancelReturn($draftReturn, $this->adminRequester);

        $this->assertEquals(ReturnStatus::CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cannot_cancel_return_once_inspected(): void
    {
        $this->expectException(ConflictHttpException::class);
        $this->service->cancelReturn($this->returnRequest, $this->adminRequester);
    }

    public function test_http_endpoints_for_workflow_actions(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        $response = $this->actingAs($this->adminApprover)->post(route('admin.returns.approve', $this->returnRequest->id), [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'accepted_good_quantity' => 5,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
                [
                    'item_id' => $itemB_req->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('return_requests', [
            'id' => $this->returnRequest->id,
            'status' => ReturnStatus::APPROVED->value,
            'approved_by' => $this->adminApprover->id,
        ]);
    }
}
