<?php

declare(strict_types=1);

namespace Tests\Feature\Credit;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Credit\CreditEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Product $productA;
    protected Product $productB;
    protected Order $order;
    protected OrderItem $orderItemA;
    protected OrderItem $orderItemB;
    protected CreditEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eligibilityService = app(CreditEligibilityService::class);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-ELIG-001',
            'name' => 'Acme Wholesale Buyer',
            'contact_name' => 'John Doe',
            'phone' => '+1 555-0100',
            'email' => 'buyer@acmewholesale.com',
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
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
            'credit_limit' => '50000.00',
        ]);

        $category = Category::create([
            'name' => 'General Goods',
            'code' => 'GEN-01',
        ]);

        $taxProfile10 = TaxProfile::create([
            'name' => 'Standard VAT 10%',
            'code' => 'VAT10',
            'rate' => '0.1000',
            'is_exempt' => false,
            'is_active' => true,
        ]);

        $taxProfile5 = TaxProfile::create([
            'name' => 'Reduced Rate 5%',
            'code' => 'RED05',
            'rate' => '0.0500',
            'is_exempt' => false,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'sku' => 'PROD-ELIG-A',
            'name' => 'Premium Widget A',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile10->id,
            'cost_price' => '20.00',
            'default_selling_price' => '30.00',
            'minimum_allowed_price' => '25.00',
            'mrp' => '40.00',
            'status' => 'ACTIVE',
        ]);

        $this->productB = Product::create([
            'sku' => 'PROD-ELIG-B',
            'name' => 'Economy Widget B',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile5->id,
            'cost_price' => '10.00',
            'default_selling_price' => '15.00',
            'minimum_allowed_price' => '12.00',
            'mrp' => '20.00',
            'status' => 'ACTIVE',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026-ELIG-001',
            'idempotency_key' => 'idemp-elig-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'delivery_status' => DeliveryStatus::DELIVERED,
            'adjustment_status' => AdjustmentStatus::NONE,
            'subtotal' => '450.00',
            'tax_total' => '37.50',
            'adjustment_total' => '0.00',
            'grand_total' => '487.50',
            'submitted_at' => Carbon::now()->subDays(5),
            'approved_at' => Carbon::now()->subDays(4),
            'completed_at' => Carbon::now()->subDays(2),
        ]);

        $this->orderItemA = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => 'piece',
            'unit_price' => '30.00',
            'ordered_quantity' => 10,
            'fulfillable_quantity' => 10,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'tax_profile_id' => $taxProfile10->id,
            'tax_profile_code_snapshot' => 'VAT10',
            'tax_profile_name_snapshot' => 'Standard VAT 10%',
            'tax_rate' => '0.1000',
            'taxable_amount' => '300.00',
            'tax_amount' => '30.00',
            'line_total' => '330.00',
        ]);

        $this->orderItemB = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => 'piece',
            'unit_price' => '15.00',
            'ordered_quantity' => 10,
            'fulfillable_quantity' => 10,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'tax_profile_id' => $taxProfile5->id,
            'tax_profile_code_snapshot' => 'RED05',
            'tax_profile_name_snapshot' => 'Reduced Rate 5%',
            'tax_rate' => '0.0500',
            'taxable_amount' => '150.00',
            'tax_amount' => '7.50',
            'line_total' => '157.50',
        ]);
    }

    public function test_approved_return_calculates_exact_credit_eligibility(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-000001',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => 1,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now()->subDay(),
            'approved_at' => Carbon::now(),
            'is_credit_processed' => false,
        ]);

        // Item A: 4 requested, 4 received -> 3 accepted good, 1 accepted damaged, 0 rejected (4 eligible)
        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItemA->id,
            'product_id' => $this->productA->id,
            'requested_quantity' => 4,
            'received_quantity' => 4,
            'accepted_good_quantity' => 3,
            'accepted_damaged_quantity' => 1,
            'rejected_quantity' => 0,
            'unit_price_snapshot' => '30.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_profile_code_snapshot' => 'VAT10',
            'tax_profile_name_snapshot' => 'Standard VAT 10%',
            'reason_code' => ReturnReasonCode::DAMAGED_IN_TRANSIT,
        ]);

        // Item B: 2 requested, 2 received -> 2 accepted good, 0 accepted damaged, 0 rejected (2 eligible)
        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItemB->id,
            'product_id' => $this->productB->id,
            'requested_quantity' => 2,
            'received_quantity' => 2,
            'accepted_good_quantity' => 2,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'unit_price_snapshot' => '15.00',
            'tax_rate_snapshot' => '0.0500',
            'tax_profile_code_snapshot' => 'RED05',
            'tax_profile_name_snapshot' => 'Reduced Rate 5%',
            'reason_code' => ReturnReasonCode::WRONG_ITEM,
        ]);

        $eligibility = $this->eligibilityService->calculateReturnEligibility($returnRequest);

        // Expected Item A: 4 units * $30.00 = $120.00 subtotal, 10% tax = $12.00, total = $132.00
        // Expected Item B: 2 units * $15.00 = $30.00 subtotal, 5% tax = $1.50, total = $31.50
        // Total Subtotal = $150.00, Total Tax = $13.50, Grand Total = $163.50
        $this->assertEquals('150.00', $eligibility['eligible_subtotal']);
        $this->assertEquals('13.50', $eligibility['eligible_tax']);
        $this->assertEquals('163.50', $eligibility['eligible_total']);
        $this->assertCount(2, $eligibility['items']);
        $this->assertEquals(4, $eligibility['items'][0]['eligible_quantity']);
        $this->assertEquals('120.00', $eligibility['items'][0]['line_subtotal']);
        $this->assertEquals('12.00', $eligibility['items'][0]['tax_amount_snapshot']);
        $this->assertEquals('132.00', $eligibility['items'][0]['line_total']);
    }

    public function test_rejected_return_items_are_excluded_from_credit_eligibility(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-000002',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => 1,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now()->subDay(),
            'approved_at' => Carbon::now(),
            'is_credit_processed' => false,
        ]);

        // 5 requested, 5 received -> 2 accepted good, 0 accepted damaged, 3 REJECTED (only 2 eligible)
        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItemA->id,
            'product_id' => $this->productA->id,
            'requested_quantity' => 5,
            'received_quantity' => 5,
            'accepted_good_quantity' => 2,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 3,
            'unit_price_snapshot' => '30.00',
            'tax_rate_snapshot' => '0.1000',
            'tax_profile_code_snapshot' => 'VAT10',
            'reason_code' => ReturnReasonCode::DAMAGED_IN_TRANSIT,
        ]);

        $eligibility = $this->eligibilityService->calculateReturnEligibility($returnRequest);

        // 2 units * $30.00 = $60.00 subtotal, 10% tax = $6.00, total = $66.00
        $this->assertEquals('60.00', $eligibility['eligible_subtotal']);
        $this->assertEquals('6.00', $eligibility['eligible_tax']);
        $this->assertEquals('66.00', $eligibility['eligible_total']);
        $this->assertEquals(2, $eligibility['items'][0]['eligible_quantity']);
        $this->assertEquals(3, $eligibility['items'][0]['rejected_quantity']);
    }

    public function test_non_approved_returns_cannot_generate_credit_eligibility(): void
    {
        $statuses = [
            ReturnStatus::REQUESTED,
            ReturnStatus::INSPECTED,
            ReturnStatus::REJECTED,
            ReturnStatus::CANCELLED,
        ];

        foreach ($statuses as $status) {
            $returnRequest = ReturnRequest::create([
                'return_number' => 'RET-STATUS-' . $status->value,
                'order_id' => $this->order->id,
                'customer_id' => $this->customer->id,
                'salesman_id' => $this->salesman->id,
                'warehouse_id' => 1,
                'status' => $status,
                'created_by' => $this->salesman->id,
                'requested_at' => Carbon::now(),
                'is_credit_processed' => false,
            ]);

            $this->expectException(ValidationException::class);
            $this->eligibilityService->calculateReturnEligibility($returnRequest);
        }
    }

    public function test_already_credit_processed_return_is_rejected(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-ALREADY-CREDITED',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => 1,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now()->subDay(),
            'approved_at' => Carbon::now(),
            'is_credit_processed' => true,
            'credit_note_id' => 999,
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItemA->id,
            'product_id' => $this->productA->id,
            'requested_quantity' => 2,
            'received_quantity' => 2,
            'accepted_good_quantity' => 2,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'unit_price_snapshot' => '30.00',
            'tax_rate_snapshot' => '0.1000',
            'reason_code' => ReturnReasonCode::WRONG_ITEM,
        ]);

        $this->expectException(ValidationException::class);
        $this->eligibilityService->calculateReturnEligibility($returnRequest);
    }

    public function test_eligibility_preserves_historical_prices_after_product_master_change(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-2026-HIST-PRICE',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => 1,
            'status' => ReturnStatus::APPROVED,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'requested_at' => Carbon::now()->subDay(),
            'approved_at' => Carbon::now(),
            'is_credit_processed' => false,
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItemA->id,
            'product_id' => $this->productA->id,
            'requested_quantity' => 2,
            'received_quantity' => 2,
            'accepted_good_quantity' => 2,
            'accepted_damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'unit_price_snapshot' => '30.00', // Historical order unit price
            'tax_rate_snapshot' => '0.1000',
            'reason_code' => ReturnReasonCode::WRONG_ITEM,
        ]);

        // Modify Product Master price and MRP
        $this->productA->update([
            'default_selling_price' => '99.00',
            'mrp' => '150.00',
        ]);

        $eligibility = $this->eligibilityService->calculateReturnEligibility($returnRequest);

        // Subtotal must remain 2 * $30.00 = $60.00, not $198.00
        $this->assertEquals('60.00', $eligibility['eligible_subtotal']);
        $this->assertEquals('6.00', $eligibility['eligible_tax']);
        $this->assertEquals('66.00', $eligibility['eligible_total']);
    }
}
