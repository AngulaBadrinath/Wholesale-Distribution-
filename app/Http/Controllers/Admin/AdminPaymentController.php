<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Auth\PermissionService;
use App\Services\Payment\PaymentEvidenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected PaymentEvidenceService $evidenceService,
    ) {}

    /**
     * Get a secure, temporary presigned URL for payment evidence preview.
     *
     * @throws AuthorizationException
     */
    public function evidenceUrl(Request $request, Payment $payment): JsonResponse
    {
        $actor = $request->user();

        // 1. Authorize payment.view permission
        $this->permissionService->authorize($actor, Permission::PAYMENT_VIEW);

        // 2. Anti-IDOR Scope Check
        if ($actor->role === UserRole::SALESMAN) {
            $isAssigned = $payment->customer && $payment->customer->salesman_id === $actor->id;
            $isRecorder = $payment->recorded_by === $actor->id;

            if (! $isAssigned && ! $isRecorder) {
                throw new AuthorizationException('You are not authorized to access payment evidence for this account.');
            }
        }

        // 3. Ensure evidence exists on record
        if (! $payment->hasEvidence() || empty($payment->evidence_object_key)) {
            return response()->json([
                'message' => 'This payment record has no visual evidence attached.',
            ], 422);
        }

        $url = $this->evidenceService->getTemporaryPreviewUrl($payment, $actor, 15);
        $expiresAt = now()->addMinutes(15)->toIso8601String();

        return response()->json([
            'url' => $url,
            'expires_at' => $expiresAt,
            'mime_type' => $payment->evidence_mime_type ?? 'image/jpeg',
            'original_name' => $payment->evidence_original_name ?? 'payment_evidence.jpg',
            'size_bytes' => $payment->evidence_size_bytes,
        ]);
    }

    /**
     * Authenticated, secure stream for private storage drivers in local/staging environments.
     *
     * @throws AuthorizationException
     */
    public function streamEvidence(Request $request, Payment $payment): StreamedResponse
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::PAYMENT_VIEW);

        if ($actor->role === UserRole::SALESMAN) {
            $isAssigned = $payment->customer && $payment->customer->salesman_id === $actor->id;
            $isRecorder = $payment->recorded_by === $actor->id;

            if (! $isAssigned && ! $isRecorder) {
                throw new AuthorizationException('You are not authorized to stream evidence for this account.');
            }
        }

        if (! $payment->hasEvidence() || empty($payment->evidence_object_key)) {
            abort(404, 'No evidence file associated with this payment.');
        }

        $disk = $this->evidenceService->getDisk();
        $objectKey = $payment->evidence_object_key;

        if (! Storage::disk($disk)->exists($objectKey)) {
            abort(404, 'Evidence file not found in secure storage.');
        }

        return Storage::disk($disk)->response($objectKey, $payment->evidence_original_name ?? 'evidence.jpg', [
            'Content-Type' => $payment->evidence_mime_type ?? 'image/jpeg',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
