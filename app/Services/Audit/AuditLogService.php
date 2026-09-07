<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    /**
     * Sensitive keys that must be stripped or redacted from audit metadata.
     *
     * @var array<int, string>
     */
    protected array $redactedKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'remember_token',
        'mfa_secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_code',
        'secret',
        'authorization',
        'card_number',
        'cvv',
        'binary_data',
        'file_contents',
    ];

    /**
     * Record a structured business audit event.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $eventType,
        string $module,
        string $action,
        ?User $actor = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $referenceNumber = null,
        ?string $description = null,
        array $metadata = []
    ): AuditLog {
        $actorEmail = $actor?->email;
        $actorRole = $actor?->role instanceof UserRole ? $actor->role->value : ($actor?->role ?? 'SYSTEM');
        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        $sanitizedMetadata = $this->sanitizeMetadata($metadata);

        // 1. Create durable database audit record
        $auditLog = AuditLog::create([
            'event_type' => $eventType,
            'module' => strtoupper($module),
            'action' => $action,
            'actor_id' => $actor?->id,
            'actor_email' => $actorEmail,
            'actor_role_snapshot' => $actorRole,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'reference_number' => $referenceNumber,
            'description' => $description,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'metadata' => ! empty($sanitizedMetadata) ? $sanitizedMetadata : null,
            'created_at' => Carbon::now(),
        ]);

        // 2. Also forward to structured log channel for external SIEM / log aggregators
        Log::info("Audit Event [{$eventType}]: {$action}", [
            'audit_id' => $auditLog->id,
            'module' => $module,
            'actor_id' => $actor?->id,
            'actor_email' => $actorEmail,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'reference_number' => $referenceNumber,
        ]);

        return $auditLog;
    }

    /**
     * Get paginated and filtered activity timeline.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getTimeline(array $filters = [], ?User $user = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::query()->with(['actor:id,name,email,role']);

        if (! empty($filters['module'])) {
            $query->forModule((string) $filters['module']);
        }

        if (! empty($filters['event_type'])) {
            $query->forEventType((string) $filters['event_type']);
        }

        if (! empty($filters['actor_id'])) {
            $query->forActor((int) $filters['actor_id']);
        }

        if (! empty($filters['entity_type']) && ! empty($filters['entity_id'])) {
            $query->forEntity((string) $filters['entity_type'], (int) $filters['entity_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        $pageSize = min(max((int) ($filters['per_page'] ?? $perPage), 5), 100);

        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($pageSize);
    }

    /**
     * Get audit history for a specific business entity.
     */
    public function getEntityHistory(string $entityType, int $entityId, int $perPage = 25): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with(['actor:id,name,email,role'])
            ->forEntity($entityType, $entityId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Recursively sanitize metadata array by removing or redacting sensitive keys.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeMetadata(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);

            if ($this->isKeyRedacted($keyLower)) {
                $clean[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeMetadata($value);
            } elseif (is_string($value) && strlen($value) > 2000) {
                $clean[$key] = substr($value, 0, 2000).'... [TRUNCATED]';
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Check if key matches any redacted pattern.
     */
    protected function isKeyRedacted(string $key): bool
    {
        foreach ($this->redactedKeys as $redacted) {
            if ($key === $redacted || str_contains($key, $redacted)) {
                return true;
            }
        }

        return false;
    }
}
