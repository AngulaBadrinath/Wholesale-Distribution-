import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import {
    Boxes,
    Calendar,
    Filter,
    Search,
    DollarSign,
    Package,
    AlertTriangle,
    CheckCircle2,
    ShieldAlert,
    RotateCcw,
    Layers,
    Warehouse as WarehouseIcon
} from 'lucide-react';

interface ValuationRow {
    inventory_balance_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    category_name: string;
    warehouse_id: number;
    warehouse_name: string;
    warehouse_code: string;
    on_hand: number;
    reserved: number;
    available: number;
    damaged: number;
    unit_cost_price: string | null;
    on_hand_valuation: string | null;
    available_valuation: string | null;
    status: string;
}

interface ValuationReport {
    data: ValuationRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    can_view_cost_price: boolean;
    summary: {
        total_on_hand_qty: number;
        total_reserved_qty: number;
        total_available_qty: number;
        total_damaged_qty: number;
        total_valuation: string | null;
    };
    filters: {
        warehouse_id?: string;
        category_id?: string;
        stock_status?: string;
        search?: string;
    };
}

interface MovementRow {
    id: number;
    movement_number: string;
    created_at: string;
    warehouse_id: number;
    warehouse_name: string;
    product_id: number;
    product_name: string;
    product_sku: string;
    movement_type: string;
    quantity: number;
    from_state: string;
    to_state: string;
    on_hand_before: number;
    on_hand_after: number;
    available_before: number;
    available_after: number;
    reference_type: string;
    reference_number: string;
    notes: string;
}

interface MovementReport {
    data: MovementRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    filters: {
        warehouse_id?: string;
        product_id?: string;
        movement_type?: string;
        date_from?: string;
        date_to?: string;
        search?: string;
    };
}

interface LowStockRow {
    inventory_balance_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    warehouse_name: string;
    on_hand: number;
    reserved: number;
    available: number;
    unit_cost_price: string | null;
    status: string;
}

interface Props {
    activeTab: 'valuation' | 'movement' | 'low_stock';
    valuationReport: ValuationReport | null;
    movementReport: MovementReport | null;
    lowStockAlerts: LowStockRow[] | null;
    warehouses: Array<{ id: number; name: string; code: string }>;
    filters: Record<string, any>;
}

