<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\AccountStatus;
use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryFailure;
use App\Models\Order;
use App\Models\User;
use App\Services\Reporting\DeliveryPerformanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $driver1;
    protected User $driver2;
    protected Customer $customer;
    protected Order $order;
    protected DeliveryPerformanceReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->driver1 = User::factory()->create([
            'name' => 'Driver Dan',
            'email' => 'dan@distro.test',
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->driver2 = User::factory()->create([
            'name' => 'Driver Dave',
            'email' => 'dave@distro.test',
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-DEL-01',
            'name' => 'Apex Retail',
            'contact_name' => 'Alice Apex',
            'email' => 'apex@test.com',
            'phone' => '1234567890',
            'salesman_id' => $this->admin->id,
            'credit_limit' => '10000.00',
            'payment_terms' => 'NET_30',
            'status' => 'ACTIVE',
            'billing_address_line1' => '100 Apex Way',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-DEL-01',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DELIVERED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => '100.00',
            'tax_total' => '10.00',
            'adjustment_total' => '0.00',
            'grand_total' => '110.00',
            'idempotency_key' => Str::uuid()->toString(),
            'draft_token' => Str::random(32),
        ]);

        $this->service = app(DeliveryPerformanceReportService::class);
    }

    public function test_delivery_summary_computes_turnaround_and_success_rate(): void
    {
        $baseTime = Carbon::parse('2026-09-01 08:00:00');

        // Delivery 1: Delivered by Driver 1 (assigned 08:00, picked up 09:00, delivered 11:00 = 3h total, 1h pickup, 2h transit)
        Delivery::create([
            'delivery_number' => 'DEL-001',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver1->id,
            'status' => DeliveryStatus::DELIVERED,
            'delivery_address_line1' => '100 Apex Way',
            'delivery_city' => 'Atlanta',
            'delivery_state' => 'GA',
            'delivery_postal_code' => '30301',
            'delivery_country_code' => 'USA',
            'scheduled_date' => $baseTime->toDateString(),
            'assigned_at' => $baseTime,
            'picked_up_at' => $baseTime->copy()->addHour(),
            'delivered_at' => $baseTime->copy()->addHours(3),
            'created_by' => $this->admin->id,
            'total_items_count' => 1,
            'total_units_count' => 5,
        ]);

        // Delivery 2: Failed by Driver 1
        $del2 = Delivery::create([
            'delivery_number' => 'DEL-002',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver1->id,
            'status' => DeliveryStatus::FAILED,
            'delivery_address_line1' => '100 Apex Way',
            'delivery_city' => 'Atlanta',
            'delivery_state' => 'GA',
            'delivery_postal_code' => '30301',
            'delivery_country_code' => 'USA',
            'scheduled_date' => $baseTime->toDateString(),
            'assigned_at' => $baseTime,
            'picked_up_at' => $baseTime->copy()->addHour(),
            'failed_at' => $baseTime->copy()->addHours(2),
            'created_by' => $this->admin->id,
            'total_items_count' => 1,
            'total_units_count' => 5,
        ]);

        DeliveryFailure::create([
            'delivery_id' => $del2->id,
            'driver_id' => $this->driver1->id,
            'failure_reason' => DeliveryFailureReason::CUSTOMER_UNAVAILABLE,
            'driver_notes' => 'Customer store was closed',
            'failed_at' => $baseTime->copy()->addHours(2),
        ]);

        // Delivery 3: Assigned to Driver 2 (In transit)
        Delivery::create([
            'delivery_number' => 'DEL-003',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver2->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_address_line1' => '100 Apex Way',
            'delivery_city' => 'Atlanta',
            'delivery_state' => 'GA',
            'delivery_postal_code' => '30301',
            'delivery_country_code' => 'USA',
            'scheduled_date' => $baseTime->toDateString(),
            'assigned_at' => $baseTime,
            'created_by' => $this->admin->id,
            'total_items_count' => 1,
            'total_units_count' => 5,
        ]);

        $summary = $this->service->getDeliverySummary([], $this->admin);

        $this->assertEquals(3, $summary['total_deliveries']);
        $this->assertEquals(1, $summary['delivered_count']);
        $this->assertEquals(1, $summary['failed_count']);
        $this->assertEquals(1, $summary['in_transit_count']);
        $this->assertEquals(50.0, $summary['success_rate_percent']); // 1 delivered out of 2 completed (1 delivered + 1 failed)
        $this->assertEquals(3.0, $summary['average_turnaround_hours']); // 3 hours
        $this->assertEquals(1.0, $summary['average_assignment_to_pickup_hours']); // 1 hour
        $this->assertEquals(2.0, $summary['average_transit_to_delivery_hours']); // 2 hours
    }

    public function test_driver_performance_breakdown_and_failure_analysis(): void
    {
        $baseTime = Carbon::parse('2026-09-01 08:00:00');

        $del = Delivery::create([
            'delivery_number' => 'DEL-DAN-1',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver1->id,
            'status' => DeliveryStatus::FAILED,
            'delivery_address_line1' => '100 Apex Way',
            'delivery_city' => 'Atlanta',
            'delivery_state' => 'GA',
            'delivery_postal_code' => '30301',
            'delivery_country_code' => 'USA',
            'scheduled_date' => $baseTime->toDateString(),
            'assigned_at' => $baseTime,
            'failed_at' => $baseTime->copy()->addHours(2),
            'created_by' => $this->admin->id,
            'total_items_count' => 1,
            'total_units_count' => 5,
        ]);

        DeliveryFailure::create([
            'delivery_id' => $del->id,
            'driver_id' => $this->driver1->id,
            'failure_reason' => DeliveryFailureReason::BUSINESS_CLOSED,
            'driver_notes' => 'Gate locked',
            'failed_at' => $baseTime->copy()->addHours(2),
        ]);

        $breakdown = $this->service->getDriverPerformanceBreakdown([], $this->admin);
        $this->assertCount(2, $breakdown);

        $danRow = collect($breakdown)->firstWhere('driver_id', $this->driver1->id);
        $this->assertEquals(1, $danRow['deliveries_assigned']);
        $this->assertEquals(1, $danRow['deliveries_failed']);
        $this->assertEquals(0.0, $danRow['success_rate_percent']);

        $failures = $this->service->getDeliveryFailureAnalysis([], $this->admin);
        $this->assertCount(1, $failures);
        $this->assertEquals(DeliveryFailureReason::BUSINESS_CLOSED->value, $failures[0]['failure_reason']);
        $this->assertEquals(1, $failures[0]['failure_count']);
        $this->assertEquals(100.0, $failures[0]['percentage']);
    }

    public function test_driver_scoping_restricts_to_own_deliveries(): void
    {
        $baseTime = Carbon::parse('2026-09-01 08:00:00');

        Delivery::create([
            'delivery_number' => 'DEL-DAN-2',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver1->id,
            'status' => DeliveryStatus::DELIVERED,
            'delivery_address_line1' => '100 Apex Way',
            'delivery_city' => 'Atlanta',
            'delivery_state' => 'GA',
            'delivery_postal_code' => '30301',
            'delivery_country_code' => 'USA',
            'scheduled_date' => $baseTime->toDateString(),
            'assigned_at' => $baseTime,
            'delivered_at' => $baseTime->copy()->addHours(2),
            'created_by' => $this->admin->id,
            'total_items_count' => 1,
            'total_units_count' => 5,
        ]);

        Delivery::create([
            'delivery_number' => 'DEL-DAVE-1',
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver2->id,
            'status' => DeliveryStatus::DELIVERED,
            'delivery_address_line1' => '100 Apex Way',
            'delivery_city' => 'Atlanta',
            'delivery_state' => 'GA',
            'delivery_postal_code' => '30301',
            'delivery_country_code' => 'USA',
            'scheduled_date' => $baseTime->toDateString(),
            'assigned_at' => $baseTime,
            'delivered_at' => $baseTime->copy()->addHours(3),
            'created_by' => $this->admin->id,
            'total_items_count' => 1,
            'total_units_count' => 5,
        ]);

        // When queried by Driver 1, only 1 delivery is seen
        $summaryDan = $this->service->getDeliverySummary([], $this->driver1);
        $this->assertEquals(1, $summaryDan['total_deliveries']);

        $listDan = $this->service->getDeliveriesList([], $this->driver1);
        $this->assertEquals(1, $listDan['total']);
        $this->assertEquals($this->driver1->id, $listDan['data'][0]['driver_id']);
    }
}
