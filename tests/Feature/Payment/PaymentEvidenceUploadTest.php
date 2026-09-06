<?php

namespace Tests\Feature\Payment;

use App\Services\Payment\PaymentEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentEvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentEvidenceService $evidenceService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->evidenceService = app(PaymentEvidenceService::class);
    }

    /**
     * Create a genuine minimal 1x1 JPEG image file.
     */
    protected function createGenuineJpeg(string $filename = 'cheque.jpg', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->image($filename, 600, 400)->size($kilobytes);
    }

    public function test_genuine_jpeg_is_stored_privately_with_uuid_path(): void
    {
        $file = $this->createGenuineJpeg('valid_cheque.jpg', 150);

        $metadata = $this->evidenceService->validateAndStoreEvidence($file);

        $this->assertArrayHasKey('evidence_object_key', $metadata);
        $this->assertArrayHasKey('evidence_original_name', $metadata);
        $this->assertEquals('valid_cheque.jpg', $metadata['evidence_original_name']);
        $this->assertEquals('image/jpeg', $metadata['evidence_mime_type']);

        $key = $metadata['evidence_object_key'];
        $this->assertMatchesRegularExpression('/^payments\/\d{4}\/\d{2}\/[a-f0-9\-]+\.jpg$/', $key);

        Storage::disk('local')->assertExists($key);
    }

    public function test_renamed_pdf_file_is_rejected(): void
    {
        // Create fake PDF with PDF header but .jpg name
        $tempPath = tempnam(sys_get_temp_dir(), 'test_pdf');
        file_put_contents($tempPath, "%PDF-1.4\n%Fake PDF binary content\n%%EOF");

        $file = new UploadedFile(
            $tempPath,
            'fraudulent_cheque.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->expectException(ValidationException::class);
        $this->evidenceService->validateAndStoreEvidence($file);

        @unlink($tempPath);
    }

    public function test_renamed_png_file_is_rejected(): void
    {
        // Create fake PNG with PNG header but .jpg name
        $tempPath = tempnam(sys_get_temp_dir(), 'test_png');
        file_put_contents($tempPath, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR" . str_repeat('A', 50));

        $file = new UploadedFile(
            $tempPath,
            'fake_cheque.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->expectException(ValidationException::class);
        $this->evidenceService->validateAndStoreEvidence($file);

        @unlink($tempPath);
    }

    public function test_renamed_executable_file_is_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_exe');
        file_put_contents($tempPath, "MZ\x90\x00\x03\x00\x00\x00" . str_repeat('X', 50));

        $file = new UploadedFile(
            $tempPath,
            'malware.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->expectException(ValidationException::class);
        $this->evidenceService->validateAndStoreEvidence($file);

        @unlink($tempPath);
    }

    public function test_file_with_pdf_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->expectException(ValidationException::class);
        $this->evidenceService->validateAndStoreEvidence($file);
    }

    public function test_file_with_png_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->image('document.png');

        $this->expectException(ValidationException::class);
        $this->evidenceService->validateAndStoreEvidence($file);
    }

    public function test_oversized_file_exceeding_5mb_is_rejected(): void
    {
        $file = UploadedFile::fake()->image('huge_photo.jpg')->size(6 * 1024); // 6 MB

        $this->expectException(ValidationException::class);
        $this->evidenceService->validateAndStoreEvidence($file);
    }

    public function test_malformed_jpeg_with_magic_bytes_but_corrupted_body_is_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_corrupt');
        // Start with magic bytes FF D8 FF but corrupt rest
        file_put_contents($tempPath, "\xFF\xD8\xFF\x00CORRUPT_PAYLOAD_NOT_A_REAL_IMAGE");

        $file = new UploadedFile(
            $tempPath,
            'corrupt.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->expectException(ValidationException::class);
        $this->evidenceService->validateAndStoreEvidence($file);

        @unlink($tempPath);
    }
}
