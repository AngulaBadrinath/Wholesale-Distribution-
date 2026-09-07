<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\AccountStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reporting\DeliveryPerformanceReportService;
use App\Services\Reporting\FinancialReportService;
use App\Services\Reporting\InventoryReportService;
use App\Services\Reporting\SalesmanPerformanceReportService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman1;
    protected User $salesman2;
    protected User $driver1;
    protected User $driver2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman1 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman2 = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->driver1 = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->driver2 = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->get(route('admin.reports.sales'))->assertRedirect(route('login'));
        $this->get(route('admin.reports.financial'))->assertRedirect(route('login'));
        $this->get(route('admin.reports.inventory'))->assertRedirect(route('login'));
    }

    public function test_salesman_is_forbidden_from_financial_reports(): void
    {
        $response = $this->actingAs($this->salesman1)->get(route('admin.reports.financial'));
        $response->assertStatus(403);
    }

    public function test_driver_is_forbidden_from_financial_reports(): void
    {
        $response = $this->actingAs($this->driver1)->get(route('admin.reports.financial'));
        $response->assertStatus(403);
    }

    public function test_accountant_can_access_financial_reports(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('admin.reports.financial'));
        $response->assertStatus(200);
    }

    public function test_salesman_parameter_tampering_is_prevented_in_salesman_performance_service(): void
    {
        $service = app(SalesmanPerformanceReportService::class);

        // Salesman 1 attempts to query Salesman 2 by passing salesman_id = salesman2->id
        $report = $service->getSalesmanPerformanceReport([
            'salesman_id' => $this->salesman2->id,
        ], $this->salesman1);

        // Result MUST ONLY contain Salesman 1
        $this->assertCount(1, $report['data']);
        $this->assertEquals($this->salesman1->id, $report['data'][0]['salesman_id']);
    }

    public function test_cost_price_is_hidden_from_salesman_in_inventory_service(): void
    {
        $service = app(InventoryReportService::class);

        $this->assertFalse($service->canViewCostPrice($this->salesman1));
        $this->assertFalse($service->canViewCostPrice($this->driver1));
        $this->assertTrue($service->canViewCostPrice($this->admin));
        $this->assertTrue($service->canViewCostPrice($this->accountant));
    }

    public function test_driver_parameter_tampering_is_prevented_in_delivery_performance_service(): void
    {
        $service = app(DeliveryPerformanceReportService::class);

        // Driver 1 attempts to query Driver 2 data by passing driver_id = driver2->id
        $breakdown = $service->getDriverPerformanceBreakdown([
            'driver_id' => $this->driver2->id,
        ], $this->driver1);

        // Breakdown MUST ONLY contain Driver 1
        $this->assertCount(1, $breakdown);
        $this->assertEquals($this->driver1->id, $breakdown[0]['driver_id']);
    }
}
