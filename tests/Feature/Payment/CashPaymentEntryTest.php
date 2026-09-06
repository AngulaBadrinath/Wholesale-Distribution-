<?php

namespace Tests\Feature\Payment;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashPaymentEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected User $salesman1;
    protected User $salesman2;
    protected User $warehouseManager;
    protected Customer $customer1;
    protected Customer $customer2;
    protected Customer $inactiveCustomer;
    protected Order $order1;
    protected Order $draftOrder;
    protected Order $cancelledOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Arthur Accountant',
            'email' => 'accountant@wholesale.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman1 = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'salesman1@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman2 = User::factory()->create([
            'name' => 'Sally Salesman',
            'email' => 'salesman2@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouseManager = User::factory()->create([
            'name' => 'Wendy Warehouse',
            'email' => 'warehouse@wholesale.test',
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer1 = Customer::create([
            'salesman_id' => $this->salesman1->id,
            'name' => 'Metro Grocery',
            'code' => 'CUST-METRO',
            'contact_name' => 'Mark Manager',
            'phone' => '+1-555-0201',
            'email' => 'metro@wholesale.test',
            'billing_address_line1' => '100 Metro Way',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->customer2 = Customer::create([
            'salesman_id' => $this->salesman2->id,
            'name' => 'Gotham Supermarket',
            'code' => 'CUST-GOTHAM',
            'contact_name' => 'Gary Grocer',
            'phone' => '+1-555-0202',
            'email' => 'gotham@wholesale.test',
            'billing_address_line1' => '200 Gotham Ave',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10002',
            'billing_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->inactiveCustomer = Customer::create([
            'salesman_id' => $this->salesman1->id,
            'name' => 'Defunct Bodega',
            'code' => 'CUST-DEFUNCT',
            'contact_name' => 'Dan Defunct',
            'phone' => '+1-555-0203',
            'email' => 'defunct@wholesale.test',
            'billing_address_line1' => '300 Old St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10003',
            'billing_country' => 'USA',
            'status' => CustomerStatus::INACTIVE,
        ]);

        $this->order1 = Order::create([
            'order_number' => 'ORD-2026-000101',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => \App\Enums\FulfillmentStatus::UNALLOCATED,
            'payment_status' => \App\Enums\PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => \App\Enums\AdjustmentStatus::NONE,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-cash-ord-1',
            'subtotal' => '1000.00',
            'tax_total' => '100.00',
            'adjustment_total' => '0.00',
            'grand_total' => '1100.00',
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $this->draftOrder = Order::create([
            'order_number' => 'ORD-DRAFT-001',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::DRAFT,
            'fulfillment_status' => \App\Enums\FulfillmentStatus::UNALLOCATED,
            'payment_status' => \App\Enums\PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => \App\Enums\AdjustmentStatus::NONE,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-draft-1',
            'subtotal' => '500.00',
            'tax_total' => '50.00',
            'adjustment_total' => '0.00',
            'grand_total' => '550.00',
            'version' => 1,
        ]);

        $this->cancelledOrder = Order::create([
            'order_number' => 'ORD-CANC-001',
            'customer_id' => $this->customer1->id,
            'salesman_id' => $this->salesman1->id,
            'created_by' => $this->salesman1->id,
            'status' => OrderStatus::CANCELLED,
            'fulfillment_status' => \App\Enums\FulfillmentStatus::UNALLOCATED,
            'payment_status' => \App\Enums\PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => \App\Enums\AdjustmentStatus::NONE,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-canc-1',
            'subtotal' => '500.00',
            'tax_total' => '50.00',
            'adjustment_total' => '0.00',
            'grand_total' => '550.00',
            'version' => 1,
            'submitted_at' => now(),
            'cancelled_at' => now(),
        ]);
    }

    public function test_admin_can_record_cash_payment(): void
    {
        $payload = [
            'customer_id' => $this->customer1->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'receipt_reference' => 'RCPT-CASH-101',
            'notes' => 'Cash received at warehouse counter',
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'payment' => [
                'id',
                'payment_number',
                'amount',
                'status',
                'payment_method',
            ],
        ]);

        $this->assertDatabaseHas('payments', [
            'customer_id' => $this->customer1->id,
            'payment_method' => 'CASH',
            'status' => 'PENDING_VERIFICATION',
            'amount' => '500.00',
            'recorded_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_record_cash_payment_linked_to_order(): void
    {
        $payload = [
            'customer_id' => $this->customer1->id,
            'order_id' => $this->order1->id,
            'amount' => 1100.00,
            'payment_date' => now()->toDateString(),
            'receipt_reference' => 'RCPT-FULL-ORD',
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('payments', [
            'customer_id' => $this->customer1->id,
            'order_id' => $this->order1->id,
            'payment_method' => 'CASH',
            'amount' => '1100.00',
        ]);
    }

    public function test_salesman_can_record_cash_payment_for_assigned_customer(): void
    {
        $payload = [
            'customer_id' => $this->customer1->id,
            'amount' => 250.00,
            'payment_date' => now()->toDateString(),
            'receipt_reference' => 'SALES-RCPT-01',
        ];

        $response = $this->actingAs($this->salesman1)->postJson(route('salesman.payments.cash.store'), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('payments', [
            'customer_id' => $this->customer1->id,
            'payment_method' => 'CASH',
            'recorded_by' => $this->salesman1->id,
        ]);
    }

    public function test_salesman_cannot_record_cash_payment_for_unassigned_customer_anti_idor(): void
    {
        // Salesman 1 attempting to record cash payment for customer 2 (assigned to Salesman 2)
        $payload = [
            'customer_id' => $this->customer2->id,
            'amount' => 300.00,
            'payment_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->salesman1)->postJson(route('salesman.payments.cash.store'), $payload);

        $response->assertStatus(403);
    }

    public function test_cannot_record_payment_for_inactive_customer(): void
    {
        $payload = [
            'customer_id' => $this->inactiveCustomer->id,
            'amount' => 100.00,
            'payment_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_id');
    }

    public function test_cannot_link_payment_to_order_belonging_to_another_customer(): void
    {
        $payload = [
            'customer_id' => $this->customer2->id, // Mismatched customer with order1 (customer1)
            'order_id' => $this->order1->id,
            'amount' => 100.00,
            'payment_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('order_id');
    }

    public function test_cannot_link_payment_to_draft_order(): void
    {
        $payload = [
            'customer_id' => $this->customer1->id,
            'order_id' => $this->draftOrder->id,
            'amount' => 100.00,
            'payment_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('order_id');
    }

    public function test_cannot_link_payment_to_cancelled_order(): void
    {
        $payload = [
            'customer_id' => $this->customer1->id,
            'order_id' => $this->cancelledOrder->id,
            'amount' => 100.00,
            'payment_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('order_id');
    }

    public function test_cannot_record_payment_with_zero_or_negative_amount(): void
    {
        $payloadZero = [
            'customer_id' => $this->customer1->id,
            'amount' => 0.00,
            'payment_date' => now()->toDateString(),
        ];

        $responseZero = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payloadZero);
        $responseZero->assertStatus(422);
        $responseZero->assertJsonValidationErrors('amount');

        $payloadNeg = [
            'customer_id' => $this->customer1->id,
            'amount' => -50.00,
            'payment_date' => now()->toDateString(),
        ];

        $responseNeg = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payloadNeg);
        $responseNeg->assertStatus(422);
        $responseNeg->assertJsonValidationErrors('amount');
    }

    public function test_cannot_record_payment_with_future_date(): void
    {
        $payload = [
            'customer_id' => $this->customer1->id,
            'amount' => 100.00,
            'payment_date' => now()->addDays(2)->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payment_date');
    }

    public function test_unauthorized_role_cannot_record_payment(): void
    {
        $payload = [
            'customer_id' => $this->customer1->id,
            'amount' => 100.00,
            'payment_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->warehouseManager)->postJson(route('admin.payments.cash.store'), $payload);

        $response->assertStatus(403);
    }
}
