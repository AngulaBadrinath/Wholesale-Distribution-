<?php

namespace App\Services\Tax;

use App\DTOs\Tax\TaxProfileData;
use App\Enums\TaxProfileStatus;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TaxProfileService
{
    /**
     * List tax profiles with filtering, sorting, and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15, ?User $actor = null): LengthAwarePaginator
    {
        $query = TaxProfile::query();

        // 1. Search term (name, code, description)
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // 2. Status filter (ACTIVE, INACTIVE, ALL)
        if (! empty($filters['status']) && $filters['status'] !== 'ALL') {
            $statusEnum = $filters['status'] instanceof TaxProfileStatus
                ? $filters['status']
                : TaxProfileStatus::tryFrom((string) $filters['status']);

            if ($statusEnum) {
                $query->where('status', $statusEnum);
            }
        }

        // 3. Sorting
        $allowedSorts = ['name', 'code', 'rate', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'name';
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortBy, $sortOrder)->orderBy('id', 'asc');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->through(fn (TaxProfile $profile) => $this->formatTaxProfile($profile));

        return $paginator;
    }

    /**
     * Retrieve all active tax profiles for dropdown selections.
     *
     * @return Collection<int, TaxProfile>
     */
    public function getActiveProfiles(): Collection
    {
        return TaxProfile::query()
            ->active()
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Create a new Tax Profile master record.
     *
     * @throws ValidationException
     */
    public function create(TaxProfileData $data, User $actor, ?string $ip = null): TaxProfile
    {
        // 1. Unique code validation
        if (TaxProfile::where('code', $data->code)->exists()) {
            throw ValidationException::withMessages([
                'code' => "Tax profile code '{$data->code}' is already in use.",
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $ip) {
            $taxProfile = TaxProfile::create($data->toArray());

            // 2. Audit logging
            Log::info('Tax profile master record created', [
                'event' => 'audit.tax_event',
                'action' => 'TAX_PROFILE_CREATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'tax_profile_id' => $taxProfile->id,
                'code' => $taxProfile->code,
                'name' => $taxProfile->name,
                'rate' => (string) $taxProfile->rate,
                'status' => $taxProfile->status->value,
                'ip_address' => $ip ?? request()?->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $taxProfile;
        });
    }

    /**
     * Update an existing Tax Profile master record.
     *
     * @throws ValidationException
     */
    public function update(TaxProfile $taxProfile, TaxProfileData $data, User $actor, ?string $ip = null): TaxProfile
    {
        return DB::transaction(function () use ($taxProfile, $data, $actor, $ip) {
            /** @var TaxProfile $lockedProfile */
            $lockedProfile = TaxProfile::query()
                ->lockForUpdate()
                ->findOrFail($taxProfile->id);

            // 1. Unique code check excluding self
            if (TaxProfile::where('code', $data->code)->where('id', '!=', $lockedProfile->id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => "Tax profile code '{$data->code}' is already in use by another profile.",
                ]);
            }

            $previousValues = [
                'name' => $lockedProfile->name,
                'code' => $lockedProfile->code,
                'rate' => (string) $lockedProfile->rate,
                'description' => $lockedProfile->description,
                'status' => $lockedProfile->status->value,
            ];

            $lockedProfile->fill($data->toArray());
            $lockedProfile->save();

            $newValues = [
                'name' => $lockedProfile->name,
                'code' => $lockedProfile->code,
                'rate' => (string) $lockedProfile->rate,
                'description' => $lockedProfile->description,
                'status' => $lockedProfile->status->value,
            ];

            // 2. Audit logging
            Log::info('Tax profile master record updated', [
                'event' => 'audit.tax_event',
                'action' => 'TAX_PROFILE_UPDATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'tax_profile_id' => $lockedProfile->id,
                'code' => $lockedProfile->code,
                'previous_values' => $previousValues,
                'new_values' => $newValues,
                'ip_address' => $ip ?? request()?->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedProfile;
        });
    }

    /**
     * Transition a Tax Profile's lifecycle status (ACTIVE <-> INACTIVE).
     */
    public function transitionStatus(TaxProfile $taxProfile, TaxProfileStatus $newStatus, User $actor, ?string $ip = null): TaxProfile
    {
        return DB::transaction(function () use ($taxProfile, $newStatus, $actor, $ip) {
            /** @var TaxProfile $lockedProfile */
            $lockedProfile = TaxProfile::query()
                ->lockForUpdate()
                ->findOrFail($taxProfile->id);

            if ($lockedProfile->status === $newStatus) {
                return $lockedProfile;
            }

            $previousStatus = $lockedProfile->status->value;
            $lockedProfile->status = $newStatus;
            $lockedProfile->save();

            Log::info('Tax profile status transitioned', [
                'event' => 'audit.tax_event',
                'action' => 'TAX_PROFILE_STATUS_CHANGED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'tax_profile_id' => $lockedProfile->id,
                'code' => $lockedProfile->code,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus->value,
                'ip_address' => $ip ?? request()?->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedProfile;
        });
    }

    /**
     * Delete an unreferenced Tax Profile.
     *
     * @throws ValidationException
     */
    public function delete(TaxProfile $taxProfile, User $actor, ?string $ip = null): void
    {
        DB::transaction(function () use ($taxProfile, $actor, $ip) {
            /** @var TaxProfile $lockedProfile */
            $lockedProfile = TaxProfile::query()
                ->lockForUpdate()
                ->findOrFail($taxProfile->id);

            // Referential integrity check: prohibit deleting if linked to any products
            $linkedProductCount = $lockedProfile->products()->count();
            if ($linkedProductCount > 0) {
                throw ValidationException::withMessages([
                    'tax_profile' => "Cannot delete tax profile '{$lockedProfile->name}' because it is assigned to {$linkedProductCount} product(s). Deactivate the profile instead.",
                ]);
            }

            $profileId = $lockedProfile->id;
            $code = $lockedProfile->code;
            $name = $lockedProfile->name;

            $lockedProfile->delete();

            Log::info('Tax profile master record deleted', [
                'event' => 'audit.tax_event',
                'action' => 'TAX_PROFILE_DELETED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'tax_profile_id' => $profileId,
                'code' => $code,
                'name' => $name,
                'ip_address' => $ip ?? request()?->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Format a Tax Profile instance for API / Inertia responses.
     *
     * @return array<string, mixed>
     */
    public function formatTaxProfile(TaxProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'code' => $profile->code,
            'rate' => (string) $profile->rate,
            'formatted_rate' => rtrim(rtrim((string) $profile->rate, '0'), '.').'%',
            'description' => $profile->description,
            'status' => $profile->status instanceof TaxProfileStatus ? $profile->status->value : (string) $profile->status,
            'status_label' => $profile->status instanceof TaxProfileStatus ? $profile->status->label() : (string) $profile->status,
            'status_badge_variant' => $profile->status instanceof TaxProfileStatus ? $profile->status->badgeVariant() : 'secondary',
            'products_count' => $profile->relationLoaded('products') ? $profile->products->count() : $profile->products()->count(),
            'created_at' => $profile->created_at?->toIso8601String(),
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }
}
