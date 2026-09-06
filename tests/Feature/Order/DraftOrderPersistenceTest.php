<?php

namespace Tests\Feature\Order;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DraftOrderPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesman;
    protected User $otherSalesman;
    protected User $admin;
    protected Customer $customer;
    protected Customer $unassignedCustomer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesman = User::factory()->create([
            'name' => 'Salesman Sam',
            'email' => 'sam.sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->otherSalesman = User::factory()->create([
            'name' => 'Salesman Bob',
            'email' => 'bob.sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Downtown Supermarket',
            'code' => 'CUST-001',
            'contact_name' => 'John Doe',
            'phone' => '+1-555-0100',
            'billing_address_line1' => '100 Main St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '100 Main St',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->unassignedCustomer = Customer::create([
            'salesman_id' => $this->otherSalesman->id,
            'name' => 'Uptown Groceries',
            'code' => 'CUST-002',
            'contact_name' => 'Jane Smith',
            'phone' => '+1-555-0200',
            'billing_address_line1' => '200 High St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '200 High St',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'CAT-BEV',
            'status' => 'ACTIVE',
            'sort_order' => 1,
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard VAT 10%',
            'code' => 'VAT-10',
            'rate' => '10.0000',
            'is_exempt' => false,
            'status' => TaxProfileStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->productA = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Organic Orange Juice',
            'sku' => 'BEV-001',
            'unit' => 'BOTTLE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->productB = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Premium Sparkling Water',
            'sku' => 'BEV-002',
            'unit' => 'CASE',
            'cost_price' => '20.00',
            'minimum_allowed_price' => '30.00',
            'default_selling_price' => '40.00',
            'mrp' => '50.00',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    /** 1. Create draft succeeds with status DRAFT, no order_number, draft_token, and version 1 */
    public function test_salesman_can_save_new_order_as_draft(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'notes' => 'Draft order notes',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 5,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->postJson('/salesman/orders/drafts', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'draft' => [
                    'id',
                    'draft_token',
                    'version',
                    'customer_id',
                    'subtotal',
                    'tax_total',
                    'grand_total',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'status' => 'DRAFT',
            'order_number' => null,
            'version' => 1,
            'subtotal' => '100.00',
            'tax_total' => '10.00',
            'grand_total' => '110.00',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->productA->id,
            'ordered_quantity' => 5,
            'unit_price' => '20.00',
            'taxable_amount' => '100.00',
            'tax_amount' => '10.00',
            'line_total' => '110.00',
        ]);
    }

    /** 2. Update existing draft modifies items and increments version */
    public function test_salesman_can_update_existing_draft_items_and_quantities(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
            'currency' => 'USD',
            'subtotal' => '100.00',
            'tax_total' => '10.00',
            'grand_total' => '110.00',
        ]);

        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 5,
            'unit_price' => '20.00',
            'tax_rate_snapshot' => '10.0000',
            'taxable_amount' => '100.00',
            'tax_amount' => '10.00',
            'line_total' => '110.00',
        ]);

        $updatePayload = [
            'customer_id' => $this->customer->id,
            'expected_version' => 1,
            'notes' => 'Updated draft notes',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 10,
                    'unit_price' => '18.00',
                ],
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 2,
                    'unit_price' => '35.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->putJson("/salesman/orders/drafts/{$draft->id}", $updatePayload);

        $response->assertOk()
            ->assertJsonPath('draft.version', 2)
            ->assertJsonPath('draft.subtotal', '250.00')
            ->assertJsonPath('draft.tax_total', '25.00')
            ->assertJsonPath('draft.grand_total', '275.00');

        $draft->refresh();
        $this->assertSame(2, $draft->version);
        $this->assertSame('250.00', (string) $draft->subtotal);
        $this->assertSame('275.00', (string) $draft->grand_total);
        $this->assertCount(2, $draft->items);
    }

    /** 3. Stale version returns 409 Conflict */
    public function test_stale_draft_version_returns_409_conflict(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 3, // Current DB version is 3
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'expected_version' => 2, // Client has stale version 2
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->putJson("/salesman/orders/drafts/{$draft->id}", $payload);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    /** 4. Salesman cannot access, update, discard, or submit another salesman's draft */
    public function test_salesman_cannot_access_or_mutate_another_salesman_draft(): void
    {
        $otherDraft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->unassignedCustomer->id,
            'salesman_id' => $this->otherSalesman->id,
            'created_by' => $this->otherSalesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        // 1. Attempt Edit
        $editResponse = $this->actingAs($this->salesman)
            ->get("/salesman/orders/drafts/{$otherDraft->id}/edit");
        $this->assertTrue(in_array($editResponse->status(), [403, 404], true));

        // 2. Attempt Update
        $this->actingAs($this->salesman)
            ->putJson("/salesman/orders/drafts/{$otherDraft->id}", [
                'customer_id' => $this->customer->id,
                'items' => [],
            ])
            ->assertForbidden();

        // 3. Attempt Discard
        $this->actingAs($this->salesman)
            ->delete("/salesman/orders/drafts/{$otherDraft->id}")
            ->assertForbidden();

        // 4. Attempt Submit
        $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$otherDraft->id}/submit")
            ->assertForbidden();
    }

    /** 5. Draft list scopes strictly to salesman and supports search */
    public function test_draft_list_scopes_strictly_to_salesman_with_search(): void
    {
        // Create 2 drafts for current salesman
        $myDraft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        // Create 1 draft for other salesman
        Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->unassignedCustomer->id,
            'salesman_id' => $this->otherSalesman->id,
            'created_by' => $this->otherSalesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        // Create 1 submitted order for current salesman (must not appear in drafts)
        Order::create([
            'order_number' => 'ORD-2026-000099',
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
        ]);

        $response = $this->actingAs($this->salesman)
            ->get('/salesman/orders/drafts');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Salesman/Orders/Drafts')
                ->has('drafts.data', 1)
                ->where('drafts.data.0.id', $myDraft->id)
            );
    }

    /** 6. Salesman can resume draft in builder with full item and customer details */
    public function test_salesman_can_resume_draft_in_order_builder(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
            'notes' => 'Special delivery instructions',
        ]);

        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 3,
            'unit_price' => '20.00',
            'taxable_amount' => '60.00',
            'tax_amount' => '6.00',
            'line_total' => '66.00',
        ]);

        $response = $this->actingAs($this->salesman)
            ->get("/salesman/orders/drafts/{$draft->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Salesman/Orders/Create')
                ->where('initialDraft.id', $draft->id)
                ->where('initialDraft.customer_id', $this->customer->id)
                ->where('initialDraft.notes', 'Special delivery instructions')
                ->has('initialDraft.items', 1)
            );
    }

    /** 7. Discard draft deletes draft order and items cleanly */
    public function test_salesman_can_discard_own_draft(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 3,
            'unit_price' => '20.00',
        ]);

        $response = $this->actingAs($this->salesman)
            ->delete("/salesman/orders/drafts/{$draft->id}");

        $response->assertRedirect('/salesman/orders/drafts');

        $this->assertDatabaseMissing('orders', ['id' => $draft->id]);
        $this->assertDatabaseMissing('order_items', ['product_id' => $this->productA->id]);
    }

    /** 8. Submitted order cannot be discarded or modified via draft endpoints */
    public function test_submitted_order_cannot_be_mutated_or_discarded_via_draft_endpoints(): void
    {
        $submittedOrder = Order::create([
            'order_number' => 'ORD-2026-000001',
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
        ]);

        // Attempt update via draft PUT
        $this->actingAs($this->salesman)
            ->putJson("/salesman/orders/drafts/{$submittedOrder->id}", [
                'customer_id' => $this->customer->id,
                'items' => [],
            ])
            ->assertStatus(422);

        // Attempt discard via draft DELETE
        $this->actingAs($this->salesman)
            ->delete("/salesman/orders/drafts/{$submittedOrder->id}")
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('orders', ['id' => $submittedOrder->id, 'status' => 'SUBMITTED']);
    }

    /** 9. Submit draft transitions to SUBMITTED, generates order_number, and recalculates authoritative taxes */
    public function test_salesman_can_submit_saved_draft_atomically(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
            'currency' => 'USD',
        ]);

        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => 'Old Name Snapshot',
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 5,
            'unit_price' => '20.00',
            'tax_rate_snapshot' => '10.0000',
            'taxable_amount' => '100.00',
            'tax_amount' => '10.00',
            'line_total' => '110.00',
        ]);

        $response = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draft->id}/submit");

        $draft->refresh();

        $response->assertRedirect("/salesman/orders/{$draft->id}");

        $this->assertSame(OrderStatus::SUBMITTED, $draft->status);
        $this->assertNotNull($draft->order_number);
        $this->assertStringStartsWith('ORD-', $draft->order_number);
        $this->assertNotNull($draft->submitted_at);
        $this->assertSame('100.00', (string) $draft->subtotal);
        $this->assertSame('10.00', (string) $draft->tax_total);
        $this->assertSame('110.00', (string) $draft->grand_total);

        // Snapshot is updated with current product master name
        $item = $draft->items->first();
        $this->assertSame('Organic Orange Juice', $item->product_name_snapshot);
    }

    /** 10. Draft submission fails if customer became inactive or on hold */
    public function test_draft_submission_fails_if_customer_became_inactive(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 5,
            'unit_price' => '20.00',
        ]);

        // Customer becomes INACTIVE while draft was parked
        $this->customer->update(['status' => CustomerStatus::INACTIVE]);

        $response = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draft->id}/submit");

        $response->assertSessionHasErrors();

        $draft->refresh();
        $this->assertSame(OrderStatus::DRAFT, $draft->status);
        $this->assertNull($draft->order_number);
    }

    /** 11. Draft submission fails if product became inactive */
    public function test_draft_submission_fails_if_product_became_inactive(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 5,
            'unit_price' => '20.00',
        ]);

        // Product A deactivated while draft was parked
        $this->productA->update(['status' => ProductStatus::INACTIVE]);

        $response = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draft->id}/submit");

        $response->assertSessionHasErrors();

        $draft->refresh();
        $this->assertSame(OrderStatus::DRAFT, $draft->status);
    }

    /** 12. Draft submission re-validates price boundaries if product minimum price increased */
    public function test_draft_submission_revalidates_price_boundaries_against_current_product_pricing(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        // Saved at price 16.00 when minimum was 15.00
        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 5,
            'unit_price' => '16.00',
        ]);

        // Admin updates minimum allowed price to 18.00 while draft was parked
        $this->productA->update(['minimum_allowed_price' => '18.00']);

        $response = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draft->id}/submit");

        $response->assertSessionHasErrors();

        $draft->refresh();
        $this->assertSame(OrderStatus::DRAFT, $draft->status);
    }

    /** 13. Double submission of same draft is idempotent and returns submitted order */
    public function test_double_submission_of_same_draft_is_idempotent(): void
    {
        $draft = Order::create([
            'order_number' => null,
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
        ]);

        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 2,
            'unit_price' => '20.00',
        ]);

        // First submit
        $response1 = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draft->id}/submit");
        $response1->assertRedirect("/salesman/orders/{$draft->id}");

        $draft->refresh();
        $orderNumber = $draft->order_number;

        // Second submit (idempotent replay)
        $response2 = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draft->id}/submit");
        $response2->assertRedirect("/salesman/orders/{$draft->id}");

        $draft->refresh();
        $this->assertSame($orderNumber, $draft->order_number);
        $this->assertSame(OrderStatus::SUBMITTED, $draft->status);
    }

    /** 14. Unassigned customer cannot be used to save draft */
    public function test_salesman_cannot_save_draft_for_unassigned_customer(): void
    {
        $payload = [
            'customer_id' => $this->unassignedCustomer->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->postJson('/salesman/orders/drafts', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    /** 15. Direct order creation from FEAT-ORD-001 continues working without regressions */
    public function test_regression_direct_order_creation_from_feat_ord_001_continues_working(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Immediate direct order',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 3,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::SUBMITTED, $order->status);
        $this->assertNotNull($order->order_number);
        $this->assertNotNull($order->draft_token);
        $response->assertRedirect("/salesman/orders/{$order->id}");
    }
}
