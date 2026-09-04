<?php

namespace App\DTOs\Customer;

use App\Enums\CustomerStatus;
use App\Enums\PaymentTerms;

readonly class CustomerData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $contact_name,
        public ?string $email,
        public string $phone,
        public string $billing_address_line1,
        public ?string $billing_address_line2,
        public string $billing_city,
        public string $billing_state,
        public string $billing_postal_code,
        public string $billing_country,
        public ?string $shipping_address_line1,
        public ?string $shipping_address_line2,
        public ?string $shipping_city,
        public ?string $shipping_state,
        public ?string $shipping_postal_code,
        public string $shipping_country,
        public ?string $tax_id,
        public float $credit_limit,
        public string $payment_terms,
        public CustomerStatus $status,
        public ?string $notes,
        public ?int $salesman_id = null,
    ) {}

    /**
     * Build instance from validated array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $statusValue = $data['status'] ?? CustomerStatus::ACTIVE->value;
        $status = $statusValue instanceof CustomerStatus
            ? $statusValue
            : (CustomerStatus::tryFrom((string) $statusValue) ?? CustomerStatus::ACTIVE);

        $paymentTerms = $data['payment_terms'] ?? PaymentTerms::NET_30->value;

        return new self(
            code: strtoupper(trim((string) ($data['code'] ?? ''))),
            name: trim((string) ($data['name'] ?? '')),
            contact_name: trim((string) ($data['contact_name'] ?? '')),
            email: isset($data['email']) && trim((string) $data['email']) !== '' ? strtolower(trim((string) $data['email'])) : null,
            phone: trim((string) ($data['phone'] ?? '')),
            billing_address_line1: trim((string) ($data['billing_address_line1'] ?? '')),
            billing_address_line2: isset($data['billing_address_line2']) && trim((string) $data['billing_address_line2']) !== '' ? trim((string) $data['billing_address_line2']) : null,
            billing_city: trim((string) ($data['billing_city'] ?? '')),
            billing_state: trim((string) ($data['billing_state'] ?? '')),
            billing_postal_code: trim((string) ($data['billing_postal_code'] ?? '')),
            billing_country: strtoupper(trim((string) ($data['billing_country'] ?? 'US'))),
            shipping_address_line1: isset($data['shipping_address_line1']) && trim((string) $data['shipping_address_line1']) !== '' ? trim((string) $data['shipping_address_line1']) : null,
            shipping_address_line2: isset($data['shipping_address_line2']) && trim((string) $data['shipping_address_line2']) !== '' ? trim((string) $data['shipping_address_line2']) : null,
            shipping_city: isset($data['shipping_city']) && trim((string) $data['shipping_city']) !== '' ? trim((string) $data['shipping_city']) : null,
            shipping_state: isset($data['shipping_state']) && trim((string) $data['shipping_state']) !== '' ? trim((string) $data['shipping_state']) : null,
            shipping_postal_code: isset($data['shipping_postal_code']) && trim((string) $data['shipping_postal_code']) !== '' ? trim((string) $data['shipping_postal_code']) : null,
            shipping_country: strtoupper(trim((string) ($data['shipping_country'] ?? 'US'))),
            tax_id: isset($data['tax_id']) && trim((string) $data['tax_id']) !== '' ? trim((string) $data['tax_id']) : null,
            credit_limit: (float) ($data['credit_limit'] ?? 0.00),
            payment_terms: (string) $paymentTerms,
            status: $status,
            notes: isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            salesman_id: isset($data['salesman_id']) && $data['salesman_id'] !== '' && $data['salesman_id'] !== null ? (int) $data['salesman_id'] : null,
        );
    }

    /**
     * Convert DTO to array for database persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'billing_address_line1' => $this->billing_address_line1,
            'billing_address_line2' => $this->billing_address_line2,
            'billing_city' => $this->billing_city,
            'billing_state' => $this->billing_state,
            'billing_postal_code' => $this->billing_postal_code,
            'billing_country' => $this->billing_country,
            'shipping_address_line1' => $this->shipping_address_line1,
            'shipping_address_line2' => $this->shipping_address_line2,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_postal_code' => $this->shipping_postal_code,
            'shipping_country' => $this->shipping_country,
            'tax_id' => $this->tax_id,
            'credit_limit' => $this->credit_limit,
            'payment_terms' => $this->payment_terms,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'salesman_id' => $this->salesman_id,
        ];
    }
}
