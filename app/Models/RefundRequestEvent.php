<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundRequestEvent extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'refund_request_events';

    /**
     * Disable standard updated_at timestamp.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'refund_request_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'notes',
        'metadata',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Refund request audit events are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \LogicException('Refund request audit events are permanent records and cannot be deleted.');
        });
    }

    /**
     * Accessor alias for action to support event naming convention.
     */
    public function getEventAttribute(): ?string
    {
        return $this->action;
    }

    /**
     * Accessor alias for actor_id to support user_id naming convention.
     */
    public function getUserIdAttribute(): ?int
    {
        return $this->actor_id;
    }

    /**
     * Parent refund request.
     */
    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }

    /**
     * Actor who triggered the event.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * User alias for actor relationship.
     */
    public function user(): BelongsTo
    {
        return $this->actor();
    }
}
