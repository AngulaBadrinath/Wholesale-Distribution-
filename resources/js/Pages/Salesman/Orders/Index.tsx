import React, { useEffect, useState, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { OrderHistoryItem, OrderHistoryFilters } from '@/types/order';
import { PaginatedResponse } from '@/types';
import OrderHistoryFiltersComponent from './Partials/OrderHistoryFilters';
import OrderHistoryTable from './Partials/OrderHistoryTable';
import OrderHistoryCard from './Partials/OrderHistoryCard';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import {
    Receipt,
    Plus,
    FileText,
    ChevronLeft,
    ChevronRight,
    ShoppingBag,
    SearchX,
} from 'lucide-react';

interface OrderHistoryIndexProps {
    orders: PaginatedResponse<OrderHistoryItem>;
    filters: OrderHistoryFilters;
    statusOptions: Array<{ value: string; label: string }>;
    fulfillmentOptions: Array<{ value: string; label: string }>;
    paymentOptions: Array<{ value: string; label: string }>;
    deliveryOptions: Array<{ value: string; label: string }>;
}

export default function OrderHistoryIndex({
    orders,
    filters,
    statusOptions,
    fulfillmentOptions,
    paymentOptions,
    deliveryOptions,
}: OrderHistoryIndexProps) {
    const [localFilters, setLocalFilters] = useState<OrderHistoryFilters>(filters);
    const isFirstRender = useRef(true);

    // Debounced search / filter sync with Inertia URL state
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            const queryParams: Record<string, string> = {};

            if (localFilters.search) queryParams.search = localFilters.search;
            if (localFilters.status) queryParams.status = localFilters.status;
            if (localFilters.fulfillment_status) queryParams.fulfillment_status = localFilters.fulfillment_status;
            if (localFilters.payment_status) queryParams.payment_status = localFilters.payment_status;
            if (localFilters.delivery_status) queryParams.delivery_status = localFilters.delivery_status;
            if (localFilters.date_from) queryParams.date_from = localFilters.date_from;
            if (localFilters.date_to) queryParams.date_to = localFilters.date_to;

            router.get('/salesman/orders', queryParams, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(timer);
    }, [localFilters]);

    const handleFilterChange = (newFilters: OrderHistoryFilters) => {
        setLocalFilters(newFilters);
    };

    const handleResetFilters = () => {
        setLocalFilters({
            search: '',
            status: '',
            fulfillment_status: '',
            payment_status: '',
            delivery_status: '',
            date_from: '',
            date_to: '',
        });
        router.get('/salesman/orders', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const hasActiveFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.fulfillment_status ||
        filters.payment_status ||
        filters.delivery_status ||
        filters.date_from ||
        filters.date_to
    );

    return (
        <AppLayout title="Order History">
            <Head title="Sales Order History & Status Timeline" />

            <div className="space-y-6 pb-16">
                {/* Page Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-5">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-lg bg-primary/10 text-primary">
                                <Receipt className="h-5 w-5" />
                            </div>
                            <h1 className="text-xl font-bold tracking-tight text-foreground">
                                Sales Order History
                            </h1>
                            <Badge variant="secondary" className="font-mono text-xs">
                                {orders.total} {orders.total === 1 ? 'Order' : 'Orders'}
                            </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Inspect your committed customer orders, live multi-state workflow progress, and detailed financial summaries.
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Link href="/salesman/orders/drafts">
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs h-9">
                                <FileText className="h-3.5 w-3.5 text-muted-foreground" />
                                <span>Draft Orders</span>
                            </Button>
                        </Link>
                        <Link href="/salesman/orders/create">
                            <Button size="sm" className="gap-1.5 text-xs h-9">
                                <Plus className="h-3.5 w-3.5" />
                                <span>New Order</span>
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Filters Toolbar */}
                <OrderHistoryFiltersComponent
                    filters={localFilters}
                    statusOptions={statusOptions}
                    fulfillmentOptions={fulfillmentOptions}
                    paymentOptions={paymentOptions}
                    deliveryOptions={deliveryOptions}
                    onFilterChange={handleFilterChange}
                    onReset={handleResetFilters}
                />

                {/* Main Content: Desktop Table vs Mobile Cards */}
                {orders.data.length > 0 ? (
                    <div className="space-y-4">
                        {/* Desktop & Tablet Table (md and up) */}
                        <div className="hidden md:block">
                            <OrderHistoryTable orders={orders.data} />
                        </div>

                        {/* Mobile Cards Feed (< md) */}
                        <div className="grid grid-cols-1 gap-3 md:hidden">
                            {orders.data.map((order) => (
                                <OrderHistoryCard key={order.id} order={order} />
                            ))}
                        </div>

                        {/* Pagination Bar */}
                        {orders.total > orders.per_page && (
                            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t text-xs text-muted-foreground">
                                <div className="font-mono text-[11px]">
                                    Showing <span className="font-semibold text-foreground">{orders.from}</span> to{' '}
                                    <span className="font-semibold text-foreground">{orders.to}</span> of{' '}
                                    <span className="font-semibold text-foreground">{orders.total}</span> orders
                                </div>

                                <div className="flex items-center gap-1.5">
                                    {orders.prev_page_url ? (
                                        <Link href={orders.prev_page_url} preserveScroll preserveState>
                                            <Button variant="outline" size="sm" className="h-8 text-xs gap-1">
                                                <ChevronLeft className="h-3.5 w-3.5" />
                                                <span>Previous</span>
                                            </Button>
                                        </Link>
                                    ) : (
                                        <Button variant="outline" size="sm" disabled className="h-8 text-xs gap-1 opacity-50">
                                            <ChevronLeft className="h-3.5 w-3.5" />
                                            <span>Previous</span>
                                        </Button>
                                    )}

                                    {/* Page Number Indicators */}
                                    <div className="hidden sm:flex items-center gap-1">
                                        {orders.links.map((link, i) => {
                                            if (link.label.includes('Previous') || link.label.includes('Next')) return null;

                                            return link.url ? (
                                                <Link key={i} href={link.url} preserveScroll preserveState>
                                                    <Button
                                                        variant={link.active ? 'default' : 'outline'}
                                                        size="sm"
                                                        className="h-8 w-8 p-0 text-xs font-mono"
                                                    >
                                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                                    </Button>
                                                </Link>
                                            ) : (
                                                <span key={i} className="px-2 text-muted-foreground font-mono">
                                                    ...
                                                </span>
                                            );
                                        })}
                                    </div>

                                    {orders.next_page_url ? (
                                        <Link href={orders.next_page_url} preserveScroll preserveState>
                                            <Button variant="outline" size="sm" className="h-8 text-xs gap-1">
                                                <span>Next</span>
                                                <ChevronRight className="h-3.5 w-3.5" />
                                            </Button>
                                        </Link>
                                    ) : (
                                        <Button variant="outline" size="sm" disabled className="h-8 text-xs gap-1 opacity-50">
                                            <span>Next</span>
                                            <ChevronRight className="h-3.5 w-3.5" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    /* Empty States */
                    <Card className="border-dashed border-2 py-12">
                        <CardContent className="flex flex-col items-center justify-center text-center space-y-4">
                            <div className="h-12 w-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
                                {hasActiveFilters ? (
                                    <SearchX className="h-6 w-6" />
                                ) : (
                                    <ShoppingBag className="h-6 w-6" />
                                )}
                            </div>

                            <div className="space-y-1 max-w-sm">
                                <h3 className="text-base font-bold text-foreground">
                                    {hasActiveFilters ? 'No Matching Orders Found' : 'No Submitted Orders Yet'}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {hasActiveFilters
                                        ? 'No orders match your active search term or filter criteria. Try adjusting or clearing your filters.'
                                        : 'You have not submitted any customer orders yet. Create and submit your first sales order to track its lifecycle here.'}
                                </p>
                            </div>

                            <div className="pt-2">
                                {hasActiveFilters ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetFilters}
                                        className="text-xs"
                                    >
                                        Clear All Filters
                                    </Button>
                                ) : (
                                    <Link href="/salesman/orders/create">
                                        <Button size="sm" className="gap-1.5 text-xs">
                                            <Plus className="h-3.5 w-3.5" />
                                            <span>Create New Order</span>
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
