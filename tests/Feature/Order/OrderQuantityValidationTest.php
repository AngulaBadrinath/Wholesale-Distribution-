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
use Tests\TestCase;

class OrderQuantityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesman;
    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesman = User::factory()->create([
            'name' => 'Salesman Sam',
            'email' => 'sam.quantity@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Metro Supermarket',
            'code' => 'CUST-QTY-001',
            'contact_name' => 'Alice Manager',
            'phone' => '+1-555-0999',
            'billing_address_line1' => '100 Metro Way',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '100 Metro Way',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV-QTY',
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

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Sparkling Lemon Water',
            'sku' => 'BEV-LEM-01',
            'unit' => 'CASE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    /** 1. Quantity = 1 is accepted at minimum allowed boundary */
    public function test_quantity_of_one_is_accepted_at_minimum_boundary(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order);
        $this->assertSame(1, $order->items->first()->ordered_quantity);
        $this->assertSame('20.00', (string) $order->subtotal);
        $this->assertSame('2.00', (string) $order->tax_total);
        $this->assertSame('22.00', (string) $order->grand_total);
        $response->assertRedirect("/salesman/orders/{$order->id}");
    }

    /** 2. Quantity = 999999 is accepted at maximum allowed boundary */
    public function test_quantity_of_999999_is_accepted_at_maximum_boundary(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 999999,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order);
        $this->assertSame(999999, $order->items->first()->ordered_quantity);
        $this->assertSame('19999980.00', (string) $order->subtotal);
        $this->assertSame('1999998.00', (string) $order->tax_total);
        $this->assertSame('21999978.00', (string) $order->grand_total);
        $response->assertRedirect("/salesman/orders/{$order->id}");
    }

    /** 3. Quantity = 0 is rejected with 422 */
    public function test_quantity_of_zero_is_rejected(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 0,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /** 4. Negative quantity is rejected with 422 */
    public function test_negative_quantity_is_rejected(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => -10,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /** 5. Fractional / decimal quantity is rejected with 422 */
    public function test_fractional_quantity_is_rejected(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3.75,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /** 6. Excessive quantity above 999999 is rejected with 422 */
    public function test_excessive_quantity_above_999999_is_rejected(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1000000,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /** 7. Non-numeric quantity is rejected with 422 */
    public function test_non_numeric_quantity_is_rejected(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 'five',
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    /** 8. Draft quantity update recalculates preview totals and increments version */
    public function test_draft_quantity_update_recalculates_preview_totals_and_increments_version(): void
    {
        // Step 1: Create draft with quantity = 2 (subtotal = 40.00, tax = 4.00, grand = 44.00)
        $createResponse = $this->actingAs($this->salesman)
            ->postJson('/salesman/orders/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2,
                        'unit_price' => '20.00',
                    ],
                ],
            ]);

        $createResponse->assertOk();
        $draftId = $createResponse->json('draft.id');

        $draft = Order::find($draftId);
        $this->assertSame(1, $draft->version);
        $this->assertSame('40.00', (string) $draft->subtotal);
        $this->assertSame('4.00', (string) $draft->tax_total);
        $this->assertSame('44.00', (string) $draft->grand_total);
        $this->assertSame(2, $draft->items->first()->ordered_quantity);

        // Step 2: Update draft quantity to 5 (subtotal = 100.00, tax = 10.00, grand = 110.00)
        $updateResponse = $this->actingAs($this->salesman)
            ->putJson("/salesman/orders/drafts/{$draftId}", [
                'customer_id' => $this->customer->id,
                'expected_version' => 1,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 5,
                        'unit_price' => '20.00',
                    ],
                ],
            ]);

        $updateResponse->assertOk();
        $draft->refresh();

        $this->assertSame(2, $draft->version);
        $this->assertSame('100.00', (string) $draft->subtotal);
        $this->assertSame('10.00', (string) $draft->tax_total);
        $this->assertSame('110.00', (string) $draft->grand_total);
        $this->assertSame(5, $draft->items->first()->ordered_quantity);
    }

    /** 9. Draft update with invalid quantity is rejected */
    public function test_draft_update_with_invalid_quantity_is_rejected(): void
    {
        $createResponse = $this->actingAs($this->salesman)
            ->postJson('/salesman/orders/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2,
                        'unit_price' => '20.00',
                    ],
                ],
            ]);

        $draftId = $createResponse->json('draft.id');

        $updateResponse = $this->actingAs($this->salesman)
            ->putJson("/salesman/orders/drafts/{$draftId}", [
                'customer_id' => $this->customer->id,
                'expected_version' => 1,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 0,
                        'unit_price' => '20.00',
                    ],
                ],
            ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    /** 10. Resumed draft submits with authoritative quantity and totals */
    public function test_resumed_draft_submits_with_authoritative_quantity_and_totals(): void
    {
        $createResponse = $this->actingAs($this->salesman)
            ->postJson('/salesman/orders/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 4,
                        'unit_price' => '20.00',
                    ],
                ],
            ]);

        $draftId = $createResponse->json('draft.id');
        $draft = Order::find($draftId);

        // Submit draft
        $submitResponse = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draft->id}/submit");

        $submitResponse->assertRedirect("/salesman/orders/{$draft->id}");
        $draft->refresh();

        $this->assertSame(OrderStatus::SUBMITTED, $draft->status);
        $this->assertSame(4, $draft->items->first()->ordered_quantity);
        $this->assertSame('80.00', (string) $draft->subtotal);
        $this->assertSame('8.00', (string) $draft->tax_total);
        $this->assertSame('88.00', (string) $draft->grand_total);
        $this->assertNotNull($draft->order_number);
    }
}
