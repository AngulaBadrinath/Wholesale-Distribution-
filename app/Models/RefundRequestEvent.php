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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'refund_request_id',
        'user_id',
        'event',
        'from_status',
        'to_status',
        'notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
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
     * Parent refund request.
     */
    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }

    /**
     * Actor who triggered the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
