<?php

namespace Tests\Feature\Notification;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Notification\NotificationPreferenceService;
use App\Services\Notification\NotificationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected NotificationPreferenceService $preferenceService;
    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::SALESMAN->value,
            'status' => AccountStatus::ACTIVE->value,
        ]);

        $this->preferenceService = app(NotificationPreferenceService::class);
        $this->notificationService = app(NotificationService::class);
    }

    public function test_user_preferences_default_to_enabled(): void
    {
        $prefs = $this->preferenceService->getUserPreferences($this->user);

        $this->assertCount(7, $prefs);
        foreach ($prefs as $pref) {
            $this->assertTrue($pref['is_in_app_enabled']);
        }
    }

    public function test_user_can_disable_configurable_category_and_suppress_notifications(): void
    {
        // 1. Disable INVENTORY notifications for user
        $this->preferenceService->updatePreferences($this->user, [
            'INVENTORY' => false,
        ]);

        $this->assertFalse($this->preferenceService->isNotificationEnabled($this->user, 'INVENTORY'));

        // 2. Sending INVENTORY notification returns null and is not stored
        $result = $this->notificationService->send(
            recipient: $this->user,
            type: 'LOW_STOCK_ALERT',
            category: 'INVENTORY',
            title: 'Low Stock',
            message: 'Stock is low'
        );

        $this->assertNull($result);
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $this->user->id,
            'category' => 'INVENTORY',
        ]);
    }

    public function test_mandatory_categories_cannot_be_disabled_via_service(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Category 'SECURITY' is mandatory and cannot be disabled.");

        $this->preferenceService->updatePreferences($this->user, [
            'SECURITY' => false,
        ]);
    }

    public function test_mandatory_categories_always_deliver_notifications_regardless_of_attempts(): void
    {
        $this->assertTrue($this->preferenceService->isNotificationEnabled($this->user, 'SECURITY'));
        $this->assertTrue($this->preferenceService->isNotificationEnabled($this->user, 'SYSTEM'));

        $securityNotification = $this->notificationService->send(
            recipient: $this->user,
            type: 'PASSWORD_CHANGED',
            category: 'SECURITY',
            title: 'Security Alert',
            message: 'Your password was changed.',
            severity: 'CRITICAL'
        );

        $this->assertNotNull($securityNotification);
        $this->assertDatabaseHas('in_app_notifications', [
            'id' => $securityNotification->id,
            'user_id' => $this->user->id,
            'category' => 'SECURITY',
        ]);
    }

    public function test_http_endpoint_updates_preferences(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/notifications/preferences', [
                'preferences' => [
                    'ORDERS' => true,
                    'PAYMENTS' => false,
                    'DELIVERY' => false,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Notification preferences updated successfully.');

        $this->assertTrue($this->preferenceService->isNotificationEnabled($this->user, 'ORDERS'));
        $this->assertFalse($this->preferenceService->isNotificationEnabled($this->user, 'PAYMENTS'));
        $this->assertFalse($this->preferenceService->isNotificationEnabled($this->user, 'DELIVERY'));
    }

    public function test_http_endpoint_rejects_disabling_mandatory_categories(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/notifications/preferences', [
                'preferences' => [
                    'SECURITY' => false,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Category 'SECURITY' is mandatory and cannot be disabled.");
    }
}
