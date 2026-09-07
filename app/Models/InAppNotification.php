<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InAppNotification extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'in_app_notifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'notification_type',
        'type',
        'category',
        'severity',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'action_url',
        'is_read',
        'read_at',
        'deduplication_key',
        'metadata',
    ];

    /**
     * Set type attribute as notification_type.
     */
    public function setTypeAttribute(string $value): void
    {
        $this->attributes['notification_type'] = $value;
    }

    /**
     * Get type attribute from notification_type.
     */
    public function getTypeAttribute(): ?string
    {
        return $this->attributes['notification_type'] ?? null;
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['type'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'entity_id' => 'integer',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user recipient of this notification.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope query to a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope query to unread notifications only.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope query to read notifications only.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope query by category.
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', strtoupper($category));
    }

    /**
     * Mark this notification instance as read.
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        return $this->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark this notification instance as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }
}
