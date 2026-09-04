<?php

namespace App\Models;

use App\Enums\TaxProfileStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'rate',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => TaxProfileStatus::class,
        'rate' => 'decimal:4',
    ];

    /**
     * Get all products assigned to this tax profile.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tax_profile_id');
    }

    /**
     * Scope query to active tax profiles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TaxProfileStatus::ACTIVE);
    }

    /**
     * Scope query by search term across name, code, and description.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);
        $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $likeOp = $isPgsql ? 'ILIKE' : 'LIKE';

        return $query->where(function (Builder $q) use ($term, $likeOp) {
            $q->where('name', $likeOp, "%{$term}%")
                ->orWhere('code', $likeOp, "%{$term}%")
                ->orWhere('description', $likeOp, "%{$term}%");
        });
    }

    /**
     * Determine if this tax profile is active.
     */
    public function isActive(): bool
    {
        return $this->status === TaxProfileStatus::ACTIVE;
    }
}
