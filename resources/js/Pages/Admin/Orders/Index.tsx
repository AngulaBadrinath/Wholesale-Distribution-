import React from 'react';
import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    AdminOrderQueueCounts,
    AdminOrderQueueFilters,
    AdminOrderQueueItem,
} from '@/types/order';
import AdminOrderQueueTabs from './Partials/AdminOrderQueueTabs';
import AdminOrderQueueFiltersBar from './Partials/AdminOrderQueueFilters';
import AdminOrderQueueTable from './Partials/AdminOrderQueueTable';
import AdminOrderQueueCard from './Partials/AdminOrderQueueCard';
import { Button } from '@/Components/ui/button';
import {
    Layers,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedOrders {
    data: AdminOrderQueueItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface AdminOrdersIndexProps {
    orders: PaginatedOrders;
    counts: AdminOrderQueueCounts;
    filters: AdminOrderQueueFilters;
    eligibleSalesmen: Array<{ id: number; name: string }>;
    orderStatuses: Array<{ value: string; label: string }>;
    fulfillmentStatuses: Array<{ value: string; label: string }>;
    paymentStatuses: Array<{ value: string; label: string }>;
    deliveryStatuses: Array<{ value: string; label: string }>;
    adjustmentStatuses: Array<{ value: string; label: string }>;
    can: {
        view: boolean;
        approve: boolean;
        reject: boolean;
        cancel: boolean;
    };
}

export default function AdminOrdersIndex({
    orders,
    counts,
    filters,
    eligibleSalesmen,
    orderStatuses,
    fulfillmentStatuses,
    paymentStatuses,
    deliveryStatuses,
    adjustmentStatuses,
    can,
}: AdminOrdersIndexProps) {
    // Handle operational queue tab switching
    const handleSelectQueue = (queueKey: string) => {
        router.get(
            '/admin/orders',
            {
                ...filters,
                queue: queueKey,
                page: 1, // Reset to first page when changing queues
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    // Handle filter changes
    const handleFilterChange = (newFilters: Partial<AdminOrderQueueFilters>) => {
        router.get(
            '/admin/orders',
            {
                ...filters,
                ...newFilters,
                page: 1, // Reset to page 1 on filter changes
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    // Handle column sort toggle
    const handleSortChange = (column: string) => {
        const isCurrent = filters.sort_by === column;
        const newDirection = isCurrent && filters.sort_direction === 'asc' ? 'desc' : 'asc';

        router.get(
            '/admin/orders',
            {
                ...filters,
                sort_by: column,
                sort_direction: newDirection,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    // Reset filters while preserving the active queue
    const handleReset = () => {
        router.get(
            '/admin/orders',
            {
                queue: filters.queue || 'new',
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    return (
        <AppLayout title="Order Operations Queue">
            <Head title="Admin Order Queue — Operational Workflows" />

            <div className="max-w-7xl mx-auto space-y-4 pb-16">
                {/* Header Title & Subtitle */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border pb-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2.5">
                            <div className="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                <Layers className="h-4 w-4" />
                            </div>
                            <h1 className="text-xl font-bold tracking-tight text-foreground">
                                Operational Order Queues
                            </h1>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Centralized dispatch and review inbox for incoming wholesale commercial orders.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground font-mono bg-muted/60 px-2.5 py-1 rounded-md border border-border">
                            Total Records: <strong className="text-foreground">{orders.total.toLocaleString()}</strong>
                        </span>
                    </div>
                </div>

                {/* Main Queue Framework Container */}
                <div>
                    {/* Operational Queue Tabs */}
                    <AdminOrderQueueTabs
                        activeQueue={filters.queue || 'new'}
                        counts={counts}
                        onSelectQueue={handleSelectQueue}
                    />

                    {/* Filter and Search Toolbar */}
                    <AdminOrderQueueFiltersBar
                        filters={filters}
                        eligibleSalesmen={eligibleSalesmen}
                        orderStatuses={orderStatuses}
                        fulfillmentStatuses={fulfillmentStatuses}
                        paymentStatuses={paymentStatuses}
                        deliveryStatuses={deliveryStatuses}
                        adjustmentStatuses={adjustmentStatuses}
                        onFilterChange={handleFilterChange}
                        onReset={handleReset}
                    />
                </div>

                {/* Content View: Desktop/Tablet Table */}
                <div className="hidden md:block">
                    <AdminOrderQueueTable
                        orders={orders.data}
                        sortBy={filters.sort_by}
                        sortDirection={filters.sort_direction}
                        onSortChange={handleSortChange}
                    />
                </div>

                {/* Content View: Mobile Card Feed */}
                <div className="md:hidden space-y-3 my-4">
                    {orders.data.length === 0 ? (
                        <div className="bg-card border border-border rounded-lg p-8 text-center space-y-2">
                            <p className="text-sm font-semibold text-foreground">No orders found</p>
                            <p className="text-xs text-muted-foreground">
                                No records match the active queue and filter selections.
                            </p>
                        </div>
                    ) : (
                        orders.data.map((order) => (
                            <AdminOrderQueueCard key={order.id} order={order} />
                        ))
                    )}
                </div>

                {/* Pagination Controls */}
                {orders.total > 0 && (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 px-1">
                        <div className="text-xs text-muted-foreground">
                            Showing <span className="font-semibold text-foreground">{orders.from || 0}</span> to{' '}
                            <span className="font-semibold text-foreground">{orders.to || 0}</span> of{' '}
                            <span className="font-semibold text-foreground">{orders.total.toLocaleString()}</span> orders
                        </div>

                        {/* Page Link Buttons */}
                        {orders.last_page > 1 && (
                            <div className="flex items-center gap-1">
                                {orders.links.map((link, idx) => {
                                    if (link.label.includes('Previous')) {
                                        return (
                                            <Button
                                                key={idx}
                                                variant="outline"
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                                className="h-8 px-2.5 text-xs gap-1"
                                                aria-label="Previous Page"
                                            >
                                                <ChevronLeft className="h-3.5 w-3.5" />
                                                <span className="hidden sm:inline">Previous</span>
                                            </Button>
                                        );
                                    }

                                    if (link.label.includes('Next')) {
                                        return (
                                            <Button
                                                key={idx}
                                                variant="outline"
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                                className="h-8 px-2.5 text-xs gap-1"
                                                aria-label="Next Page"
                                            >
                                                <span className="hidden sm:inline">Next</span>
                                                <ChevronRight className="h-3.5 w-3.5" />
                                            </Button>
                                        );
                                    }

                                    return (
                                        <Button
                                            key={idx}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                            className={`h-8 w-8 p-0 text-xs font-mono ${link.active ? 'pointer-events-none font-bold' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
