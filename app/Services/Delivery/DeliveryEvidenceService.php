<?php

namespace App\Services\Delivery;

use App\Services\Storage\StorageManagerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryEvidenceService
{
    public const MAX_BYTES = 5242880; // 5MB

    public function __construct(
        protected StorageManagerService $storageManager
    ) {}

    /**
     * Get the configured storage disk for delivery evidence.
     */
    public function getDisk(): string
    {
        return config('filesystems.delivery_evidence_disk', env('FILESYSTEM_DISK', 'local'));
    }

    /**
     * Store and validate an uploaded Proof of Delivery (POD) photo/document.
     *
     * @throws ValidationException
     */
    public function storePodEvidence(UploadedFile $file, int $deliveryId): string
    {
        // 1. File size check (5MB max)
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'pod_evidence' => 'The POD evidence file must not exceed 5MB.',
            ]);
        }

        // 2. Server-side magic bytes validation: JPEG (\xFF\xD8\xFF) or PNG (\x89PNG\r\n\x1a\n)
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'pod_evidence' => 'Unable to read uploaded evidence file.',
            ]);
        }

        $handle = fopen($realPath, 'rb');
        $header = fread($handle, 8);
        fclose($handle);

        $isJpeg = str_starts_with($header, "\xFF\xD8\xFF");
        $isPng = str_starts_with($header, "\x89PNG\r\n\x1a\n");

        if (! $isJpeg && ! $isPng) {
            throw ValidationException::withMessages([
                'pod_evidence' => 'The POD evidence file must be a genuine JPEG or PNG image.',
            ]);
        }

        $extension = $isJpeg ? 'jpg' : 'png';
        $uuid = (string) Str::uuid();
        $path = "deliveries/{$deliveryId}/pod/{$uuid}.{$extension}";

        $disk = $this->getDisk();
        $stored = $this->storageManager->put($path, file_get_contents($realPath), $disk);

        if (! $stored) {
            throw ValidationException::withMessages([
                'pod_evidence' => 'Failed to persist POD evidence to secure storage.',
            ]);
        }

        return $path;
    }

    /**
     * Store an uploaded recipient signature image.
     *
     * @throws ValidationException
     */
    public function storeSignature(UploadedFile $file, int $deliveryId): string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'recipient_signature' => 'The signature file must not exceed 5MB.',
            ]);
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'recipient_signature' => 'Unable to read signature file.',
            ]);
        }

        $handle = fopen($realPath, 'rb');
        $header = fread($handle, 8);
        fclose($handle);

        $isJpeg = str_starts_with($header, "\xFF\xD8\xFF");
        $isPng = str_starts_with($header, "\x89PNG\r\n\x1a\n");

        if (! $isJpeg && ! $isPng) {
            throw ValidationException::withMessages([
                'recipient_signature' => 'The signature must be a genuine JPEG or PNG image.',
            ]);
        }

        $extension = $isJpeg ? 'jpg' : 'png';
        $uuid = (string) Str::uuid();
        $path = "deliveries/{$deliveryId}/signatures/{$uuid}.{$extension}";

        $disk = $this->getDisk();
        $stored = $this->storageManager->put($path, file_get_contents($realPath), $disk);

        if (! $stored) {
            throw ValidationException::withMessages([
                'recipient_signature' => 'Failed to persist delivery signature to secure storage.',
            ]);
        }

        return $path;
    }

    /**
     * Generate temporary signed URL for viewing POD or signature evidence.
     */
    public function getTemporaryUrl(?string $path, int $expirationMinutes = 15): ?string
    {
        if (empty($path)) {
            return null;
        }

        $disk = $this->getDisk();
        if (! $this->storageManager->exists($path, $disk)) {
            return null;
        }

        return $this->storageManager->temporaryUrl($path, $expirationMinutes, [], $disk);
    }
}

