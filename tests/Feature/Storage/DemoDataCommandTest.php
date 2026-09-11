<?php

namespace Tests\Feature\Storage;

use App\Models\Category;
use App\Models\Product;
use App\Services\Demo\DemoDataGeneratorService;
use Database\Seeders\ManualTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('s3');
    }

    public function test_manual_testing_seeder_and_demo_generator_runs_successfully(): void
    {
        $this->seed(ManualTestingSeeder::class);

        // Verify categories and products were seeded
        $this->assertGreaterThan(0, Category::count());
        $this->assertGreaterThan(0, Product::count());

        // Test DemoDataGeneratorService image generation
        $demoGenerator = app(DemoDataGeneratorService::class);
        $count = $demoGenerator->seedProductImages('local');
        $this->assertGreaterThan(0, $count);

        $productWithImage = Product::whereHas('images')->first();
        $this->assertNotNull($productWithImage);
        $this->assertNotEmpty($productWithImage->images);

        // Test operational evidence generation
        $evCounts = $demoGenerator->seedOperationalEvidence('local');
        $this->assertGreaterThanOrEqual(0, $evCounts['payments']);
        $this->assertGreaterThanOrEqual(0, $evCounts['deliveries']);
    }

    public function test_production_environment_guard_aborts_seeder(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $seeder = new ManualTestingSeeder();
        $seeder->run();
    }
}
