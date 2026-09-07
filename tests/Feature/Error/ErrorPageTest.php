<?php

declare(strict_types=1);

namespace Tests\Feature\Error;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_receives_error_page_on_forbidden_resource(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
        ]);

        // Delivery partner has no role.manage permission to access /security/roles
        $response = $this->actingAs($user)->get('/security/roles');

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 403)
        );
    }

    public function test_not_found_resource_receives_error_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/non-existent-page-url-xyz');

        $response->assertStatus(404);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
        );
    }

    public function test_api_requests_receive_standard_json_errors_not_inertia(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/security/roles');

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    }
}
