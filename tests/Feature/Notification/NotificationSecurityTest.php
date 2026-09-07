<?php

namespace Tests\Feature\Notification;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create([
            'role' => UserRole::SALESMAN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->userB = User::factory()->create([
            'role' => UserRole::SALESMAN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->notificationService = app(NotificationService::class);
    }

    public function test_user_a_cannot_mark_user_b_notification_as_read_idor_prevention(): void
    {
        $notificationB = $this->notificationService->send(
            recipient: $this->userB,
            type: 'TARGET_B',
            category: 'ORDERS',
            title: 'For User B',
            message: 'Secret payload'
        );

        $response = $this->actingAs($this->userA)
            ->postJson("/notifications/{$notificationB->id}/read");

        $response->assertStatus(404);
        $this->assertFalse($notificationB->fresh()->is_read);
    }

    public function test_user_a_cannot_delete_user_b_notification_idor_prevention(): void
    {
        $notificationB = $this->notificationService->send(
            recipient: $this->userB,
            type: 'TARGET_B',
            category: 'ORDERS',
            title: 'For User B',
            message: 'Secret payload'
        );

        $response = $this->actingAs($this->userA)
            ->deleteJson("/notifications/{$notificationB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('in_app_notifications', ['id' => $notificationB->id]);
    }

    public function test_feed_only_returns_authenticated_user_notifications(): void
    {
        $this->notificationService->send(
            recipient: $this->userA,
            type: 'A_NOTIF',
            category: 'ORDERS',
            title: 'A title',
            message: 'A msg'
        );

        $this->notificationService->send(
            recipient: $this->userB,
            type: 'B_NOTIF',
            category: 'ORDERS',
            title: 'B title',
            message: 'B msg'
        );

        $response = $this->actingAs($this->userA)
            ->getJson('/notifications/feed');

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'A title');
    }

    public function test_unauthenticated_request_is_denied(): void
    {
        $response = $this->getJson('/notifications/feed');
        $response->assertStatus(401);
    }
}
