<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryFailure;
use App\Models\User;
use App\Services\Auth\ResourceScopeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DeliveryPerformanceReportService
{
    public function __construct(
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Build base query for deliveries based on filters and role scoping.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getBaseDeliveryQuery(array $filters = [], ?User $user = null): Builder
    {
        $query = Delivery::query();

        if ($user) {
            $query = $this->resourceScopeService->scopeDeliveries($query, $user);
        }

        if (! empty($filters['driver_id'])) {
            if (! $user || $user->role !== UserRole::DELIVERY_PARTNER) {
                $query->where('deliveries.driver_id', (int) $filters['driver_id']);
            }
        }

        if (! empty($filters['status'])) {
            $query->where('deliveries.status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('deliveries.customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('deliveries.created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('deliveries.created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query;
    }

    /**
     * Get overall delivery performance summary metrics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDeliverySummary(array $filters = [], ?User $user = null): array
    {
        $query = $this->getBaseDeliveryQuery($filters, $user);

        $totalDeliveries = (clone $query)->count();
        $deliveredCount = (clone $query)->where('status', DeliveryStatus::DELIVERED->value)->count();
        $failedCount = (clone $query)->where('status', DeliveryStatus::FAILED->value)->count();
        $returnedCount = (clone $query)->where('status', DeliveryStatus::RETURNED_TO_WAREHOUSE->value)->count();
        $inTransitCount = (clone $query)->whereIn('status', [
            DeliveryStatus::ASSIGNED->value,
            DeliveryStatus::PICKED_UP->value,
            DeliveryStatus::OUT_FOR_DELIVERY->value,
        ])->count();

        $completedAttempts = $deliveredCount + $failedCount + $returnedCount;
        $successRate = $completedAttempts > 0
            ? round(($deliveredCount / $completedAttempts) * 100, 1)
            : 0.0;

        // Compute average turnaround times for delivered records
        $deliveredDeliveries = (clone $query)
            ->where('status', DeliveryStatus::DELIVERED->value)
            ->whereNotNull('assigned_at')
            ->whereNotNull('delivered_at')
            ->get(['assigned_at', 'picked_up_at', 'delivered_at']);

        $totalTurnaroundMinutes = 0;
        $totalPickupMinutes = 0;
        $totalTransitMinutes = 0;
        $turnaroundCount = 0;
        $pickupCount = 0;
        $transitCount = 0;

        foreach ($deliveredDeliveries as $d) {
            $assigned = $d->assigned_at ? Carbon::parse($d->assigned_at) : null;
            $pickedUp = $d->picked_up_at ? Carbon::parse($d->picked_up_at) : null;
            $delivered = $d->delivered_at ? Carbon::parse($d->delivered_at) : null;

            if ($assigned && $delivered && $delivered->isAfter($assigned)) {
                $totalTurnaroundMinutes += $assigned->diffInMinutes($delivered);
                $turnaroundCount++;
            }

            if ($assigned && $pickedUp && $pickedUp->isAfter($assigned)) {
                $totalPickupMinutes += $assigned->diffInMinutes($pickedUp);
                $pickupCount++;
            }

            if ($pickedUp && $delivered && $delivered->isAfter($pickedUp)) {
                $totalTransitMinutes += $pickedUp->diffInMinutes($delivered);
                $transitCount++;
            }
        }

        $avgTurnaroundHours = $turnaroundCount > 0 ? round(($totalTurnaroundMinutes / $turnaroundCount) / 60, 2) : 0.0;
        $avgPickupHours = $pickupCount > 0 ? round(($totalPickupMinutes / $pickupCount) / 60, 2) : 0.0;
        $avgTransitHours = $transitCount > 0 ? round(($totalTransitMinutes / $transitCount) / 60, 2) : 0.0;

        return [
            'total_deliveries' => $totalDeliveries,
            'delivered_count' => $deliveredCount,
            'failed_count' => $failedCount,
            'returned_count' => $returnedCount,
            'in_transit_count' => $inTransitCount,
            'success_rate_percent' => $successRate,
            'average_turnaround_hours' => $avgTurnaroundHours,
            'average_assignment_to_pickup_hours' => $avgPickupHours,
            'average_transit_to_delivery_hours' => $avgTransitHours,
            'filters' => [
                'driver_id' => $filters['driver_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ];
    }

    /**
     * Get driver performance breakdown league table.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getDriverPerformanceBreakdown(array $filters = [], ?User $user = null): array
    {
        $driversQuery = User::query()->where('role', UserRole::DELIVERY_PARTNER);

        if ($user && $user->role === UserRole::DELIVERY_PARTNER) {
            $driversQuery->where('id', $user->id);
        } elseif (! empty($filters['driver_id'])) {
            $driversQuery->where('id', (int) $filters['driver_id']);
        }

        $drivers = $driversQuery->orderBy('name', 'asc')->get();

        $rows = [];

        foreach ($drivers as $driver) {
            $driverDeliveryQuery = Delivery::query()->where('driver_id', $driver->id);

            if (! empty($filters['date_from'])) {
                $driverDeliveryQuery->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
            }
            if (! empty($filters['date_to'])) {
                $driverDeliveryQuery->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
            }

            $assigned = (clone $driverDeliveryQuery)->count();
            $delivered = (clone $driverDeliveryQuery)->where('status', DeliveryStatus::DELIVERED->value)->count();
            $failed = (clone $driverDeliveryQuery)->where('status', DeliveryStatus::FAILED->value)->count();
            $returned = (clone $driverDeliveryQuery)->where('status', DeliveryStatus::RETURNED_TO_WAREHOUSE->value)->count();

            $completed = $delivered + $failed + $returned;
            $successRate = $completed > 0 ? round(($delivered / $completed) * 100, 1) : 0.0;

            // Turnaround calculation for driver
            $driverDelivered = (clone $driverDeliveryQuery)
                ->where('status', DeliveryStatus::DELIVERED->value)
                ->whereNotNull('assigned_at')
                ->whereNotNull('delivered_at')
                ->get(['assigned_at', 'delivered_at']);

            $totalMins = 0;
            $count = 0;
            foreach ($driverDelivered as $d) {
                $a = $d->assigned_at ? Carbon::parse($d->assigned_at) : null;
                $del = $d->delivered_at ? Carbon::parse($d->delivered_at) : null;
                if ($a && $del && $del->isAfter($a)) {
                    $totalMins += $a->diffInMinutes($del);
                    $count++;
                }
            }

            $avgHours = $count > 0 ? round(($totalMins / $count) / 60, 2) : 0.0;

            $rows[] = [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'driver_email' => $driver->email,
                'deliveries_assigned' => $assigned,
                'deliveries_completed' => $delivered,
                'deliveries_failed' => $failed,
                'deliveries_returned' => $returned,
                'success_rate_percent' => $successRate,
                'average_turnaround_hours' => $avgHours,
            ];
        }

        return $rows;
    }

    /**
     * Get failure reasons distribution.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getDeliveryFailureAnalysis(array $filters = [], ?User $user = null): array
    {
        $baseDeliveryQuery = $this->getBaseDeliveryQuery($filters, $user);
        $deliveryIds = $baseDeliveryQuery->pluck('id');

        if ($deliveryIds->isEmpty()) {
            return [];
        }

        $records = DeliveryFailure::query()
            ->whereIn('delivery_id', $deliveryIds)
            ->selectRaw('failure_reason, COUNT(id) as failure_count')
            ->groupBy('failure_reason')
            ->orderBy('failure_count', 'desc')
            ->get();

        $totalFailures = $records->sum('failure_count');

        return $records->map(function ($row) use ($totalFailures) {
            $count = (int) $row->failure_count;
            $pct = $totalFailures > 0 ? round(($count / $totalFailures) * 100, 1) : 0.0;

            return [
                'failure_reason' => $row->failure_reason instanceof \App\Enums\DeliveryFailureReason ? $row->failure_reason->value : (string) $row->failure_reason,
                'failure_count' => $count,
                'percentage' => $pct,
            ];
        })->toArray();
    }

    /**
     * Get paginated deliveries for operational drill-down.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDeliveriesList(array $filters = [], ?User $user = null, int $perPage = 25): array
    {
        $query = $this->getBaseDeliveryQuery($filters, $user)
            ->with(['order:id,order_number', 'customer:id,name,code', 'driver:id,name'])
            ->orderBy('created_at', 'desc');

        $paginator = $query->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(function (Delivery $delivery) {
                return [
                    'id' => $delivery->id,
                    'delivery_number' => $delivery->delivery_number,
                    'order_id' => $delivery->order_id,
                    'order_number' => $delivery->order?->order_number,
                    'customer_id' => $delivery->customer_id,
                    'customer_name' => $delivery->customer?->name,
                    'driver_id' => $delivery->driver_id,
                    'driver_name' => $delivery->driver?->name,
                    'status' => $delivery->status->value,
                    'scheduled_date' => $delivery->scheduled_date?->toDateString(),
                    'assigned_at' => $delivery->assigned_at?->toIso8601String(),
                    'picked_up_at' => $delivery->picked_up_at?->toIso8601String(),
                    'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                    'failed_at' => $delivery->failed_at?->toIso8601String(),
                    'recipient_name' => $delivery->recipient_name,
                ];
            })->toArray(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
