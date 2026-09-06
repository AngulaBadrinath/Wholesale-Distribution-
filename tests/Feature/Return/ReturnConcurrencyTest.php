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

class ReturnConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminRequester;
    protected User $adminApprover;
    protected User $warehouseManager;
    protected User $salesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected TaxProfile $taxProfile;
    protected Product $product;
    protected Order $deliveredOrder;
    protected OrderItem $orderItem;
    protected ReturnRequestService $reqService;
    protected ReturnInspectionService $inspService;
    protected ReturnWorkflowService $wfService;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => 'ACTIVE',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-CONCUR',
            'name' => 'Concurrency Testing Warehouse',
            'address_line1' => '100 Test Blvd',
            'city' => 'Atlanta',
            'state' => 'GA',
            'postal_code' => '30301',
            'country' => 'US',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-CONCUR',
            'name' => 'Concurrency Retailer',
            'contact_name' => 'Bob Concur',
            'email' => 'bob@concurretail.com',
            'phone' => '+1 555-0999',
            'billing_address_line1' => '999 Market St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '999 Market St',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'tax_id' => 'TAX-CONCUR',
            'payment_terms' => PaymentTerms::NET_30,
            'salesman_id' => $this->salesman->id,
            'status' => 'ACTIVE',
        ]);

        $this->taxProfile = TaxProfile::create([
            'code' => 'STD_GST',
            'name' => 'Standard GST 10%',
            'rate' => 0.1000,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SKU-CONCUR-A',
            'name' => 'Concurrency Test Item 1L',
            'unit' => 'BOTTLE',
            'cost_price' => 5.00,
            'minimum_allowed_price' => 8.00,
            'default_selling_price' => 10.00,
            'mrp' => 12.00,
            'status' => 'ACTIVE',
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        $this->deliveredOrder = Order::create([
            'order_number' => 'ORD-2026-900006',
            'idempotency_key' => 'IDEMP-ORD-2026-900006',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 100.00,
            'tax_amount' => 10.00,
            'total_amount' => 110.00,
            'created_by' => $this->salesman->id,
            'ordered_at' => now()->subDays(2),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->deliveredOrder->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
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

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-006-A',
            'order_id' => $this->deliveredOrder->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
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

        $this->reqService = app(ReturnRequestService::class);
        $this->inspService = app(ReturnInspectionService::class);
        $this->wfService = app(ReturnWorkflowService::class);
    }

    public function test_concurrent_requests_prevent_over_return_of_delivered_quantity(): void
    {
        // First request grabs 6 of 10
        $req1 = $this->reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->orderItem->id,
                    'requested_quantity' => 6,
                ],
            ],
        ], $this->adminRequester);

        $this->assertEquals(ReturnStatus::REQUESTED, $req1->status);

        // Second request for 5 should fail because only 4 returnable capacity remains (10 - 6 = 4)
        $this->expectException(ValidationException::class);
        $this->reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->orderItem->id,
                    'requested_quantity' => 5,
                ],
            ],
        ], $this->adminRequester);
    }

    public function test_duplicate_approval_attempt_throws_conflict(): void
    {
        $return = $this->reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->orderItem->id,
                    'requested_quantity' => 4,
                ],
            ],
        ], $this->adminRequester);

        $itemReq = $return->items->first();
        $this->inspService->recordInspection($return, [
            'items' => [
                [
                    'item_id' => $itemReq->id,
                    'received_quantity' => 4,
                ],
            ],
        ], $this->warehouseManager);

        // First approval
        $this->wfService->approveReturn($return->fresh(), [
            'items' => [
                [
                    'item_id' => $itemReq->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->adminApprover);

        // Second approval attempt on already APPROVED return
        $this->expectException(ConflictHttpException::class);
        $this->wfService->approveReturn($return->fresh(), [
            'items' => [
                [
                    'item_id' => $itemReq->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->adminApprover);
    }
}
