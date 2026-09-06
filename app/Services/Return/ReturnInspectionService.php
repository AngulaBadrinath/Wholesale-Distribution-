<?php

namespace App\Services\Return;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\ReturnStatus;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReturnInspectionService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ReturnEvidenceService $evidenceService,
    ) {}

    /**
     * Record warehouse physical inspection for a return request.
     *
     * @param  array{
     *     inspection_notes?: string,
     *     items: array<int, array{
     *         item_id: int,
     *         received_quantity: int,
     *         item_notes?: string
     *     }>
     * } $data
     * @param  array<int, UploadedFile>  $evidenceFiles
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function recordInspection(
        ReturnRequest $returnRequest,
        array $data,
        User $actor,
        array $evidenceFiles = []
    ): ReturnRequest {
        // 1. Authorize actor
        $this->permissionService->authorize($actor, Permission::RETURN_REVIEW);

        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive user accounts cannot perform return inspections.');
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'Inspection requires line item received quantities.',
            ]);
        }

        return DB::transaction(function () use ($returnRequest, $data, $actor, $evidenceFiles) {
            /** @var ReturnRequest $lockedReturn */
            $lockedReturn = ReturnRequest::where('id', $returnRequest->id)->lockForUpdate()->firstOrFail();

            if (! $lockedReturn->status->canInspect()) {
                throw new ConflictHttpException("Return #{$lockedReturn->return_number} is in '{$lockedReturn->status->value}' status and cannot be inspected.");
            }

            $itemsMap = ReturnRequestItem::where('return_request_id', $lockedReturn->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $inspectionSummary = [];

            foreach ($data['items'] as $index => $itemInput) {
                $itemId = (int) ($itemInput['item_id'] ?? 0);
                $receivedQty = (int) ($itemInput['received_quantity'] ?? 0);
                $itemNotes = $itemInput['item_notes'] ?? null;

                /** @var ReturnRequestItem|null $returnItem */
                $returnItem = $itemsMap->get($itemId);
                if (! $returnItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}.item_id" => "Return item #{$itemId} does not belong to return request #{$lockedReturn->return_number}.",
                    ]);
                }

                if ($receivedQty < 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.received_quantity" => 'Received quantity cannot be negative.',
                    ]);
                }

                if ($receivedQty > $returnItem->requested_quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.received_quantity" => "Received quantity ({$receivedQty}) cannot exceed requested return quantity ({$returnItem->requested_quantity}).",
                    ]);
                }

                $returnItem->update([
                    'received_quantity' => $receivedQty,
                    'item_notes' => $itemNotes ?? $returnItem->item_notes,
                ]);

                $inspectionSummary[] = [
                    'item_id' => $returnItem->id,
                    'product_id' => $returnItem->product_id,
                    'requested_quantity' => $returnItem->requested_quantity,
                    'received_quantity' => $receivedQty,
                ];
            }

            // Handle evidence photos upload
            $storedPhotoPaths = $lockedReturn->evidence_photos ?? [];
            foreach ($evidenceFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $storedPhotoPaths[] = $this->evidenceService->storeEvidence($file, $lockedReturn->id);
                }
            }

            // Transition status to INSPECTED
            $now = Carbon::now();
            $lockedReturn->update([
                'status' => ReturnStatus::INSPECTED,
                'inspected_by' => $actor->id,
                'inspected_at' => $now,
                'inspection_notes' => $data['inspection_notes'] ?? $lockedReturn->inspection_notes,
                'evidence_photos' => ! empty($storedPhotoPaths) ? $storedPhotoPaths : null,
            ]);

            // Record event
            ReturnRequestEvent::create([
                'return_request_id' => $lockedReturn->id,
                'actor_id' => $actor->id,
                'event_type' => 'INSPECTION_RECORDED',
                'payload' => [
                    'inspected_by' => $actor->id,
                    'inspected_at' => $now->toIso8601String(),
                    'items' => $inspectionSummary,
                    'inspection_notes' => $data['inspection_notes'] ?? null,
                    'photo_count' => count($storedPhotoPaths),
                ],
                'created_at' => $now,
            ]);

            return $lockedReturn->fresh(['items.product', 'order', 'customer', 'inspectedBy', 'createdBy']);
        }, 3);
    }
}
