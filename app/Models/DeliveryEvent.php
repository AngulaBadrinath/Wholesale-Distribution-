<?php

namespace App\Models;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DeliveryEvent extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'delivery_events';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'delivery_id',
        'event_type',
        'from_status',
        'to_status',
        'actor_id',
        'notes',
        'metadata',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => DeliveryEventType::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     * Enforces strict ledger immutability: records cannot be updated or deleted.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Delivery events are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Delivery events are immutable and cannot be deleted.');
        });
    }

    /**
     * Get the delivery associated with this event.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    /**
     * Get the user who triggered the event.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
