<?php

namespace App\Services\Notification;

use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        protected NotificationPreferenceService $preferenceService
    ) {}

    /**
     * Send an in-app operational notification to a user.
     *
     * @param  User  $recipient  The targeted recipient user
     * @param  string  $type  Semantic notification type (e.g. ORDER_REVIEW_REQUIRED)
     * @param  string  $category  Notification category (ORDERS, PAYMENTS, INVENTORY, DELIVERY, RETURNS, SECURITY, SYSTEM)
     * @param  string  $title  Brief, user-friendly notification headline
     * @param  string  $message  Descriptive notification body
     * @param  string  $severity  INFO, SUCCESS, WARNING, CRITICAL
     * @param  string|null  $actionUrl  Deep link URL for direct navigation
     * @param  array<string, mixed>|null  $metadata  Additional contextual details
     * @param  string|null  $deduplicationKey  Deterministic key to prevent duplicate notifications
     */
    public function send(
        User $recipient,
        string $type,
        string $category,
        string $title,
        string $message,
        string $severity = 'INFO',
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?string $deduplicationKey = null
    ): ?InAppNotification {
        $categoryUpper = strtoupper(trim($category));
        $severityUpper = strtoupper(trim($severity));

        // 1. Verify recipient notification preference
        if (! $this->preferenceService->isNotificationEnabled($recipient, $categoryUpper, 'in_app')) {
            return null;
        }

        // 2. Deterministic Deduplication: check if unread notification with same key already exists
        if ($deduplicationKey !== null) {
            $existing = InAppNotification::where('user_id', $recipient->id)
                ->where('deduplication_key', $deduplicationKey)
                ->where('is_read', false)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        // 3. Create notification record
        return DB::transaction(function () use (
            $recipient,
            $type,
            $categoryUpper,
            $title,
            $message,
            $severityUpper,
            $actionUrl,
            $metadata,
            $deduplicationKey
        ) {
            return InAppNotification::create([
                'user_id' => $recipient->id,
                'type' => $type,
                'category' => $categoryUpper,
                'title' => $title,
                'message' => $message,
                'severity' => in_array($severityUpper, ['INFO', 'SUCCESS', 'WARNING', 'CRITICAL'], true) ? $severityUpper : 'INFO',
                'action_url' => $actionUrl,
                'metadata' => $metadata ?? [],
                'deduplication_key' => $deduplicationKey,
                'is_read' => false,
                'read_at' => null,
            ]);
        });
    }

    /**
     * Broadcast notification to all active users holding a specific role.
     *
     * @return array<int, InAppNotification>
     */
    public function notifyRole(
        UserRole $role,
        string $type,
        string $category,
        string $title,
        string $message,
        string $severity = 'INFO',
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?string $deduplicationPrefix = null
    ): array {
        $users = User::where('role', $role->value)
            ->where('status', 'ACTIVE')
            ->get();

        $notifications = [];

        foreach ($users as $user) {
            $dedupKey = $deduplicationPrefix !== null ? "{$deduplicationPrefix}:user:{$user->id}" : null;
            $notif = $this->send(
                recipient: $user,
                type: $type,
                category: $category,
                title: $title,
                message: $message,
                severity: $severity,
                actionUrl: $actionUrl,
                metadata: $metadata,
                deduplicationKey: $dedupKey
            );

            if ($notif !== null) {
                $notifications[] = $notif;
            }
        }

        return $notifications;
    }

    /**
     * Retrieve paginated in-app notifications for the authenticated user.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getNotificationsForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = InAppNotification::where('user_id', $user->id);

        if (! empty($filters['is_read'])) {
            if ($filters['is_read'] === 'unread' || $filters['is_read'] === '0' || $filters['is_read'] === false) {
                $query->unread();
            } elseif ($filters['is_read'] === 'read' || $filters['is_read'] === '1' || $filters['is_read'] === true) {
                $query->read();
            }
        }

        if (! empty($filters['category'])) {
            $query->forCategory($filters['category']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', strtoupper(trim((string) $filters['severity'])));
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'ilike', "%{$term}%")
                    ->orWhere('message', 'ilike', "%{$term}%")
                    ->orWhere('type', 'ilike', "%{$term}%");
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 5), 100);

        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get real-time unread notification count for user.
     */
    public function getUnreadCount(User $user): int
    {
        return InAppNotification::where('user_id', $user->id)
            ->unread()
            ->count();
    }

    /**
     * Mark a single notification as read, enforcing user-scoping (anti-IDOR).
     *
     * @throws DomainException if notification does not exist or does not belong to user
     */
    public function markAsRead(User $user, int|string $notificationId): InAppNotification
    {
        $notification = InAppNotification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if ($notification === null) {
            throw new DomainException('Notification not found or access denied.');
        }

        $notification->markAsRead();

        return $notification;
    }

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return InAppNotification::where('user_id', $user->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Delete a notification belonging to user.
     *
     * @throws DomainException if notification does not exist or does not belong to user
     */
    public function deleteNotification(User $user, int|string $notificationId): bool
    {
        $notification = InAppNotification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if ($notification === null) {
            throw new DomainException('Notification not found or access denied.');
        }

        return (bool) $notification->delete();
    }
}
