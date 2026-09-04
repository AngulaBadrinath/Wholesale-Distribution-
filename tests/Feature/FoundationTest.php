<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    /**
     * Test that the application boots and renders the Inertia root page.
     */
    public function test_application_boots_and_renders_inertia_welcome_view(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('phpVersion')
            ->has('laravelVersion')
        );
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
        $this->assertStringContainsString('Wholesale Distribution', $appName);
    }
}
