import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Warehouse,
    InventoryBalanceItem,
    InventorySummaryCounts,
    InventoryFilters,
    PaginatedInventoryBalances,
} from '@/types/inventory';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    Search,
    RotateCcw,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Boxes,
    CheckCircle2,
    AlertTriangle,
    AlertOctagon,
    Warehouse as WarehouseIcon,
    Layers,
    MapPin,
    ShieldAlert,
    Calendar,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface InventoryIndexProps {
    balances: PaginatedInventoryBalances;
    summaryCounts: InventorySummaryCounts;
    warehouses: Warehouse[];
    filters: InventoryFilters;
}

export default function InventoryIndex({
    balances,
    summaryCounts,
    warehouses,
    filters,
}: InventoryIndexProps) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const handleFilterChange = (newFilters: Partial<InventoryFilters>) => {
        router.get(
            '/admin/inventory',
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
        router.get(
            '/admin/inventory',
            {},
            { preserveState: false, preserveScroll: true, replace: true }
        );
    };

    const renderSortIcon = (column: string) => {
        if (filters.sort_by !== column) {
            return <ArrowUpDown className="ml-1.5 h-3.5 w-3.5 text-muted-foreground/60 transition-colors group-hover:text-foreground" />;
        }
        return filters.sort_direction === 'desc' ? (
            <ArrowDown className="ml-1.5 h-3.5 w-3.5 text-primary" />
        ) : (
            <ArrowUp className="ml-1.5 h-3.5 w-3.5 text-primary" />
        );
    };

    const getStockStatusBadge = (item: InventoryBalanceItem) => {
        switch (item.stock_status) {
            case 'IN_STOCK':
                return (
                    <Badge variant="outline" className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400 font-medium text-xs px-2.5 py-0.5 inline-flex items-center gap-1.5">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        In Stock
                    </Badge>
                );
            case 'LOW_STOCK':
                return (
                    <Badge variant="outline" className="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-400 font-medium text-xs px-2.5 py-0.5 inline-flex items-center gap-1.5">
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" />
                        Low Stock
                    </Badge>
                );
            case 'OUT_OF_STOCK':
                return (
                    <Badge variant="outline" className="border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-400 font-medium text-xs px-2.5 py-0.5 inline-flex items-center gap-1.5">
                        <span className="h-1.5 w-1.5 rounded-full bg-rose-500" />
                        Out of Stock
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="text-xs">
                        {item.stock_status_label}
                    </Badge>
                );
        }
    };

    const activeStatus = filters.stock_status?.toUpperCase() || 'ALL';

    return (
        <AppLayout>
            <Head title="Physical Inventory Balances — Admin Workspace" />

            <div className="space-y-6 pb-12">
                {/* Header Title Section */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Boxes className="h-5 w-5" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    Physical Inventory Balances
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Authoritative warehouse stock levels: on-hand, reservations, damage, and available inventory.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Summary Metric Cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div
                        onClick={() => handleFilterChange({ stock_status: 'ALL' })}
                        className={`cursor-pointer rounded-xl border p-4 transition-all duration-200 hover:shadow-md ${
                            activeStatus === 'ALL'
                                ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                : 'border-border bg-card'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Tracked SKUs
                            </span>
                            <Layers className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-foreground">
                                {summaryCounts.all_items.toLocaleString()}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">Total catalog items tracked</p>
                    </div>

                    <div
                        onClick={() => handleFilterChange({ stock_status: 'IN_STOCK' })}
                        className={`cursor-pointer rounded-xl border p-4 transition-all duration-200 hover:shadow-md ${
                            activeStatus === 'IN_STOCK'
                                ? 'border-emerald-500 bg-emerald-500/5 ring-1 ring-emerald-500/20'
                                : 'border-border bg-card'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                In Stock
                            </span>
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                {summaryCounts.in_stock_items.toLocaleString()}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">Healthy availability levels</p>
                    </div>

                    <div
                        onClick={() => handleFilterChange({ stock_status: 'LOW_STOCK' })}
                        className={`cursor-pointer rounded-xl border p-4 transition-all duration-200 hover:shadow-md ${
                            activeStatus === 'LOW_STOCK'
                                ? 'border-amber-500 bg-amber-500/5 ring-1 ring-amber-500/20'
                                : 'border-border bg-card'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                Low Stock
                            </span>
                            <AlertTriangle className="h-4 w-4 text-amber-500" />
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-amber-700 dark:text-amber-300">
                                {summaryCounts.low_stock_items.toLocaleString()}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">At or below reorder threshold</p>
                    </div>

                    <div
                        onClick={() => handleFilterChange({ stock_status: 'OUT_OF_STOCK' })}
                        className={`cursor-pointer rounded-xl border p-4 transition-all duration-200 hover:shadow-md ${
                            activeStatus === 'OUT_OF_STOCK'
                                ? 'border-rose-500 bg-rose-500/5 ring-1 ring-rose-500/20'
                                : 'border-border bg-card'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                                Out of Stock
                            </span>
                            <AlertOctagon className="h-4 w-4 text-rose-500" />
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-rose-700 dark:text-rose-300">
                                {summaryCounts.out_of_stock_items.toLocaleString()}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">Zero available units</p>
                    </div>
                </div>

                {/* Filter and Search Toolbar */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-sm space-y-3">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        {/* Search Input */}
                        <form onSubmit={handleSearchSubmit} className="flex-1 max-w-lg">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Search by SKU, product name, or bin location..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-9 pr-20 h-9 text-sm"
                                />
                                {searchTerm && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearchTerm('');
                                            handleFilterChange({ search: '' });
                                        }}
                                        className="absolute right-12 top-1/2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        Clear
                                    </button>
                                )}
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                    className="absolute right-1 top-1/2 -translate-y-1/2 h-7 px-2.5 text-xs"
                                >
                                    Search
                                </Button>
                            </div>
                        </form>

                        {/* Filters: Warehouse & Stock Status */}
                        <div className="flex flex-wrap items-center gap-2.5">
                            {/* Warehouse Selector */}
                            <div className="flex items-center gap-1.5 text-sm">
                                <WarehouseIcon className="h-4 w-4 text-muted-foreground" />
                                <select
                                    value={filters.warehouse_id || ''}
                                    onChange={(e) =>
                                        handleFilterChange({
                                            warehouse_id: e.target.value ? Number(e.target.value) : undefined,
                                        })
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="">All Warehouses</option>
                                    {warehouses.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name} ({w.code}){w.is_default ? ' — Default' : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Stock Status Selector */}
                            <select
                                value={activeStatus}
                                onChange={(e) => handleFilterChange({ stock_status: e.target.value })}
                                className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="ALL">All Stock Statuses</option>
                                <option value="IN_STOCK">In Stock Only</option>
                                <option value="LOW_STOCK">Low Stock Warning</option>
                                <option value="OUT_OF_STOCK">Out of Stock</option>
                            </select>

                            {/* Reset Button */}
                            {(filters.search || filters.warehouse_id || (filters.stock_status && filters.stock_status !== 'ALL')) && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleReset}
                                    className="h-9 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Desktop/Tablet High-Density Table */}
                <div className="hidden md:block rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">
                                        <button
                                            onClick={() => handleSortChange('id')}
                                            className="group flex items-center hover:text-foreground"
                                        >
                                            Product / SKU
                                            {renderSortIcon('id')}
                                        </button>
                                    </th>
                                    <th className="px-4 py-3">Warehouse</th>
                                    <th className="px-4 py-3">
                                        <button
                                            onClick={() => handleSortChange('bin_location')}
                                            className="group flex items-center hover:text-foreground"
                                        >
                                            Bin
                                            {renderSortIcon('bin_location')}
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => handleSortChange('on_hand_quantity')}
                                            className="group inline-flex items-center hover:text-foreground"
                                        >
                                            On-Hand
                                            {renderSortIcon('on_hand_quantity')}
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => handleSortChange('reserved_quantity')}
                                            className="group inline-flex items-center hover:text-foreground"
                                        >
                                            Reserved
                                            {renderSortIcon('reserved_quantity')}
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => handleSortChange('damaged_quantity')}
                                            className="group inline-flex items-center hover:text-foreground"
                                        >
                                            Damaged
                                            {renderSortIcon('damaged_quantity')}
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-right font-bold text-foreground">
                                        <button
                                            onClick={() => handleSortChange('available_quantity')}
                                            className="group inline-flex items-center hover:text-foreground"
                                        >
                                            Available
                                            {renderSortIcon('available_quantity')}
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => handleSortChange('reorder_point')}
                                            className="group inline-flex items-center hover:text-foreground"
                                        >
                                            Reorder / Safety
                                            {renderSortIcon('reorder_point')}
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {balances.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-12 text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <Boxes className="h-8 w-8 text-muted-foreground/50" />
                                                <p className="text-base font-medium text-foreground">No inventory records found</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Try adjusting your search criteria or warehouse filter.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    balances.data.map((item) => (
                                        <tr key={item.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">{item.product_name}</div>
                                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <span className="font-mono">{item.sku}</span>
                                                    {item.category_name && (
                                                        <>
                                                            <span>•</span>
                                                            <span>{item.category_name}</span>
                                                        </>
                                                    )}
                                                    <span>•</span>
                                                    <span className="uppercase text-[11px] font-semibold tracking-wider">
                                                        {item.unit}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                <div className="font-medium text-foreground">{item.warehouse_name}</div>
                                                <div className="font-mono text-muted-foreground">{item.warehouse_code}</div>
                                            </td>
                                            <td className="px-4 py-3 text-xs font-mono">
                                                {item.bin_location ? (
                                                    <span className="inline-flex items-center gap-1 rounded bg-muted px-2 py-0.5 font-medium text-foreground">
                                                        <MapPin className="h-3 w-3 text-muted-foreground" />
                                                        {item.bin_location}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground/60">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-medium text-foreground">
                                                {item.on_hand_quantity.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-muted-foreground">
                                                {item.reserved_quantity > 0 ? (
                                                    <span className="text-amber-600 dark:text-amber-400 font-medium">
                                                        {item.reserved_quantity.toLocaleString()}
                                                    </span>
                                                ) : (
                                                    '0'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-muted-foreground">
                                                {item.damaged_quantity > 0 ? (
                                                    <span className="text-rose-600 dark:text-rose-400 font-medium">
                                                        {item.damaged_quantity.toLocaleString()}
                                                    </span>
                                                ) : (
                                                    '0'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-base font-bold text-foreground">
                                                <span
                                                    className={
                                                        item.available_quantity <= 0
                                                            ? 'text-rose-600 dark:text-rose-400'
                                                            : item.stock_status === 'LOW_STOCK'
                                                            ? 'text-amber-600 dark:text-amber-400'
                                                            : 'text-foreground'
                                                    }
                                                >
                                                    {item.available_quantity.toLocaleString()}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right text-xs">
                                                <div className="font-mono text-muted-foreground">
                                                    Min: <span className="font-medium text-foreground">{item.reorder_point}</span>
                                                </div>
                                                <div className="font-mono text-muted-foreground/80">
                                                    Safe: {item.safety_stock}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {getStockStatusBadge(item)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Mobile Purpose-Built Cards */}
                <div className="space-y-3 md:hidden">
                    {balances.data.length === 0 ? (
                        <div className="rounded-xl border border-border bg-card p-8 text-center text-muted-foreground">
                            <Boxes className="mx-auto h-8 w-8 text-muted-foreground/50" />
                            <p className="mt-2 text-base font-medium text-foreground">No inventory records found</p>
                            <p className="text-xs text-muted-foreground">Try adjusting your filters.</p>
                        </div>
                    ) : (
                        balances.data.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-xl border border-border bg-card p-4 shadow-sm space-y-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="space-y-0.5">
                                        <div className="font-semibold text-foreground">{item.product_name}</div>
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <span className="font-mono font-medium">{item.sku}</span>
                                            <span>•</span>
                                            <span className="uppercase">{item.unit}</span>
                                        </div>
                                    </div>
                                    <div>{getStockStatusBadge(item)}</div>
                                </div>

                                <div className="grid grid-cols-2 gap-2 rounded-lg bg-muted/40 p-2.5 text-xs">
                                    <div>
                                        <span className="text-muted-foreground">Warehouse:</span>{' '}
                                        <span className="font-medium text-foreground">{item.warehouse_code}</span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Bin:</span>{' '}
                                        <span className="font-mono font-medium text-foreground">
                                            {item.bin_location || '—'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Reorder Point:</span>{' '}
                                        <span className="font-mono font-medium text-foreground">{item.reorder_point}</span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Safety Stock:</span>{' '}
                                        <span className="font-mono font-medium text-foreground">{item.safety_stock}</span>
                                    </div>
                                </div>

                                <div className="grid grid-cols-4 gap-2 pt-1 text-center">
                                    <div className="rounded-md border border-border p-2">
                                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">On-Hand</div>
                                        <div className="mt-0.5 font-mono text-sm font-semibold text-foreground">
                                            {item.on_hand_quantity}
                                        </div>
                                    </div>
                                    <div className="rounded-md border border-border p-2">
                                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">Reserved</div>
                                        <div className="mt-0.5 font-mono text-sm font-semibold text-amber-600 dark:text-amber-400">
                                            {item.reserved_quantity}
                                        </div>
                                    </div>
                                    <div className="rounded-md border border-border p-2">
                                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">Damaged</div>
                                        <div className="mt-0.5 font-mono text-sm font-semibold text-rose-600 dark:text-rose-400">
                                            {item.damaged_quantity}
                                        </div>
                                    </div>
                                    <div className="rounded-md border border-primary/30 bg-primary/5 p-2">
                                        <div className="text-[10px] uppercase tracking-wider font-semibold text-primary">Available</div>
                                        <div className="mt-0.5 font-mono text-sm font-bold text-primary">
                                            {item.available_quantity}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {/* Pagination Controls */}
                {balances.total > 0 && (
                    <div className="flex flex-col items-center justify-between gap-4 sm:flex-row text-xs text-muted-foreground">
                        <div>
                            Showing <span className="font-medium text-foreground">{balances.from || 0}</span> to{' '}
                            <span className="font-medium text-foreground">{balances.to || 0}</span> of{' '}
                            <span className="font-medium text-foreground">{balances.total.toLocaleString()}</span> items
                        </div>
                        <div className="flex items-center gap-1.5">
                            {balances.links.map((link, idx) => {
                                if (link.label.includes('Previous')) {
                                    return (
                                        <Button
                                            key={idx}
                                            variant="outline"
                                            size="sm"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className="h-8 px-2.5 text-xs"
                                        >
                                            <ChevronLeft className="mr-1 h-3.5 w-3.5" />
                                            Prev
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
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className="h-8 px-2.5 text-xs"
                                        >
                                            Next
                                            <ChevronRight className="ml-1 h-3.5 w-3.5" />
                                        </Button>
                                    );
                                }
                                return (
                                    <Button
                                        key={idx}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                        className="h-8 w-8 p-0 text-xs"
                                    >
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
