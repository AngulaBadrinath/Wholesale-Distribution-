<?php

namespace App\Services\Storage;

use DateTimeInterface;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorageManagerService
{
    /**
     * Canonical supported image MIME types and default extensions.
     */
    public const IMAGE_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Resolve the target filesystem disk name.
     */
    public function getDisk(?string $preferredDisk = null): string
    {
        if (! empty($preferredDisk)) {
            return $preferredDisk;
        }

        return config('filesystems.default', 'local');
    }

    /**
     * Generate a deterministic, collision-safe, domain-scoped object key.
     * Format: {domain}/{entityId}/{subType}/{uuid}.{extension}
     */
    public function generateKey(
        string $domain,
        string|int $entityId,
        string $subType,
        string $extension
    ): string {
        $sanitizedDomain = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($domain));
        $sanitizedEntity = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $entityId);
        $sanitizedSubType = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($subType));
        $cleanExt = ltrim(preg_replace('/[^a-zA-Z0-9]/', '', strtolower($extension)), '.');
        $uuid = (string) Str::uuid();

        if (empty($sanitizedSubType)) {
            return "{$sanitizedDomain}/{$sanitizedEntity}/{$uuid}.{$cleanExt}";
        }

        return "{$sanitizedDomain}/{$sanitizedEntity}/{$sanitizedSubType}/{$uuid}.{$cleanExt}";
    }

    /**
     * Put content into the resolved storage disk.
     * Note: ACL is omitted as Bucket Owner Enforced / ACLs disabled is standard.
     */
    public function put(string $path, mixed $contents, ?string $disk = null): bool
    {
        $targetDisk = $this->getDisk($disk);

        try {
            return (bool) Storage::disk($targetDisk)->put($path, $contents);
        } catch (Exception $e) {
            Log::error('StorageManagerService: put failed', [
                'disk' => $targetDisk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store an UploadedFile under a given directory with a specific filename.
     */
    public function putFileAs(
        string $directory,
        UploadedFile $file,
        string $filename,
        ?string $disk = null
    ): string|false {
        $targetDisk = $this->getDisk($disk);

        try {
            return Storage::disk($targetDisk)->putFileAs($directory, $file, $filename);
        } catch (Exception $e) {
            Log::error('StorageManagerService: putFileAs failed', [
                'disk' => $targetDisk,
                'directory' => $directory,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if an object exists in storage.
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        if (empty($path)) {
            return false;
        }

        $targetDisk = $this->getDisk($disk);

        try {
            return (bool) Storage::disk($targetDisk)->exists($path);
        } catch (Exception $e) {
            Log::warning('StorageManagerService: exists check threw exception', [
                'disk' => $targetDisk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Retrieve file contents from storage.
     */
    public function get(string $path, ?string $disk = null): ?string
    {
        $targetDisk = $this->getDisk($disk);

        try {
            return Storage::disk($targetDisk)->get($path);
        } catch (Exception $e) {
            Log::error('StorageManagerService: get failed', [
                'disk' => $targetDisk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete an object from storage.
     */
    public function delete(string $path, ?string $disk = null): bool
    {
        $targetDisk = $this->getDisk($disk);

        try {
            return (bool) Storage::disk($targetDisk)->delete($path);
        } catch (Exception $e) {
            Log::error('StorageManagerService: delete failed', [
                'disk' => $targetDisk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate a short-lived presigned URL for private S3 storage,
     * or fallback to local URL if driver does not support presigned URLs.
     */
    public function temporaryUrl(
        string $path,
        int|DateTimeInterface $expiration = 15,
        array|string|null $options = [],
        ?string $disk = null
    ): ?string {
        if (empty($path)) {
            return null;
        }

        if (is_string($options)) {
            $disk = $options;
            $options = [];
        }

        $options = is_array($options) ? $options : [];
        $targetDisk = $this->getDisk($disk);
        $expiresAt = is_int($expiration) ? now()->addMinutes($expiration) : $expiration;

        try {
            return Storage::disk($targetDisk)->temporaryUrl($path, $expiresAt, $options);
        } catch (Exception $e) {
            // Fallback for local/test driver
            try {
                return Storage::disk($targetDisk)->url($path);
            } catch (Exception) {
                return null;
            }
        }
    }

    /**
     * Compensating rollback helper: safely deletes an S3 object if database transaction fails.
     */
    public function compensateDelete(string $path, ?string $disk = null): void
    {
        if (empty($path)) {
            return;
        }

        try {
            $this->delete($path, $disk);
        } catch (Exception $e) {
            Log::warning('StorageManagerService: compensating delete failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Authoritative binary magic byte inspection for image uploads.
     *
     * @throws ValidationException
     */
    public function validateImageBinary(
        UploadedFile $file,
        array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'],
        int $maxBytes = 5242880,
        string $field = 'file'
    ): string {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file is corrupted or failed to transfer.',
            ]);
        }

        if ($file->getSize() > $maxBytes) {
            $maxMb = round($maxBytes / 1048576, 1);
            throw ValidationException::withMessages([
                $field => "The uploaded file must not exceed {$maxMb}MB.",
            ]);
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file could not be read from temp storage.',
            ]);
        }

        // Fileinfo inspection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $realPath);
        finfo_close($finfo);

        if (! is_string($detectedMime) || ! in_array($detectedMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file format is not supported. Allowed: ' . implode(', ', $allowedMimes),
            ]);
        }

        // Binary image integrity
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw ValidationException::withMessages([
                $field => 'The uploaded image contains malformed or corrupted pixel data.',
            ]);
        }

        // Prohibit SVG
        if (str_contains(strtolower($detectedMime), 'svg') || str_contains(strtolower($detectedMime), 'xml')) {
            throw ValidationException::withMessages([
                $field => 'SVG and XML vector files are strictly prohibited.',
            ]);
        }

        return $detectedMime;
    }

    /**
     * Authoritative binary magic byte inspection strictly for JPEG files (Cheque & Money Order evidence).
     *
     * @throws ValidationException
     */
    public function validateJpegBinary(
        UploadedFile $file,
        int $maxBytes = 5242880,
        string $field = 'evidence'
    ): string {
        $mime = $this->validateImageBinary($file, ['image/jpeg'], $maxBytes, $field);

        $realPath = $file->getRealPath();
        $handle = fopen($realPath, 'rb');
        $header = fread($handle, 3);
        fclose($handle);

        if (! str_starts_with($header, "\xFF\xD8\xFF")) {
            throw ValidationException::withMessages([
                $field => 'The file does not match genuine JPEG binary magic bytes.',
            ]);
        }

        return $mime;
    }
}