export default function InventoryReport({
    activeTab,
    valuationReport,
    movementReport,
    lowStockAlerts,
    warehouses,
    filters
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
    const [stockStatus, setStockStatus] = useState(filters.stock_status || '');

    const handleTabChange = (tab: string) => {
        router.get('/admin/reports/inventory', {
            tab,
            warehouse_id: warehouseId || undefined,
            search: search || undefined,
        }, { preserveState: true });
    };

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/reports/inventory', {
            tab: activeTab,
            search: search || undefined,
            warehouse_id: warehouseId || undefined,
            stock_status: stockStatus || undefined,
        }, { preserveState: true });
    };

    const handleReset = () => {
        setSearch('');
        setWarehouseId('');
        setStockStatus('');
        router.get('/admin/reports/inventory', { tab: activeTab });
    };

    return (
        <AppLayout title="Inventory Reports & Valuation">
            <Head title="Inventory Reports" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Inventory Valuation & Movement Reports</h1>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Physical inventory balance valuations, immutable movement audit trails, and reorder alerts.
                        </p>
                    </div>
                </div>

                {/* Filter Bar */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                    <form onSubmit={handleFilter} className="flex flex-wrap items-center gap-3">
                        <div className="flex-1 min-w-[200px]">
                            <Input
                                placeholder="Search by SKU or product name..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-8 text-xs"
                            />
                        </div>

                        <select
                            value={warehouseId}
                            onChange={(e) => setWarehouseId(e.target.value)}
                            className="h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground focus:ring-1 focus:ring-ring"
                        >
                            <option value="">All Warehouses</option>
                            {warehouses.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name} ({w.code})
                                </option>
                            ))}
                        </select>

                        {activeTab === 'valuation' && (
                            <select
                                value={stockStatus}
                                onChange={(e) => setStockStatus(e.target.value)}
                                className="h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground focus:ring-1 focus:ring-ring"
                            >
                                <option value="">All Stock States</option>
                                <option value="IN_STOCK">In Stock (&gt; 10 units)</option>
                                <option value="LOW_STOCK">Low Stock (1-10 units)</option>
                                <option value="OUT_OF_STOCK">Out of Stock (0 units)</option>
                                <option value="DAMAGED_ONLY">Damaged Stock Only</option>
                            </select>
                        )}

                        <div className="flex items-center gap-2">
                            <Button type="submit" size="sm" className="h-8 text-xs">
                                <Filter className="h-3.5 w-3.5 mr-1" />
                                Filter
                            </Button>
                            {(search || warehouseId || stockStatus) && (
                                <Button type="button" variant="outline" size="sm" onClick={handleReset} className="h-8 text-xs">
                                    Reset
                                </Button>
                            )}
                        </div>
                    </form>
                </div>

                {/* KPI Strip (Valuation Tab) */}
                {activeTab === 'valuation' && valuationReport && (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Total On-Hand</span>
                            <div className="mt-2 text-xl font-bold tracking-tight text-foreground">
                                {valuationReport.summary.total_on_hand_qty.toLocaleString()}
                            </div>
                        </div>
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Reserved</span>
                            <div className="mt-2 text-xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                                {valuationReport.summary.total_reserved_qty.toLocaleString()}
                            </div>
                        </div>
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Available</span>
                            <div className="mt-2 text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                                {valuationReport.summary.total_available_qty.toLocaleString()}
                            </div>
                        </div>
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Damaged / Quarantine</span>
                            <div className="mt-2 text-xl font-bold tracking-tight text-rose-500">
                                {valuationReport.summary.total_damaged_qty.toLocaleString()}
                            </div>
                        </div>
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Total Valuation (Cost)</span>
                            <div className="mt-2 text-xl font-bold tracking-tight text-foreground">
                                {valuationReport.can_view_cost_price && valuationReport.summary.total_valuation
                                    ? `$${valuationReport.summary.total_valuation}`
                                    : 'Protected (Admin Only)'}
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab Controls */}
                <div className="flex items-center gap-2 border-b border-border pb-2 text-xs">
                    <button
                        onClick={() => handleTabChange('valuation')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'valuation'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Stock Valuation
                    </button>
                    <button
                        onClick={() => handleTabChange('movement')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'movement'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Movement Ledger
                    </button>
                    <button
                        onClick={() => handleTabChange('low_stock')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'low_stock'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Low Stock Alerts
                    </button>
                </div>

                {/* Tab 1: Valuation Table */}
                {activeTab === 'valuation' && valuationReport && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Stock Valuation Breakdown</h3>
                            <span className="text-xs text-muted-foreground">Page {valuationReport.current_page} of {valuationReport.last_page}</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">SKU</th>
                                        <th className="p-3">Product Name</th>
                                        <th className="p-3">Category</th>
                                        <th className="p-3">Warehouse</th>
                                        <th className="p-3 text-right">On-Hand</th>
                                        <th className="p-3 text-right">Reserved</th>
                                        <th className="p-3 text-right">Available</th>
                                        <th className="p-3 text-right">Damaged</th>
                                        {valuationReport.can_view_cost_price && (
                                            <>
                                                <th className="p-3 text-right">Unit Cost</th>
                                                <th className="p-3 text-right">On-Hand Value</th>
                                                <th className="p-3 text-right">Available Value</th>
                                            </>
                                        )}
                                        <th className="p-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {valuationReport.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={12} className="p-8 text-center text-muted-foreground">
                                                No inventory balances found.
                                            </td>
                                        </tr>
                                    ) : (
                                        valuationReport.data.map((row) => (
                                            <tr key={row.inventory_balance_id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-mono text-muted-foreground">{row.product_sku}</td>
                                                <td className="p-3 font-medium text-foreground">{row.product_name}</td>
                                                <td className="p-3 text-muted-foreground">{row.category_name}</td>
                                                <td className="p-3">{row.warehouse_name}</td>
                                                <td className="p-3 text-right font-medium">{row.on_hand}</td>
                                                <td className="p-3 text-right text-blue-600 dark:text-blue-400">{row.reserved}</td>
                                                <td className="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{row.available}</td>
                                                <td className="p-3 text-right text-rose-500">{row.damaged}</td>
                                                {valuationReport.can_view_cost_price && (
                                                    <>
                                                        <td className="p-3 text-right font-mono">${row.unit_cost_price}</td>
                                                        <td className="p-3 text-right font-mono font-medium">${row.on_hand_valuation}</td>
                                                        <td className="p-3 text-right font-mono font-bold text-foreground">${row.available_valuation}</td>
                                                    </>
                                                )}
                                                <td className="p-3 text-center">
                                                    <Badge
                                                        variant="outline"
                                                        className={`text-[10px] ${
                                                            row.available <= 0
                                                                ? 'bg-rose-500/10 text-rose-600 border-rose-500/20'
                                                                : row.available <= 10
                                                                ? 'bg-amber-500/10 text-amber-600 border-amber-500/20'
                                                                : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                                        }`}
                                                    >
                                                        {row.status}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Tab 2: Movement Ledger */}
                {activeTab === 'movement' && movementReport && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Immutable Inventory Movement Ledger</h3>
                            <span className="text-xs text-muted-foreground">Total movements: {movementReport.total}</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">Movement #</th>
                                        <th className="p-3">Date</th>
                                        <th className="p-3">Type</th>
                                        <th className="p-3">Product</th>
                                        <th className="p-3">Warehouse</th>
                                        <th className="p-3 text-right">Quantity</th>
                                        <th className="p-3">Transition</th>
                                        <th className="p-3">Reference</th>
                                        <th className="p-3">Notes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {movementReport.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="p-8 text-center text-muted-foreground">
                                                No movement records found.
                                            </td>
                                        </tr>
                                    ) : (
                                        movementReport.data.map((m) => (
                                            <tr key={m.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-mono font-medium text-foreground">{m.movement_number}</td>
                                                <td className="p-3 font-mono text-muted-foreground">
                                                    {new Date(m.created_at).toLocaleString()}
                                                </td>
                                                <td className="p-3">
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {m.movement_type}
                                                    </Badge>
                                                </td>
                                                <td className="p-3">
                                                    <div className="font-medium text-foreground">{m.product_name}</div>
                                                    <div className="text-[11px] font-mono text-muted-foreground">{m.product_sku}</div>
                                                </td>
                                                <td className="p-3 text-muted-foreground">{m.warehouse_name}</td>
                                                <td className="p-3 text-right font-mono font-bold">{m.quantity}</td>
                                                <td className="p-3 font-mono text-[11px]">
                                                    <span className="text-muted-foreground">{m.from_state || 'NONE'}</span>
                                                    <span className="text-primary font-bold"> &rarr; </span>
                                                    <span className="text-foreground">{m.to_state || 'NONE'}</span>
                                                </td>
                                                <td className="p-3 font-mono text-xs">
                                                    {m.reference_number || '—'}
                                                </td>
                                                <td className="p-3 text-muted-foreground max-w-xs truncate">{m.notes || '—'}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Tab 3: Low Stock Alerts */}
                {activeTab === 'low_stock' && lowStockAlerts && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Low Stock & Reorder Queue</h3>
                            <span className="text-xs text-amber-600 dark:text-amber-400 font-medium">
                                {lowStockAlerts.length} items requiring replenishment
                            </span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">SKU</th>
                                        <th className="p-3">Product Name</th>
                                        <th className="p-3">Warehouse</th>
                                        <th className="p-3 text-right">On-Hand</th>
                                        <th className="p-3 text-right">Reserved</th>
                                        <th className="p-3 text-right">Available</th>
                                        <th className="p-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {lowStockAlerts.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="p-8 text-center text-emerald-600 dark:text-emerald-400">
                                                All products have healthy inventory levels (&gt; 10 available units).
                                            </td>
                                        </tr>
                                    ) : (
                                        lowStockAlerts.map((item) => (
                                            <tr key={item.inventory_balance_id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-mono text-muted-foreground">{item.product_sku}</td>
                                                <td className="p-3 font-medium text-foreground">{item.product_name}</td>
                                                <td className="p-3 text-muted-foreground">{item.warehouse_name}</td>
                                                <td className="p-3 text-right">{item.on_hand}</td>
                                                <td className="p-3 text-right text-blue-600">{item.reserved}</td>
                                                <td className="p-3 text-right font-bold text-rose-600 dark:text-rose-400">
                                                    {item.available}
                                                </td>
                                                <td className="p-3 text-center">
                                                    <Badge
                                                        variant="outline"
                                                        className={`text-[10px] ${
                                                            item.available <= 0
                                                                ? 'bg-rose-500/10 text-rose-600 border-rose-500/20'
                                                                : 'bg-amber-500/10 text-amber-600 border-amber-500/20'
                                                        }`}
                                                    >
                                                        {item.status}
                                                    </Badge>
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
