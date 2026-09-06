<?php

namespace Tests\Feature\Inventory;

use App\Enums\AccountStatus;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryAdjustmentType;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Warehouse $warehouse;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;
    protected InventoryBalance $balance;
    protected InventoryAdjustmentService $adjustmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adjustmentService = app(InventoryAdjustmentService::class);

        $this->admin = User::create([
            'name' => 'Admin Controller',
            'email' => 'admin.adj@example.com',
            'password' => bcrypt('Password123!'),
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::create([
            'name' => 'Sales Staff',
            'email' => 'sales.adj@example.com',
            'password' => bcrypt('Password123!'),
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Distribution Center',
                'country_code' => 'US',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Rate',
            'code' => 'TAX-STD',
            'rate' => 0.10,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'name' => 'Diet Soda 500ml',
            'sku' => 'BEV-SODA-01',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 1.00,
            'minimum_allowed_price' => 1.50,
            'default_selling_price' => 2.00,
            'mrp' => 2.50,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'CAN',
        ]);

        $this->balance = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();
        $this->balance->update([
            'on_hand_quantity' => 100,
            'reserved_quantity' => 20,
            'available_quantity' => 80,
            'damaged_quantity' => 0,
            'reorder_point' => 20,
            'safety_stock' => 10,
            'version' => 1,
        ]);
    }

    public function test_admin_can_increase_on_hand_stock_successfully(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::INCREASE_ON_HAND->value,
            'reason_code' => InventoryAdjustmentReason::FOUND_STOCK->value,
            'quantity' => 25,
            'expected_version' => 1,
            'notes' => 'Found 25 surplus units in secondary staging area during weekly count.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->balance->refresh();
        $this->assertEquals(125, $this->balance->on_hand_quantity);
        $this->assertEquals(20, $this->balance->reserved_quantity);
        $this->assertEquals(105, $this->balance->available_quantity);
        $this->assertEquals(0, $this->balance->damaged_quantity);
        $this->assertEquals(2, $this->balance->version);

        $this->assertDatabaseHas('inventory_adjustments', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::INCREASE_ON_HAND->value,
            'reason_code' => InventoryAdjustmentReason::FOUND_STOCK->value,
            'quantity' => 25,
            'on_hand_before' => 100,
            'on_hand_after' => 125,
            'available_before' => 80,
            'available_after' => 105,
            'actor_id' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'movement_type' => InventoryMovementType::INCREASE_ON_HAND->value,
            'from_state' => InventoryStockState::EXTERNAL->value,
            'to_state' => InventoryStockState::AVAILABLE->value,
            'quantity' => 25,
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_decrease_available_stock_successfully(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::DECREASE_ON_HAND->value,
            'reason_code' => InventoryAdjustmentReason::CYCLE_COUNT_DISCREPANCY->value,
            'quantity' => 30,
            'expected_version' => 1,
            'notes' => 'Cycle count shortage confirmed after physical audit.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->balance->refresh();
        $this->assertEquals(70, $this->balance->on_hand_quantity);
        $this->assertEquals(20, $this->balance->reserved_quantity);
        $this->assertEquals(50, $this->balance->available_quantity);
        $this->assertEquals(0, $this->balance->damaged_quantity);
        $this->assertEquals(2, $this->balance->version);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'movement_type' => InventoryMovementType::DECREASE_ON_HAND->value,
            'from_state' => InventoryStockState::AVAILABLE->value,
            'to_state' => InventoryStockState::NONE->value,
            'quantity' => 30,
        ]);
    }

    public function test_admin_cannot_decrease_stock_beyond_available(): void
    {
        $this->expectException(ValidationException::class);

        $this->adjustmentService->adjustBalance([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::DECREASE_ON_HAND,
            'reason_code' => InventoryAdjustmentReason::CYCLE_COUNT_DISCREPANCY,
            'quantity' => 85, // Available is 80
            'notes' => 'Attempting to decrease more than available',
        ], $this->admin);
    }

    public function test_admin_can_transfer_available_stock_to_damaged(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::TRANSFER_TO_DAMAGED->value,
            'reason_code' => InventoryAdjustmentReason::DAMAGED_WRITE_OFF->value,
            'quantity' => 15,
            'notes' => 'Transferring water-damaged cans to quarantine zone.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->balance->refresh();
        $this->assertEquals(100, $this->balance->on_hand_quantity);
        $this->assertEquals(20, $this->balance->reserved_quantity);
        $this->assertEquals(65, $this->balance->available_quantity);
        $this->assertEquals(15, $this->balance->damaged_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'movement_type' => InventoryMovementType::DAMAGE_ISOLATION->value,
            'from_state' => InventoryStockState::AVAILABLE->value,
            'to_state' => InventoryStockState::DAMAGED->value,
            'quantity' => 15,
        ]);
    }

    public function test_admin_cannot_transfer_to_damaged_beyond_available(): void
    {
        $this->expectException(ValidationException::class);

        $this->adjustmentService->adjustBalance([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::TRANSFER_TO_DAMAGED,
            'reason_code' => InventoryAdjustmentReason::DAMAGED_WRITE_OFF,
            'quantity' => 90, // Available is 80
            'notes' => 'Attempting transfer beyond available limit',
        ], $this->admin);
    }

    public function test_admin_can_dispose_damaged_stock(): void
    {
        // Setup existing damaged stock
        $this->balance->update([
            'on_hand_quantity' => 100,
            'available_quantity' => 60,
            'reserved_quantity' => 20,
            'damaged_quantity' => 20,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::DAMAGE_DISPOSAL->value,
            'reason_code' => InventoryAdjustmentReason::EXPIRATION_DISPOSAL->value,
            'quantity' => 15,
            'notes' => 'Certified scrap disposal for expired stock with waste vendor.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->balance->refresh();
        $this->assertEquals(85, $this->balance->on_hand_quantity);
        $this->assertEquals(20, $this->balance->reserved_quantity);
        $this->assertEquals(60, $this->balance->available_quantity);
        $this->assertEquals(5, $this->balance->damaged_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'movement_type' => InventoryMovementType::DAMAGE_RELEASE->value,
            'from_state' => InventoryStockState::DAMAGED->value,
            'to_state' => InventoryStockState::NONE->value,
            'quantity' => 15,
        ]);
    }

    public function test_admin_cannot_dispose_damaged_stock_beyond_damaged_quantity(): void
    {
        // Setup damaged stock as 10
        $this->balance->update([
            'on_hand_quantity' => 100,
            'available_quantity' => 70,
            'reserved_quantity' => 20,
            'damaged_quantity' => 10,
        ]);

        $this->expectException(ValidationException::class);

        $this->adjustmentService->adjustBalance([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::DAMAGE_DISPOSAL,
            'reason_code' => InventoryAdjustmentReason::EXPIRATION_DISPOSAL,
            'quantity' => 15, // Damaged is only 10
            'notes' => 'Attempting to dispose more than damaged',
        ], $this->admin);
    }

    public function test_optimistic_concurrency_conflict_throws_conflict_exception(): void
    {
        $this->expectException(ConflictHttpException::class);

        $this->adjustmentService->adjustBalance([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::INCREASE_ON_HAND,
            'reason_code' => InventoryAdjustmentReason::FOUND_STOCK,
            'quantity' => 10,
            'expected_version' => 999, // Mismatched version
            'notes' => 'Adjustment with stale version',
        ], $this->admin);
    }

    public function test_salesman_cannot_post_inventory_adjustments(): void
    {
        $response = $this->actingAs($this->salesman)->post(route('admin.inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::INCREASE_ON_HAND->value,
            'reason_code' => InventoryAdjustmentReason::FOUND_STOCK->value,
            'quantity' => 10,
            'notes' => 'Salesman attempting unauthorized adjustment',
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_post_inventory_adjustments(): void
    {
        $response = $this->post(route('admin.inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::INCREASE_ON_HAND->value,
            'reason_code' => InventoryAdjustmentReason::FOUND_STOCK->value,
            'quantity' => 10,
            'notes' => 'Unauthenticated adjustment',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_validation_rejects_empty_notes_or_zero_quantity(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::INCREASE_ON_HAND->value,
            'reason_code' => InventoryAdjustmentReason::FOUND_STOCK->value,
            'quantity' => 0,
            'notes' => 'abc', // Less than 5 chars
        ]);

        $response->assertSessionHasErrors(['quantity', 'notes']);
    }

    public function test_adjustments_are_correctly_listed_and_filtered(): void
    {
        $this->adjustmentService->adjustBalance([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::INCREASE_ON_HAND,
            'reason_code' => InventoryAdjustmentReason::FOUND_STOCK,
            'quantity' => 10,
            'notes' => 'First adjustment test notes',
        ], $this->admin);

        $this->adjustmentService->adjustBalance([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'adjustment_type' => InventoryAdjustmentType::TRANSFER_TO_DAMAGED,
            'reason_code' => InventoryAdjustmentReason::DAMAGED_WRITE_OFF,
            'quantity' => 5,
            'notes' => 'Second adjustment test notes',
        ], $this->admin);

        $all = $this->adjustmentService->listAdjustments([], 15, $this->admin);
        $this->assertEquals(2, $all->total());

        $filtered = $this->adjustmentService->listAdjustments([
            'adjustment_type' => InventoryAdjustmentType::TRANSFER_TO_DAMAGED->value,
        ], 15, $this->admin);

        $this->assertEquals(1, $filtered->total());
        $this->assertEquals(InventoryAdjustmentType::TRANSFER_TO_DAMAGED, $filtered->first()->adjustment_type);
    }
}
