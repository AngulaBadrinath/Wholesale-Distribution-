import React, { useState, useEffect } from 'react';
import { AdminOrderQueueFilters } from '@/types/order';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Search,
    X,
    Filter,
    RotateCcw,
    Calendar,
    ChevronDown,
    SlidersHorizontal,
    ArrowUpDown,
} from 'lucide-react';

interface AdminOrderQueueFiltersProps {
    filters: AdminOrderQueueFilters;
    eligibleSalesmen: Array<{ id: number; name: string }>;
    orderStatuses: Array<{ value: string; label: string }>;
    fulfillmentStatuses: Array<{ value: string; label: string }>;
    paymentStatuses: Array<{ value: string; label: string }>;
    deliveryStatuses: Array<{ value: string; label: string }>;
    adjustmentStatuses: Array<{ value: string; label: string }>;
    onFilterChange: (newFilters: Partial<AdminOrderQueueFilters>) => void;
    onReset: () => void;
}

export default function AdminOrderQueueFiltersBar({
    filters,
    eligibleSalesmen,
    orderStatuses,
    fulfillmentStatuses,
    paymentStatuses,
    deliveryStatuses,
    adjustmentStatuses,
    onFilterChange,
    onReset,
}: AdminOrderQueueFiltersProps) {
    const [searchValue, setSearchValue] = useState(filters.search || '');
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

    // Sync external filter changes to local search input state
    useEffect(() => {
        setSearchValue(filters.search || '');
    }, [filters.search]);

    // Debounce search input to avoid request flooding
    useEffect(() => {
        const handler = setTimeout(() => {
            if (searchValue !== (filters.search || '')) {
                onFilterChange({ search: searchValue });
            }
        }, 300);

        return () => clearTimeout(handler);
    }, [searchValue]);

    // Compute active secondary filters count (excluding queue, page, per_page)
    const activeFilterCount = [
        filters.status && filters.status !== 'ALL',
        filters.fulfillment_status && filters.fulfillment_status !== 'ALL',
        filters.payment_status && filters.payment_status !== 'ALL',
        filters.delivery_status && filters.delivery_status !== 'ALL',
        filters.adjustment_status && filters.adjustment_status !== 'ALL',
        filters.salesman_id && filters.salesman_id !== 'ALL',
        Boolean(filters.date_from),
        Boolean(filters.date_to),
    ].filter(Boolean).length;

    return (
        <div className="bg-card border-x border-b border-border p-3 sm:p-4 rounded-b-lg space-y-3">
            {/* Primary Toolbar: Search + Quick Selects + Mobile Toggle */}
            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
                {/* Search Input */}
                <div className="relative flex-1 min-w-[240px]">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                    <Input
                        type="text"
                        placeholder="Search by Order #, Customer, or Salesman..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        className="pl-9 pr-8 h-9 text-xs"
                    />
                    {searchValue && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearchValue('');
                                onFilterChange({ search: '' });
                            }}
                            className="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground rounded-xs"
                            aria-label="Clear search input"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>

                {/* Desktop Quick Dropdowns */}
                <div className="hidden lg:flex items-center gap-2">
                    {/* Salesman Filter */}
                    <select
                        value={filters.salesman_id || 'ALL'}
                        onChange={(e) => onFilterChange({ salesman_id: e.target.value })}
                        className="h-9 px-2.5 text-xs bg-background border border-input rounded-md text-foreground focus:outline-hidden focus:ring-1 focus:ring-primary cursor-pointer"
                        aria-label="Filter by Salesman"
                    >
                        <option value="ALL">All Sales Representatives</option>
                        {eligibleSalesmen.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>

                    {/* Order Status */}
                    <select
                        value={filters.status || 'ALL'}
                        onChange={(e) => onFilterChange({ status: e.target.value })}
                        className="h-9 px-2.5 text-xs bg-background border border-input rounded-md text-foreground focus:outline-hidden focus:ring-1 focus:ring-primary cursor-pointer"
                        aria-label="Filter by Order Status"
                    >
                        <option value="ALL">All Order States</option>
                        {orderStatuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>

                    {/* Payment Status */}
                    <select
                        value={filters.payment_status || 'ALL'}
                        onChange={(e) => onFilterChange({ payment_status: e.target.value })}
                        className="h-9 px-2.5 text-xs bg-background border border-input rounded-md text-foreground focus:outline-hidden focus:ring-1 focus:ring-primary cursor-pointer"
                        aria-label="Filter by Payment Status"
                    >
                        <option value="ALL">All Payments</option>
                        {paymentStatuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>

                    {/* Fulfillment Status */}
                    <select
                        value={filters.fulfillment_status || 'ALL'}
                        onChange={(e) => onFilterChange({ fulfillment_status: e.target.value })}
                        className="h-9 px-2.5 text-xs bg-background border border-input rounded-md text-foreground focus:outline-hidden focus:ring-1 focus:ring-primary cursor-pointer"
                        aria-label="Filter by Fulfillment Status"
                    >
                        <option value="ALL">All Fulfillment</option>
                        {fulfillmentStatuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>

                    {/* Sort Order */}
                    <select
                        value={filters.sort_by ? `${filters.sort_by}:${filters.sort_direction || 'desc'}` : 'default'}
                        onChange={(e) => {
                            if (e.target.value === 'default') {
                                onFilterChange({ sort_by: undefined, sort_direction: undefined });
                            } else {
                                const [by, dir] = e.target.value.split(':');
                                onFilterChange({ sort_by: by, sort_direction: dir });
                            }
                        }}
                        className="h-9 px-2.5 text-xs bg-background border border-input rounded-md text-foreground focus:outline-hidden focus:ring-1 focus:ring-primary cursor-pointer"
                        aria-label="Sort Orders"
                    >
                        <option value="default">Queue Default Sort</option>
                        <option value="submitted_at:desc">Latest Submitted First</option>
                        <option value="submitted_at:asc">Oldest Submitted First</option>
                        <option value="order_number:desc">Order # (High-Low)</option>
                        <option value="order_number:asc">Order # (Low-High)</option>
                        <option value="grand_total:desc">Amount (Highest)</option>
                        <option value="grand_total:asc">Amount (Lowest)</option>
                    </select>

                    {/* Reset Button */}
                    {(activeFilterCount > 0 || searchValue) && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onReset}
                            className="h-9 px-2.5 text-xs text-muted-foreground hover:text-foreground gap-1"
                            title="Reset all filters"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                            <span>Reset</span>
                        </Button>
                    )}
                </div>

                {/* Mobile Filter Toggle Button */}
                <div className="flex items-center gap-2 lg:hidden">
                    <Button
                        type="button"
                        variant={activeFilterCount > 0 ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setMobileFiltersOpen(!mobileFiltersOpen)}
                        className="flex-1 sm:flex-initial h-9 text-xs gap-1.5 justify-center"
                    >
                        <Filter className="h-3.5 w-3.5" />
                        <span>Filter & Sort</span>
                        {activeFilterCount > 0 && (
                            <Badge variant="secondary" className="ml-1 h-4 px-1 text-[10px]">
                                {activeFilterCount}
                            </Badge>
                        )}
                    </Button>

                    {(activeFilterCount > 0 || searchValue) && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onReset}
                            className="h-9 px-2 text-xs text-muted-foreground hover:text-foreground"
                            aria-label="Reset filters"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                        </Button>
                    )}
                </div>
            </div>

            {/* Expanded Filter Panel (Desktop Extended Date Range or Mobile Collapsible) */}
            <div className={`pt-2 border-t border-border/60 ${mobileFiltersOpen ? 'block' : 'hidden lg:flex'} lg:items-center lg:justify-between gap-3 text-xs`}>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:items-center gap-2.5 flex-wrap">
                    {/* Date Range Inputs */}
                    <div className="flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                        <span className="text-muted-foreground text-[11px] font-medium">Submitted:</span>
                        <Input
                            type="date"
                            value={filters.date_from || ''}
                            onChange={(e) => onFilterChange({ date_from: e.target.value })}
                            className="h-8 text-xs w-32 px-2"
                            aria-label="Submitted Date From"
                        />
                        <span className="text-muted-foreground text-[11px]">to</span>
                        <Input
                            type="date"
                            value={filters.date_to || ''}
                            onChange={(e) => onFilterChange({ date_to: e.target.value })}
                            className="h-8 text-xs w-32 px-2"
                            aria-label="Submitted Date To"
                        />
                    </div>

                    {/* Mobile Only Selects */}
                    <div className="lg:hidden space-y-2 col-span-full pt-2">
                        <div>
                            <label className="text-[11px] font-medium text-muted-foreground block mb-1">
                                Sales Representative
                            </label>
                            <select
                                value={filters.salesman_id || 'ALL'}
                                onChange={(e) => onFilterChange({ salesman_id: e.target.value })}
                                className="w-full h-9 px-2 text-xs bg-background border border-input rounded-md"
                            >
                                <option value="ALL">All Sales Representatives</option>
                                {eligibleSalesmen.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="text-[11px] font-medium text-muted-foreground block mb-1">
                                    Order Status
                                </label>
                                <select
                                    value={filters.status || 'ALL'}
                                    onChange={(e) => onFilterChange({ status: e.target.value })}
                                    className="w-full h-9 px-2 text-xs bg-background border border-input rounded-md"
                                >
                                    <option value="ALL">All States</option>
                                    {orderStatuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-[11px] font-medium text-muted-foreground block mb-1">
                                    Payment
                                </label>
                                <select
                                    value={filters.payment_status || 'ALL'}
                                    onChange={(e) => onFilterChange({ payment_status: e.target.value })}
                                    className="w-full h-9 px-2 text-xs bg-background border border-input rounded-md"
                                >
                                    <option value="ALL">All Payments</option>
                                    {paymentStatuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-[11px] font-medium text-muted-foreground block mb-1">
                                    Fulfillment
                                </label>
                                <select
                                    value={filters.fulfillment_status || 'ALL'}
                                    onChange={(e) => onFilterChange({ fulfillment_status: e.target.value })}
                                    className="w-full h-9 px-2 text-xs bg-background border border-input rounded-md"
                                >
                                    <option value="ALL">All Fulfillment</option>
                                    {fulfillmentStatuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-[11px] font-medium text-muted-foreground block mb-1">
                                    Delivery
                                </label>
                                <select
                                    value={filters.delivery_status || 'ALL'}
                                    onChange={(e) => onFilterChange({ delivery_status: e.target.value })}
                                    className="w-full h-9 px-2 text-xs bg-background border border-input rounded-md"
                                >
                                    <option value="ALL">All Delivery</option>
                                    {deliveryStatuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-[11px] font-medium text-muted-foreground block mb-1">
                                Sorting
                            </label>
                            <select
                                value={filters.sort_by ? `${filters.sort_by}:${filters.sort_direction || 'desc'}` : 'default'}
                                onChange={(e) => {
                                    if (e.target.value === 'default') {
                                        onFilterChange({ sort_by: undefined, sort_direction: undefined });
                                    } else {
                                        const [by, dir] = e.target.value.split(':');
                                        onFilterChange({ sort_by: by, sort_direction: dir });
                                    }
                                }}
                                className="w-full h-9 px-2 text-xs bg-background border border-input rounded-md"
                            >
                                <option value="default">Queue Default Sort</option>
                                <option value="submitted_at:desc">Latest Submitted First</option>
                                <option value="submitted_at:asc">Oldest Submitted First</option>
                                <option value="order_number:desc">Order # (High-Low)</option>
                                <option value="order_number:asc">Order # (Low-High)</option>
                                <option value="grand_total:desc">Amount (Highest)</option>
                                <option value="grand_total:asc">Amount (Lowest)</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Page Size Selector */}
                <div className="flex items-center gap-2 justify-end pt-2 lg:pt-0">
                    <span className="text-muted-foreground text-[11px]">Rows per page:</span>
                    <select
                        value={filters.per_page || 25}
                        onChange={(e) => onFilterChange({ per_page: Number(e.target.value) })}
                        className="h-7 px-2 text-xs bg-background border border-input rounded-md text-foreground cursor-pointer"
                        aria-label="Rows per page"
                    >
                        <option value={15}>15</option>
                        <option value={25}>25</option>
                        <option value={50}>50</option>
                        <option value={100}>100</option>
                    </select>
                </div>
            </div>
        </div>
    );
}
