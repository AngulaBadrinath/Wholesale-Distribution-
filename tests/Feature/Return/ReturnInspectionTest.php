<?php

namespace Tests\Feature\Return;

use App\Enums\AllocationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\InventoryBalance;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ReturnInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $warehouseManager;
    protected User $salesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;
    protected Order $deliveredOrder;
    protected OrderItem $itemA;
    protected OrderItem $itemB;
    protected ReturnRequest $returnRequest;
    protected ReturnInspectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->create([
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
            'salesman_id' => $this->salesman->id,
            'status' => 'ACTIVE',
        ]);

        $this->taxProfile = TaxProfile::create([
            'code' => 'STD_GST',
            'name' => 'Standard GST 10%',
            'rate' => 0.1000,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'sku' => 'SKU-INSP-A',
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
            'sku' => 'SKU-INSP-B',
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
            'order_number' => 'ORD-2026-900003',
            'idempotency_key' => 'IDEMP-ORD-2026-900003',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 250.00,
            'tax_amount' => 25.00,
            'total_amount' => 275.00,
            'created_by' => $this->salesman->id,
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

        // Create initial ReturnRequest (5 units of Item A, 3 units of Item B)
        $reqService = app(ReturnRequestService::class);
        $this->returnRequest = $reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 5,
                    'reason_code' => ReturnReasonCode::DEFECTIVE->value,
                ],
                [
                    'order_item_id' => $this->itemB->id,
                    'requested_quantity' => 3,
                    'reason_code' => ReturnReasonCode::DAMAGED_IN_TRANSIT->value,
                ],
            ],
        ], $this->admin);

        $this->service = app(ReturnInspectionService::class);
    }

    public function test_warehouse_manager_can_record_inspection_with_valid_counts(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        $photoContent = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00" . str_repeat('A', 500);
        $photo = UploadedFile::fake()->createWithContent('damage.jpg', $photoContent);

        $inspected = $this->service->recordInspection($this->returnRequest, [
            'inspection_notes' => 'Received 4 of 5 for Item A, all 3 for Item B.',
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'received_quantity' => 4, // 1 unit missing in transit
                    'item_notes' => '1 bottle missing',
                ],
                [
                    'item_id' => $itemB_req->id,
                    'received_quantity' => 3,
                    'item_notes' => 'Cartons crushed',
                ],
            ],
        ], $this->warehouseManager, [$photo]);

        $this->assertEquals(ReturnStatus::INSPECTED, $inspected->status);
        $this->assertEquals($this->warehouseManager->id, $inspected->inspected_by);
        $this->assertNotNull($inspected->inspected_at);
        $this->assertCount(1, $inspected->evidence_photos);

        $this->assertEquals(4, $itemA_req->fresh()->received_quantity);
        $this->assertEquals(3, $itemB_req->fresh()->received_quantity);

        // Invariant check: physical stock is NOT mutated yet
        $balanceA = InventoryBalance::where('product_id', $this->productA->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(0, (int) ($balanceA?->on_hand_quantity ?? 0));

        // Invariant check: order item returned_quantity is NOT mutated yet
        $this->assertEquals(0, $this->itemA->fresh()->returned_quantity);
        $this->assertEquals(0, $this->itemB->fresh()->returned_quantity);
    }

    public function test_rejects_inspection_when_received_quantity_exceeds_requested(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);

        $this->expectException(ValidationException::class);
        $this->service->recordInspection($this->returnRequest, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'received_quantity' => 8, // Requested was only 5
                ],
            ],
        ], $this->warehouseManager);
    }

    public function test_rejects_inspection_when_received_quantity_is_negative(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);

        $this->expectException(ValidationException::class);
        $this->service->recordInspection($this->returnRequest, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'received_quantity' => -1,
                ],
            ],
        ], $this->warehouseManager);
    }

    public function test_rejects_fake_non_image_evidence(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $fakeFile = UploadedFile::fake()->createWithContent('malicious.jpg', '<?php echo "evil"; ?>');

        $this->expectException(ValidationException::class);
        $this->service->recordInspection($this->returnRequest, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'received_quantity' => 5,
                ],
            ],
        ], $this->warehouseManager, [$fakeFile]);
    }

    public function test_http_endpoint_records_inspection_successfully(): void
    {
        $itemA_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemA->id);
        $itemB_req = $this->returnRequest->items->firstWhere('order_item_id', $this->itemB->id);

        $response = $this->actingAs($this->warehouseManager)->post(route('admin.returns.inspect', $this->returnRequest->id), [
            'inspection_notes' => 'All items physically checked.',
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'received_quantity' => 5,
                ],
                [
                    'item_id' => $itemB_req->id,
                    'received_quantity' => 3,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('return_requests', [
            'id' => $this->returnRequest->id,
            'status' => ReturnStatus::INSPECTED->value,
            'inspected_by' => $this->warehouseManager->id,
        ]);
    }
}
