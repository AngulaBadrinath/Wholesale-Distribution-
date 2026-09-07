<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\SecurityLogService;
use App\Services\Auth\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminAuditController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected SecurityLogService $securityLogService,
        protected PermissionService $permissionService
    ) {}

    /**
     * Display the comprehensive business audit activity timeline.
     */
    public function timeline(Request $request): InertiaResponse|JsonResponse
    {
        $this->permissionService->authorize($request->user(), Permission::AUDIT_VIEW);

        $filters = $request->only([
            'module',
            'event_type',
            'entity_type',
            'entity_id',
            'actor_id',
            'date_from',
            'date_to',
            'search',
            'per_page',
        ]);

        $logs = $this->auditLogService->getTimeline($filters);

        // Fetch distinct modules for filtering dropdown
        $modules = AuditLog::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->values()
            ->all();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'logs' => $logs,
                'modules' => $modules,
                'filters' => $filters,
            ]);
        }

        return Inertia::render('Admin/Audit/Timeline', [
            'logs' => $logs,
            'modules' => $modules,
            'filters' => $filters,
        ]);
    }

    /**
     * Display the sensitive security event log.
     */
    public function security(Request $request): InertiaResponse|JsonResponse
    {
        $this->permissionService->authorize($request->user(), Permission::AUDIT_SECURITY_VIEW);

        $filters = $request->only([
            'event_type',
            'severity',
            'actor_id',
            'date_from',
            'date_to',
            'search',
            'per_page',
        ]);

        $logs = $this->securityLogService->getSecurityLogs($filters);

        $eventTypes = SecurityLog::select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->values()
            ->all();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'logs' => $logs,
                'event_types' => $eventTypes,
                'filters' => $filters,
            ]);
        }

        return Inertia::render('Admin/Audit/Security', [
            'logs' => $logs,
            'event_types' => $eventTypes,
            'filters' => $filters,
        ]);
    }

    /**
     * Inspect audit history for a specific business entity.
     */
    public function entityHistory(Request $request, string $entityType, int|string $entityId): JsonResponse
    {
        $this->permissionService->authorize($request->user(), Permission::AUDIT_VIEW);

        $history = $this->auditLogService->getEntityHistory($entityType, (int) $entityId);

        return response()->json([
            'entity_type' => $entityType,
            'entity_id' => (int) $entityId,
            'history' => $history,
        ]);
    }
}
