import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import {
    TrendingUp,
    Calendar,
    Filter,
    ArrowUpDown,
    Download,
    Eye,
    ChevronLeft,
    ChevronRight,
    Search,
    DollarSign,
    Package,
    Building2,
    Users
} from 'lucide-react';

interface SalesSummary {
    total_orders: number;
    gross_sales: string;
    tax_total: string;
    discount_total: string;
    net_sales: string;
    average_order_value: string;
    total_units_sold: number;
}

interface DailyRow {
    date: string;
    order_count: number;
    gross_sales: string;
    tax_total: string;
    discount_total: string;
    net_sales: string;
    average_order_value: string;
}

interface CustomerSalesRow {
    customer_id: number;
    customer_name: string;
    customer_code: string;
    order_count: number;
    gross_sales: string;
    tax_total: string;
    discount_total: string;
    net_sales: string;
    average_order_value: string;
}

interface ProductSalesRow {
    product_id: number;
    product_name: string;
    product_sku: string;
    order_count: number;
    total_quantity_sold: number;
    gross_sales: string;
    tax_total: string;
    net_sales: string;
    average_unit_realization: string;
}

interface OrderRow {
    id: number;
    order_number: string;
    customer_id: number;
    customer_name?: string;
    customer_code?: string;
    salesman_id: number;
    salesman_name?: string;
    status: string;
    fulfillment_status: string;
    payment_status: string;
    subtotal: string;
    tax_total: string;
    adjustment_total: string;
    grand_total: string;
    created_at: string;
}

interface PaginatedOrders {
    data: OrderRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    summary: SalesSummary;
    dailyBreakdown: DailyRow[];
    salesByCustomer: CustomerSalesRow[];
    salesByProduct: ProductSalesRow[];
    contributingOrders: PaginatedOrders;
    filters: {
        date_from?: string;
        date_to?: string;
        status?: string;
        payment_status?: string;
        customer_id?: string;
        salesman_id?: string;
    };
}

