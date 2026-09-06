<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'return_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'return_number',
        'order_id',
        'customer_id',
        'salesman_id',
        'warehouse_id',
        'status',
        'created_by',
        'inspected_by',
        'approved_by',
        'requested_at',
        'inspected_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'rejection_reason',
        'notes',
        'inspection_notes',
        'evidence_photos',
        'estimated_refund_subtotal',
        'estimated_refund_tax',
        'estimated_refund_total',
        'is_credit_processed',
        'credit_note_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ReturnStatus::class,
        'requested_at' => 'datetime',
        'inspected_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'evidence_photos' => 'array',
        'estimated_refund_subtotal' => 'decimal:2',
        'estimated_refund_tax' => 'decimal:2',
        'estimated_refund_total' => 'decimal:2',
        'is_credit_processed' => 'boolean',
    ];

    /**
     * Original delivered order relationship.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Customer owning the return.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Assigned salesman if applicable.
     */
    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    /**
     * Target warehouse for return inspection and stock disposition.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * User who initiated the return request.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Warehouse manager/inspector who recorded physical inspection.
     */
    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /**
     * Authorized manager/admin who approved the return.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Return line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class, 'return_request_id');
    }

    /**
     * Chronological immutable lifecycle events.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ReturnRequestEvent::class, 'return_request_id')->orderBy('created_at', 'asc');
    }

    /**
     * Scope query to active open return workflows.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReturnStatus::REQUESTED,
            ReturnStatus::UNDER_REVIEW,
            ReturnStatus::INSPECTED,
        ]);
    }

    /**
     * Scope query to terminal return requests.
     */
    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReturnStatus::APPROVED,
            ReturnStatus::REJECTED,
            ReturnStatus::CANCELLED,
        ]);
    }

    /**
     * Scope query for resource-level authorization (Anti-IDOR).
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::SALESMAN) {
            return $query->where(function (Builder $sub) use ($user) {
                $sub->where('salesman_id', $user->id)
                    ->orWhereHas('customer', function (Builder $c) use ($user) {
                        $c->where('salesman_id', $user->id);
                    });
            });
        }

        return $query;
    }
}
