<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'security_logs';

    /**
     * Disable updated_at timestamp because security logs are strictly immutable.
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
        'event_type',
        'severity',
        'actor_id',
        'actor_email',
        'actor_role',
        'ip_address',
        'user_agent',
        'context',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'actor_id' => 'integer',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     * Strict immutability enforcement at Eloquent event layer.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new DomainException('Security log records are strictly immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new DomainException('Security log records are strictly immutable and cannot be deleted.');
        });
    }

    /**
     * Prevent direct updates on existing instances.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new DomainException('Security log records are strictly immutable and cannot be updated.');
    }

    /**
     * Prevent direct deletions on existing instances.
     */
    public function delete(): ?bool
    {
        throw new DomainException('Security log records are strictly immutable and cannot be deleted.');
    }

    /**
     * Get the actor user associated with this security log.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Scope query by event type.
     */
    public function scopeForEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope query by severity level.
     */
    public function scopeForSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', strtoupper($severity));
    }

    /**
     * Scope query by actor user ID.
     */
    public function scopeForActor(Builder $query, int $actorId): Builder
    {
        return $query->where('actor_id', $actorId);
    }

    /**
     * Scope query by date range.
     */
    public function scopeDateBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Search across event types, emails, and IP addresses.
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
            $q->where('event_type', $like, "%{$term}%")
                ->orWhere('actor_email', $like, "%{$term}%")
                ->orWhere('ip_address', $like, "%{$term}%");
        });
    }
}
