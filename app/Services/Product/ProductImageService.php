<?php

namespace App\Services\Product;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductImageService
{
    /**
     * Allowed MIME types and their canonical file extensions.
     *
     * @var array<string, string>
     */
    protected const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Upload and attach an image to a product master record.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function upload(
        Product $product,
        UploadedFile $file,
        User $actor,
        bool $isPrimaryRequested = false,
        int $sortOrder = 0,
        ?string $ip = null
    ): ProductImage {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);

        // 1. Authoritative server-side magic byte & binary content inspection
        $mimeType = $this->inspectAndValidateImageContent($file);
        $extension = self::ALLOWED_MIME_EXTENSIONS[$mimeType];
        $sizeBytes = $file->getSize();

        // 2. Generate secure non-guessable object key
        $uuid = Str::uuid()->toString();
        $filename = "{$uuid}.{$extension}";
        $directory = "products/{$product->id}";
        $objectKey = "{$directory}/{$filename}";

        // 3. Stage file to private cloud object storage
        $storedPath = Storage::disk('s3')->putFileAs($directory, $file, $filename, 'private');

        if (! $storedPath) {
            throw ValidationException::withMessages([
                'image' => 'Failed to store image asset to private storage.',
            ]);
        }

        // 4. Atomic database persistence with compensating delete on failure
        try {
            return DB::transaction(function () use (
                $product,
                $objectKey,
                $file,
                $mimeType,
                $sizeBytes,
                $isPrimaryRequested,
                $sortOrder,
                $actor,
                $ip
            ) {
                // Determine if this should be the primary image
                $hasExistingPrimary = ProductImage::query()
                    ->where('product_id', $product->id)
                    ->where('is_primary', true)
                    ->exists();

                $isPrimary = (! $hasExistingPrimary) || $isPrimaryRequested;

                if ($isPrimary && $hasExistingPrimary) {
                    ProductImage::query()
                        ->where('product_id', $product->id)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);
                }

                // Sanitize original filename for display
                $sanitizedOriginalName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $file->getClientOriginalName()) ?: "image.{$mimeType}";

                /** @var ProductImage $productImage */
                $productImage = ProductImage::create([
                    'product_id' => $product->id,
                    'object_key' => $objectKey,
                    'original_filename' => substr($sanitizedOriginalName, 0, 255),
                    'mime_type' => $mimeType,
                    'size_bytes' => $sizeBytes,
                    'is_primary' => $isPrimary,
                    'sort_order' => $sortOrder,
                ]);

                Log::info('Product image uploaded', [
                    'event' => 'audit.product_image_event',
                    'action' => 'PRODUCT_IMAGE_UPLOADED',
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'actor_role' => $actor->role?->value,
                    'product_id' => $product->id,
                    'image_id' => $productImage->id,
                    'original_filename' => $productImage->original_filename,
                    'mime_type' => $productImage->mime_type,
                    'size_bytes' => $productImage->size_bytes,
                    'is_primary' => $productImage->is_primary,
                    'ip_address' => $ip,
                    'timestamp' => now()->toIso8601String(),
                ]);

                return $productImage;
            });
        } catch (Exception $e) {
            // Compensating transaction: remove S3 object to prevent orphaned storage
            try {
                Storage::disk('s3')->delete($objectKey);
            } catch (Exception $cleanupEx) {
                Log::error('Failed to cleanup S3 object after DB rollback', [
                    'object_key' => $objectKey,
                    'error' => $cleanupEx->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Designate an image as the primary image for a product.
     *
     * @throws AuthorizationException
     * @throws NotFoundHttpException
     */
    public function setPrimary(Product $product, ProductImage $image, User $actor, ?string $ip = null): ProductImage
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);
        $this->verifyOwnership($product, $image);

        return DB::transaction(function () use ($product, $image, $actor, $ip) {
            // Unset current primary
            ProductImage::query()
                ->where('product_id', $product->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            // Set new primary
            $image->is_primary = true;
            $image->save();

            Log::info('Product image set as primary', [
                'event' => 'audit.product_image_event',
                'action' => 'PRODUCT_IMAGE_SET_PRIMARY',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'product_id' => $product->id,
                'image_id' => $image->id,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $image;
        });
    }

    /**
     * Delete an image from database and private cloud storage.
     *
     * @throws AuthorizationException
     * @throws NotFoundHttpException
     */
    public function delete(Product $product, ProductImage $image, User $actor, ?string $ip = null): bool
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);
        $this->verifyOwnership($product, $image);

        $objectKey = $image->object_key;
        $imageId = $image->id;
        $wasPrimary = $image->is_primary;

        DB::transaction(function () use ($product, $image, $wasPrimary) {
            $image->delete();

            // If deleted image was primary, promote next available image
            if ($wasPrimary) {
                /** @var ProductImage|null $nextImage */
                $nextImage = ProductImage::query()
                    ->where('product_id', $product->id)
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($nextImage) {
                    $nextImage->is_primary = true;
                    $nextImage->save();
                }
            }
        });

        // Remove from private S3 object storage
        try {
            Storage::disk('s3')->delete($objectKey);
        } catch (Exception $e) {
            Log::error('Failed to delete S3 object for deleted product image', [
                'product_id' => $product->id,
                'image_id' => $imageId,
                'object_key' => $objectKey,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Product image deleted', [
            'event' => 'audit.product_image_event',
            'action' => 'PRODUCT_IMAGE_DELETED',
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'actor_role' => $actor->role?->value,
            'product_id' => $product->id,
            'image_id' => $imageId,
            'was_primary' => $wasPrimary,
            'ip_address' => $ip,
            'timestamp' => now()->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Generate temporary presigned GET URL for an image.
     */
    public function getTemporaryUrl(ProductImage $image, int $expirationMinutes = 15): ?string
    {
        if (empty($image->object_key)) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl(
                $image->object_key,
                now()->addMinutes($expirationMinutes)
            );
        } catch (Exception $e) {
            // Fallback for disks/drivers (such as local fake) without temporaryUrl driver method
            try {
                return Storage::disk('s3')->url($image->object_key);
            } catch (Exception) {
                return null;
            }
        }
    }

    /**
     * Format product image model into structured presentation array.
     *
     * @return array<string, mixed>
     */
    public function formatImage(ProductImage $image): array
    {
        return [
            'id' => $image->id,
            'product_id' => $image->product_id,
            'original_filename' => $image->original_filename,
            'mime_type' => $image->mime_type,
            'size_bytes' => $image->size_bytes,
            'is_primary' => (bool) $image->is_primary,
            'sort_order' => (int) $image->sort_order,
            'url' => $this->getTemporaryUrl($image),
            'created_at' => $image->created_at?->toIso8601String(),
        ];
    }

    /**
     * Inspect file binary content via magic bytes and verify image validity.
     *
     * @throws ValidationException
     */
    protected function inspectAndValidateImageContent(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'image' => 'The uploaded image payload could not be read.',
            ]);
        }

        // 1. Fileinfo magic byte inspection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $realPath);
        finfo_close($finfo);

        if (! is_string($detectedMime) || ! array_key_exists($detectedMime, self::ALLOWED_MIME_EXTENSIONS)) {
            throw ValidationException::withMessages([
                'image' => 'The file is not a supported image format. Only genuine JPEG, PNG, and WebP images are allowed.',
            ]);
        }

        // 2. Verify binary image integrity with getimagesize
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw ValidationException::withMessages([
                'image' => 'The uploaded image file is corrupt or malformed.',
            ]);
        }

        // 3. Prohibit SVG content detection
        if (str_contains(strtolower($detectedMime), 'svg') || str_contains(strtolower($detectedMime), 'xml')) {
            throw ValidationException::withMessages([
                'image' => 'SVG files are strictly prohibited.',
            ]);
        }

        return $detectedMime;
    }

    /**
     * Strictly verify that the image belongs to the specified product (IDOR prevention).
     *
     * @throws NotFoundHttpException
     */
    protected function verifyOwnership(Product $product, ProductImage $image): void
    {
        if ((int) $image->product_id !== (int) $product->id) {
            throw new NotFoundHttpException('The requested image does not belong to this product.');
        }
    }

    /**
     * Ensure actor has an active account status and required permission.
     *
     * @throws AuthorizationException
     */
    protected function ensureActorIsActiveAndAuthorized(User $actor, Permission $permission): void
    {
        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive accounts are not authorized to perform image operations.');
        }

        $this->permissionService->authorize($actor, $permission);
    }
}
