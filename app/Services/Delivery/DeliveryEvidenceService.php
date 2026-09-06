<?php

namespace App\Services\Delivery;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryEvidenceService
{
    public const DISK = 'local';
    public const DIRECTORY = 'private/delivery_evidence';
    public const MAX_BYTES = 5242880; // 5MB

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
        $filename = sprintf('delivery_%d_pod_%s.%s', $deliveryId, Str::random(24), $extension);
        $path = self::DIRECTORY . '/' . $filename;

        Storage::disk(self::DISK)->put($path, file_get_contents($realPath));

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
        $filename = sprintf('delivery_%d_sig_%s.%s', $deliveryId, Str::random(24), $extension);
        $path = self::DIRECTORY . '/signatures/' . $filename;

        Storage::disk(self::DISK)->put($path, file_get_contents($realPath));

        return $path;
    }
}
