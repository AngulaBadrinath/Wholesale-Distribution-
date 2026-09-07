<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\UserRole;
use App\Models\SecurityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class SecurityLogService
{
    /**
     * Keys to strictly redact from security log context.
     *
     * @var array<int, string>
     */
    protected array $redactedKeys = [
        'password',
        'token',
        'secret',
        'mfa_secret',
        'recovery_code',
        'two_factor_secret',
        'authorization',
        'cookie',
    ];

    /**
     * Log a dedicated security event.
     *
     * @param  array<string, mixed>  $context
     */
    public function logSecurityEvent(
        string $eventType,
        string $severity = 'INFO',
        ?User $actor = null,
        ?string $email = null,
        array $context = []
    ): SecurityLog {
        $actorEmail = $email ?? $actor?->email;
        $actorRole = $actor?->role instanceof UserRole ? $actor->role->value : ($actor?->role ?? null);
        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        $sanitizedContext = $this->sanitizeContext($context);

        // 1. Create database record
        $securityLog = SecurityLog::create([
            'event_type' => $eventType,
            'severity' => strtoupper($severity),
            'actor_id' => $actor?->id,
            'actor_email' => $actorEmail,
            'actor_role' => $actorRole,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'context' => ! empty($sanitizedContext) ? $sanitizedContext : null,
            'created_at' => Carbon::now(),
        ]);

        // 2. Also log to dedicated security channel or default log
        try {
            Log::channel('security')->info("Security Event [{$eventType}] - {$severity}", [
                'security_log_id' => $securityLog->id,
                'actor_id' => $actor?->id,
                'actor_email' => $actorEmail,
                'severity' => $severity,
                'ip_address' => $ip,
            ]);
        } catch (\Throwable $e) {
            // Fallback to default logger if dedicated channel is not configured in environment
            Log::info("Security Event [{$eventType}] - {$severity}", [
                'security_log_id' => $securityLog->id,
                'actor_id' => $actor?->id,
                'actor_email' => $actorEmail,
                'severity' => $severity,
                'ip_address' => $ip,
            ]);
        }

        return $securityLog;
    }

    /**
     * Get paginated security logs (Restricted to privileged roles).
     *
     * @param  array<string, mixed>  $filters
     */
    public function getSecurityLogs(array $filters = [], ?User $user = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = SecurityLog::query()->with(['actor:id,name,email,role']);

        if (! empty($filters['event_type'])) {
            $query->forEventType((string) $filters['event_type']);
        }

        if (! empty($filters['severity'])) {
            $query->forSeverity((string) $filters['severity']);
        }

        if (! empty($filters['actor_id'])) {
            $query->forActor((int) $filters['actor_id']);
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
     * Sanitize context array to remove sensitive secrets.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeContext(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);

            if ($this->isKeyRedacted($keyLower)) {
                $clean[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeContext($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

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
