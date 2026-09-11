<?php

namespace Tests\Feature\Storage;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class S3IntegrationTest extends TestCase
{
    protected string $testKey = 'healthcheck/unique-distributors.txt';
    protected string $testContent = 'Unique Distributors S3 connectivity test';

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('filesystems.disks.s3.key')) || empty(config('filesystems.disks.s3.secret'))) {
            $this->markTestSkipped('S3 credentials are not configured in the test environment.');
        }
    }

    protected function tearDown(): void
    {
        // Ensure cleanup after test
        try {
            Storage::disk('s3')->delete($this->testKey);
        } catch (\Throwable $e) {
            // suppress cleanup errors
        }

        parent::tearDown();
    }

    public function test_s3_put_exists_get_delete_lifecycle(): void
    {
        $disk = Storage::disk('s3');

        // 1. PUT
        $putResult = $disk->put($this->testKey, $this->testContent);
        $this->assertTrue($putResult, 'Storage::disk(s3)->put() must return true.');

        // 2. EXISTS
        $existsResult = $disk->exists($this->testKey);
        $this->assertTrue($existsResult, 'Storage::disk(s3)->exists() must return true for existing object.');

        // 3. GET
        $getContent = $disk->get($this->testKey);
        $this->assertEquals($this->testContent, $getContent, 'Storage::disk(s3)->get() must return exact content.');

        // 4. DELETE
        $deleteResult = $disk->delete($this->testKey);
        $this->assertTrue($deleteResult, 'Storage::disk(s3)->delete() must return true on successful deletion.');

        // 5. EXISTS AFTER DELETE
        $existsAfterDelete = $disk->exists($this->testKey);
        $this->assertFalse($existsAfterDelete, 'Storage::disk(s3)->exists() must return false after deletion.');

        // 6. Direct AWS SDK Verification
        $s3Config = config('filesystems.disks.s3');
        $s3ClientConfig = [
            'version' => 'latest',
            'region' => $s3Config['region'],
            'credentials' => [
                'key' => $s3Config['key'],
                'secret' => $s3Config['secret'],
            ],
            'use_path_style_endpoint' => $s3Config['use_path_style_endpoint'] ?? false,
            'http' => $s3Config['http'] ?? [],
        ];
        $s3 = new S3Client($s3ClientConfig);
        $list = $s3->listObjectsV2([
            'Bucket' => $s3Config['bucket'],
            'Prefix' => $this->testKey,
        ]);

        $this->assertEmpty($list['Contents'] ?? [], 'S3 bucket must not contain the healthcheck object after test completion.');
    }
}
