<?php

namespace App\Services\Payment;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentEvidenceService
{
    /**
     * Maximum allowed evidence upload size in bytes (5 MB).
     */
    public const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /**
     * JPEG magic bytes sequence (FF D8 FF).
     */
    public const JPEG_MAGIC_BYTES = "\xFF\xD8\xFF";

    /**
     * Default presigned temporary preview URL TTL in minutes.
     */
    public const PREVIEW_URL_EXPIRATION_MINUTES = 15;

    /**
     * Validate and store visual payment evidence into private storage.
     *
     * @param  UploadedFile  $file
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateAndStoreEvidence(UploadedFile $file): array
    {
        // 1. Basic upload integrity
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'evidence' => 'The uploaded payment evidence file is corrupted or failed to transfer.',
            ]);
        }

        // 2. Enforce file size limit
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'evidence' => 'The payment evidence file exceeds the maximum permitted size of 5 MB.',
            ]);
        }

        // 3. Client extension verification (must be jpg or jpeg)
        $clientExt = strtolower($file->getClientOriginalExtension());
        if (! in_array($clientExt, ['jpg', 'jpeg'], true)) {
            throw ValidationException::withMessages([
                'evidence' => 'Payment evidence must be a valid JPEG image (.jpg or .jpeg).',
            ]);
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'evidence' => 'Unable to read the temporary uploaded file.',
            ]);
        }

        // 4. Inspect magic bytes (\xFF\xD8\xFF)
        $handle = @fopen($realPath, 'rb');
        if (! $handle) {
            throw ValidationException::withMessages([
                'evidence' => 'Failed to open the uploaded file for binary inspection.',
            ]);
        }
        $magicBytes = fread($handle, 3);
        fclose($handle);

        if ($magicBytes !== self::JPEG_MAGIC_BYTES) {
            throw ValidationException::withMessages([
                'evidence' => 'The uploaded file is not a genuine JPEG image. Header magic bytes verification failed.',
            ]);
        }

        // 5. Inspect MIME type via Fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $realPath) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mimeType !== 'image/jpeg') {
            throw ValidationException::withMessages([
                'evidence' => 'The uploaded file MIME type does not match image/jpeg.',
            ]);
        }

        // 6. Inspect structural image dimensions and type
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false || ($imageInfo[2] ?? null) !== IMAGETYPE_JPEG) {
            throw ValidationException::withMessages([
                'evidence' => 'The uploaded file contains malformed image data and cannot be parsed as JPEG.',
            ]);
        }

        // 7. Generate random, unguessable private object key: payments/{year}/{month}/{uuid}.jpg
        $year = date('Y');
        $month = date('m');
        $uuid = (string) Str::uuid();
        $objectKey = "payments/{$year}/{$month}/{$uuid}.jpg";

        // 8. Store privately
        $disk = $this->getDisk();
        $contents = file_get_contents($realPath);
        $stored = Storage::disk($disk)->put($objectKey, $contents, 'private');

        if (! $stored) {
            throw ValidationException::withMessages([
                'evidence' => 'Failed to persist payment evidence to secure storage.',
            ]);
        }

        // 9. Return metadata payload
        return [
            'evidence_object_key' => $objectKey,
            'evidence_original_name' => $file->getClientOriginalName(),
            'evidence_mime_type' => 'image/jpeg',
            'evidence_size_bytes' => $file->getSize(),
            'evidence_uploaded_at' => now(),
        ];
    }

    /**
     * Authorize and generate a secure, temporary presigned preview URL for payment evidence.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function getTemporaryPreviewUrl(Payment $payment, User $actor, int $expirationMinutes = self::PREVIEW_URL_EXPIRATION_MINUTES): string
    {
        // 1. Verify payment.view permission
        if (! $actor->hasPermission(Permission::PAYMENT_VIEW)) {
            throw new AuthorizationException('You do not possess the required permission [payment.view] to preview payment evidence.');
        }

        // 2. Resource scope / Anti-IDOR check
        if ($actor->role === UserRole::SALESMAN) {
            $isAssignedSalesman = $payment->customer && $payment->customer->salesman_id === $actor->id;
            $isRecorder = $payment->recorded_by === $actor->id;

            if (! $isAssignedSalesman && ! $isRecorder) {
                throw new AuthorizationException('You are not authorized to access payment evidence for this customer account.');
            }
        }

        // 3. Ensure evidence exists
        if (! $payment->hasEvidence() || empty($payment->evidence_object_key)) {
            throw ValidationException::withMessages([
                'payment' => 'This payment record has no visual evidence attached.',
            ]);
        }

        $disk = $this->getDisk();
        $objectKey = $payment->evidence_object_key;

        // 4. Verify object exists in storage
        if (! Storage::disk($disk)->exists($objectKey)) {
            throw ValidationException::withMessages([
                'payment' => 'The payment evidence file was not found in secure storage.',
            ]);
        }

        // 5. Generate presigned URL or private stream URL depending on driver
        $driver = config("filesystems.disks.{$disk}.driver", 'local');

        if ($driver === 's3') {
            return Storage::disk($disk)->temporaryUrl(
                $objectKey,
                now()->addMinutes($expirationMinutes)
            );
        }

        // Local / Test driver fallback: Route to authenticated evidence stream endpoint
        return route('admin.payments.evidence.stream', [
            'payment' => $payment->id,
            'expires' => now()->addMinutes($expirationMinutes)->timestamp,
        ]);
    }

    /**
     * Get the configured storage disk for payment evidence.
     */
    public function getDisk(): string
    {
        return config('filesystems.payment_evidence_disk', env('FILESYSTEM_DISK', 'local'));
    }
}
