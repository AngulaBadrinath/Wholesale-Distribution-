import React, { useState } from 'react';
import { OrderHistoryFilters } from '@/types/order';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Search, RotateCcw, Filter, X, Calendar, ChevronDown, ChevronUp } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';

interface OrderHistoryFiltersProps {
    filters: OrderHistoryFilters;
    statusOptions: Array<{ value: string; label: string }>;
    fulfillmentOptions: Array<{ value: string; label: string }>;
    paymentOptions: Array<{ value: string; label: string }>;
    deliveryOptions: Array<{ value: string; label: string }>;
    onFilterChange: (newFilters: OrderHistoryFilters) => void;
    onReset: () => void;
}

export default function OrderHistoryFiltersComponent({
    filters,
    statusOptions,
    fulfillmentOptions,
    paymentOptions,
    deliveryOptions,
    onFilterChange,
    onReset,
}: OrderHistoryFiltersProps) {
    const [mobileExpanded, setMobileExpanded] = useState(false);

    const activeFilterCount = [
        filters.status,
        filters.fulfillment_status,
        filters.payment_status,
        filters.delivery_status,
        filters.date_from,
        filters.date_to,
    ].filter(Boolean).length;

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        onFilterChange({
            ...filters,
            search: e.target.value,
        });
    };

    const handleSelectChange = (key: keyof OrderHistoryFilters, value: string) => {
        onFilterChange({
            ...filters,
            [key]: value === 'ALL' ? '' : value,
        });
    };

    return (
        <div className="space-y-3 bg-card border rounded-lg p-4 shadow-xs">
            {/* Primary Toolbar: Search + Quick Toggle */}
            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                {/* Search Input */}
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Search by order number (e.g. ORD-), customer name or code..."
                        value={filters.search || ''}
                        onChange={handleSearchChange}
                        className="pl-9 pr-8 text-xs h-9 w-full bg-background"
                        maxLength={100}
                    />
                    {filters.search && (
                        <button
                            type="button"
                            onClick={() => onFilterChange({ ...filters, search: '' })}
                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            aria-label="Clear search input"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>

                {/* Mobile Filter Toggle & Reset */}
                <div className="flex items-center gap-2 shrink-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setMobileExpanded(!mobileExpanded)}
                        className="sm:hidden text-xs h-9 gap-1.5 flex-1"
                    >
                        <Filter className="h-3.5 w-3.5" />
                        <span>Filters</span>
                        {activeFilterCount > 0 && (
                            <Badge variant="default" className="text-[10px] px-1.5 py-0 h-4">
                                {activeFilterCount}
                            </Badge>
                        )}
                        {mobileExpanded ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                    </Button>

                    {(activeFilterCount > 0 || filters.search) && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onReset}
                            className="text-xs h-9 gap-1.5 text-muted-foreground hover:text-foreground"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Reset</span>
                        </Button>
                    )}
                </div>
            </div>

            {/* Filter Controls Row (Responsive Grid: Desktop visible, Mobile collapsible) */}
            <div className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 pt-2 sm:pt-0 border-t sm:border-t-0 ${mobileExpanded ? 'block' : 'hidden sm:grid'}`}>
                {/* Order Status */}
                <div className="space-y-1">
                    <label htmlFor="filter-status" className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                        Order Status
                    </label>
                    <select
                        id="filter-status"
                        value={filters.status || 'ALL'}
                        onChange={(e) => handleSelectChange('status', e.target.value)}
                        className="w-full text-xs h-8 rounded-md border border-input bg-background px-2 py-1 text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="ALL">All Order States</option>
                        {statusOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Fulfillment Status */}
                <div className="space-y-1">
                    <label htmlFor="filter-fulfillment" className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                        Fulfillment
                    </label>
                    <select
                        id="filter-fulfillment"
                        value={filters.fulfillment_status || 'ALL'}
                        onChange={(e) => handleSelectChange('fulfillment_status', e.target.value)}
                        className="w-full text-xs h-8 rounded-md border border-input bg-background px-2 py-1 text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="ALL">All Fulfillment</option>
                        {fulfillmentOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Payment Status */}
                <div className="space-y-1">
                    <label htmlFor="filter-payment" className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                        Payment
                    </label>
                    <select
                        id="filter-payment"
                        value={filters.payment_status || 'ALL'}
                        onChange={(e) => handleSelectChange('payment_status', e.target.value)}
                        className="w-full text-xs h-8 rounded-md border border-input bg-background px-2 py-1 text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="ALL">All Payments</option>
                        {paymentOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Delivery Status */}
                <div className="space-y-1">
                    <label htmlFor="filter-delivery" className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                        Delivery
                    </label>
                    <select
                        id="filter-delivery"
                        value={filters.delivery_status || 'ALL'}
                        onChange={(e) => handleSelectChange('delivery_status', e.target.value)}
                        className="w-full text-xs h-8 rounded-md border border-input bg-background px-2 py-1 text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="ALL">All Deliveries</option>
                        {deliveryOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Date From */}
                <div className="space-y-1">
                    <label htmlFor="filter-date-from" className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                        <Calendar className="h-3 w-3" />
                        <span>Date From</span>
                    </label>
                    <Input
                        id="filter-date-from"
                        type="date"
                        value={filters.date_from || ''}
                        onChange={(e) => handleSelectChange('date_from', e.target.value)}
                        className="text-xs h-8 bg-background"
                    />
                </div>

                {/* Date To */}
                <div className="space-y-1">
                    <label htmlFor="filter-date-to" className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                        <Calendar className="h-3 w-3" />
                        <span>Date To</span>
                    </label>
                    <Input
                        id="filter-date-to"
                        type="date"
                        value={filters.date_to || ''}
                        onChange={(e) => handleSelectChange('date_to', e.target.value)}
                        className="text-xs h-8 bg-background"
                    />
                </div>
            </div>
        </div>
    );
}
