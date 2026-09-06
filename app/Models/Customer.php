<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\PaymentTerms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'contact_name',
        'email',
        'phone',
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'tax_id',
        'credit_limit',
        'payment_terms',
        'status',
        'notes',
        'salesman_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => CustomerStatus::class,
        'payment_terms' => PaymentTerms::class,
        'credit_limit' => 'decimal:2',
        'salesman_id' => 'integer',
    ];

    /**
     * Get the salesman assigned to this customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function salesman(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    /**
     * Scope query to active customers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::ACTIVE);
    }

    /**
     * Scope query to customers assigned to a specific salesman.
     */
    public function scopeAssignedTo(Builder $query, int|User $salesman): Builder
    {
        $salesmanId = $salesman instanceof User ? $salesman->id : $salesman;

        return $query->where('salesman_id', $salesmanId);
    }

    /**
     * Scope query to unassigned customers.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('salesman_id');
    }

    /**
     * Scope query based on the authenticated actor's resource scope.
     * Salesmen can only access assigned customers; privileged roles have unrestricted access.
     */
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if ($user && $user->role === \App\Enums\UserRole::SALESMAN) {
            return $query->where('customers.salesman_id', $user->id);
        }

        return $query;
    }

    /**
     * Scope query by search term across code, name, contact name, email, and phone.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);
        $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $like = $isPgsql ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('name', $like, "%{$term}%")
                ->orWhere('code', $like, "%{$term}%")
                ->orWhere('contact_name', $like, "%{$term}%")
                ->orWhere('email', $like, "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    /**
     * Scope query by customer status filter.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || strtoupper($status) === 'ALL') {
            return $query;
        }

        $resolved = CustomerStatus::tryFrom(strtoupper(trim($status)));

        if ($resolved) {
            return $query->where('status', $resolved);
        }

        return $query;
    }

    /**
     * Check if customer is active.
     */
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::ACTIVE;
    }

    /**
     * Check if customer can place orders.
     */
    public function canPlaceOrders(): bool
    {
        return $this->status instanceof CustomerStatus && $this->status->canPlaceOrders();
    }

    /**
     * Authoritatively verify that the customer is eligible to place new orders.
     * Throws ValidationException with specific operational context if ineligible.
     * Future Phase 05 Order domain will consume this validation contract.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureCanPlaceOrders(): void
    {
        if (! $this->canPlaceOrders()) {
            $statusLabel = $this->status instanceof CustomerStatus ? $this->status->label() : (string) $this->status;
            $message = match ($this->status) {
                CustomerStatus::ON_HOLD => 'Customer account is currently on hold and cannot participate in new sales orders.',
                CustomerStatus::INACTIVE => 'Customer account is deactivated. New order creation is prohibited.',
                default => "Customer account status ({$statusLabel}) is not eligible for order placement.",
            };

            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer_id' => $message,
            ]);
        }
    }

    /**
     * Return formatted multi-line billing address.
     */
    public function formattedBillingAddress(): string
    {
        $lines = array_filter([
            $this->billing_address_line1,
            $this->billing_address_line2,
            trim("{$this->billing_city}, {$this->billing_state} {$this->billing_postal_code}"),
            $this->billing_country,
        ]);

        return implode("\n", $lines);
    }

    /**
     * Return formatted multi-line shipping address.
     */
    public function formattedShippingAddress(): string
    {
        if (empty($this->shipping_address_line1)) {
            return $this->formattedBillingAddress();
        }

        $lines = array_filter([
            $this->shipping_address_line1,
            $this->shipping_address_line2,
            trim("{$this->shipping_city}, {$this->shipping_state} {$this->shipping_postal_code}"),
            $this->shipping_country,
        ]);

        return implode("\n", $lines);
    }

    /**
     * Accessor for single-line formatted billing address.
     */
    public function getFormattedBillingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->billing_address_line1,
            $this->billing_address_line2,
            trim("{$this->billing_city}, {$this->billing_state} {$this->billing_postal_code}"),
            $this->billing_country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Accessor for single-line formatted shipping address.
     */
    public function getFormattedShippingAddressAttribute(): string
    {
        if (empty($this->shipping_address_line1)) {
            return $this->formatted_billing_address;
        }

        $parts = array_filter([
            $this->shipping_address_line1,
            $this->shipping_address_line2,
            trim("{$this->shipping_city}, {$this->shipping_state} {$this->shipping_postal_code}"),
            $this->shipping_country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get all payments recorded for this customer.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id')->orderBy('payment_date', 'desc');
    }
}
