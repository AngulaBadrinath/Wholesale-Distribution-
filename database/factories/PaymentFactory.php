<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentReversalReason;
use App\Enums\PaymentTransactionStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_number' => 'PAY-' . strtoupper(Str::random(10)),
            'customer_id' => fn () => Customer::first()?->id ?? Customer::create([
                'salesman_id' => User::factory()->salesman()->create()->id,
                'name' => 'Acme Corporation',
                'code' => 'CUST-' . strtoupper(Str::random(6)),
                'contact_name' => fake()->name(),
                'phone' => '+1-555-0100',
                'email' => fake()->unique()->safeEmail(),
                'billing_address_line1' => '100 Main St',
                'billing_city' => 'Metropolis',
                'billing_state' => 'NY',
                'billing_postal_code' => '10001',
                'billing_country' => 'USA',
                'status' => \App\Enums\CustomerStatus::ACTIVE,
            ])->id,
            'order_id' => null,
            'payment_method' => PaymentMethod::CASH,
            'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
            'amount' => 1500.00,
            'payment_date' => now()->toDateString(),
            'notes' => 'Test payment transaction',
            'recorded_by' => fn () => User::factory()->salesman()->create()->id,
            'version' => 1,
        ];
    }

    /**
     * Set payment method to CASH.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::CASH,
            'receipt_reference' => 'RCPT-' . fake()->numerify('#####'),
        ]);
    }

    /**
     * Set payment method to CHEQUE.
     */
    public function cheque(?string $bankName = null, ?string $chequeNumber = null): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::CHEQUE,
            'bank_name' => $bankName ?? fake()->company() . ' Bank',
            'cheque_number' => $chequeNumber ?? (string) fake()->numberBetween(100000, 999999),
            'cheque_date' => now()->toDateString(),
            'evidence_object_key' => 'payments/2026/09/' . Str::uuid() . '.jpg',
            'evidence_original_name' => 'cheque_scan.jpg',
            'evidence_mime_type' => 'image/jpeg',
            'evidence_size_bytes' => 102400,
            'evidence_uploaded_at' => now(),
        ]);
    }

    /**
     * Set payment method to MONEY_ORDER.
     */
    public function moneyOrder(?string $issuerName = null, ?string $moNumber = null): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::MONEY_ORDER,
            'issuer_name' => $issuerName ?? 'Postal Services',
            'money_order_number' => $moNumber ?? 'MO-' . fake()->numerify('########'),
            'evidence_object_key' => 'payments/2026/09/' . Str::uuid() . '.jpg',
            'evidence_original_name' => 'money_order_receipt.jpg',
            'evidence_mime_type' => 'image/jpeg',
            'evidence_size_bytes' => 102400,
            'evidence_uploaded_at' => now(),
        ]);
    }

    /**
     * Indicate that payment is VERIFIED.
     */
    public function verified(?User $verifier = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::VERIFIED,
            'verified_by' => $verifier?->id ?? User::factory()->admin(),
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate that payment is REJECTED.
     */
    public function rejected(?User $rejector = null, PaymentRejectionReason $reason = PaymentRejectionReason::ILLEGIBLE_EVIDENCE, string $notes = 'Evidence image is blurred'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::REJECTED,
            'rejected_by' => $rejector?->id ?? User::factory()->admin(),
            'rejection_reason_code' => $reason,
            'rejection_notes' => $notes,
            'rejected_at' => now(),
        ]);
    }

    /**
     * Indicate that payment is REVERSED.
     */
    public function reversed(?User $reverser = null, PaymentReversalReason $reason = PaymentReversalReason::BOUNCED_CHEQUE, string $notes = 'Cheque bounced due to insufficient funds'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::REVERSED,
            'reversed_by' => $reverser?->id ?? User::factory()->accountant(),
            'reversal_reason_code' => $reason,
            'reversal_notes' => $notes,
            'reversed_at' => now(),
        ]);
    }
}