export default function SalesReport({
    summary,
    dailyBreakdown,
    salesByCustomer,
    salesByProduct,
    contributingOrders,
    filters
}: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [activeTab, setActiveTab] = useState<'daily' | 'customer' | 'product' | 'orders'>('daily');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/reports/sales', {
            ...filters,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true });
    };

    const handleReset = () => {
        setDateFrom('');
        setDateTo('');
        router.get('/admin/reports/sales');
    };

    return (
        <AppLayout title="Sales Reports & Realization">
            <Head title="Sales Reports" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Sales & Commercial Reports</h1>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Authoritative sales analysis from approved orders, line allocations, taxes, and customer revenue.
                        </p>
                    </div>
                </div>

                {/* Filter Controls Bar */}
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
                                Apply Filters
                            </Button>
                            {(dateFrom || dateTo) && (
                                <Button type="button" variant="outline" size="sm" onClick={handleReset} className="h-8 text-xs">
                                    Clear
                                </Button>
                            )}
                        </div>
                    </form>
                </div>

                {/* KPI Metrics Strip */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Net Sales</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">${summary.net_sales}</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Gross Subtotal</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">${summary.gross_sales}</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Sales Tax</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">${summary.tax_total}</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Discounts</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">${summary.discount_total}</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Total Orders</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">{summary.total_orders}</div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Avg Order (AOV)</span>
                        <div className="mt-2 text-xl font-bold tracking-tight text-foreground">${summary.average_order_value}</div>
                    </div>
                </div>

                {/* Tab Navigation */}
                <div className="flex items-center gap-2 border-b border-border pb-2 text-xs">
                    <button
                        onClick={() => setActiveTab('daily')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'daily'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Daily Breakdown ({dailyBreakdown.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('customer')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'customer'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Sales by Customer ({salesByCustomer.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('product')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'product'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Sales by Product ({salesByProduct.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('orders')}
                        className={`px-3 py-1.5 font-medium rounded-md transition-colors ${
                            activeTab === 'orders'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        Contributing Orders ({contributingOrders.total})
                    </button>
                </div>

                {/* Tab Content */}
                {activeTab === 'daily' && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Daily Sales Timeline</h3>
                            <span className="text-xs text-muted-foreground">Showing daily aggregations</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">Date</th>
                                        <th className="p-3 text-right">Orders</th>
                                        <th className="p-3 text-right">Gross Subtotal</th>
                                        <th className="p-3 text-right">Tax Total</th>
                                        <th className="p-3 text-right">Discounts</th>
                                        <th className="p-3 text-right">Net Sales</th>
                                        <th className="p-3 text-right">Average Order</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {dailyBreakdown.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="p-8 text-center text-muted-foreground">
                                                No sales transactions recorded in this date window.
                                            </td>
                                        </tr>
                                    ) : (
                                        dailyBreakdown.map((row) => (
                                            <tr key={row.date} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-mono font-medium">{row.date}</td>
                                                <td className="p-3 text-right">{row.order_count}</td>
                                                <td className="p-3 text-right font-mono">${row.gross_sales}</td>
                                                <td className="p-3 text-right font-mono text-muted-foreground">${row.tax_total}</td>
                                                <td className="p-3 text-right font-mono text-amber-600 dark:text-amber-400">-${row.discount_total}</td>
                                                <td className="p-3 text-right font-mono font-bold text-foreground">${row.net_sales}</td>
                                                <td className="p-3 text-right font-mono">${row.average_order_value}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {activeTab === 'customer' && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Sales Ranked by Customer</h3>
                            <span className="text-xs text-muted-foreground">Top customers by revenue</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">Customer</th>
                                        <th className="p-3">Code</th>
                                        <th className="p-3 text-right">Orders</th>
                                        <th className="p-3 text-right">Gross Subtotal</th>
                                        <th className="p-3 text-right">Discounts</th>
                                        <th className="p-3 text-right">Net Sales</th>
                                        <th className="p-3 text-right">Avg Order</th>
                                        <th className="p-3 text-center">Drill-down</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {salesByCustomer.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="p-8 text-center text-muted-foreground">
                                                No customer sales found.
                                            </td>
                                        </tr>
                                    ) : (
                                        salesByCustomer.map((c) => (
                                            <tr key={c.customer_id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-medium text-foreground">{c.customer_name}</td>
                                                <td className="p-3 font-mono text-muted-foreground">{c.customer_code}</td>
                                                <td className="p-3 text-right">{c.order_count}</td>
                                                <td className="p-3 text-right font-mono">${c.gross_sales}</td>
                                                <td className="p-3 text-right font-mono text-amber-600 dark:text-amber-400">-${c.discount_total}</td>
                                                <td className="p-3 text-right font-mono font-bold text-foreground">${c.net_sales}</td>
                                                <td className="p-3 text-right font-mono">${c.average_order_value}</td>
                                                <td className="p-3 text-center">
                                                    <Link href={`/customers/${c.customer_id}`}>
                                                        <Button variant="ghost" size="sm" className="h-7 text-xs">
                                                            Profile
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

                {activeTab === 'product' && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Product Sales Performance</h3>
                            <span className="text-xs text-muted-foreground">Units sold & realization</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">SKU</th>
                                        <th className="p-3">Product Name</th>
                                        <th className="p-3 text-right">Orders</th>
                                        <th className="p-3 text-right">Units Sold</th>
                                        <th className="p-3 text-right">Avg Realization</th>
                                        <th className="p-3 text-right">Gross Sales</th>
                                        <th className="p-3 text-right">Net Sales</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {salesByProduct.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="p-8 text-center text-muted-foreground">
                                                No product sales data found.
                                            </td>
                                        </tr>
                                    ) : (
                                        salesByProduct.map((p) => (
                                            <tr key={p.product_id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-mono text-muted-foreground">{p.product_sku}</td>
                                                <td className="p-3 font-medium text-foreground">{p.product_name}</td>
                                                <td className="p-3 text-right">{p.order_count}</td>
                                                <td className="p-3 text-right font-medium">{p.total_quantity_sold.toLocaleString()}</td>
                                                <td className="p-3 text-right font-mono">${p.average_unit_realization}</td>
                                                <td className="p-3 text-right font-mono">${p.gross_sales}</td>
                                                <td className="p-3 text-right font-mono font-bold text-foreground">${p.net_sales}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {activeTab === 'orders' && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                        <div className="p-4 border-b border-border flex items-center justify-between">
                            <h3 className="font-semibold text-sm">Contributing Order Records</h3>
                            <span className="text-xs text-muted-foreground">Total orders: {contributingOrders.total}</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-muted/50 border-b border-border text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3">Order Number</th>
                                        <th className="p-3">Customer</th>
                                        <th className="p-3">Salesman</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Date</th>
                                        <th className="p-3 text-right">Subtotal</th>
                                        <th className="p-3 text-right">Tax</th>
                                        <th className="p-3 text-right">Grand Total</th>
                                        <th className="p-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {contributingOrders.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="p-8 text-center text-muted-foreground">
                                                No order records found.
                                            </td>
                                        </tr>
                                    ) : (
                                        contributingOrders.data.map((order) => (
                                            <tr key={order.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="p-3 font-mono font-medium text-foreground">{order.order_number}</td>
                                                <td className="p-3">{order.customer_name}</td>
                                                <td className="p-3 text-muted-foreground">{order.salesman_name || 'Direct'}</td>
                                                <td className="p-3">
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {order.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-3 text-muted-foreground font-mono">
                                                    {new Date(order.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="p-3 text-right font-mono">${order.subtotal}</td>
                                                <td className="p-3 text-right font-mono text-muted-foreground">${order.tax_total}</td>
                                                <td className="p-3 text-right font-mono font-bold text-foreground">${order.grand_total}</td>
                                                <td className="p-3 text-center">
                                                    <Link href={`/admin/orders/${order.id}`}>
                                                        <Button variant="ghost" size="sm" className="h-7 text-xs">
                                                            <Eye className="h-3.5 w-3.5 mr-1" />
                                                            View
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
