<?php

namespace Tests\Feature\Storage;

use App\Services\Storage\StorageManagerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorageManagerServiceTest extends TestCase
{
    protected StorageManagerService $storageManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageManager = app(StorageManagerService::class);
        Storage::fake('local');
        Storage::fake('s3');
    }

    public function test_key_generation_follows_canonical_domain_structure(): void
    {
        $key = $this->storageManager->generateKey('payments', 42, 'evidence', 'jpg');
        $this->assertMatchesRegularExpression('#^payments/42/evidence/[a-f0-9\-]{36}\.jpg$#', $key);

        $keyDelivery = $this->storageManager->generateKey('deliveries', 108, 'signatures', 'png');
        $this->assertMatchesRegularExpression('#^deliveries/108/signatures/[a-f0-9\-]{36}\.png$#', $keyDelivery);

        $keyProduct = $this->storageManager->generateKey('products', 15, 'images', 'webp');
        $this->assertMatchesRegularExpression('#^products/15/images/[a-f0-9\-]{36}\.webp$#', $keyProduct);
    }

    public function test_put_exists_get_delete_lifecycle_on_storage_manager(): void
    {
        $key = 'test/domain/sample.txt';
        $content = 'Sample content for storage manager test';

        $this->assertTrue($this->storageManager->put($key, $content, 's3'));
        $this->assertTrue($this->storageManager->exists($key, 's3'));
        $this->assertSame($content, $this->storageManager->get($key, 's3'));

        $this->assertTrue($this->storageManager->delete($key, 's3'));
        $this->assertFalse($this->storageManager->exists($key, 's3'));
    }

    public function test_temporary_url_generation(): void
    {
        $key = 'payments/1/evidence/test.jpg';
        $this->storageManager->put($key, 'fake image data', 's3');

        $url = $this->storageManager->temporaryUrl($key, 15, [], 's3');
        $this->assertNotNull($url);
        $this->assertIsString($url);
    }

    public function test_compensating_delete_safely_removes_staged_object(): void
    {
        $key = 'staged/temp/failure_rollback.jpg';
        $this->storageManager->put($key, 'temporary payload', 's3');
        $this->assertTrue($this->storageManager->exists($key, 's3'));

        $this->storageManager->compensateDelete($key, 's3');
        $this->assertFalse($this->storageManager->exists($key, 's3'));
    }

    public function test_jpeg_magic_byte_validation_accepts_valid_jpeg(): void
    {
        // 1x1 valid JPEG binary
        $jpegData = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00\xFF\xDB\x00C\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\t\t\x08\n\x0C\x14\r\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C $.' \",#\x1C\x1C(7),01444\x1F'9=82<.342\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\t\n\x0B\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00\xBF\x00\xFF\xD9";
        
        $tempPath = tempnam(sys_get_temp_dir(), 'test_jpg');
        file_put_contents($tempPath, $jpegData);

        $file = new UploadedFile($tempPath, 'valid_evidence.jpg', 'image/jpeg', null, true);

        $mime = $this->storageManager->validateJpegBinary($file);
        $this->assertSame('image/jpeg', $mime);

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    public function test_jpeg_magic_byte_validation_rejects_spoofed_file(): void
    {
        $fakeJpeg = "This is not a real JPEG image but has jpg extension";
        $tempPath = tempnam(sys_get_temp_dir(), 'fake_jpg');
        file_put_contents($tempPath, $fakeJpeg);

        $file = new UploadedFile($tempPath, 'fake.jpg', 'image/jpeg', null, true);

        $this->expectException(ValidationException::class);
        try {
            $this->storageManager->validateJpegBinary($file);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
