<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'suppliers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supplier_code',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'payment_terms_days',
        'tax_id',
        'status',
        'notes',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => SupplierStatus::class,
        'payment_terms_days' => 'integer',
    ];

    /**
     * Relationship: Supplier Bills
     */
    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    /**
     * Relationship: Supplier Payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Relationship: Payable Transactions (Sub-ledger)
     */
    public function payableTransactions(): HasMany
    {
        return $this->hasMany(PayableTransaction::class);
    }

    /**
     * Relationship: Creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Active suppliers only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SupplierStatus::ACTIVE->value);
    }

    /**
     * Scope: Search by name, code, contact, email, phone
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('supplier_code', 'ilike', "%{$term}%")
                ->orWhere('contact_person', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
                ->orWhere('phone', 'ilike', "%{$term}%");
        });
    }
}
