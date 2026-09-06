import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { InventoryDetailPayload } from '@/types/inventory';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Boxes,
    ArrowLeft,
    CheckCircle2,
    AlertTriangle,
    AlertOctagon,
    Warehouse as WarehouseIcon,
    MapPin,
    ShieldAlert,
    PackageCheck,
    Layers,
    Calendar,
    User,
    TrendingUp,
    TrendingDown,
    FileText,
} from 'lucide-react';

interface InventoryShowProps {
    detail: InventoryDetailPayload;
}

export default function InventoryShow({ detail }: InventoryShowProps) {
    const { balance, commercial_summary, composition_proportions, active_allocations } = detail;

    const getStockStatusBadge = () => {
        switch (balance.stock_status) {
            case 'IN_STOCK':
                return (
                    <Badge variant="outline" className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400 font-medium text-xs px-3 py-1 inline-flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                        In Stock
                    </Badge>
                );
            case 'LOW_STOCK':
                return (
                    <Badge variant="outline" className="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-400 font-medium text-xs px-3 py-1 inline-flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-amber-500 animate-pulse" />
                        Low Stock Warning
                    </Badge>
                );
            case 'OUT_OF_STOCK':
                return (
                    <Badge variant="outline" className="border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-400 font-medium text-xs px-3 py-1 inline-flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-rose-500" />
                        Out of Stock
                    </Badge>
                );
            default:
                return <Badge variant="outline">{balance.stock_status_label}</Badge>;
        }
    };

    return (
        <AppLayout>
            <Head title={`Stock Detail: ${balance.product_name} (${balance.sku})`} />

            <div className="space-y-6 pb-12 max-w-7xl mx-auto">
                {/* Back Link & Header Title Section */}
                <div className="space-y-3">
                    <Link
                        href="/admin/inventory"
                        className="inline-flex items-center text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="mr-1.5 h-3.5 w-3.5" />
                        Back to Physical Inventory Balances
                    </Link>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    {balance.product_name}
                                </h1>
                                {getStockStatusBadge()}
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="font-mono font-medium text-foreground bg-muted px-2 py-0.5 rounded">
                                    SKU: {balance.sku}
                                </span>
                                {balance.category_name && (
                                    <>
                                        <span>•</span>
                                        <span>Category: <strong className="text-foreground">{balance.category_name}</strong></span>
                                    </>
                                )}
                                <span>•</span>
                                <span className="uppercase">Unit: <strong className="text-foreground">{balance.unit}</strong></span>
                            </div>
                        </div>

                        {/* Warehouse & Bin Badges */}
                        <div className="flex items-center gap-2">
                            <div className="flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium shadow-sm">
                                <WarehouseIcon className="h-4 w-4 text-muted-foreground" />
                                <span>{balance.warehouse_name}</span>
                                <span className="font-mono text-muted-foreground">({balance.warehouse_code})</span>
                            </div>
                            {balance.bin_location && (
                                <div className="flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-mono font-medium shadow-sm">
                                    <MapPin className="h-4 w-4 text-primary" />
                                    <span>Bin: {balance.bin_location}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Stock Composition Visual Bar */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-sm space-y-2">
                    <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold uppercase tracking-wider text-muted-foreground">
                            Physical Stock Composition (On-Hand: {balance.on_hand_quantity.toLocaleString()} units)
                        </span>
                        <span className="text-muted-foreground font-mono">
                            Available: {composition_proportions.available_percent}% | Hold: {composition_proportions.reserved_percent}% | Damaged: {composition_proportions.damaged_percent}%
                        </span>
                    </div>

                    <div className="h-4 w-full overflow-hidden rounded-full bg-muted flex">
                        {composition_proportions.available_percent > 0 && (
                            <div
                                style={{ width: `${composition_proportions.available_percent}%` }}
                                className="bg-emerald-500 transition-all duration-300"
                                title={`Available: ${balance.available_quantity} (${composition_proportions.available_percent}%)`}
                            />
                        )}
                        {composition_proportions.reserved_percent > 0 && (
                            <div
                                style={{ width: `${composition_proportions.reserved_percent}%` }}
                                className="bg-amber-500 transition-all duration-300"
                                title={`Physical Hold: ${balance.reserved_quantity} (${composition_proportions.reserved_percent}%)`}
                            />
                        )}
                        {composition_proportions.damaged_percent > 0 && (
                            <div
                                style={{ width: `${composition_proportions.damaged_percent}%` }}
                                className="bg-rose-500 transition-all duration-300"
                                title={`Damaged: ${balance.damaged_quantity} (${composition_proportions.damaged_percent}%)`}
                            />
                        )}
                    </div>

                    <div className="flex items-center justify-between text-[11px] text-muted-foreground pt-1">
                        <div className="flex items-center gap-4">
                            <span className="inline-flex items-center gap-1.5">
                                <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                Available ({balance.available_quantity})
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="h-2 w-2 rounded-full bg-amber-500" />
                                Physical Hold ({balance.reserved_quantity})
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="h-2 w-2 rounded-full bg-rose-500" />
                                Damaged ({balance.damaged_quantity})
                            </span>
                        </div>
                        <span className="text-[10px] text-muted-foreground italic">
                            Invariant: On-Hand = Available + Hold + Damaged
                        </span>
                    </div>
                </div>

                {/* Primary Metric KPI Grid (Physical vs Commercial) */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {/* Physical Available */}
                    <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                Physical Available
                            </span>
                            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                            {balance.available_quantity.toLocaleString()}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">Sellable units in warehouse</p>
                    </div>

                    {/* Commercially Allocated */}
                    <div className="rounded-xl border border-indigo-500/30 bg-indigo-500/5 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-400">
                                Commercial Allocated
                            </span>
                            <PackageCheck className="h-4 w-4 text-indigo-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-indigo-700 dark:text-indigo-300">
                            {commercial_summary.allocated_quantity.toLocaleString()}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">Assigned across active orders</p>
                    </div>

                    {/* Open Unallocated Demand */}
                    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Unallocated Demand
                            </span>
                            <Layers className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-foreground">
                            {commercial_summary.unallocated_demand.toLocaleString()}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">Approved orders awaiting stock</p>
                    </div>

                    {/* Net Commercial Coverage */}
                    <div className={`rounded-xl border p-4 shadow-sm ${
                        commercial_summary.is_surplus
                            ? 'border-border bg-card'
                            : 'border-rose-500/40 bg-rose-500/5'
                    }`}>
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Net Commercial Coverage
                            </span>
                            {commercial_summary.is_surplus ? (
                                <TrendingUp className="h-4 w-4 text-emerald-600" />
                            ) : (
                                <TrendingDown className="h-4 w-4 text-rose-600" />
                            )}
                        </div>
                        <div className={`mt-2 text-2xl font-bold ${
                            commercial_summary.is_surplus
                                ? 'text-emerald-700 dark:text-emerald-400'
                                : 'text-rose-700 dark:text-rose-400'
                        }`}>
                            {commercial_summary.net_coverage >= 0 ? `+${commercial_summary.net_coverage}` : commercial_summary.net_coverage}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {commercial_summary.is_surplus ? 'Available exceeds open demand' : 'Stock deficit against demand'}
                        </p>
                    </div>
                </div>

                {/* Secondary Inventory Parameter Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-3 text-xs">
                        <div className="text-muted-foreground">Reorder Point</div>
                        <div className="mt-1 font-mono text-base font-semibold text-foreground">
                            {balance.reorder_point.toLocaleString()} units
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-3 text-xs">
                        <div className="text-muted-foreground">Safety Stock</div>
                        <div className="mt-1 font-mono text-base font-semibold text-foreground">
                            {balance.safety_stock.toLocaleString()} units
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-3 text-xs">
                        <div className="text-muted-foreground">Last Physical Count</div>
                        <div className="mt-1 text-xs font-medium text-foreground">
                            {balance.last_counted_at ? new Date(balance.last_counted_at).toLocaleDateString() : 'Not recorded'}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-3 text-xs">
                        <div className="text-muted-foreground">Record Version</div>
                        <div className="mt-1 font-mono text-xs font-medium text-muted-foreground">
                            v{balance.version} (Optimistic Concurrency)
                        </div>
                    </div>
                </div>

                {/* Active Commercial Order Commitments Table */}
                <div className="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                    <div className="border-b border-border bg-muted/30 px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <PackageCheck className="h-4 w-4 text-indigo-500" />
                            <h2 className="text-sm font-semibold text-foreground">
                                Active Commercial Order Commitments ({active_allocations.length})
                            </h2>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            Allocated batches from approved orders
                        </span>
                    </div>

                    {active_allocations.length === 0 ? (
                        <div className="p-8 text-center text-muted-foreground">
                            <PackageCheck className="mx-auto h-8 w-8 text-muted-foreground/40" />
                            <p className="mt-2 text-sm font-medium text-foreground">No active order allocations</p>
                            <p className="text-xs text-muted-foreground">
                                No customer orders currently hold allocated shipping batches for this SKU.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/20 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Allocation #</th>
                                        <th className="px-4 py-3">Order # / Customer</th>
                                        <th className="px-3 py-3 text-right">Allocated</th>
                                        <th className="px-3 py-3 text-right">Picked</th>
                                        <th className="px-3 py-3 text-right">Dispatched</th>
                                        <th className="px-3 py-3 text-center">Status</th>
                                        <th className="px-4 py-3 text-right">Allocated At</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {active_allocations.map((alloc) => (
                                        <tr key={alloc.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3 font-mono text-xs font-medium text-foreground">
                                                {alloc.allocation_number}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">{alloc.order_number}</div>
                                                <div className="text-xs text-muted-foreground">{alloc.customer_name}</div>
                                            </td>
                                            <td className="px-3 py-3 text-right font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                                {alloc.allocated_quantity.toLocaleString()}
                                            </td>
                                            <td className="px-3 py-3 text-right font-mono text-muted-foreground">
                                                {alloc.picked_quantity.toLocaleString()}
                                            </td>
                                            <td className="px-3 py-3 text-right font-mono text-muted-foreground">
                                                {alloc.dispatched_quantity.toLocaleString()}
                                            </td>
                                            <td className="px-3 py-3 text-center">
                                                <Badge variant="outline" className="text-xs">
                                                    {alloc.status_label}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right text-xs text-muted-foreground">
                                                {alloc.allocated_at ? new Date(alloc.allocated_at).toLocaleString() : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Immutable Stock Movement History Ledger */}
                <div className="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                    <div className="border-b border-border bg-muted/30 px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Layers className="h-4 w-4 text-emerald-500" />
                            <h2 className="text-sm font-semibold text-foreground">
                                Stock Movement History Ledger ({detail.recent_movements?.length ?? 0})
                            </h2>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            Immutable audit trail of all physical state transitions
                        </span>
                    </div>

                    {!detail.recent_movements || detail.recent_movements.length === 0 ? (
                        <div className="p-8 text-center text-muted-foreground">
                            <Layers className="mx-auto h-8 w-8 text-muted-foreground/40" />
                            <p className="mt-2 text-sm font-medium text-foreground">No movement records found</p>
                            <p className="text-xs text-muted-foreground">
                                No physical stock mutations or reservations have been recorded for this item yet.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/20 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Movement #</th>
                                        <th className="px-3 py-3">Type</th>
                                        <th className="px-3 py-3">Transition</th>
                                        <th className="px-3 py-3 text-right">Quantity</th>
                                        <th className="px-4 py-3 text-center">Physical Snapshot (Before → After)</th>
                                        <th className="px-3 py-3">Reference #</th>
                                        <th className="px-3 py-3">Actor</th>
                                        <th className="px-4 py-3 text-right">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {detail.recent_movements.map((mov) => (
                                        <tr key={mov.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3 font-mono text-xs font-medium text-foreground">
                                                {mov.movement_number}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Badge variant="outline" className="text-xs">
                                                    {mov.movement_type_label}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3 text-xs">
                                                <span className="text-muted-foreground">{mov.from_state}</span>
                                                <span className="mx-1 text-muted-foreground">→</span>
                                                <strong className="text-foreground">{mov.to_state}</strong>
                                            </td>
                                            <td className="px-3 py-3 text-right font-mono font-semibold text-foreground">
                                                {mov.quantity.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-center text-xs">
                                                <div className="inline-flex items-center gap-2 font-mono text-[11px] bg-muted/50 px-2 py-1 rounded">
                                                    <span>Avail: {mov.available_before} → {mov.available_after}</span>
                                                    <span>•</span>
                                                    <span>Res: {mov.reserved_before} → {mov.reserved_after}</span>
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 font-mono text-xs text-muted-foreground">
                                                {mov.reference_number || '—'}
                                            </td>
                                            <td className="px-3 py-3 text-xs text-foreground">
                                                {mov.actor_name}
                                            </td>
                                            <td className="px-4 py-3 text-right text-xs text-muted-foreground">
                                                {mov.created_at ? new Date(mov.created_at).toLocaleString() : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
