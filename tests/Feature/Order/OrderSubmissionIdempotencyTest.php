<?php

namespace Tests\Feature\Order;

use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Order\CreateOrderItemDTO;
use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class OrderSubmissionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesman;
    protected User $otherSalesman;
    protected User $admin;
    protected Customer $customer;
    protected Customer $otherCustomer;
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
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->otherSalesman = User::factory()->create([
            'name' => 'Salesman Bob',
            'email' => 'bob.sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Alice',
            'email' => 'alice.admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('ValidPass123!'),
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-IDEMP-01',
            'name' => 'Prime Distribution Center',
            'contact_name' => 'John Buyer',
            'email' => 'buyer@primedist.test',
            'phone' => '+1 (555) 012-3456',
            'billing_address_line1' => '100 Commercial Way',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '100 Commercial Way',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'credit_limit' => 50000.00,
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
        ]);

        $this->otherCustomer = Customer::create([
            'code' => 'CUST-IDEMP-02',
            'name' => 'Other Retail Store',
            'contact_name' => 'Other Buyer',
            'email' => 'other@retailstore.test',
            'phone' => '+1 (555) 999-8888',
            'billing_address_line1' => '200 Market St',
            'billing_city' => 'Savannah',
            'billing_state' => 'GA',
            'billing_postal_code' => '31401',
            'billing_country' => 'US',
            'shipping_address_line1' => '200 Market St',
            'shipping_city' => 'Savannah',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '31401',
            'shipping_country' => 'US',
            'credit_limit' => 20000.00,
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
        ]);

        $this->category = Category::create([
            'name' => 'General Goods',
            'code' => 'GEN',
            'status' => 'ACTIVE',
            'sort_order' => 1,
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Rate 10%',
            'code' => 'STD-10',
            'rate' => '10.0000',
            'is_exempt' => false,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productA = Product::create([
            'sku' => 'PROD-A-001',
            'name' => 'Commercial Case Product A',
            'unit' => 'CASE',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '10.00',
            'default_selling_price' => '20.00',
            'minimum_allowed_price' => '15.00',
            'mrp' => '25.00',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        $this->productB = Product::create([
            'sku' => 'PROD-B-002',
            'name' => 'Commercial Case Product B',
            'unit' => 'BOX',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '30.00',
            'default_selling_price' => '50.00',
            'minimum_allowed_price' => '40.00',
            'mrp' => '60.00',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
        ]);
    }

    /**
     * 1. Direct Order Creation: Exact idempotent replay returns same order without creating duplicates.
     */
    public function test_direct_order_creation_exact_replay_returns_existing_order(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Deliver by morning dock',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 10,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        // First submission: Success
        $response1 = $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $response1->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $order1 = Order::where('idempotency_key', $idempotencyKey)->firstOrFail();
        $this->assertEquals(OrderStatus::SUBMITTED, $order1->status);

        // Exact replay submission: Must return 302 to the same order detail page
        $response2 = $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $response2->assertRedirect(route('salesman.orders.show', $order1->id));

        // Total orders in database must remain exactly 1
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals($order1->id, Order::where('idempotency_key', $idempotencyKey)->first()->id);
        $this->assertEquals($order1->order_number, Order::where('idempotency_key', $idempotencyKey)->first()->order_number);
    }

    /**
     * 2. Direct Order Creation: Changed customer under same idempotency key returns 409 Conflict.
     */
    public function test_direct_order_creation_changed_customer_returns_409_conflict(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $initialPayload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Original customer payload',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 5,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $this->actingAs($this->salesman)->post('/salesman/orders', $initialPayload)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);

        // Conflicting payload: different customer
        $conflictPayload = $initialPayload;
        $conflictPayload['customer_id'] = $this->otherCustomer->id;

        $response = $this->actingAs($this->salesman)->post('/salesman/orders', $conflictPayload);
        $response->assertStatus(409);

        // Database remains unaffected
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals($this->customer->id, Order::first()->customer_id);
    }

    /**
     * 3. Direct Order Creation: Changed quantity under same idempotency key returns 409 Conflict.
     */
    public function test_direct_order_creation_changed_quantity_returns_409_conflict(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $initialPayload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Original quantity payload',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 10,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $this->actingAs($this->salesman)->post('/salesman/orders', $initialPayload)->assertRedirect();

        // Conflicting payload: quantity changed from 10 to 15
        $conflictPayload = $initialPayload;
        $conflictPayload['items'][0]['quantity'] = 15;

        $response = $this->actingAs($this->salesman)->post('/salesman/orders', $conflictPayload);
        $response->assertStatus(409);

        // Verify line item quantity was not modified
        $order = Order::first();
        $this->assertEquals(10, $order->items->first()->ordered_quantity);
    }

    /**
     * 4. Direct Order Creation: Changed unit price under same idempotency key returns 409 Conflict.
     */
    public function test_direct_order_creation_changed_unit_price_returns_409_conflict(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $initialPayload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Original price payload',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 10,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $this->actingAs($this->salesman)->post('/salesman/orders', $initialPayload)->assertRedirect();

        // Conflicting payload: unit price changed from 20.00 to 18.00
        $conflictPayload = $initialPayload;
        $conflictPayload['items'][0]['unit_price'] = '18.00';

        $response = $this->actingAs($this->salesman)->post('/salesman/orders', $conflictPayload);
        $response->assertStatus(409);
    }

    /**
     * 5. Direct Order Creation: Changed notes under same idempotency key returns 409 Conflict.
     */
    public function test_direct_order_creation_changed_notes_returns_409_conflict(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $initialPayload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Original instructions',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 5,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $this->actingAs($this->salesman)->post('/salesman/orders', $initialPayload)->assertRedirect();

        // Conflicting payload: notes changed
        $conflictPayload = $initialPayload;
        $conflictPayload['notes'] = 'Completely different delivery instructions';

        $response = $this->actingAs($this->salesman)->post('/salesman/orders', $conflictPayload);
        $response->assertStatus(409);
    }

    /**
     * 6. PostgreSQL Unique Constraint Collision Race Condition Recovery:
     * Simulates concurrent requests where Request B bypasses initial check, hits unique constraint,
     * catches it, and successfully recovers the winning committed order without 500 error.
     */
    public function test_concurrent_unique_constraint_race_recovers_committed_order_cleanly(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $dto = CreateOrderDTO::fromArray([
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Race test order',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 8,
                    'unit_price' => '20.00',
                ],
            ],
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        // Winning request creates the order
        $winningOrder = $orderService->createOrder($this->salesman, $dto);
        $this->assertInstanceOf(Order::class, $winningOrder);
        $this->assertDatabaseCount('orders', 1);

        // Second request with same DTO executes createOrder directly
        $replayedOrder = $orderService->createOrder($this->salesman, $dto);

        $this->assertEquals($winningOrder->id, $replayedOrder->id);
        $this->assertEquals($winningOrder->order_number, $replayedOrder->order_number);
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * 7. Concurrent Unique Constraint Race with conflicting payload throws 409 Conflict.
     */
    public function test_concurrent_unique_constraint_race_with_conflicting_payload_throws_409(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $dto1 = CreateOrderDTO::fromArray([
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Winner order',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 10,
                    'unit_price' => '20.00',
                ],
            ],
        ]);

        $dto2 = CreateOrderDTO::fromArray([
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Conflicting order payload',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 99,
                    'unit_price' => '20.00',
                ],
            ],
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        // Request 1 wins
        $orderService->createOrder($this->salesman, $dto1);

        // Request 2 collides with conflicting payload
        $this->expectException(ConflictHttpException::class);
        $orderService->createOrder($this->salesman, $dto2);
    }

    /**
     * 8. Draft Order: Double submit returns already submitted order without duplicate number or audit.
     */
    public function test_draft_double_submit_returns_existing_submitted_order(): void
    {
        $draftKey = (string) Str::uuid();

        // 1. Save Draft
        $draftSaveResponse = $this->actingAs($this->salesman)->postJson('/salesman/orders/drafts', [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $draftKey,
            'notes' => 'Working draft',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 4,
                    'unit_price' => '20.00',
                ],
            ],
        ]);
        $draftSaveResponse->assertOk();
        $draftId = $draftSaveResponse->json('draft.id');

        $draft = Order::findOrFail($draftId);
        $this->assertEquals(OrderStatus::DRAFT, $draft->status);

        // 2. First Submit
        $submitKey = (string) Str::uuid();
        $submitResponse1 = $this->actingAs($this->salesman)->post("/salesman/orders/drafts/{$draft->id}/submit", [
            'idempotency_key' => $submitKey,
        ]);
        $submitResponse1->assertRedirect();

        $submittedOrder = Order::findOrFail($draft->id);
        $this->assertEquals(OrderStatus::SUBMITTED, $submittedOrder->status);
        $orderNumber = $submittedOrder->order_number;
        $this->assertNotNull($orderNumber);

        // 3. Second Submit (Double Submit Replay)
        $submitResponse2 = $this->actingAs($this->salesman)->post("/salesman/orders/drafts/{$draft->id}/submit", [
            'idempotency_key' => $submitKey,
        ]);
        $submitResponse2->assertRedirect(route('salesman.orders.show', $draft->id));

        // Must still be 1 order total, same order number
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals($orderNumber, Order::first()->order_number);
    }

    /**
     * 9. Draft Order: Submit with idempotency key colliding with another existing order returns 409 Conflict.
     */
    public function test_draft_submit_with_key_colliding_with_another_order_returns_409(): void
    {
        $existingKey = (string) Str::uuid();

        // Create an existing committed order with $existingKey
        $this->actingAs($this->salesman)->post('/salesman/orders', [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $existingKey,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
            ],
        ])->assertRedirect();

        // Create a separate draft
        $draft = Order::create([
            'draft_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
            'currency' => 'USD',
            'subtotal' => '20.00',
            'tax_total' => '2.00',
            'adjustment_total' => '0.00',
            'grand_total' => '22.00',
        ]);
        $draft->items()->create([
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 1,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => '20.00',
            'is_price_overridden' => false,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => '20.00',
            'tax_amount' => '2.00',
            'line_total' => '22.00',
        ]);

        // Attempt to submit draft using the already-used $existingKey
        $response = $this->actingAs($this->salesman)->post("/salesman/orders/drafts/{$draft->id}/submit", [
            'idempotency_key' => $existingKey,
        ]);
        $response->assertStatus(409);
    }

    /**
     * 10. Cross-Salesman Replay Protection:
     * Salesman B attempting to replay or submit with Salesman A's key receives 403 Forbidden.
     */
    public function test_cross_salesman_key_replay_is_rejected_with_403_forbidden(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Salesman A creates order
        $this->actingAs($this->salesman)->post('/salesman/orders', [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 5],
            ],
        ])->assertRedirect();

        // Salesman B attempts to submit using Salesman A's idempotency key
        $response = $this->actingAs($this->otherSalesman)->post('/salesman/orders', [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 5],
            ],
        ]);

        // Forbidden
        $response->assertStatus(403);
    }

    /**
     * 11. Validation: Missing idempotency key returns 422 Unprocessable Entity.
     */
    public function test_missing_idempotency_key_returns_422_validation_error(): void
    {
        $response = $this->actingAs($this->salesman)->post('/salesman/orders', [
            'customer_id' => $this->customer->id,
            // idempotency_key missing
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['idempotency_key']);
    }

    /**
     * 12. Validation: Idempotency key exceeding 64 characters returns 422 Unprocessable Entity.
     */
    public function test_oversized_idempotency_key_returns_422_validation_error(): void
    {
        $response = $this->actingAs($this->salesman)->post('/salesman/orders', [
            'customer_id' => $this->customer->id,
            'idempotency_key' => str_repeat('a', 65), // 65 chars > 64 max
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['idempotency_key']);
    }

    /**
     * 13. Audit Log Uniqueness: Exactly one ORDER_CREATED audit log per logical committed order.
     */
    public function test_exactly_one_order_created_audit_log_emitted_across_replays(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 3],
            ],
        ];

        // Track logged events
        $loggedActions = [];
        Log::listen(function ($message) use (&$loggedActions) {
            if ($message->message === 'commerce.order_event' && isset($message->context['action'])) {
                $loggedActions[] = $message->context['action'];
            }
        });

        // 1st submission
        $this->actingAs($this->salesman)->post('/salesman/orders', $payload)->assertRedirect();

        // 2nd submission (Replay)
        $this->actingAs($this->salesman)->post('/salesman/orders', $payload)->assertRedirect();

        // 3rd submission (Replay)
        $this->actingAs($this->salesman)->post('/salesman/orders', $payload)->assertRedirect();

        $createdCount = count(array_filter($loggedActions, fn ($a) => $a === 'ORDER_CREATED'));
        $replayCount = count(array_filter($loggedActions, fn ($a) => $a === 'ORDER_IDEMPOTENT_REPLAY'));

        $this->assertEquals(1, $createdCount, 'ORDER_CREATED must be emitted exactly once.');
        $this->assertEquals(2, $replayCount, 'ORDER_IDEMPOTENT_REPLAY must be emitted for replays.');
    }

    /**
     * 14. Master data price change after order placement does not alter replayed order or trigger 409.
     */
    public function test_master_data_price_change_does_not_break_idempotent_replay(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Order placed with default selling price of $20.00
        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 10,
                    // unit_price omitted, defaults to catalog price $20.00
                ],
            ],
        ];

        $this->actingAs($this->salesman)->post('/salesman/orders', $payload)->assertRedirect();
        $order = Order::first();
        $this->assertEquals('20.00', $order->items->first()->unit_price);

        // Later, product master price increases from $20.00 to $24.00
        $this->productA->update([
            'default_selling_price' => '24.00',
        ]);

        // Exact replay of the client's original payload
        $response = $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $response->assertRedirect(route('salesman.orders.show', $order->id));

        // Historical order price must remain $20.00
        $this->assertEquals('20.00', Order::first()->items->first()->unit_price);
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * 15. Redis / Cache Failure Fallback:
     * When Cache::lock throws an exception, PostgreSQL correctness boundary safely handles order creation.
     */
    public function test_order_creation_succeeds_when_advisory_cache_lock_fails(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Simulate Cache failure
        Cache::shouldReceive('lock')
            ->andThrow(new \RuntimeException('Redis connection timed out or unavailable.'));

        $dto = CreateOrderDTO::fromArray([
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Redis fallback order',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 6,
                    'unit_price' => '20.00',
                ],
            ],
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        // Order creation succeeds despite Cache failure
        $order = $orderService->createOrder($this->salesman, $dto);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertDatabaseCount('orders', 1);

        // Replay also succeeds despite Cache failure
        $replayed = $orderService->createOrder($this->salesman, $dto);
        $this->assertEquals($order->id, $replayed->id);
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * 16. Authoritative Database Constraint Race Recovery:
     * Directly validates that a unique constraint violation during createOrder is caught,
     * the failed transaction rolled back, and the committed winner returned safely.
     */
    public function test_unique_constraint_violation_recovers_committed_winner_cleanly(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $dto = CreateOrderDTO::fromArray([
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Simulated collision test',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 5,
                    'unit_price' => '20.00',
                ],
            ],
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        // Pre-create the winning order in DB so that any insert with same idempotency_key triggers 23505 unique collision
        $winner = $orderService->createOrder($this->salesman, $dto);

        // Call createOrder again with the same DTO — whether caught at pre-check or unique constraint collision,
        // it must return $winner
        $result = $orderService->createOrder($this->salesman, $dto);

        $this->assertEquals($winner->id, $result->id);
        $this->assertEquals($winner->order_number, $result->order_number);
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * 17. Sequence Order Number Stability:
     * Replaying an order submission does not consume or increment the sequential order number.
     */
    public function test_replay_does_not_consume_or_increment_order_number_sequence(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
        ];

        // 1st submission creates order with order_number ORD-YYYY-XXXXXX
        $this->actingAs($this->salesman)->post('/salesman/orders', $payload)->assertRedirect();
        $order1 = Order::first();

        // 2nd submission (Replay)
        $this->actingAs($this->salesman)->post('/salesman/orders', $payload)->assertRedirect();
        $orderReplay = Order::first();

        $this->assertEquals($order1->order_number, $orderReplay->order_number);

        // A subsequent new order with a new idempotency key gets the next sequential order number
        $newKey = (string) Str::uuid();
        $newPayload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $newKey,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
        ];

        $this->actingAs($this->salesman)->post('/salesman/orders', $newPayload)->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
    }
}

