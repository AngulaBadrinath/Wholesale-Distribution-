<?php

namespace App\Services\Return;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnEvidenceService
{
    public const DISK = 'local';
    public const DIRECTORY = 'private/return_evidence';
    public const MAX_BYTES = 5242880; // 5MB

    /**
     * Store and validate an uploaded return condition photo/evidence.
     *
     * @throws ValidationException
     */
    public function storeEvidence(UploadedFile $file, int $returnRequestId): string
    {
        // 1. File size check (5MB max)
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'evidence_photos' => 'The return evidence file must not exceed 5MB.',
            ]);
        }

        // 2. Server-side magic bytes validation: JPEG (\xFF\xD8\xFF) or PNG (\x89PNG\r\n\x1a\n)
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'evidence_photos' => 'Unable to read uploaded evidence file.',
            ]);
        }

        $handle = fopen($realPath, 'rb');
        $header = fread($handle, 8);
        fclose($handle);

        $isJpeg = str_starts_with($header, "\xFF\xD8\xFF");
        $isPng = str_starts_with($header, "\x89PNG\r\n\x1a\n");

        if (! $isJpeg && ! $isPng) {
            throw ValidationException::withMessages([
                'evidence_photos' => 'The return evidence file must be a genuine JPEG or PNG image.',
            ]);
        }

        $extension = $isJpeg ? 'jpg' : 'png';
        $filename = sprintf('return_%d_photo_%s.%s', $returnRequestId, Str::random(24), $extension);
        $path = self::DIRECTORY . '/' . $filename;

        Storage::disk(self::DISK)->put($path, file_get_contents($realPath));

        return $path;
    }
}
