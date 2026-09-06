<?php

namespace Tests\Feature\Delivery;

use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryItem;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $driver1;
    private User $driver2;
    private User $salesman;
    private Warehouse $warehouse;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Logistics']);
        $this->driver1 = User::factory()->deliveryPartner()->create(['name' => 'Driver Frank']);
        $this->driver2 = User::factory()->deliveryPartner()->create(['name' => 'Driver Gina']);
        $this->salesman = User::factory()->salesman()->create(['name' => 'Sales Sam']);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-HIST-01',
            'name' => 'Central Hub',
            'address_line1' => '1200 Logistics Park',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Riverside Foods',
            'code' => 'CUST-RF-01',
            'contact_name' => 'Rachel Green',
            'phone' => '+15555678901',
            'email' => 'rachel@riverside.com',
            'billing_address_line1' => '500 Riverside Drive',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '500 Riverside Drive',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createSampleDelivery(User $driver, DeliveryStatus $status = DeliveryStatus::ASSIGNED): Delivery
    {
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8)),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DISPATCHED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => $status,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-' . Str::uuid(),
            'subtotal' => 200.00,
            'tax_total' => 10.00,
            'grand_total' => 210.00,
        ]);

        $product = Product::create([
            'sku' => 'PROD-HIST-' . strtoupper(Str::random(4)),
            'name' => 'Olive Oil Bottle 5L',
            'unit' => 'BOTTLE',
            'cost_price' => 15.00,
            'minimum_allowed_price' => 18.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999)),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $driver->id,
            'status' => $status,
            'delivery_address_line1' => '500 Riverside Drive',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'created_by' => $this->admin->id,
        ]);

        // Add history events
        DeliveryEvent::create([
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::CREATED,
            'from_status' => null,
            'to_status' => DeliveryStatus::PENDING_ASSIGNMENT->value,
            'actor_id' => $this->admin->id,
            'notes' => 'Delivery created from approved order.',
            'created_at' => Carbon::now()->subHours(3),
        ]);

        DeliveryEvent::create([
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::ASSIGNED,
            'from_status' => DeliveryStatus::PENDING_ASSIGNMENT->value,
            'to_status' => DeliveryStatus::ASSIGNED->value,
            'actor_id' => $this->admin->id,
            'notes' => "Assigned to {$driver->name}.",
            'created_at' => Carbon::now()->subHours(2),
        ]);

        return $delivery;
    }

    public function test_admin_can_view_deliveries_index_with_filters_and_counts(): void
    {
        $this->createSampleDelivery($this->driver1, DeliveryStatus::ASSIGNED);
        $this->createSampleDelivery($this->driver2, DeliveryStatus::DELIVERED);

        $response = $this->actingAs($this->admin)
            ->get('/admin/deliveries');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Deliveries/Index')
                ->has('deliveries.data', 2)
                ->has('badgeCounts')
                ->has('availableDrivers')
                ->has('filters')
                ->where('badgeCounts.all', 2)
                ->where('badgeCounts.assigned', 1)
                ->where('badgeCounts.delivered', 1)
            );
    }

    public function test_admin_can_filter_deliveries_by_tab_and_driver(): void
    {
        $d1 = $this->createSampleDelivery($this->driver1, DeliveryStatus::ASSIGNED);
        $d2 = $this->createSampleDelivery($this->driver2, DeliveryStatus::DELIVERED);

        // Filter by assigned tab
        $response = $this->actingAs($this->admin)
            ->get('/admin/deliveries?tab=assigned');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Deliveries/Index')
                ->has('deliveries.data', 1)
                ->where('deliveries.data.0.id', $d1->id)
            );

        // Filter by driver_id
        $responseDriver = $this->actingAs($this->admin)
            ->get("/admin/deliveries?driver_id={$this->driver2->id}");

        $responseDriver->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Deliveries/Index')
                ->has('deliveries.data', 1)
                ->where('deliveries.data.0.id', $d2->id)
            );
    }

    public function test_admin_can_retrieve_delivery_history_events_via_api(): void
    {
        $delivery = $this->createSampleDelivery($this->driver1, DeliveryStatus::ASSIGNED);

        $response = $this->actingAs($this->admin)
            ->getJson("/delivery/{$delivery->id}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'delivery_id',
                'delivery_number',
                'events' => [
                    '*' => [
                        'id',
                        'event_type',
                        'from_status',
                        'to_status',
                        'notes',
                        'created_at',
                        'actor' => ['id', 'name', 'role'],
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('events'));
    }

    public function test_driver_can_retrieve_own_delivery_history_events_via_api(): void
    {
        $delivery = $this->createSampleDelivery($this->driver1, DeliveryStatus::ASSIGNED);

        $response = $this->actingAs($this->driver1)
            ->getJson("/delivery/{$delivery->id}/history");

        $response->assertStatus(200)
            ->assertJson([
                'delivery_id' => $delivery->id,
            ]);
    }

    public function test_anti_idor_driver_cannot_retrieve_other_driver_history_events_via_api(): void
    {
        $delivery = $this->createSampleDelivery($this->driver1, DeliveryStatus::ASSIGNED);

        // Driver 2 trying to access Driver 1's delivery history gets 404
        $response = $this->actingAs($this->driver2)
            ->getJson("/delivery/{$delivery->id}/history");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_history_endpoint(): void
    {
        $delivery = $this->createSampleDelivery($this->driver1, DeliveryStatus::ASSIGNED);

        $response = $this->getJson("/delivery/{$delivery->id}/history");

        $response->assertStatus(401);
    }

    public function test_unauthorized_user_without_permission_cannot_access_admin_deliveries(): void
    {
        // Salesman does not have delivery.view permission
        $response = $this->actingAs($this->salesman)
            ->get('/admin/deliveries');

        $response->assertStatus(403);
    }
}
