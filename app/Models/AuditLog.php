<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * Disable updated_at timestamp because audit records are strictly immutable.
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
        'module',
        'action',
        'actor_id',
        'actor_email',
        'actor_role_snapshot',
        'entity_type',
        'entity_id',
        'reference_number',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'actor_id' => 'integer',
        'entity_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     * Strict immutability enforcement at Eloquent event layer.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new DomainException('Audit log records are strictly immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new DomainException('Audit log records are strictly immutable and cannot be deleted.');
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
        throw new DomainException('Audit log records are strictly immutable and cannot be updated.');
    }

    /**
     * Prevent direct deletions on existing instances.
     */
    public function delete(): ?bool
    {
        throw new DomainException('Audit log records are strictly immutable and cannot be deleted.');
    }

    /**
     * Get the actor user associated with this audit entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Scope query by module.
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', strtoupper($module));
    }

    /**
     * Scope query by event type.
     */
    public function scopeForEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope query by specific entity.
     */
    public function scopeForEntity(Builder $query, string $entityType, int $entityId): Builder
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
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
     * Search across reference numbers, actions, event types, and descriptions.
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
            $q->where('reference_number', $like, "%{$term}%")
                ->orWhere('action', $like, "%{$term}%")
                ->orWhere('event_type', $like, "%{$term}%")
                ->orWhere('description', $like, "%{$term}%")
                ->orWhere('actor_email', $like, "%{$term}%");
        });
    }
}
