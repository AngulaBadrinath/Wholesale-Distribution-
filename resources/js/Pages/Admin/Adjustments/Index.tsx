import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    OrderAdjustmentQueueItem,
    OrderAdjustmentQueueCounts,
    OrderAdjustmentQueueFilters,
} from '@/types/order';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    SlidersHorizontal,
    Search,
    RotateCcw,
    ChevronLeft,
    ChevronRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    AlertTriangle,
    CheckCircle2,
    Clock,
    FileText,
    User,
    Building2,
    Package,
    ShieldAlert,
    ExternalLink,
} from 'lucide-react';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedAdjustments {
    data: OrderAdjustmentQueueItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface AdjustmentsIndexProps {
    adjustments: PaginatedAdjustments;
    counts: OrderAdjustmentQueueCounts;
    filters: OrderAdjustmentQueueFilters;
    reasonCodes: Array<{ value: string; label: string }>;
}

export default function AdjustmentsIndex({
    adjustments,
    counts,
    filters,
    reasonCodes,
}: AdjustmentsIndexProps) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const handleTabSelect = (statusKey: string, impactKey: string = 'ALL') => {
        router.get(
            '/admin/adjustments',
            {
                ...filters,
                status: statusKey,
                impact_case: impactKey,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const handleFilterChange = (newFilters: Partial<OrderAdjustmentQueueFilters>) => {
        router.get(
            '/admin/adjustments',
            {
                ...filters,
                ...newFilters,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        handleFilterChange({ search: searchTerm });
    };

    const handleSortChange = (column: string) => {
        const isCurrent = filters.sort_by === column;
        const newDirection = isCurrent && filters.sort_direction === 'asc' ? 'desc' : 'asc';
        handleFilterChange({
            sort_by: column,
            sort_direction: newDirection,
        });
    };

    const handleReset = () => {
        setSearchTerm('');
        router.get('/admin/adjustments', {}, { preserveState: true, preserveScroll: true });
    };

    const renderSortHeader = (label: string, column: string, align: 'left' | 'right' | 'center' = 'left') => {
        const isCurrentSort = filters.sort_by === column;
        const Icon = isCurrentSort
            ? filters.sort_direction === 'asc'
                ? ArrowUp
                : ArrowDown
            : ArrowUpDown;

        return (
            <th
                scope="col"
                className={`py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground ${
                    align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left'
                }`}
            >
                <button
                    type="button"
                    onClick={() => handleSortChange(column)}
                    className={`inline-flex items-center gap-1 hover:text-foreground transition-colors group focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary rounded-xs ${
                        align === 'right' ? 'justify-end' : ''
                    }`}
                >
                    <span>{label}</span>
                    <Icon className={`h-3 w-3 ${isCurrentSort ? 'text-primary' : 'text-muted-foreground/50 group-hover:text-foreground'}`} />
                </button>
            </th>
        );
    };

    return (
        <AppLayout title="Order Adjustment Review Queue">
            <Head title="Order Adjustment Review Queue" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border pb-5">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Adjustment Review Queue
                            </h1>
                            <Badge variant="outline" className="text-xs bg-muted/50 font-normal">
                                Phase 06 Operations
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Triage, evaluate, and review post-submission quantity adjustment requests across pending orders.
                        </p>
                    </div>
                </div>

                {/* Queue Summary Tabs */}
                <div className="border-b border-border">
                    <nav className="flex space-x-2 overflow-x-auto pb-px" aria-label="Adjustment Queue Tabs">
                        <button
                            type="button"
                            onClick={() => handleTabSelect('SUBMITTED', 'ALL')}
                            className={`flex items-center gap-2 py-2.5 px-3.5 border-b-2 text-xs font-medium whitespace-nowrap transition-colors ${
                                filters.status === 'SUBMITTED' && filters.impact_case === 'ALL'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
                            }`}
                        >
                            <span>Pending Review</span>
                            <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-primary/10 text-primary">
                                {counts.submitted}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabSelect('SUBMITTED', 'CASE_B')}
                            className={`flex items-center gap-2 py-2.5 px-3.5 border-b-2 text-xs font-medium whitespace-nowrap transition-colors ${
                                filters.status === 'SUBMITTED' && filters.impact_case === 'CASE_B'
                                    ? 'border-amber-500 text-amber-500'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
                            }`}
                        >
                            <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
                            <span>Allocation Affected (Case B)</span>
                            <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                {counts.case_b}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabSelect('APPROVED', 'ALL')}
                            className={`flex items-center gap-2 py-2.5 px-3.5 border-b-2 text-xs font-medium whitespace-nowrap transition-colors ${
                                filters.status === 'APPROVED'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
                            }`}
                        >
                            <span>Approved</span>
                            <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-muted-foreground">
                                {counts.approved}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabSelect('REJECTED', 'ALL')}
                            className={`flex items-center gap-2 py-2.5 px-3.5 border-b-2 text-xs font-medium whitespace-nowrap transition-colors ${
                                filters.status === 'REJECTED'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
                            }`}
                        >
                            <span>Rejected</span>
                            <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-muted-foreground">
                                {counts.rejected}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabSelect('CANCELLED', 'ALL')}
                            className={`flex items-center gap-2 py-2.5 px-3.5 border-b-2 text-xs font-medium whitespace-nowrap transition-colors ${
                                filters.status === 'CANCELLED'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
                            }`}
                        >
                            <span>Withdrawn</span>
                            <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-muted-foreground">
                                {counts.cancelled}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabSelect('ALL', 'ALL')}
                            className={`flex items-center gap-2 py-2.5 px-3.5 border-b-2 text-xs font-medium whitespace-nowrap transition-colors ${
                                filters.status === 'ALL'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
                            }`}
                        >
                            <span>All Adjustments</span>
                            <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-muted-foreground">
                                {counts.all}
                            </span>
                        </button>
                    </nav>
                </div>

                {/* Filters Bar */}
                <div className="bg-card border border-border rounded-lg p-4 shadow-xs space-y-4">
                    <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                        {/* Search Input */}
                        <div className="lg:col-span-5 relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search by adjustment #, order #, customer, or requester..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 text-xs h-9"
                            />
                        </div>

                        {/* Impact Case Filter */}
                        <div className="lg:col-span-3">
                            <select
                                value={filters.impact_case || 'ALL'}
                                onChange={(e) => handleFilterChange({ impact_case: e.target.value })}
                                aria-label="Filter by allocation impact case"
                                className="w-full text-xs h-9 rounded-md border border-input bg-background px-3 py-1 shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary text-foreground"
                            >
                                <option value="ALL">All Impact Cases</option>
                                <option value="CASE_A">Case A: Standard (Unallocated Only)</option>
                                <option value="CASE_B">Case B: Allocation Affected</option>
                            </select>
                        </div>

                        {/* Reason Filter */}
                        <div className="lg:col-span-3">
                            <select
                                value={filters.reason_code || ''}
                                onChange={(e) => handleFilterChange({ reason_code: e.target.value })}
                                aria-label="Filter by reason code"
                                className="w-full text-xs h-9 rounded-md border border-input bg-background px-3 py-1 shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary text-foreground"
                            >
                                <option value="">All Reason Codes</option>
                                {reasonCodes.map((rc) => (
                                    <option key={rc.value} value={rc.value}>
                                        {rc.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Actions */}
                        <div className="lg:col-span-1 flex items-center gap-2">
                            <Button type="submit" size="sm" className="h-9 w-full text-xs">
                                Filter
                            </Button>
                            {(filters.search || filters.reason_code || filters.impact_case !== 'ALL') && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleReset}
                                    className="h-9 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                                    title="Reset filters"
                                >
                                    <RotateCcw className="h-3.5 w-3.5" />
                                </Button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Queue Data Table (Desktop / Tablet) */}
                {adjustments.data.length === 0 ? (
                    <div className="bg-card border border-border rounded-lg p-12 text-center space-y-3">
                        <div className="h-12 w-12 rounded-full bg-muted/60 text-muted-foreground flex items-center justify-center mx-auto">
                            <FileText className="h-6 w-6" />
                        </div>
                        <h3 className="text-base font-semibold text-foreground">No adjustment requests found</h3>
                        <p className="text-xs text-muted-foreground max-w-sm mx-auto">
                            There are currently no adjustment requests matching the selected queue or filter criteria.
                        </p>
                    </div>
                ) : (
                    <div className="bg-card border border-border rounded-lg overflow-hidden shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse" aria-label="Order Adjustment Review Queue">
                                <thead>
                                    <tr className="border-b border-border bg-muted/30">
                                        {renderSortHeader('Adjustment #', 'adjustment_number')}
                                        <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Order & Customer
                                        </th>
                                        <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Requester
                                        </th>
                                        <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Reason
                                        </th>
                                        <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Allocation Impact
                                        </th>
                                        {renderSortHeader('Projected Reduction', 'projected_grand_total_reduction', 'right')}
                                        <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Status
                                        </th>
                                        {renderSortHeader('Requested At', 'requested_at')}
                                        <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground text-right">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {adjustments.data.map((adj) => (
                                        <tr key={adj.id} className="hover:bg-muted/20 transition-colors">
                                            {/* Adjustment # */}
                                            <td className="py-3 px-3.5 whitespace-nowrap">
                                                <div className="font-mono text-xs font-semibold text-foreground">
                                                    {adj.adjustment_number}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    {adj.items_count} {adj.items_count === 1 ? 'item' : 'items'}
                                                </div>
                                            </td>

                                            {/* Order & Customer */}
                                            <td className="py-3 px-3.5">
                                                <div className="flex items-center gap-1.5 font-medium text-xs text-foreground">
                                                    <span>{adj.order_number}</span>
                                                    <Badge variant="outline" className="text-[10px] px-1 py-0 h-4">
                                                        {adj.order_status}
                                                    </Badge>
                                                </div>
                                                <div className="text-xs text-muted-foreground truncate max-w-[200px]">
                                                    {adj.customer_name} ({adj.customer_code})
                                                </div>
                                            </td>

                                            {/* Requester */}
                                            <td className="py-3 px-3.5 whitespace-nowrap">
                                                <div className="text-xs font-medium text-foreground">
                                                    {adj.requester_name}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    {adj.requester_role}
                                                </div>
                                            </td>

                                            {/* Reason */}
                                            <td className="py-3 px-3.5 whitespace-nowrap">
                                                <Badge variant="outline" className="text-[11px] font-normal">
                                                    {adj.reason_label}
                                                </Badge>
                                            </td>

                                            {/* Allocation Impact */}
                                            <td className="py-3 px-3.5 whitespace-nowrap">
                                                {adj.impact_case === 'CASE_B' ? (
                                                    <Badge variant="destructive" className="text-[11px] gap-1 bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30">
                                                        <AlertTriangle className="h-3 w-3" />
                                                        <span>Case B ({adj.affected_allocation_quantity} alloc. affected)</span>
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-[11px] gap-1 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 bg-emerald-500/10">
                                                        <CheckCircle2 className="h-3 w-3" />
                                                        <span>Case A (Unallocated)</span>
                                                    </Badge>
                                                )}
                                            </td>

                                            {/* Projected Reduction */}
                                            <td className="py-3 px-3.5 text-right whitespace-nowrap font-mono text-xs font-semibold text-destructive">
                                                -${adj.projected_grand_total_reduction}
                                            </td>

                                            {/* Status */}
                                            <td className="py-3 px-3.5 whitespace-nowrap">
                                                <Badge
                                                    variant={
                                                        adj.status === 'SUBMITTED'
                                                            ? 'default'
                                                            : adj.status === 'APPROVED'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className="text-[11px]"
                                                >
                                                    {adj.status_label}
                                                </Badge>
                                            </td>

                                            {/* Requested At */}
                                            <td className="py-3 px-3.5 whitespace-nowrap text-xs text-muted-foreground">
                                                {adj.requested_at_formatted || '—'}
                                            </td>

                                            {/* Action */}
                                            <td className="py-3 px-3.5 text-right whitespace-nowrap">
                                                <Link href={`/admin/orders/${adj.order_id}/adjustments/${adj.id}/review`}>
                                                    <Button
                                                        variant="default"
                                                        size="sm"
                                                        className="h-8 min-h-[32px] px-3 text-xs gap-1.5 shadow-xs"
                                                    >
                                                        <FileText className="h-3.5 w-3.5" />
                                                        <span>Review</span>
                                                    </Button>
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="px-4 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-muted-foreground bg-muted/10">
                            <div>
                                Showing <span className="font-medium text-foreground">{adjustments.from || 0}</span> to{' '}
                                <span className="font-medium text-foreground">{adjustments.to || 0}</span> of{' '}
                                <span className="font-medium text-foreground">{adjustments.total}</span> requests
                            </div>
                            <div className="flex items-center gap-1">
                                {adjustments.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                                        className="h-8 min-w-[32px] px-2 text-xs"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
