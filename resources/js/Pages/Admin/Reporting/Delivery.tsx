import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import {
    Truck,
    Calendar,
    Filter,
    Clock,
    CheckCircle2,
    XCircle,
    RotateCcw,
    AlertTriangle,
    Eye,
    TrendingUp,
    Users
} from 'lucide-react';

interface DeliverySummary {
    total_deliveries: number;
    delivered_count: number;
    failed_count: number;
    returned_count: number;
    in_transit_count: number;
    success_rate_percent: number;
    average_turnaround_hours: number;
    average_assignment_to_pickup_hours: number;
    average_transit_to_delivery_hours: number;
}

interface DriverRow {
    driver_id: number;
    driver_name: string;
    driver_email: string;
    deliveries_assigned: number;
    deliveries_completed: number;
    deliveries_failed: number;
    deliveries_returned: number;
    success_rate_percent: number;
    average_turnaround_hours: number;
}

interface FailureRow {
    failure_reason: string;
    failure_count: number;
    percentage: number;
}

interface DeliveryRow {
    id: number;
    delivery_number: string;
    order_id: number;
    order_number?: string;
    customer_id: number;
    customer_name?: string;
    driver_id: number | null;
    driver_name?: string | null;
    status: string;
    scheduled_date: string | null;
    assigned_at: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    failed_at: string | null;
    recipient_name: string | null;
}

interface PaginatedDeliveries {
    data: DeliveryRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    summary: DeliverySummary;
    driverBreakdown: DriverRow[];
    failureAnalysis: FailureRow[];
    deliveriesList: PaginatedDeliveries;
    filters: {
        driver_id?: string;
        status?: string;
        customer_id?: string;
        date_from?: string;
        date_to?: string;
    };
}

