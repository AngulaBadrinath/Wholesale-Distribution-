<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentStatus;
use App\Enums\AllocationStatus;
use App\Enums\CategoryStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManualTestingSeeder extends Seeder
{
    /**
     * Run the manual testing database seeds.
     */
    public function run(): void
    {
        // 1. Mandatory Environment Safety Guard
        if (app()->environment('production')) {
            abort(403, 'ManualTestingSeeder is strictly prohibited in production environments.');
        }

        $defaultPassword = Hash::make('Password123!');

        // 2. Seed Non-Production Test Accounts
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin.qa@example.test'],
            [
                'name' => 'Super Administrator (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::SUPER_ADMIN,
                'status' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin.qa@example.test'],
            [
                'name' => 'Operations Admin (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::ADMIN,
                'status' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $accountant = User::updateOrCreate(
            ['email' => 'accountant.qa@example.test'],
            [
                'name' => 'Finance Accountant (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::ACCOUNTANT,
                'status' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $salesmanA = User::updateOrCreate(
            ['email' => 'salesman.a@example.test'],
            [
                'name' => 'Sales Representative North (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::SALESMAN,
                'status' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $salesmanB = User::updateOrCreate(
            ['email' => 'salesman.b@example.test'],
            [
                'name' => 'Sales Representative South (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::SALESMAN,
                'status' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $warehouseManager = User::updateOrCreate(
            ['email' => 'warehouse.qa@example.test'],
            [
                'name' => 'Warehouse Supervisor (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::WAREHOUSE_MANAGER,
                'status' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $driver = User::updateOrCreate(
            ['email' => 'driver.qa@example.test'],
            [
                'name' => 'Logistics Driver (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::DELIVERY_PARTNER,
                'status' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $suspendedUser = User::updateOrCreate(
            ['email' => 'suspended.qa@example.test'],
            [
                'name' => 'Suspended Staff (QA)',
                'password' => $defaultPassword,
                'role' => UserRole::SALESMAN,
                'status' => AccountStatus::SUSPENDED,
                'email_verified_at' => now(),
            ]
        );

        // 3. Seed / Resolve Default Warehouse
        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();
        if (! $warehouse) {
            $warehouse = Warehouse::create([
                'code' => 'WH-MAIN',
                'name' => 'Metropolitan Distribution Hub',
                'address_line1' => '100 Logistics Parkway, Suite 400',
                'city' => 'North Metropolis',
                'state' => 'NY',
                'postal_code' => '10001',
                'country_code' => 'US',
                'contact_name' => 'Warehouse Supervisor',
                'contact_phone' => '+1 (555) 900-1234',
                'contact_email' => 'warehouse.qa@example.test',
                'is_active' => true,
                'is_default' => true,
            ]);
        }

        // 4. Seed Tax Profiles
        $taxStandard = TaxProfile::updateOrCreate(
            ['code' => 'TAX-STD-10'],
            [
                'name' => 'Standard Wholesale Tax (10%)',
                'rate' => 10.0000,
                'description' => 'Standard rate applicable to general beverages and household supplies.',
                'status' => TaxProfileStatus::ACTIVE,
            ]
        );

        $taxReduced = TaxProfile::updateOrCreate(
            ['code' => 'TAX-RED-5'],
            [
                'name' => 'Essential Grocery Concession (5%)',
                'rate' => 5.0000,
                'description' => 'Reduced rate for staple dry grocery goods.',
                'status' => TaxProfileStatus::ACTIVE,
            ]
        );

        $taxExempt = TaxProfile::updateOrCreate(
            ['code' => 'TAX-EXEMPT-0'],
            [
                'name' => 'Tax Exempt Goods (0%)',
                'rate' => 0.0000,
                'description' => 'Zero-rated items.',
                'status' => TaxProfileStatus::ACTIVE,
            ]
        );

        // 5. Seed Product Categories
        $catGrocery = Category::updateOrCreate(
            ['code' => 'CAT-GROC'],
            [
                'name' => 'Grocery & Staples',
                'description' => 'Grains, oils, sugar, and baking supplies.',
                'sort_order' => 1,
                'status' => CategoryStatus::ACTIVE,
            ]
        );

        $catBeverages = Category::updateOrCreate(
            ['code' => 'CAT-BEV'],
            [
                'name' => 'Beverages',
                'description' => 'Juices, sodas, sparkling water, and energy drinks.',
                'sort_order' => 2,
                'status' => CategoryStatus::ACTIVE,
            ]
        );

        $catSnacks = Category::updateOrCreate(
            ['code' => 'CAT-SNAK'],
            [
                'name' => 'Snacks & Confectionery',
                'description' => 'Chips, cookies, chocolates, and packaged nuts.',
                'sort_order' => 3,
                'status' => CategoryStatus::ACTIVE,
            ]
        );

        $catHousehold = Category::updateOrCreate(
            ['code' => 'CAT-HOUS'],
            [
                'name' => 'Household & Cleaning',
                'description' => 'Detergents, disinfectants, and paper goods.',
                'sort_order' => 4,
                'status' => CategoryStatus::ACTIVE,
            ]
        );

        $catPersonalCare = Category::updateOrCreate(
            ['code' => 'CAT-CARE'],
            [
                'name' => 'Personal Care',
                'description' => 'Soaps, shampoos, and hygiene essentials.',
                'sort_order' => 5,
                'status' => CategoryStatus::ACTIVE,
            ]
        );

        // 6. Seed Representative Product Master
        $productsData = [
            [
                'sku' => 'BEV-ORG-001',
                'name' => 'Organic Orange Juice 1L (Case of 12)',
                'description' => '100% cold-pressed organic orange juice in recyclable glass bottles.',
                'category_id' => $catBeverages->id,
                'unit' => 'CASE',
                'cost_price' => 24.00,
                'minimum_allowed_price' => 28.00,
                'default_selling_price' => 34.50,
                'mrp' => 42.00,
                'tax_profile_id' => $taxStandard->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 450,
                'stock_reserved' => 50,
            ],
            [
                'sku' => 'BEV-SPK-002',
                'name' => 'Sparkling Mineral Water 500ml (Pack of 24)',
                'description' => 'Naturally carbonated spring mineral water.',
                'category_id' => $catBeverages->id,
                'unit' => 'PACK',
                'cost_price' => 12.50,
                'minimum_allowed_price' => 15.00,
                'default_selling_price' => 19.95,
                'mrp' => 24.00,
                'tax_profile_id' => $taxStandard->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 800,
                'stock_reserved' => 0,
            ],
            [
                'sku' => 'GROC-BAS-001',
                'name' => 'Premium Basmati Rice 10kg Bag',
                'description' => 'Aged extra-long grain aromatic basmati rice.',
                'category_id' => $catGrocery->id,
                'unit' => 'BAG',
                'cost_price' => 18.00,
                'minimum_allowed_price' => 20.00,
                'default_selling_price' => 24.50,
                'mrp' => 29.99,
                'tax_profile_id' => $taxReduced->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 300,
                'stock_reserved' => 20,
            ],
            [
                'sku' => 'GROC-OIL-002',
                'name' => 'Extra Virgin Olive Oil 5L Tin',
                'description' => 'First cold-pressed Mediterranean extra virgin olive oil.',
                'category_id' => $catGrocery->id,
                'unit' => 'TIN',
                'cost_price' => 38.00,
                'minimum_allowed_price' => 42.00,
                'default_selling_price' => 49.00,
                'mrp' => 59.95,
                'tax_profile_id' => $taxReduced->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 120,
                'stock_reserved' => 15,
            ],
            [
                'sku' => 'SNAK-ALM-001',
                'name' => 'Roasted Salted Almonds 1kg (Box of 6)',
                'description' => 'California almonds oven roasted and lightly salted.',
                'category_id' => $catSnacks->id,
                'unit' => 'BOX',
                'cost_price' => 45.00,
                'minimum_allowed_price' => 52.00,
                'default_selling_price' => 62.00,
                'mrp' => 74.50,
                'tax_profile_id' => $taxStandard->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 8, // Low Stock for testing
                'stock_reserved' => 0,
            ],
            [
                'sku' => 'SNAK-CHOC-002',
                'name' => 'Dark Chocolate Bars 85% (Display Box of 20)',
                'description' => 'Single-origin fair-trade dark chocolate bars.',
                'category_id' => $catSnacks->id,
                'unit' => 'BOX',
                'cost_price' => 22.00,
                'minimum_allowed_price' => 26.00,
                'default_selling_price' => 31.00,
                'mrp' => 38.00,
                'tax_profile_id' => $taxStandard->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 0, // Out of Stock for testing
                'stock_reserved' => 0,
            ],
            [
                'sku' => 'HOUS-DET-001',
                'name' => 'Eco Laundry Liquid Detergent 5L (Case of 2)',
                'description' => 'Plant-based biodegradable concentrated laundry detergent.',
                'category_id' => $catHousehold->id,
                'unit' => 'CASE',
                'cost_price' => 16.00,
                'minimum_allowed_price' => 19.50,
                'default_selling_price' => 25.00,
                'mrp' => 32.00,
                'tax_profile_id' => $taxStandard->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 240,
                'stock_reserved' => 10,
            ],
            [
                'sku' => 'CARE-SOP-001',
                'name' => 'Moisturizing Hand Soap 500ml (Pack of 12)',
                'description' => 'Antibacterial aloe vera gentle foaming hand soap.',
                'category_id' => $catPersonalCare->id,
                'unit' => 'PACK',
                'cost_price' => 14.00,
                'minimum_allowed_price' => 16.50,
                'default_selling_price' => 21.00,
                'mrp' => 26.50,
                'tax_profile_id' => $taxStandard->id,
                'status' => ProductStatus::ACTIVE,
                'stock_on_hand' => 180,
                'stock_reserved' => 0,
            ],
            [
                'sku' => 'GROC-DISC-009',
                'name' => 'Discontinued Spices Blend 500g',
                'description' => 'Legacy spice formula phased out in current catalogue.',
                'category_id' => $catGrocery->id,
                'unit' => 'PACK',
                'cost_price' => 5.00,
                'minimum_allowed_price' => 6.00,
                'default_selling_price' => 8.00,
                'mrp' => 12.00,
                'tax_profile_id' => $taxExempt->id,
                'status' => ProductStatus::INACTIVE, // Inactive product for validation testing
                'stock_on_hand' => 0,
                'stock_reserved' => 0,
            ],
        ];

        $seededProducts = [];
        foreach ($productsData as $pData) {
            $stockOnHand = $pData['stock_on_hand'];
            $stockReserved = $pData['stock_reserved'];
            unset($pData['stock_on_hand'], $pData['stock_reserved']);

            $product = Product::updateOrCreate(
                ['sku' => $pData['sku']],
                $pData
            );

            // Update inventory balance
            InventoryBalance::updateOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                ],
                [
                    'on_hand_quantity' => $stockOnHand,
                    'reserved_quantity' => $stockReserved,
                    'available_quantity' => max(0, $stockOnHand - $stockReserved),
                    'damaged_quantity' => 0,
                    'reorder_point' => 50,
                    'safety_stock' => 20,
                    'is_active' => true,
                    'version' => 1,
                    'last_counted_at' => now(),
                ]
            );

            $seededProducts[$product->sku] = $product;
        }

        // 7. Seed Synthetic Customer Accounts with Scoped Salesman Assignments
        $customersData = [
            // Salesman A Territory (North)
            [
                'code' => 'CUST-APEX-01',
                'name' => 'Apex Supermarket Group (North)',
                'contact_name' => 'David Miller',
                'email' => 'david.m@apex-retail.test',
                'phone' => '+1 (555) 234-5678',
                'salesman_id' => $salesmanA->id,
                'status' => CustomerStatus::ACTIVE,
                'credit_limit' => 50000.00,
                'payment_terms' => PaymentTerms::NET_30,
                'billing_address_line1' => '101 Commerce Boulevard',
                'billing_city' => 'North Metropolis',
                'billing_state' => 'NY',
                'billing_postal_code' => '10001',
                'billing_country' => 'US',
                'shipping_address_line1' => 'Warehouse Bay 4, 101 Commerce Blvd',
                'shipping_city' => 'North Metropolis',
                'shipping_state' => 'NY',
                'shipping_postal_code' => '10001',
                'shipping_country' => 'US',
            ],
            [
                'code' => 'CUST-BEAC-02',
                'name' => 'Beacon Gourmet & Deli',
                'contact_name' => 'Sarah Jenkins',
                'email' => 'sarah@beacon-deli.test',
                'phone' => '+1 (555) 345-6789',
                'salesman_id' => $salesmanA->id,
                'status' => CustomerStatus::ACTIVE,
                'credit_limit' => 15000.00,
                'payment_terms' => PaymentTerms::NET_15,
                'billing_address_line1' => '45 Market Square',
                'billing_city' => 'North Metropolis',
                'billing_state' => 'NY',
                'billing_postal_code' => '10002',
                'billing_country' => 'US',
                'shipping_address_line1' => '45 Market Square, Rear Dock',
                'shipping_city' => 'North Metropolis',
                'shipping_state' => 'NY',
                'shipping_postal_code' => '10002',
                'shipping_country' => 'US',
            ],
            [
                'code' => 'CUST-ECHO-05',
                'name' => 'Echo Corner Grocers (Inactive)',
                'contact_name' => 'Frank Miller',
                'email' => 'frank@echo-grocers.test',
                'phone' => '+1 (555) 678-9012',
                'salesman_id' => $salesmanA->id,
                'status' => CustomerStatus::INACTIVE, // Inactive customer
                'credit_limit' => 5000.00,
                'payment_terms' => PaymentTerms::COD,
                'billing_address_line1' => '88 Old Pine Road',
                'billing_city' => 'North Metropolis',
                'billing_state' => 'NY',
                'billing_postal_code' => '10005',
                'billing_country' => 'US',
                'shipping_address_line1' => '88 Old Pine Road',
                'shipping_city' => 'North Metropolis',
                'shipping_state' => 'NY',
                'shipping_postal_code' => '10005',
                'shipping_country' => 'US',
            ],

            // Salesman B Territory (South)
            [
                'code' => 'CUST-CRST-03',
                'name' => 'Crestline Wholesale Mart (South)',
                'contact_name' => 'Marcus Vance',
                'email' => 'm.vance@crestline-mart.test',
                'phone' => '+1 (555) 456-7890',
                'salesman_id' => $salesmanB->id,
                'status' => CustomerStatus::ACTIVE,
                'credit_limit' => 75000.00,
                'payment_terms' => PaymentTerms::NET_30,
                'billing_address_line1' => '700 Harbor View Way',
                'billing_city' => 'South Bay Harbor',
                'billing_state' => 'NJ',
                'billing_postal_code' => '07001',
                'billing_country' => 'US',
                'shipping_address_line1' => 'Dock 12, 700 Harbor View Way',
                'shipping_city' => 'South Bay Harbor',
                'shipping_state' => 'NJ',
                'shipping_postal_code' => '07001',
                'shipping_country' => 'US',
            ],
            [
                'code' => 'CUST-DLTA-04',
                'name' => 'Delta Convenience Stores (On Hold)',
                'contact_name' => 'Elena Rostova',
                'email' => 'elena@delta-stores.test',
                'phone' => '+1 (555) 567-8901',
                'salesman_id' => $salesmanB->id,
                'status' => CustomerStatus::ON_HOLD, // On-hold customer
                'credit_limit' => 10000.00,
                'payment_terms' => PaymentTerms::DUE_ON_RECEIPT,
                'billing_address_line1' => '220 Industrial Parkway',
                'billing_city' => 'South Bay Harbor',
                'billing_state' => 'NJ',
                'billing_postal_code' => '07004',
                'billing_country' => 'US',
                'shipping_address_line1' => '220 Industrial Parkway',
                'shipping_city' => 'South Bay Harbor',
                'shipping_state' => 'NJ',
                'shipping_postal_code' => '07004',
                'shipping_country' => 'US',
            ],
        ];

        $seededCustomers = [];
        foreach ($customersData as $cData) {
            $customer = Customer::updateOrCreate(
                ['code' => $cData['code']],
                $cData
            );
            $seededCustomers[$customer->code] = $customer;
        }

        // 8. Seed Representative Orders & Deliveries
        $custApex = $seededCustomers['CUST-APEX-01'];
        $prodJuice = $seededProducts['BEV-ORG-001'];
        $prodRice = $seededProducts['GROC-BAS-001'];

        // Order 1: Completed Historical Order
        $orderCompleted = Order::updateOrCreate(
            ['order_number' => 'ORD-2026-0001'],
            [
                'customer_id' => $custApex->id,
                'salesman_id' => $salesmanA->id,
                'created_by' => $salesmanA->id,
                'status' => OrderStatus::COMPLETED,
                'fulfillment_status' => FulfillmentStatus::DISPATCHED,
                'payment_status' => PaymentStatus::PAID,
                'delivery_status' => DeliveryStatus::DELIVERED,
                'adjustment_status' => AdjustmentStatus::NONE,
                'currency' => 'USD',
                'subtotal' => 590.00,
                'tax_total' => 54.00,
                'adjustment_total' => 0.00,
                'grand_total' => 644.00,
                'notes' => 'Weekly replenishment order for store #101.',
                'idempotency_key' => 'idemp-seed-ord-0001',
                'submitted_at' => Carbon::now()->subDays(5),
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderCompleted->id, 'product_id' => $prodJuice->id],
            [
                'product_name_snapshot' => $prodJuice->name,
                'sku_snapshot' => $prodJuice->sku,
                'unit_snapshot' => $prodJuice->unit,
                'tax_profile_code_snapshot' => $taxStandard->code,
                'tax_profile_name_snapshot' => $taxStandard->name,
                'tax_rate_snapshot' => 10.00,
                'ordered_quantity' => 10,
                'cancelled_quantity' => 0,
                'reserved_quantity' => 10,
                'picked_quantity' => 10,
                'dispatched_quantity' => 10,
                'delivered_quantity' => 10,
                'unit_price' => 34.50,
                'tax_profile_id' => $prodJuice->tax_profile_id,
                'taxable_amount' => 345.00,
                'tax_amount' => 34.50,
                'line_total' => 379.50,
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderCompleted->id, 'product_id' => $prodRice->id],
            [
                'product_name_snapshot' => $prodRice->name,
                'sku_snapshot' => $prodRice->sku,
                'unit_snapshot' => $prodRice->unit,
                'tax_profile_code_snapshot' => $taxReduced->code,
                'tax_profile_name_snapshot' => $taxReduced->name,
                'tax_rate_snapshot' => 5.00,
                'ordered_quantity' => 10,
                'cancelled_quantity' => 0,
                'reserved_quantity' => 10,
                'picked_quantity' => 10,
                'dispatched_quantity' => 10,
                'delivered_quantity' => 10,
                'unit_price' => 24.50,
                'tax_profile_id' => $prodRice->tax_profile_id,
                'taxable_amount' => 245.00,
                'tax_amount' => 12.25,
                'line_total' => 257.25,
            ]
        );

        // Ensure Invoice exists for Order 1
        if (! \App\Models\Invoice::where('order_id', $orderCompleted->id)->exists()) {
            app(\App\Services\Invoices\InvoiceGeneratorService::class)->generateInvoiceForOrder($orderCompleted, $accountant);
        }

        // Seed Payment for Order 1
        Payment::updateOrCreate(
            ['payment_number' => 'PAY-2026-0001'],
            [
                'customer_id' => $custApex->id,
                'order_id' => $orderCompleted->id,
                'recorded_by' => $salesmanA->id,
                'verified_by' => $accountant->id,
                'amount' => 644.00,
                'payment_method' => PaymentMethod::CASH,
                'status' => PaymentTransactionStatus::VERIFIED,
                'payment_date' => Carbon::now()->subDays(4),
                'verified_at' => Carbon::now()->subDays(4),
                'notes' => 'Cash received upon dock handover.',
            ]
        );

        // Order 2: Out for Delivery Active Route
        $orderInTransit = Order::updateOrCreate(
            ['order_number' => 'ORD-2026-0002'],
            [
                'customer_id' => $custApex->id,
                'salesman_id' => $salesmanA->id,
                'created_by' => $salesmanA->id,
                'status' => OrderStatus::PROCESSING,
                'fulfillment_status' => FulfillmentStatus::DISPATCHED,
                'payment_status' => PaymentStatus::UNPAID,
                'delivery_status' => DeliveryStatus::OUT_FOR_DELIVERY,
                'adjustment_status' => AdjustmentStatus::NONE,
                'currency' => 'USD',
                'subtotal' => 345.00,
                'tax_total' => 34.50,
                'adjustment_total' => 0.00,
                'grand_total' => 379.50,
                'notes' => 'Priority morning delivery required.',
                'idempotency_key' => 'idemp-seed-ord-0002',
                'submitted_at' => Carbon::now()->subHours(4),
            ]
        );

        $orderInTransitItem = OrderItem::updateOrCreate(
            ['order_id' => $orderInTransit->id, 'product_id' => $prodJuice->id],
            [
                'product_name_snapshot' => $prodJuice->name,
                'sku_snapshot' => $prodJuice->sku,
                'unit_snapshot' => $prodJuice->unit,
                'tax_profile_code_snapshot' => $taxStandard->code,
                'tax_profile_name_snapshot' => $taxStandard->name,
                'tax_rate_snapshot' => 10.00,
                'ordered_quantity' => 10,
                'cancelled_quantity' => 0,
                'reserved_quantity' => 10,
                'picked_quantity' => 10,
                'dispatched_quantity' => 10,
                'delivered_quantity' => 0,
                'unit_price' => 34.50,
                'tax_profile_id' => $prodJuice->tax_profile_id,
                'taxable_amount' => 345.00,
                'tax_amount' => 34.50,
                'line_total' => 379.50,
            ]
        );

        // Seed Delivery Run for Driver
        $delivery = Delivery::updateOrCreate(
            ['delivery_number' => 'DEL-2026-0001'],
            [
                'order_id' => $orderInTransit->id,
                'customer_id' => $custApex->id,
                'driver_id' => $driver->id,
                'created_by' => $warehouseManager->id,
                'status' => DeliveryStatus::OUT_FOR_DELIVERY,
                'scheduled_date' => Carbon::today(),
                'delivery_contact_name' => 'David Miller',
                'delivery_contact_phone' => '+1 (555) 234-5678',
                'delivery_address_line1' => 'Warehouse Bay 4, 101 Commerce Blvd',
                'delivery_city' => 'North Metropolis',
                'delivery_state' => 'NY',
                'delivery_postal_code' => '10001',
                'delivery_country_code' => 'US',
                'assigned_at' => Carbon::now()->subHours(3),
                'out_for_delivery_at' => Carbon::now()->subHours(2),
                'driver_instructions' => 'Call ahead 15 minutes before arrival at rear dock.',
            ]
        );

        $allocation = OrderItemAllocation::updateOrCreate(
            ['allocation_number' => 'ALC-2026-0001'],
            [
                'order_id' => $orderInTransit->id,
                'order_item_id' => $orderInTransitItem->id,
                'product_id' => $prodJuice->id,
                'allocated_quantity' => 10,
                'reserved_quantity' => 10,
                'picked_quantity' => 10,
                'dispatched_quantity' => 10,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => AllocationStatus::DISPATCHED,
                'warehouse_code' => $warehouse->code,
                'allocated_by' => $warehouseManager->id,
                'allocated_at' => Carbon::now()->subHours(3),
            ]
        );

        DeliveryItem::updateOrCreate(
            [
                'delivery_id' => $delivery->id,
                'product_id' => $prodJuice->id,
            ],
            [
                'order_item_id' => $orderInTransitItem->id,
                'order_item_allocation_id' => $allocation->id,
                'product_name_snapshot' => $prodJuice->name,
                'sku_snapshot' => $prodJuice->sku,
                'deliverable_quantity' => 10,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
            ]
        );

        // Order 3: Active Working Draft for Salesman A
        $orderDraft = Order::updateOrCreate(
            ['order_number' => 'DFT-2026-0001'],
            [
                'customer_id' => $custApex->id,
                'salesman_id' => $salesmanA->id,
                'created_by' => $salesmanA->id,
                'status' => OrderStatus::DRAFT,
                'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
                'payment_status' => PaymentStatus::UNPAID,
                'delivery_status' => DeliveryStatus::PENDING_ASSIGNMENT,
                'adjustment_status' => AdjustmentStatus::NONE,
                'currency' => 'USD',
                'subtotal' => 103.50,
                'tax_total' => 10.35,
                'adjustment_total' => 0.00,
                'grand_total' => 113.85,
                'notes' => 'Draft order in progress for next week replenishment.',
                'idempotency_key' => 'idemp-seed-draft-0001',
                'draft_token' => 'a1b2c3d4-e5f6-4a8b-9c0d-1e2f3a4b5c6d',
                'version' => 1,
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderDraft->id, 'product_id' => $prodJuice->id],
            [
                'product_name_snapshot' => $prodJuice->name,
                'sku_snapshot' => $prodJuice->sku,
                'unit_snapshot' => $prodJuice->unit,
                'tax_profile_code_snapshot' => $taxStandard->code,
                'tax_profile_name_snapshot' => $taxStandard->name,
                'tax_rate_snapshot' => 10.00,
                'ordered_quantity' => 3,
                'cancelled_quantity' => 0,
                'reserved_quantity' => 0,
                'picked_quantity' => 0,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'unit_price' => 34.50,
                'tax_profile_id' => $prodJuice->tax_profile_id,
                'taxable_amount' => 103.50,
                'tax_amount' => 10.35,
                'line_total' => 113.85,
            ]
        );

        // Authoritatively synchronize all seeded demo business events with General Ledger
        app(\App\Services\Accounting\JournalMappingService::class)->syncUnpostedHistoricalEvents($superAdmin);
    }
}
