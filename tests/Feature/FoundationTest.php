<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test that obsolete foundation scaffold route is removed and returns 404.
     */
    public function test_obsolete_foundation_route_is_removed(): void
    {
        $admin = \App\Models\User::factory()->create([
            'role' => \App\Enums\UserRole::ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/foundation');

        $response->assertStatus(404);
    }

    /**
     * Test that the /health endpoint responds with expected JSON contract.
     */
    public function test_health_check_endpoint_contract(): void
    {
        $response = $this->get('/health');

        // Health endpoint returns 200 or 503 depending on Redis/DB in test environment
        $this->assertContains($response->getStatusCode(), [200, 503]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'application' => [
                    'status',
                    'version',
                    'environment',
                ],
                'database' => [
                    'status',
                ],
                'redis' => [
                    'status',
                ],
            ],
        ]);
    }

    /**
     * Test that database connection is operational.
     */
    public function test_database_connection_operational(): void
    {
        $result = DB::select('SELECT 1 as ping');

        $this->assertNotEmpty($result);
        $this->assertEquals(1, $result[0]->ping);
    }

    /**
     * Test that application configuration baseline is loaded.
     */
    public function test_application_configuration_baseline(): void
    {
        $appName = config('app.name');

        $this->assertNotEmpty($appName);
        $this->assertStringContainsString('Unique Distributors', $appName);
    }
}
