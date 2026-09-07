<?php

namespace Tests\Feature\Notification;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->notificationService = app(NotificationService::class);
    }

    public function test_can_send_operational_notification_to_user(): void
    {
        $notification = $this->notificationService->send(
            recipient: $this->user,
            type: 'ORDER_APPROVAL_REQUIRED',
            category: 'ORDERS',
            title: 'Order ORD-10023 Requires Approval',
            message: 'A new order has been submitted and awaits your review.',
            severity: 'WARNING',
            actionUrl: '/orders/10023',
            metadata: ['order_id' => 10023, 'total' => 4500.00]
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('in_app_notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'notification_type' => 'ORDER_APPROVAL_REQUIRED',
            'category' => 'ORDERS',
            'severity' => 'WARNING',
            'action_url' => '/orders/10023',
            'is_read' => false,
        ]);
        $this->assertEquals(10023, $notification->metadata['order_id']);
    }

    public function test_deterministic_deduplication_prevents_duplicate_notifications(): void
    {
        $dedupKey = 'order:approval:10023';

        $first = $this->notificationService->send(
            recipient: $this->user,
            type: 'ORDER_APPROVAL_REQUIRED',
            category: 'ORDERS',
            title: 'Order Approval',
            message: 'Review needed',
            deduplicationKey: $dedupKey
        );

        $second = $this->notificationService->send(
            recipient: $this->user,
            type: 'ORDER_APPROVAL_REQUIRED',
            category: 'ORDERS',
            title: 'Order Approval',
            message: 'Review needed again',
            deduplicationKey: $dedupKey
        );

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, InAppNotification::where('user_id', $this->user->id)->count());
    }

    public function test_can_retrieve_paginated_notifications_and_feed(): void
    {
        $this->notificationService->send(
            recipient: $this->user,
            type: 'ORDER_CREATED',
            category: 'ORDERS',
            title: 'Order 1',
            message: 'Created'
        );

        $this->notificationService->send(
            recipient: $this->user,
            type: 'PAYMENT_RECEIVED',
            category: 'PAYMENTS',
            title: 'Payment 1',
            message: 'Received'
        );

        $response = $this->actingAs($this->user)
            ->getJson('/notifications/feed');

        $response->assertOk()
            ->assertJsonStructure([
                'unread_count',
                'notifications',
            ])
            ->assertJsonPath('unread_count', 2);
    }

    public function test_can_mark_notification_as_read_and_unread_count_decrements(): void
    {
        $notification = $this->notificationService->send(
            recipient: $this->user,
            type: 'DELIVERY_DISPATCHED',
            category: 'DELIVERY',
            title: 'Dispatch',
            message: 'Dispatched'
        );

        $this->assertEquals(1, $this->notificationService->getUnreadCount($this->user));

        $response = $this->actingAs($this->user)
            ->postJson("/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertTrue($notification->fresh()->is_read);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        $this->notificationService->send(
            recipient: $this->user,
            type: 'NOTIF_1',
            category: 'ORDERS',
            title: '1',
            message: '1'
        );
        $this->notificationService->send(
            recipient: $this->user,
            type: 'NOTIF_2',
            category: 'ORDERS',
            title: '2',
            message: '2'
        );

        $this->assertEquals(2, $this->notificationService->getUnreadCount($this->user));

        $response = $this->actingAs($this->user)
            ->postJson('/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('updated_count', 2);

        $this->assertEquals(0, $this->notificationService->getUnreadCount($this->user));
    }

    public function test_notify_role_broadcasts_to_active_users_holding_role(): void
    {
        $admin2 = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'status' => AccountStatus::SUSPENDED->value,
        ]);

        $salesman = User::factory()->create([
            'role' => UserRole::SALESMAN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $dispatched = $this->notificationService->notifyRole(
            role: UserRole::ADMIN,
            type: 'SYSTEM_MAINTENANCE',
            category: 'SYSTEM',
            title: 'Scheduled Maintenance',
            message: 'Maintenance tonight at 2AM',
            severity: 'INFO'
        );

        $this->assertCount(2, $dispatched);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $this->user->id, 'notification_type' => 'SYSTEM_MAINTENANCE']);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $admin2->id, 'notification_type' => 'SYSTEM_MAINTENANCE']);
        $this->assertDatabaseMissing('in_app_notifications', ['user_id' => $inactiveAdmin->id, 'notification_type' => 'SYSTEM_MAINTENANCE']);
        $this->assertDatabaseMissing('in_app_notifications', ['user_id' => $salesman->id, 'notification_type' => 'SYSTEM_MAINTENANCE']);
    }
}