export default function DeliveryReport({
    summary,
    driverBreakdown,
    failureAnalysis,
    deliveriesList,
    filters
}: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [activeTab, setActiveTab] = useState<'drivers' | 'failures' | 'deliveries'>('drivers');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/reports/delivery', {
            ...filters,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true });
    };

    const handleReset = () => {
        setDateFrom('');
        setDateTo('');
        router.get('/admin/reports/delivery');
    };

    return (
        <AppLayout title="Delivery Performance & Turnaround Reports">
            <Head title="Delivery Reports" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Delivery Performance & Logistics Turnaround</h1>
                            <Badge variant="outline" className="bg-indigo-500/10 text-indigo-600 border-indigo-500/20 text-xs py-0.5">
                                FEAT-REP-005
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Dispatch speeds, lifecycle milestones, partner success rates, and delivery failure root cause analytics.
                        </p>
                    </div>
                </div>

                {/* Filter Controls */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                    <form onSubmit={handleFilter} className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                <Calendar className="h-3.5 w-3.5" />
                                From:
                            </span>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="h-8 w-36 text-xs"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                <Calendar className="h-3.5 w-3.5" />
                                To:
                            </span>
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="h-8 w-36 text-xs"
                            />
                        </div>

                        <div className="flex items-center gap-2 ml-auto">
                            <Button type="submit" size="sm" className="h-8 text-xs">
                                <Filter className="h-3.5 w-3.5 mr-1" />
                                Apply Range
                            </Button>
                            {(dateFrom || dateTo) && (
                                <Button type="button" variant="outline" size="sm" onClick={handleReset} className="h-8 text-xs">
                                    Clear
                                </Button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Summary KPIs */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Total Deliveries</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">{summary.total_deliveries}</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Success Rate</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                            {summary.success_rate_percent}%
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Delivered</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">{summary.delivered_count}</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Failed / Returned</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-rose-500">
                            {summary.failed_count + summary.returned_count}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Avg Turnaround</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">{summary.average_turnaround_hours}h</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Avg Transit Time</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">{summary.average_transit_to_delivery_hours}h</div>
                    </div>
                </div>

                {/* Tab Navigation */}
                <div className="flex items-center gap-2 border-b border-border pb-2 text-xs">
                    <button
                        onClick={() => setActiveTab('drivers')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'drivers'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Driver Performance ({driverBreakdown.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('failures')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'failures'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Failure Reason Analysis ({failureAnalysis.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('deliveries')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'deliveries'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Delivery Log Records ({deliveriesList.total})
                    </button>
                </div>

                {/* Tab 1: Driver Breakdown */}
                {activeTab === 'drivers' && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Delivery Partner League Table</h3>
                            <span className="text-xs text-muted-foreground">Assigned driver outcomes & speeds</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">Driver Name</th>
                                        <th className="p-3 text-right">Assigned</th>
                                        <th className="p-3 text-right">Delivered</th>
                                        <th className="p-3 text-right">Failed</th>
                                        <th className="p-3 text-right">Returned</th>
                                        <th className="p-3 text-right">Success Rate</th>
                                        <th className="p-3 text-right">Avg Turnaround</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {driverBreakdown.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="p-8 text-center text-muted-foreground">
                                                No delivery drivers found in scope.
                                            </td>
                                        </tr>
                                    ) : (
                                        driverBreakdown.map((driver) => (
                                            <tr key={driver.driver_id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3">
                                                    <div className="font-medium text-foreground">{driver.driver_name}</div>
                                                    <div className="text-[11px] text-muted-foreground">{driver.driver_email}</div>
                                                </td>
                                                <td className="p-3 text-right">{driver.deliveries_assigned}</td>
                                                <td className="p-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {driver.deliveries_completed}
                                                </td>
                                                <td className="p-3 text-right text-rose-500">{driver.deliveries_failed}</td>
                                                <td className="p-3 text-right text-amber-500">{driver.deliveries_returned}</td>
                                                <td className="p-3 text-right font-bold text-foreground">{driver.success_rate_percent}%</td>
                                                <td className="p-3 text-right font-mono">{driver.average_turnaround_hours}h</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Tab 2: Failure Analysis */}
                {activeTab === 'failures' && (
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs space-y-4">
                        <div className="border-b border-border pb-3">
                            <h3 className="font-semibold text-sm">Delivery Failure Reasons Distribution</h3>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Breakdown of reported exceptions and delivery attempt failures.
                            </p>
                        </div>

                        {failureAnalysis.length === 0 ? (
                            <div className="p-8 text-center text-emerald-600 dark:text-emerald-400 text-xs">
                                No delivery failures recorded in this period.
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {failureAnalysis.map((f) => (
                                    <div key={f.failure_reason} className="space-y-1.5">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-medium text-foreground">{f.failure_reason}</span>
                                            <span className="font-mono text-muted-foreground">
                                                {f.failure_count} occurrences ({f.percentage}%)
                                            </span>
                                        </div>
                                        <div className="h-2 w-full rounded-full bg-muted overflow-hidden">
                                            <div
                                                className="h-full bg-rose-500 rounded-full transition-all"
                                                style={{ width: `${f.percentage}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Tab 3: Deliveries List */}
                {activeTab === 'deliveries' && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Deliveries Queue & Status Log</h3>
                            <span className="text-xs text-muted-foreground">Total records: {deliveriesList.total}</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">Delivery #</th>
                                        <th className="p-3">Order</th>
                                        <th className="p-3">Customer</th>
                                        <th className="p-3">Driver</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Assigned</th>
                                        <th className="p-3">Delivered</th>
                                        <th className="p-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {deliveriesList.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="p-8 text-center text-muted-foreground">
                                                No delivery records found.
                                            </td>
                                        </tr>
                                    ) : (
                                        deliveriesList.data.map((d) => (
                                            <tr key={d.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-mono font-medium text-foreground">{d.delivery_number}</td>
                                                <td className="p-3 font-mono text-muted-foreground">{d.order_number}</td>
                                                <td className="p-3">{d.customer_name}</td>
                                                <td className="p-3 text-muted-foreground">{d.driver_name || 'Unassigned'}</td>
                                                <td className="p-3">
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {d.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-3 font-mono text-[11px] text-muted-foreground">
                                                    {d.assigned_at ? new Date(d.assigned_at).toLocaleString() : '—'}
                                                </td>
                                                <td className="p-3 font-mono text-[11px] text-foreground font-medium">
                                                    {d.delivered_at ? new Date(d.delivered_at).toLocaleString() : '—'}
                                                </td>
                                                <td className="p-3 text-center">
                                                    <Link href={`/admin/orders/${d.order_id}`}>
                                                        <Button variant="ghost" size="sm" className="h-7 text-xs">
                                                            <Eye className="h-3.5 w-3.5 mr-1" />
                                                            Order
                                                        </Button>
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
