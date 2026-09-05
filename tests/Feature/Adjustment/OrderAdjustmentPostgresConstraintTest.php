<?php

namespace Tests\Feature\Adjustment;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentReasonCode;
use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderAdjustmentPostgresConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Postgres Admin',
            'email' => 'pg_admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Postgres Salesman',
            'email' => 'pg_sales@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Postgres Mart',
            'code' => 'CUST-PG-01',
            'contact_name' => 'Perry Postgres',
            'phone' => '+1-555-9999',
            'email' => 'pg@wholesale.test',
            'billing_address_line1' => '999 Database Rd',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '999 Database Rd',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => 20000.00,
        ]);

        $this->category = Category::create([
            'name' => 'Grains',
            'code' => 'GRAINS',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Zero Tax',
            'code' => 'ZERO-TAX',
            'rate' => 0.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'name' => 'Barley 20kg',
            'sku' => 'SKU-BARLEY-20',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 10.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 15.00,
            'mrp' => 18.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BAG',
        ]);
    }

    protected function createOrder(int $quantity = 10): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-PG-' . uniqid(),
            'idempotency_key' => 'idemp-pg-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => $quantity * 15.00,
            'tax_total' => 0.00,
            'grand_total' => $quantity * 15.00,
            'submitted_at' => Carbon::now()->subHours(2),
            'approved_at' => Carbon::now()->subHour(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => $quantity,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => 15.00,
            'is_price_overridden' => false,
            'tax_rate_snapshot' => 0.00,
            'taxable_amount' => $quantity * 15.00,
            'tax_amount' => 0.00,
            'line_total' => $quantity * 15.00,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * Test partial unique index idx_order_adjustments_single_open.
     * Prevents multiple SUBMITTED requests for the same order_id directly in the database.
     */
    public function test_postgresql_partial_unique_index_single_open_request(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL partial unique index tests run only on pgsql.');
        }

        $order = $this->createOrder();

        // 1st SUBMITTED adjustment
        DB::table('order_adjustments')->insert([
            'adjustment_number' => 'ADJ-RAW-01',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => 150.00,
            'order_tax_total_snapshot' => 0.00,
            'order_grand_total_snapshot' => 150.00,
            'status' => 'SUBMITTED',
            'reason_code' => 'CUSTOMER_REQUEST',
            'request_fingerprint' => hash('sha256', 'fingerprint1'),
            'idempotency_key' => 'key-1',
            'requested_by' => $this->admin->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2nd SUBMITTED adjustment directly via SQL bypasses application logic
        try {
            DB::table('order_adjustments')->insert([
                'adjustment_number' => 'ADJ-RAW-02',
                'order_id' => $order->id,
                'order_number_snapshot' => $order->order_number,
                'order_version_snapshot' => 1,
                'order_status_snapshot' => 'APPROVED',
                'order_subtotal_snapshot' => 150.00,
                'order_tax_total_snapshot' => 0.00,
                'order_grand_total_snapshot' => 150.00,
                'status' => 'SUBMITTED', // duplicate open status!
                'reason_code' => 'WAREHOUSE_DAMAGE',
                'request_fingerprint' => hash('sha256', 'fingerprint2'),
                'idempotency_key' => 'key-2',
                'requested_by' => $this->admin->id,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('PostgreSQL partial unique index idx_order_adjustments_single_open did not reject duplicate SUBMITTED row.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('idx_order_adjustments_single_open', $e->getMessage());
        }
    }

    /**
     * Test unique constraint on adjustment_number and idempotency_key.
     */
    public function test_postgresql_unique_constraints_on_adjustment_number_and_idempotency_key(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL unique constraint tests run only on pgsql.');
        }

        $order = $this->createOrder();

        DB::table('order_adjustments')->insert([
            'adjustment_number' => 'ADJ-UNIQUE-01',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => 150.00,
            'order_tax_total_snapshot' => 0.00,
            'order_grand_total_snapshot' => 150.00,
            'status' => 'CANCELLED',
            'reason_code' => 'CUSTOMER_REQUEST',
            'request_fingerprint' => hash('sha256', 'fp-uniq'),
            'idempotency_key' => 'idemp-uniq-key',
            'requested_by' => $this->admin->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplicate adjustment_number must fail
        try {
            DB::table('order_adjustments')->insert([
                'adjustment_number' => 'ADJ-UNIQUE-01', // duplicate!
                'order_id' => $order->id,
                'order_number_snapshot' => $order->order_number,
                'order_version_snapshot' => 1,
                'order_status_snapshot' => 'APPROVED',
                'order_subtotal_snapshot' => 150.00,
                'order_tax_total_snapshot' => 0.00,
                'order_grand_total_snapshot' => 150.00,
                'status' => 'CANCELLED',
                'reason_code' => 'CUSTOMER_REQUEST',
                'request_fingerprint' => hash('sha256', 'fp-diff'),
                'idempotency_key' => 'idemp-diff-key',
                'requested_by' => $this->admin->id,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Unique constraint on adjustment_number did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_adjustments_adjustment_number_unique', $e->getMessage());
        }

        // Duplicate idempotency_key must fail
        try {
            DB::table('order_adjustments')->insert([
                'adjustment_number' => 'ADJ-UNIQUE-02',
                'order_id' => $order->id,
                'order_number_snapshot' => $order->order_number,
                'order_version_snapshot' => 1,
                'order_status_snapshot' => 'APPROVED',
                'order_subtotal_snapshot' => 150.00,
                'order_tax_total_snapshot' => 0.00,
                'order_grand_total_snapshot' => 150.00,
                'status' => 'CANCELLED',
                'reason_code' => 'CUSTOMER_REQUEST',
                'request_fingerprint' => hash('sha256', 'fp-diff2'),
                'idempotency_key' => 'idemp-uniq-key', // duplicate!
                'requested_by' => $this->admin->id,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Unique constraint on idempotency_key did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_adjustments_idempotency_key_unique', $e->getMessage());
        }
    }

    /**
     * Test CHECK constraints on quantity reductions and financial reductions.
     */
    public function test_postgresql_check_constraints_on_quantities_and_amounts(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL check constraint tests run only on pgsql.');
        }

        $order = $this->createOrder();
        $item = $order->items->first();

        // 1. Negative projected subtotal reduction on order_adjustments must fail
        try {
            DB::table('order_adjustments')->insert([
                'adjustment_number' => 'ADJ-CHK-01',
                'order_id' => $order->id,
                'order_number_snapshot' => $order->order_number,
                'order_version_snapshot' => 1,
                'order_status_snapshot' => 'APPROVED',
                'order_subtotal_snapshot' => 150.00,
                'order_tax_total_snapshot' => 0.00,
                'order_grand_total_snapshot' => 150.00,
                'projected_subtotal_reduction' => -10.00, // VIOLATION!
                'status' => 'CANCELLED',
                'reason_code' => 'CUSTOMER_REQUEST',
                'request_fingerprint' => hash('sha256', 'chk-fp'),
                'idempotency_key' => 'chk-key-1',
                'requested_by' => $this->admin->id,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('CHECK constraint order_adjustments_projected_subtotal_reduction_check did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_adjustments_projected_subtotal_reduction_check', $e->getMessage());
        }

        // Insert valid parent adjustment
        $adjId = DB::table('order_adjustments')->insertGetId([
            'adjustment_number' => 'ADJ-CHK-VALID',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => 150.00,
            'order_tax_total_snapshot' => 0.00,
            'order_grand_total_snapshot' => 150.00,
            'projected_subtotal_reduction' => 15.00,
            'status' => 'CANCELLED',
            'reason_code' => 'CUSTOMER_REQUEST',
            'request_fingerprint' => hash('sha256', 'chk-valid-fp'),
            'idempotency_key' => 'chk-valid-key',
            'requested_by' => $this->admin->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. requested_quantity_reduction <= 0 on order_adjustment_items must fail
        try {
            DB::table('order_adjustment_items')->insert([
                'adjustment_id' => $adjId,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name_snapshot' => $item->product_name_snapshot,
                'sku_snapshot' => $item->sku_snapshot,
                'unit_price_snapshot' => $item->unit_price,
                'tax_rate_snapshot' => 0.00,
                'ordered_quantity_snapshot' => 10,
                'cancelled_quantity_snapshot' => 0,
                'fulfillable_quantity_snapshot' => 10,
                'allocated_quantity_snapshot' => 0,
                'unallocated_quantity_snapshot' => 10,
                'requested_quantity_reduction' => 0, // VIOLATION! Must be > 0
                'affected_allocation_quantity' => 0,
                'projected_fulfillable_quantity' => 10,
                'projected_cancelled_quantity' => 0,
                'projected_taxable_amount_reduction' => 0.00,
                'projected_tax_amount_reduction' => 0.00,
                'projected_line_total_reduction' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('CHECK constraint order_adj_items_requested_qty_check did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_adj_items_requested_qty_check', $e->getMessage());
        }
    }

    /**
     * Test non-destructive foreign keys (RESTRICT on delete).
     */
    public function test_postgresql_non_destructive_foreign_keys_prevent_cascading_loss(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL foreign key restriction tests run only on pgsql.');
        }

        $order = $this->createOrder();
        $item = $order->items->first();

        $adjId = DB::table('order_adjustments')->insertGetId([
            'adjustment_number' => 'ADJ-FK-01',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => 150.00,
            'order_tax_total_snapshot' => 0.00,
            'order_grand_total_snapshot' => 150.00,
            'status' => 'SUBMITTED',
            'reason_code' => 'CUSTOMER_REQUEST',
            'request_fingerprint' => hash('sha256', 'fk-fp'),
            'idempotency_key' => 'fk-key-1',
            'requested_by' => $this->admin->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_adjustment_items')->insert([
            'adjustment_id' => $adjId,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_name_snapshot' => $item->product_name_snapshot,
            'sku_snapshot' => $item->sku_snapshot,
            'unit_price_snapshot' => $item->unit_price,
            'tax_rate_snapshot' => 0.00,
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 10,
            'requested_quantity_reduction' => 2,
            'affected_allocation_quantity' => 0,
            'projected_fulfillable_quantity' => 8,
            'projected_cancelled_quantity' => 2,
            'projected_taxable_amount_reduction' => 30.00,
            'projected_tax_amount_reduction' => 0.00,
            'projected_line_total_reduction' => 30.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting to delete the parent OrderAdjustment directly must fail due to RESTRICT on order_adjustment_items
        try {
            DB::table('order_adjustments')->where('id', $adjId)->delete();
            $this->fail('RESTRICT on delete on order_adjustment_items.adjustment_id did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key constraint', strtolower($e->getMessage()));
        }
    }
}
