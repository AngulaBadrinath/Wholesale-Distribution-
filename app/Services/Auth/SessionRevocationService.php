<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionRevocationService
{
    /**
     * Retrieve all unexpired active sessions for the given user.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getActiveSessions(User $user, string $currentSessionId): Collection
    {
        $lifetimeMinutes = (int) config('session.lifetime', 120);
        $cutoffTimestamp = Carbon::now()->subMinutes($lifetimeMinutes)->getTimestamp();

        $rawSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $cutoffTimestamp)
            ->orderBy('last_activity', 'desc')
            ->get();

        return $rawSessions->map(function (object $session) use ($currentSessionId) {
            $isCurrent = ($session->id === $currentSessionId);
            $parsedDevice = $this->parseUserAgent($session->user_agent);

            return [
                // Deterministic HMAC token hides raw session ID from client
                'id' => $this->generateSessionToken($session->id),
                'device_type' => $parsedDevice['device_type'],
                'browser' => $parsedDevice['browser'],
                'platform' => $parsedDevice['platform'],
                'ip_address' => $this->maskIp($session->ip_address),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'last_active_human' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'is_current' => $isCurrent,
            ];
        });
    }

    /**
     * Find a raw database session belonging to the user matching the given token or raw ID.
     */
    public function findUserSession(User $user, string $tokenOrId): ?object
    {
        $lifetimeMinutes = (int) config('session.lifetime', 120);
        $cutoffTimestamp = Carbon::now()->subMinutes($lifetimeMinutes)->getTimestamp();

        $userSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $cutoffTimestamp)
            ->get();

        foreach ($userSessions as $session) {
            if ($this->generateSessionToken($session->id) === $tokenOrId || $session->id === $tokenOrId) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Revoke a single session belonging to the user.
     */
    public function revokeSession(User $user, string $tokenOrId, ?int $actorId = null, ?string $ip = null): bool
    {
        $session = $this->findUserSession($user, $tokenOrId);

        if (! $session) {
            return false;
        }

        DB::table('sessions')->where('id', $session->id)->delete();

        Log::info('session.security_event', [
            'action' => 'SESSION_REVOKED',
            'user_id' => $user->id,
            'actor_id' => $actorId ?? $user->id,
            'ip' => $ip,
            'timestamp' => now()->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Revoke all other sessions belonging to the user except the current session.
     */
    public function revokeOtherSessions(User $user, string $currentSessionId, ?int $actorId = null, ?string $ip = null): int
    {
        $count = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        Log::info('session.security_event', [
            'action' => 'SESSION_REVOKE_OTHERS',
            'user_id' => $user->id,
            'actor_id' => $actorId ?? $user->id,
            'revoked_count' => $count,
            'ip' => $ip,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $count;
    }

    /**
     * Revoke all active sessions belonging to the user.
     */
    public function revokeAllSessions(User $user, ?int $actorId = null, ?string $ip = null): int
    {
        $count = DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        Log::info('session.security_event', [
            'action' => 'SESSION_REVOKE_ALL',
            'user_id' => $user->id,
            'actor_id' => $actorId ?? $user->id,
            'revoked_count' => $count,
            'ip' => $ip,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $count;
    }

    /**
     * Security hook: Invalidate sessions when security-sensitive account changes occur.
     * (E.g., password reset, suspension, disablement, privilege changes).
     */
    public function revokeUserSessionsForSecurityEvent(int|User $user, string $reason, ?string $exceptSessionId = null): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        $query = DB::table('sessions')->where('user_id', $userId);

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $count = $query->delete();

        Log::info('session.security_event', [
            'action' => 'SECURITY_SESSION_INVALIDATED',
            'user_id' => $userId,
            'reason' => $reason,
            'revoked_count' => $count,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $count;
    }

    /**
     * Generate a deterministic, secure HMAC token representing the session ID.
     */
    public function generateSessionToken(string $sessionId): string
    {
        $key = config('app.key') ?: 'default-auth-token-fallback-key';

        return hash_hmac('sha256', $sessionId, $key);
    }

    /**
     * Parse User-Agent into approximate platform, browser, and device type.
     *
     * @return array{browser: string, platform: string, device_type: 'desktop'|'mobile'|'tablet'|'unknown'}
     */
    public function parseUserAgent(?string $userAgent): array
    {
        if (empty($userAgent)) {
            return [
                'browser' => 'Unknown Browser',
                'platform' => 'Unknown Platform',
                'device_type' => 'unknown',
            ];
        }

        // Platform detection
        $platform = 'Unknown Platform';
        $deviceType = 'desktop';

        if (stripos($userAgent, 'iPad') !== false) {
            $platform = 'iPadOS';
            $deviceType = 'tablet';
        } elseif (stripos($userAgent, 'iPhone') !== false) {
            $platform = 'iOS';
            $deviceType = 'mobile';
        } elseif (stripos($userAgent, 'Android') !== false) {
            $platform = 'Android';
            $deviceType = stripos($userAgent, 'Mobile') !== false ? 'mobile' : 'tablet';
        } elseif (stripos($userAgent, 'Windows') !== false) {
            $platform = 'Windows';
            $deviceType = 'desktop';
        } elseif (stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'Mac OS X') !== false) {
            $platform = 'macOS';
            $deviceType = 'desktop';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
            $deviceType = 'desktop';
        }

        // Browser detection
        $browser = 'Unknown Browser';

        if (stripos($userAgent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (stripos($userAgent, 'OPR') !== false || stripos($userAgent, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (stripos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $deviceType,
        ];
    }

    /**
     * Partially mask IP address for user privacy and security.
     */
    public function maskIp(?string $ip): string
    {
        if (empty($ip)) {
            return 'Unknown';
        }

        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return '127.0.0.1 (Local)';
        }

        // IPv4 masking: replace last two octets with ***
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/(\d+)\.(\d+)\.(\d+)\.(\d+)/', '$1.$2.***.***', $ip) ?? $ip;
        }

        // IPv6 masking: mask last 4 blocks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 4) {
                return implode(':', array_slice($parts, 0, 4)) . ':****:****';
            }
        }

        return 'Protected IP';
    }
}
