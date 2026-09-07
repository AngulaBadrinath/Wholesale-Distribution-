<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\AccountStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reporting\InventoryReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $warehouseManager;
    protected User $salesman;
    protected Warehouse $warehouse;
    protected Category $category;
    protected Product $product1;
    protected Product $product2;
    protected InventoryReportService $service;

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

        $this->warehouseManager = User::factory()->create([
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Distribution Center',
                'address_line1' => '100 Warehouse Way',
                'city' => 'Atlanta',
                'state' => 'GA',
                'postal_code' => '30301',
                'country_code' => 'USA',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $this->category = Category::create([
            'name' => 'Industrial Goods',
            'code' => 'IND-01',
            'is_active' => true,
        ]);

        $taxProfile = TaxProfile::firstOrCreate(
            ['code' => 'STANDARD'],
            ['name' => 'Standard Tax', 'rate' => '10.00', 'is_active' => true]
        );

        $this->product1 = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $taxProfile->id,
            'name' => 'Heavy Duty Compressor',
            'sku' => 'SKU-COMP-01',
            'cost_price' => '250.00',
            'default_selling_price' => '400.00',
            'minimum_allowed_price' => '350.00',
            'mrp' => '500.00',
            'unit' => 'piece',
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->product2 = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $taxProfile->id,
            'name' => 'Filter Assembly',
            'sku' => 'SKU-FILT-02',
            'cost_price' => '15.00',
            'default_selling_price' => '30.00',
            'minimum_allowed_price' => '25.00',
            'mrp' => '40.00',
            'unit' => 'piece',
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->service = app(InventoryReportService::class);
    }

    private function getOrCreateBalance(Product $product, array $attributes): InventoryBalance
    {
        return InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id],
            array_merge([
                'version' => 1,
                'is_active' => true,
            ], $attributes)
        );
    }

    public function test_inventory_valuation_calculated_accurately_with_cost_price_for_authorized_users(): void
    {
        // 10 units on hand of Product 1 @ $250.00 cost = $2,500.00
        $this->getOrCreateBalance($this->product1, [
            'on_hand_quantity' => 10,
            'reserved_quantity' => 2,
            'available_quantity' => 8,
            'damaged_quantity' => 0,
        ]);

        // 100 units on hand of Product 2 @ $15.00 cost = $1,500.00
        $this->getOrCreateBalance($this->product2, [
            'on_hand_quantity' => 100,
            'reserved_quantity' => 10,
            'available_quantity' => 85,
            'damaged_quantity' => 5,
        ]);

        $report = $this->service->getInventoryValuationReport(['warehouse_id' => $this->warehouse->id], $this->admin);

        $this->assertTrue($report['can_view_cost_price']);
        $this->assertEquals(110, $report['summary']['total_on_hand_qty']);
        $this->assertEquals(12, $report['summary']['total_reserved_qty']);
        $this->assertEquals(93, $report['summary']['total_available_qty']);
        $this->assertEquals(5, $report['summary']['total_damaged_qty']);
        $this->assertEquals('4000.00', $report['summary']['total_valuation']); // 2500 + 1500

        $row1 = collect($report['data'])->firstWhere('product_id', $this->product1->id);
        $this->assertEquals('250.00', $row1['unit_cost_price']);
        $this->assertEquals('2500.00', $row1['on_hand_valuation']);
        $this->assertEquals('2000.00', $row1['available_valuation']); // 8 * 250.00
    }

    public function test_cost_price_and_valuation_masked_for_unauthorized_roles(): void
    {
        $this->getOrCreateBalance($this->product1, [
            'on_hand_quantity' => 10,
            'reserved_quantity' => 2,
            'available_quantity' => 8,
            'damaged_quantity' => 0,
        ]);

        // When viewed by a salesman
        $report = $this->service->getInventoryValuationReport(['warehouse_id' => $this->warehouse->id], $this->salesman);

        $this->assertFalse($report['can_view_cost_price']);
        $this->assertNull($report['summary']['total_valuation']);

        $row = collect($report['data'])->firstWhere('product_id', $this->product1->id);
        $this->assertNull($row['unit_cost_price']);
        $this->assertNull($row['on_hand_valuation']);
        $this->assertNull($row['available_valuation']);
        $this->assertEquals(10, $row['on_hand']);
        $this->assertEquals(8, $row['available']);
    }

    public function test_inventory_movement_ledger_report_retrieves_audit_history(): void
    {
        $balance = $this->getOrCreateBalance($this->product1, [
            'on_hand_quantity' => 50,
            'reserved_quantity' => 0,
            'available_quantity' => 50,
            'damaged_quantity' => 0,
        ]);

        // Movement 1: Receive goods
        InventoryMovement::create([
            'movement_number' => 'MOV-001',
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product1->id,
            'inventory_balance_id' => $balance->id,
            'movement_type' => InventoryMovementType::INCREASE_ON_HAND,
            'from_state' => InventoryStockState::NONE,
            'to_state' => InventoryStockState::AVAILABLE,
            'quantity' => 50,
            'on_hand_before' => 0,
            'on_hand_after' => 50,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'available_before' => 0,
            'available_after' => 50,
            'damaged_before' => 0,
            'damaged_after' => 0,
            'reference_type' => 'PURCHASE_RECEIPT',
            'reference_number' => 'PO-1001',
            'notes' => 'Initial bulk stock receipt',
            'actor_id' => $this->warehouseManager->id,
        ]);

        $movementReport = $this->service->getInventoryMovementReport(['product_id' => $this->product1->id]);

        $this->assertCount(1, $movementReport['data']);
        $movRow = $movementReport['data'][0];

        $this->assertEquals('MOV-001', $movRow['movement_number']);
        $this->assertEquals(InventoryMovementType::INCREASE_ON_HAND->value, $movRow['movement_type']);
        $this->assertEquals(50, $movRow['quantity']);
        $this->assertEquals(0, $movRow['on_hand_before']);
        $this->assertEquals(50, $movRow['on_hand_after']);
        $this->assertEquals('PO-1001', $movRow['reference_number']);
    }

    public function test_low_stock_alerts_correctly_filters_below_threshold(): void
    {
        // Product 1: Out of stock
        $this->getOrCreateBalance($this->product1, [
            'on_hand_quantity' => 0,
            'reserved_quantity' => 0,
            'available_quantity' => 0,
            'damaged_quantity' => 0,
        ]);

        // Product 2: Low stock (5 available <= 10)
        $this->getOrCreateBalance($this->product2, [
            'on_hand_quantity' => 8,
            'reserved_quantity' => 3,
            'available_quantity' => 5,
            'damaged_quantity' => 0,
        ]);

        $alerts = $this->service->getLowStockAlerts(['warehouse_id' => $this->warehouse->id], $this->admin);

        $this->assertCount(2, $alerts);

        $outOfStock = collect($alerts)->firstWhere('product_id', $this->product1->id);
        $this->assertEquals('OUT_OF_STOCK', $outOfStock['status']);
        $this->assertEquals(0, $outOfStock['available']);

        $lowStock = collect($alerts)->firstWhere('product_id', $this->product2->id);
        $this->assertEquals('LOW_STOCK', $lowStock['status']);
        $this->assertEquals(5, $lowStock['available']);
    }
}
