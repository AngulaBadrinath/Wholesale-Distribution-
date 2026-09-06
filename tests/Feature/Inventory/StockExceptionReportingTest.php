<?php

namespace Tests\Feature\Inventory;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Enums\StockExceptionSeverity;
use App\Enums\StockExceptionStatus;
use App\Enums\StockExceptionType;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockException;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockExceptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockExceptionReportingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Warehouse $warehouse;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;
    protected InventoryBalance $balance;
    protected StockExceptionService $exceptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exceptionService = app(StockExceptionService::class);

        $this->admin = User::create([
            'name' => 'Admin Controller',
            'email' => 'admin.exc@example.com',
            'password' => bcrypt('Password123!'),
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::create([
            'name' => 'Sales Staff',
            'email' => 'sales.exc@example.com',
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
            'damaged_quantity' => 0,
            'available_quantity' => 80,
            'version' => 1,
        ]);
    }

    /**
     * 1. Report Exception on Available Stock and Quarantine Damaged Units
     */
    public function test_warehouse_staff_can_report_stock_exception_and_quarantine_available_stock(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/inventory-exceptions', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'exception_type' => StockExceptionType::DAMAGE->value,
            'severity' => StockExceptionSeverity::HIGH->value,
            'source_stock_state' => InventoryStockState::AVAILABLE->value,
            'quantity' => 10,
            'description' => 'Forklift dropped pallet, 10 cans crushed and leaking',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $exception = StockException::where('inventory_balance_id', $this->balance->id)->first();
        $this->assertNotNull($exception);
        $this->assertEquals(StockExceptionStatus::PENDING_REVIEW, $exception->status);
        $this->assertEquals(10, $exception->quantity);
        $this->assertEquals($this->admin->id, $exception->reported_by);

        // Verify balance mutation
        $this->balance->refresh();
        $this->assertEquals(100, $this->balance->on_hand_quantity);
        $this->assertEquals(20, $this->balance->reserved_quantity);
        $this->assertEquals(10, $this->balance->damaged_quantity);
        $this->assertEquals(70, $this->balance->available_quantity);

        // Verify movement ledger entry
        $movement = InventoryMovement::where('inventory_balance_id', $this->balance->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(InventoryMovementType::DAMAGE_ISOLATION, $movement->movement_type);
        $this->assertEquals(InventoryStockState::AVAILABLE, $movement->from_state);
        $this->assertEquals(InventoryStockState::DAMAGED, $movement->to_state);
        $this->assertEquals(10, $movement->quantity);
    }

    /**
     * 2. Report Exception on Reserved Stock
     */
    public function test_reporting_exception_on_reserved_stock_reduces_reserved_and_quarantines_damaged(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/inventory-exceptions', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'exception_type' => StockExceptionType::LEAKAGE->value,
            'severity' => StockExceptionSeverity::MEDIUM->value,
            'source_stock_state' => InventoryStockState::RESERVED->value,
            'quantity' => 5,
            'description' => 'Damaged during picking for order packing',
        ]);

        $response->assertRedirect();

        $this->balance->refresh();
        $this->assertEquals(100, $this->balance->on_hand_quantity);
        $this->assertEquals(15, $this->balance->reserved_quantity);
        $this->assertEquals(5, $this->balance->damaged_quantity);
        $this->assertEquals(80, $this->balance->available_quantity);

        $movement = InventoryMovement::where('inventory_balance_id', $this->balance->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(InventoryMovementType::DAMAGE_ISOLATION, $movement->movement_type);
        $this->assertEquals(InventoryStockState::RESERVED, $movement->from_state);
        $this->assertEquals(InventoryStockState::DAMAGED, $movement->to_state);
        $this->assertEquals(5, $movement->quantity);
    }

    /**
     * 3. Insufficient Available Stock Fails
     */
    public function test_reporting_more_than_available_stock_fails_with_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/inventory-exceptions', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'exception_type' => StockExceptionType::DAMAGE->value,
            'source_stock_state' => InventoryStockState::AVAILABLE->value,
            'quantity' => 150, // Only 80 available
            'description' => 'Massive warehouse incident',
        ]);

        $response->assertSessionHasErrors(['quantity']);
        $this->assertEquals(0, StockException::count());
    }

    /**
     * 4. Resolution by Authorized User
     */
    public function test_authorized_user_can_resolve_stock_exception(): void
    {
        $exception = $this->exceptionService->reportException([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'exception_type' => StockExceptionType::DAMAGE,
            'quantity' => 5,
            'description' => 'Water damage on carton',
        ], $this->admin);

        $response = $this->actingAs($this->admin)->post("/admin/inventory-exceptions/{$exception->id}/resolve", [
            'resolution_notes' => 'Damaged cans safely scrapped and recycled with hazardous waste team.',
        ]);

        $response->assertRedirect();
        $exception->refresh();
        $this->assertEquals(StockExceptionStatus::RESOLVED, $exception->status);
        $this->assertEquals($this->admin->id, $exception->resolved_by);
        $this->assertNotNull($exception->resolved_at);
        $this->assertEquals('Damaged cans safely scrapped and recycled with hazardous waste team.', $exception->resolution_notes);
    }

    /**
     * 5. Security: Salesman (without inventory.adjust) Cannot Resolve or Dismiss Exceptions
     */
    public function test_salesman_cannot_resolve_or_dismiss_exceptions(): void
    {
        $exception = $this->exceptionService->reportException([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'exception_type' => StockExceptionType::DAMAGE,
            'quantity' => 5,
            'description' => 'Broken bottles during stacking',
        ], $this->admin);

        // Resolve attempt by salesman receives 403 Forbidden
        $response1 = $this->actingAs($this->salesman)->post("/admin/inventory-exceptions/{$exception->id}/resolve", [
            'resolution_notes' => 'Salesman resolving without authorization',
        ]);
        $response1->assertForbidden();

        // Dismiss attempt by salesman receives 403 Forbidden
        $response2 = $this->actingAs($this->salesman)->post("/admin/inventory-exceptions/{$exception->id}/dismiss", [
            'dismissal_reason' => 'Salesman dismissing without authorization',
        ]);
        $response2->assertForbidden();
    }

    /**
     * 6. Dismiss Exception and Revert Quarantine
     */
    public function test_authorized_user_can_dismiss_exception_and_revert_quarantine(): void
    {
        $exception = $this->exceptionService->reportException([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'exception_type' => StockExceptionType::OTHER,
            'source_stock_state' => InventoryStockState::AVAILABLE,
            'quantity' => 8,
            'description' => 'Suspected contamination, pending QA inspection',
        ], $this->admin);

        $this->balance->refresh();
        $this->assertEquals(8, $this->balance->damaged_quantity);
        $this->assertEquals(72, $this->balance->available_quantity);

        // QA cleared it: Dismiss with quarantine reversion
        $response = $this->actingAs($this->admin)->post("/admin/inventory-exceptions/{$exception->id}/dismiss", [
            'dismissal_reason' => 'QA lab tested negative for contamination. False alarm, safe for distribution.',
            'revert_quarantine' => true,
        ]);

        $response->assertRedirect();
        $exception->refresh();
        $this->assertEquals(StockExceptionStatus::DISMISSED, $exception->status);

        // Verify stock restored
        $this->balance->refresh();
        $this->assertEquals(0, $this->balance->damaged_quantity);
        $this->assertEquals(80, $this->balance->available_quantity);

        // Verify damage release movement
        $movements = InventoryMovement::where('inventory_balance_id', $this->balance->id)->orderBy('id', 'asc')->get();
        $this->assertCount(2, $movements);
        $releaseMov = $movements[1];
        $this->assertEquals(InventoryMovementType::DAMAGE_RELEASE, $releaseMov->movement_type);
        $this->assertEquals(InventoryStockState::DAMAGED, $releaseMov->from_state);
        $this->assertEquals(InventoryStockState::AVAILABLE, $releaseMov->to_state);
        $this->assertEquals(8, $releaseMov->quantity);
    }
}
