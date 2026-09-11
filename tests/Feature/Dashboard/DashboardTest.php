<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admin_receives_authoritative_operational_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('metrics')
            ->has('metrics.pending_approval_orders')
            ->has('metrics.today_orders_count')
            ->has('metrics.today_sales_volume')
            ->has('metrics.active_customers_count')
            ->has('metrics.low_stock_items_count')
            ->has('metrics.pending_payments_count')
            ->has('metrics.active_deliveries_count')
            ->has('recentOrders')
        );
    }

    public function test_salesman_receives_salesman_overview_dashboard(): void
    {
        $salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
        ]);

        $response = $this->actingAs($salesman)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Dashboard')
            ->has('metrics')
            ->has('metrics.assigned_customers_count')
            ->has('metrics.draft_orders_count')
            ->has('metrics.in_flight_orders_count')
            ->has('metrics.completed_orders_count')
            ->has('recentOrders')
            ->has('assignedCustomers')
            ->has('categories')
        );
    }

    public function test_salesman_cannot_access_administrative_sales_reports(): void
    {
        $salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
        ]);

        $response = $this->actingAs($salesman)->get('/admin/reports/sales');
        $response->assertForbidden();

        $responseHub = $this->actingAs($salesman)->get('/admin/reports');
        $responseHub->assertForbidden();
    }

    public function test_delivery_partner_is_redirected_to_delivery_workspace(): void
    {
        $driver = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
        ]);

        $response = $this->actingAs($driver)->get('/dashboard');

        $response->assertRedirect(route('delivery.index'));
    }

    public function test_foundation_route_is_removed(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/foundation');

        $response->assertNotFound();
    }
}
