<?php

namespace Tests\Feature\Delivery;

use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Delivery;
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

class DeliveryPartnerQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $driverA;
    private User $driverB;
    private User $admin;
    private User $salesman;
    private User $accountant;
    private Warehouse $warehouse;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverA = User::factory()->deliveryPartner()->create(['name' => 'Driver Alice']);
        $this->driverB = User::factory()->deliveryPartner()->create(['name' => 'Driver Bob']);
        $this->admin = User::factory()->admin()->create();
        $this->salesman = User::factory()->salesman()->create();
        $this->accountant = User::factory()->accountant()->create();

        $this->warehouse = Warehouse::create([
            'code' => 'WH-DEL-02',
            'name' => 'Metro Distribution Center',
            'address_line1' => '700 Express Way',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Downtown Supermarket',
            'code' => 'CUST-DT-01',
            'contact_name' => 'Bob Manager',
            'phone' => '+15552345678',
            'email' => 'bob@downtown.com',
            'billing_address_line1' => '200 Downtown Blvd',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '200 Downtown Blvd',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createDeliveryForDriver(User $driver, array $attributes = []): Delivery
    {
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8)),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::RESERVED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => DeliveryStatus::ASSIGNED,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-' . Str::uuid(),
            'subtotal' => 200.00,
            'tax_total' => 10.00,
            'adjustment_total' => 0.00,
            'grand_total' => 210.00,
            'version' => 1,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        return Delivery::create(array_merge([
            'delivery_number' => 'DEL-' . date('Y') . '-' . sprintf('%06d', rand(100000, 999999)),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $driver->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_contact_name' => 'Bob Manager',
            'delivery_contact_phone' => '+15552345678',
            'delivery_address_line1' => '200 Downtown Blvd',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_driver_can_view_assigned_queue(): void
    {
        $deliveryA1 = $this->createDeliveryForDriver($this->driverA);
        $deliveryA2 = $this->createDeliveryForDriver($this->driverA, ['status' => DeliveryStatus::PICKED_UP]);
        $deliveryB = $this->createDeliveryForDriver($this->driverB);

        $response = $this->actingAs($this->driverA)->get(route('delivery.index', ['tab' => 'all']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Delivery/Index')
            ->has('deliveries.data', 2)
            ->where('deliveries.data.0.id', $deliveryA2->id)
            ->where('deliveries.data.1.id', $deliveryA1->id)
            ->where('counts.all', 2)
        );
    }

    public function test_driver_can_view_own_delivery_detail(): void
    {
        $deliveryA = $this->createDeliveryForDriver($this->driverA);

        $response = $this->actingAs($this->driverA)->get(route('delivery.show', $deliveryA));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Delivery/Show')
            ->where('delivery.id', $deliveryA->id)
            ->where('delivery.delivery_number', $deliveryA->delivery_number)
            ->where('capabilities.is_assigned_driver', true)
        );
    }

    public function test_anti_idor_driver_cannot_view_another_drivers_delivery(): void
    {
        $deliveryB = $this->createDeliveryForDriver($this->driverB);

        // Fail-closed Anti-IDOR returns 404
        $response = $this->actingAs($this->driverA)->get(route('delivery.show', $deliveryB));

        $response->assertStatus(404);
    }

    public function test_anti_idor_driver_cannot_access_history_of_another_drivers_delivery(): void
    {
        $deliveryB = $this->createDeliveryForDriver($this->driverB);

        $response = $this->actingAs($this->driverA)->getJson(route('delivery.history', $deliveryB));

        $response->assertStatus(404);
    }

    public function test_admin_can_view_any_delivery(): void
    {
        $deliveryB = $this->createDeliveryForDriver($this->driverB);

        $response = $this->actingAs($this->admin)->get(route('delivery.show', $deliveryB));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Delivery/Show')
            ->where('delivery.id', $deliveryB->id)
        );
    }

    public function test_salesman_and_accountant_are_forbidden_from_driver_portal(): void
    {
        $delivery = $this->createDeliveryForDriver($this->driverA);

        // Salesman lacks delivery.view
        $this->actingAs($this->salesman)
            ->get(route('delivery.index'))
            ->assertStatus(403);

        $this->actingAs($this->salesman)
            ->get(route('delivery.show', $delivery))
            ->assertStatus(403);

        // Accountant lacks delivery.view
        $this->actingAs($this->accountant)
            ->get(route('delivery.index'))
            ->assertStatus(403);

        $this->actingAs($this->accountant)
            ->get(route('delivery.show', $delivery))
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $delivery = $this->createDeliveryForDriver($this->driverA);

        $this->get(route('delivery.index'))->assertRedirect(route('login'));
        $this->get(route('delivery.show', $delivery))->assertRedirect(route('login'));
    }
}
