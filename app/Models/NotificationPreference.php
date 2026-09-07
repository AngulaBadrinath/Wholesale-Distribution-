<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notification_preferences';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category',
        'is_enabled',
        'is_in_app_enabled',
    ];

    /**
     * Set is_in_app_enabled attribute as is_enabled.
     */
    public function setIsInAppEnabledAttribute(bool $value): void
    {
        $this->attributes['is_enabled'] = $value;
    }

    /**
     * Get is_in_app_enabled attribute from is_enabled.
     */
    public function getIsInAppEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['is_enabled'] ?? true);
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the user that owns this notification preference.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope query for a user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
