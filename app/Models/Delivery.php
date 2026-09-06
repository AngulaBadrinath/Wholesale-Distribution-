<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Delivery extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deliveries';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
        'status' => DeliveryStatus::PENDING_ASSIGNMENT,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'delivery_number',
        'order_id',
        'customer_id',
        'driver_id',
        'status',
        'delivery_contact_name',
        'delivery_contact_phone',
        'delivery_address_line1',
        'delivery_address_line2',
        'delivery_city',
        'delivery_state',
        'delivery_postal_code',
        'delivery_country_code',
        'scheduled_date',
        'delivery_window',
        'driver_instructions',
        'assigned_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'failed_at',
        'returned_at',
        'recipient_name',
        'recipient_signature_path',
        'pod_evidence_path',
        'pod_notes',
        'version',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'scheduled_date' => 'date',
            'assigned_at' => 'immutable_datetime',
            'picked_up_at' => 'immutable_datetime',
            'out_for_delivery_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /**
     * Get the order associated with this delivery.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the customer associated with this delivery.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the assigned delivery partner driver.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the creator of the delivery record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the delivery record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all line items for this delivery.
     */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class, 'delivery_id');
    }

    /**
     * Get all historical tracking events for this delivery.
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class, 'delivery_id')->orderBy('created_at', 'asc');
    }

    /**
     * Get all structured failure records for this delivery.
     */
    public function failures(): HasMany
    {
        return $this->hasMany(DeliveryFailure::class, 'delivery_id')->orderBy('reported_at', 'desc');
    }

    /**
     * Scope query to a specific driver.
     */
    public function scopeForDriver(Builder $query, User|int $driver): Builder
    {
        $driverId = $driver instanceof User ? $driver->id : $driver;

        return $query->where('deliveries.driver_id', $driverId);
    }

    /**
     * Scope query to a specific status or array of statuses.
     */
    public function scopeForStatus(Builder $query, DeliveryStatus|string|array $status): Builder
    {
        if (is_array($status)) {
            $values = array_map(fn ($s) => $s instanceof DeliveryStatus ? $s->value : (string) $s, $status);

            return $query->whereIn('deliveries.status', $values);
        }

        $statusValue = $status instanceof DeliveryStatus ? $status->value : (string) $status;

        return $query->where('deliveries.status', $statusValue);
    }

    /**
     * Scope query to deliveries scheduled for today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('deliveries.scheduled_date', Carbon::today());
    }

    /**
     * Scope query to pending driver pickup or assignment.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('deliveries.status', [
            DeliveryStatus::PENDING_ASSIGNMENT->value,
            DeliveryStatus::ASSIGNED->value,
        ]);
    }

    /**
     * Scope query to active transit route (picked up or out for delivery).
     */
    public function scopeActiveRoute(Builder $query): Builder
    {
        return $query->whereIn('deliveries.status', [
            DeliveryStatus::PICKED_UP->value,
            DeliveryStatus::OUT_FOR_DELIVERY->value,
        ]);
    }

    /**
     * Scope query by search keyword across delivery number, order number, customer name, and city.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

        return $query->where(function (Builder $sub) use ($term) {
            $sub->where('deliveries.delivery_number', 'ILIKE', $term)
                ->orWhere('deliveries.delivery_address_line1', 'ILIKE', $term)
                ->orWhere('deliveries.delivery_city', 'ILIKE', $term)
                ->orWhereHas('order', function (Builder $orderQuery) use ($term) {
                    $orderQuery->where('order_number', 'ILIKE', $term);
                })
                ->orWhereHas('customer', function (Builder $custQuery) use ($term) {
                    $custQuery->where('name', 'ILIKE', $term)
                        ->orWhere('customer_code', 'ILIKE', $term);
                })
                ->orWhereHas('driver', function (Builder $driverQuery) use ($term) {
                    $driverQuery->where('name', 'ILIKE', $term);
                });
        });
    }

    /**
     * Calculate total deliverable units quantity across all items.
     */
    public function totalDeliverableQuantity(): int
    {
        return (int) $this->items->sum('deliverable_quantity');
    }

    /**
     * Calculate total delivered units quantity across all items.
     */
    public function totalDeliveredQuantity(): int
    {
        return (int) $this->items->sum('delivered_quantity');
    }

    /**
     * State transition predicate: Can confirm warehouse pickup.
     */
    public function canBePickedUp(): bool
    {
        return $this->status === DeliveryStatus::ASSIGNED;
    }

    /**
     * State transition predicate: Can start out-for-delivery route.
     */
    public function canStartRoute(): bool
    {
        return $this->status === DeliveryStatus::PICKED_UP;
    }

    /**
     * State transition predicate: Can complete delivery.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === DeliveryStatus::OUT_FOR_DELIVERY;
    }

    /**
     * State transition predicate: Can log delivery failure.
     */
    public function canBeFailed(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::ASSIGNED,
            DeliveryStatus::PICKED_UP,
            DeliveryStatus::OUT_FOR_DELIVERY,
        ], true);
    }

    /**
     * State transition predicate: Can reschedule delivery.
     */
    public function canBeRescheduled(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::ASSIGNED,
            DeliveryStatus::FAILED,
        ], true);
    }

    /**
     * State transition predicate: Can return to warehouse.
     */
    public function canBeReturnedToWarehouse(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::PICKED_UP,
            DeliveryStatus::OUT_FOR_DELIVERY,
            DeliveryStatus::FAILED,
        ], true);
    }
}
