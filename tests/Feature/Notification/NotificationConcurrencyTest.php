<?php

namespace Tests\Feature\Notification;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationConcurrencyTest extends TestCase
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

    public function test_concurrent_deduplicated_events_yield_single_notification_record(): void
    {
        $dedupKey = 'order:submit:10099';

        // Simulate multiple rapid queue or retry triggers with same key
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->notificationService->send(
                recipient: $this->user,
                type: 'ORDER_SUBMITTED',
                category: 'ORDERS',
                title: 'Order ORD-10099 Submitted',
                message: 'Order was submitted for processing',
                deduplicationKey: $dedupKey
            );
        }

        $this->assertCount(5, $results);
        $firstId = $results[0]->id;
        foreach ($results as $res) {
            $this->assertEquals($firstId, $res->id);
        }

        $this->assertEquals(1, InAppNotification::where('deduplication_key', $dedupKey)->count());
    }
}
