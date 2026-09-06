import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Warehouse, PaginationLink } from '@/types/inventory';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    AlertTriangle,
    ShieldAlert,
    CheckCircle2,
    XCircle,
    ArrowLeft,
    Search,
    Filter,
    PlusCircle,
    Package,
    Warehouse as WarehouseIcon,
    FileText,
    Clock,
    User,
    ChevronRight,
} from 'lucide-react';

interface StockExceptionItem {
    id: number;
    exception_number: string;
    warehouse_id: number;
    warehouse: { id: number; code: string; name: string };
    product_id: number;
    product: { id: number; sku: string; name: string; unit: string };
    order_id: number | null;
    exception_type: string;
    severity: string;
    source_stock_state: string;
    quantity: number;
    status: 'PENDING_REVIEW' | 'RESOLVED' | 'DISMISSED';
    description: string;
    reported_by: number;
    reported_by_user?: { id: number; name: string };
    resolved_by: number | null;
    resolved_by_user?: { id: number; name: string };
    resolution_notes: string | null;
    resolved_at: string | null;
    created_at: string;
}

interface PaginatedExceptions {
    data: StockExceptionItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface ExceptionsPageProps {
    exceptions: PaginatedExceptions;
    warehouses: Warehouse[];
    exception_types: { value: string; label: string }[];
    severities: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
    filters: {
        warehouse_id: number | null;
        status: string;
        severity: string;
        exception_type: string;
        search: string;
        per_page: number;
        page: number;
    };
    can_adjust: boolean;
    can_report: boolean;
}

export default function Exceptions({
    exceptions,
    warehouses,
    exception_types,
    severities,
    statuses,
    filters,
    can_adjust,
    can_report,
}: ExceptionsPageProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedWarehouse, setSelectedWarehouse] = useState<string>(filters.warehouse_id ? String(filters.warehouse_id) : '');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'ALL');
    const [selectedSeverity, setSelectedSeverity] = useState<string>(filters.severity || 'ALL');
    const [selectedType, setSelectedType] = useState<string>(filters.exception_type || 'ALL');

    // Resolve Modal State
    const [resolvingException, setResolvingException] = useState<StockExceptionItem | null>(null);
    const resolveForm = useForm({
        resolution_notes: '',
    });

    // Dismiss Modal State
    const [dismissingException, setDismissingException] = useState<StockExceptionItem | null>(null);
    const dismissForm = useForm({
        dismissal_reason: '',
        revert_quarantine: false,
    });

    const handleFilterSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get('/admin/inventory-exceptions', {
            search: search.trim() || undefined,
            warehouse_id: selectedWarehouse ? Number(selectedWarehouse) : undefined,
            status: selectedStatus !== 'ALL' ? selectedStatus : undefined,
            severity: selectedSeverity !== 'ALL' ? selectedSeverity : undefined,
            exception_type: selectedType !== 'ALL' ? selectedType : undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleClearFilters = () => {
        setSearch('');
        setSelectedWarehouse('');
        setSelectedStatus('ALL');
        setSelectedSeverity('ALL');
        setSelectedType('ALL');
        router.get('/admin/inventory-exceptions');
    };

    const submitResolve = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resolvingException) return;

        resolveForm.post(`/admin/inventory-exceptions/${resolvingException.id}/resolve`, {
            onSuccess: () => {
                setResolvingException(null);
                resolveForm.reset();
            },
        });
    };

    const submitDismiss = (e: React.FormEvent) => {
        e.preventDefault();
        if (!dismissingException) return;

        dismissForm.post(`/admin/inventory-exceptions/${dismissingException.id}/dismiss`, {
            onSuccess: () => {
                setDismissingException(null);
                dismissForm.reset();
            },
        });
    };

    const getSeverityBadge = (severity: string) => {
        switch (severity) {
            case 'CRITICAL':
                return <Badge variant="destructive" className="text-xs">Critical</Badge>;
            case 'HIGH':
                return <Badge variant="outline" className="border-rose-300 text-rose-600 bg-rose-50 dark:bg-rose-950/40 text-xs">High</Badge>;
            case 'MEDIUM':
                return <Badge variant="outline" className="border-amber-300 text-amber-600 bg-amber-50 dark:bg-amber-950/40 text-xs">Medium</Badge>;
            default:
                return <Badge variant="secondary" className="text-xs">Low</Badge>;
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'PENDING_REVIEW':
                return (
                    <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 text-xs">
                        Pending Review
                    </Badge>
                );
            case 'RESOLVED':
                return (
                    <Badge variant="outline" className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 text-xs">
                        Resolved
                    </Badge>
                );
            case 'DISMISSED':
                return (
                    <Badge variant="secondary" className="text-xs">
                        Dismissed
                    </Badge>
                );
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AppLayout>
            <Head title="Warehouse Stock Exceptions Queue" />

            <div className="space-y-6 pb-12 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Link href="/admin/inventory" className="text-xs text-muted-foreground hover:text-foreground">
                                Inventory
                            </Link>
                            <ChevronRight className="h-3 w-3 text-muted-foreground" />
                            <span className="text-xs font-semibold text-foreground">Exceptions Queue</span>
                        </div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <ShieldAlert className="h-6 w-6 text-amber-500" />
                            Warehouse Stock Exceptions
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Quarantine ledger and administrative resolution queue for damaged, missing, or compromised stock.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href="/admin/inventory">
                            <Button variant="outline" size="sm" className="h-9 gap-1.5">
                                <ArrowLeft className="h-4 w-4" />
                                Inventory Balances
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Filter Bar */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <form onSubmit={handleFilterSubmit} className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-5">
                            {/* Search */}
                            <div className="relative">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search exception #, SKU..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 text-xs h-9"
                                />
                            </div>

                            {/* Warehouse */}
                            <select
                                value={selectedWarehouse}
                                onChange={(e) => setSelectedWarehouse(e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">All Warehouses</option>
                                {warehouses.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name} ({w.code})
                                    </option>
                                ))}
                            </select>

                            {/* Status */}
                            <select
                                value={selectedStatus}
                                onChange={(e) => setSelectedStatus(e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="ALL">All Statuses</option>
                                {statuses.map((st) => (
                                    <option key={st.value} value={st.value}>
                                        {st.label}
                                    </option>
                                ))}
                            </select>

                            {/* Severity */}
                            <select
                                value={selectedSeverity}
                                onChange={(e) => setSelectedSeverity(e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="ALL">All Severities</option>
                                {severities.map((sv) => (
                                    <option key={sv.value} value={sv.value}>
                                        {sv.label}
                                    </option>
                                ))}
                            </select>

                            {/* Filter Actions */}
                            <div className="flex items-center gap-2">
                                <Button type="submit" size="sm" className="h-9 flex-1">
                                    <Filter className="mr-1.5 h-3.5 w-3.5" />
                                    Filter
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleClearFilters}
                                    className="h-9 px-3"
                                >
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>

                {/* Exceptions Table */}
                <div className="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                    <div className="border-b border-border bg-muted/30 px-4 py-3 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-foreground">
                            Exception Queue ({exceptions.total.toLocaleString()})
                        </h2>
                    </div>

                    {exceptions.data.length === 0 ? (
                        <div className="p-12 text-center text-muted-foreground">
                            <ShieldAlert className="mx-auto h-10 w-10 text-muted-foreground/30" />
                            <p className="mt-3 text-sm font-medium text-foreground">No stock exceptions found</p>
                            <p className="text-xs text-muted-foreground">
                                No inventory exceptions match the current filter criteria.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/20 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Exception #</th>
                                        <th className="px-4 py-3">Product / SKU</th>
                                        <th className="px-3 py-3">Warehouse</th>
                                        <th className="px-3 py-3">Type / Severity</th>
                                        <th className="px-3 py-3 text-right">Quarantined</th>
                                        <th className="px-3 py-3 text-center">Status</th>
                                        <th className="px-4 py-3">Reported By / Date</th>
                                        {can_adjust && <th className="px-4 py-3 text-right">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {exceptions.data.map((exc) => (
                                        <tr key={exc.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3 font-mono text-xs font-medium text-foreground">
                                                {exc.exception_number}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">{exc.product?.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">SKU: {exc.product?.sku}</div>
                                            </td>
                                            <td className="px-3 py-3 text-xs text-muted-foreground">
                                                {exc.warehouse?.code}
                                            </td>
                                            <td className="px-3 py-3">
                                                <div className="flex flex-col gap-1 items-start">
                                                    <span className="text-xs font-medium text-foreground">{exc.exception_type}</span>
                                                    {getSeverityBadge(exc.severity)}
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                <div className="font-mono font-semibold text-rose-600 dark:text-rose-400">
                                                    {exc.quantity.toLocaleString()} {exc.product?.unit}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    From {exc.source_stock_state}
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-center">
                                                {getStatusBadge(exc.status)}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                <div>{exc.reported_by_user?.name || 'Staff'}</div>
                                                <div>{new Date(exc.created_at).toLocaleDateString()}</div>
                                            </td>
                                            {can_adjust && (
                                                <td className="px-4 py-3 text-right">
                                                    {exc.status === 'PENDING_REVIEW' && (
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-7 px-2 text-xs border-emerald-300 text-emerald-700 hover:bg-emerald-50"
                                                                onClick={() => setResolvingException(exc)}
                                                            >
                                                                Resolve
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-7 px-2 text-xs border-rose-300 text-rose-700 hover:bg-rose-50"
                                                                onClick={() => setDismissingException(exc)}
                                                            >
                                                                Dismiss
                                                            </Button>
                                                        </div>
                                                    )}
                                                    {exc.status !== 'PENDING_REVIEW' && (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            {exc.status === 'RESOLVED' ? 'Resolved' : 'Dismissed'}
                                                        </span>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Resolve Modal Dialog */}
                {resolvingException && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border border-border">
                            <h3 className="text-lg font-bold text-foreground">
                                Resolve Exception [{resolvingException.exception_number}]
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Document the operational resolution taken for {resolvingException.quantity} quarantined units of {resolvingException.product?.sku}.
                            </p>

                            <form onSubmit={submitResolve} className="mt-4 space-y-4">
                                <div>
                                    <label className="text-xs font-semibold text-foreground">
                                        Resolution Notes <span className="text-destructive">*</span>
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={resolveForm.data.resolution_notes}
                                        onChange={(e) => resolveForm.setData('resolution_notes', e.target.value)}
                                        placeholder="Explain the resolution (e.g. Scrapped, returned to vendor, repackaged)..."
                                        className="mt-1 w-full rounded-md border border-input bg-background p-2 text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                        required
                                    />
                                    {resolveForm.errors.resolution_notes && (
                                        <p className="mt-1 text-xs text-destructive">{resolveForm.errors.resolution_notes}</p>
                                    )}
                                </div>

                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setResolvingException(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={resolveForm.processing}
                                        className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                    >
                                        Confirm Resolution
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Dismiss Modal Dialog */}
                {dismissingException && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border border-border">
                            <h3 className="text-lg font-bold text-foreground">
                                Dismiss Exception [{dismissingException.exception_number}]
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Dismiss this reported stock exception with a mandatory reason.
                            </p>

                            <form onSubmit={submitDismiss} className="mt-4 space-y-4">
                                <div>
                                    <label className="text-xs font-semibold text-foreground">
                                        Dismissal Reason <span className="text-destructive">*</span>
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={dismissForm.data.dismissal_reason}
                                        onChange={(e) => dismissForm.setData('dismissal_reason', e.target.value)}
                                        placeholder="Reason for dismissal (e.g. False alarm, duplicate report)..."
                                        className="mt-1 w-full rounded-md border border-input bg-background p-2 text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                        required
                                    />
                                    {dismissForm.errors.dismissal_reason && (
                                        <p className="mt-1 text-xs text-destructive">{dismissForm.errors.dismissal_reason}</p>
                                    )}
                                </div>

                                <label className="flex items-center gap-2 text-xs text-foreground cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={dismissForm.data.revert_quarantine}
                                        onChange={(e) => dismissForm.setData('revert_quarantine', e.target.checked)}
                                        className="rounded border-input text-primary focus:ring-ring"
                                    />
                                    <span>Revert quarantined stock back to {dismissingException.source_stock_state}</span>
                                </label>

                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setDismissingException(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={dismissForm.processing}
                                        variant="destructive"
                                    >
                                        Dismiss Exception
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
