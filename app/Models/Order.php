<?php

namespace App\Models;

use App\Enums\AdjustmentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->draft_token)) {
                $order->draft_token = (string) Str::uuid();
            }
            if (empty($order->version)) {
                $order->version = 1;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_number',
        'idempotency_key',
        'draft_token',
        'version',
        'customer_id',
        'salesman_id',
        'created_by',
        'status',
        'fulfillment_status',
        'payment_status',
        'delivery_status',
        'adjustment_status',
        'currency',
        'subtotal',
        'tax_total',
        'adjustment_total',
        'grand_total',
        'notes',
        'submitted_at',
        'approved_at',
        'approved_by',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => OrderStatus::class,
        'fulfillment_status' => FulfillmentStatus::class,
        'payment_status' => PaymentStatus::class,
        'delivery_status' => DeliveryStatus::class,
        'adjustment_status' => AdjustmentStatus::class,
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'adjustment_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'customer_id' => 'integer',
        'salesman_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'cancelled_by' => 'integer',
        'version' => 'integer',
    ];

    /**
     * Determine if this order is in DRAFT state.
     */
    public function isDraft(): bool
    {
        return $this->status === OrderStatus::DRAFT;
    }

    /**
     * Get the customer associated with this order.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the salesman associated with this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    /**
     * Get the staff user who created this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the staff user who approved this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the staff user who cancelled or rejected this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get the items belonging to this order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id')->orderBy('id', 'asc');
    }

    /**
     * Get all discrete item allocations for this order.
     *
     * @return HasMany<OrderItemAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(OrderItemAllocation::class, 'order_id')->orderBy('id', 'asc');
    }

    /**
     * Get all order adjustments for this order.
     *
     * @return HasMany<OrderAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class, 'order_id')->orderBy('id', 'asc');
    }

    /**
     * Get the active submitted adjustment request for this order, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<OrderAdjustment, $this>
     */
    public function activeAdjustment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderAdjustment::class, 'order_id')->where('status', \App\Enums\OrderAdjustmentStatus::SUBMITTED);
    }

    /**
     * Determine if this order currently has a pending adjustment request.
     */
    public function hasActiveAdjustment(): bool
    {
        return $this->adjustment_status === AdjustmentStatus::REQUESTED;
    }

    /**
     * Scope query based on the authenticated actor's resource scope.
     * Salesmen can only access orders for their assigned accounts.
     */
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if ($user && $user->role === UserRole::SALESMAN) {
            return $query->where('orders.salesman_id', $user->id);
        }

        return $query;
    }

    /**
     * Scope query by order status filter.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || strtoupper($status) === 'ALL') {
            return $query;
        }

        $resolved = OrderStatus::tryFrom(strtoupper(trim($status)));

        if ($resolved) {
            return $query->where('orders.status', $resolved);
        }

        return $query;
    }

    /**
     * Scope query by search term across order number or customer name.
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
            $q->where('orders.order_number', $like, "%{$term}%")
                ->orWhereHas('customer', function (Builder $custQ) use ($term, $like) {
                    $custQ->where('name', $like, "%{$term}%")
                        ->orWhere('code', $like, "%{$term}%");
                });
        });
    }
}
