import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    TrendingUp,
    Filter,
    Calendar,
    ArrowUpRight,
    ArrowDownRight,
    DollarSign,
    Layers
} from 'lucide-react';

interface RowItem {
    account_id: number;
    account_code: string;
    name: string;
    category?: string;
    amount: string;
}

interface ProfitLossReport {
    start_date: string;
    end_date: string;
    operating_revenue: {
        rows: RowItem[];
        total: string;
    };
    contra_revenue: {
        rows: RowItem[];
        total: string;
    };
    net_revenue: string;
    cost_of_goods_sold: {
        rows: RowItem[];
        total: string;
    };
    gross_profit: string;
    operating_expenses: {
        rows: RowItem[];
        total: string;
    };
    total_operating_expenses: string;
    net_income: string;
}

interface Props {
    report: ProfitLossReport;
    filters: {
        start_date?: string;
        end_date?: string;
    };
}

export default function ProfitLossPage({ report, filters }: Props) {
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/accounting/profit-loss', {
            start_date: startDate || undefined,
            end_date: endDate || undefined,
        }, { preserveState: true });
    };

    const isNetIncomePositive = parseFloat(report.net_income) >= 0;

    const formatCurrency = (val: string | number, isContra: boolean = false) => {
        const num = parseFloat(String(val || 0));
        if (Math.abs(num) < 0.001) {
            return '$0.00';
        }
        const formatted = Math.abs(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (isContra || num < 0) {
            return `-$${formatted}`;
        }
        return `$${formatted}`;
    };

    return (
        <AppLayout title="Profit & Loss Statement">
            <Head title="Profit & Loss (Income Statement)" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Profit & Loss Statement</h1>
                            <Badge
                                variant="outline"
                                className={
                                    isNetIncomePositive
                                        ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-xs py-0.5'
                                        : 'bg-red-500/10 text-red-600 border-red-500/20 text-xs py-0.5'
                                }
                            >
                                {isNetIncomePositive ? 'Net Profit' : 'Net Loss'}: {formatCurrency(report.net_income)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Income statement for period {report.start_date} to {report.end_date}.
                        </p>
                    </div>
                </div>

                {/* Filter Controls */}
                <form onSubmit={handleFilterSubmit} className="rounded-xl border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 text-xs">
                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">From Date</label>
                            <Input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="text-xs h-8"
                            />
                        </div>

                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">To Date</label>
                            <Input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                className="text-xs h-8"
                            />
                        </div>

                        <div className="flex items-end">
                            <Button type="submit" size="sm" className="text-xs h-8">
                                <Filter className="w-3.5 h-3.5 mr-1" />
                                Generate Statement
                            </Button>
                        </div>
                    </div>
                </form>

                {/* P&L Statement Structure */}
                <div className="rounded-xl border bg-card shadow-xs overflow-hidden max-w-4xl mx-auto">
                    <div className="p-6 border-b text-center space-y-1">
                        <h2 className="text-lg font-bold tracking-tight">Income Statement</h2>
                        <p className="text-xs text-muted-foreground">
                            Accounting Period: {report.start_date} through {report.end_date}
                        </p>
                    </div>

                    <div className="p-6 space-y-6 text-xs">
                        {/* 1. Operating Revenue */}
                        <div className="space-y-2">
                            <h3 className="font-bold text-sm uppercase tracking-wider text-muted-foreground border-b pb-1">
                                1. Operating Revenue
                            </h3>
                            <div className="space-y-1.5 pl-4">
                                {report.operating_revenue.rows.map((row) => (
                                    <div key={row.account_id} className="flex justify-between py-1 border-b border-border/40">
                                        <span className="text-foreground font-medium">
                                            {row.account_code} — {row.name}
                                        </span>
                                        <span className="font-mono">{formatCurrency(row.amount)}</span>
                                    </div>
                                ))}

                                {report.contra_revenue.rows.length > 0 && (
                                    <div className="pt-2">
                                        <span className="text-muted-foreground italic">Less: Sales Discounts & Allowances</span>
                                        {report.contra_revenue.rows.map((row) => (
                                            <div key={row.account_id} className="flex justify-between py-1 pl-4 border-b border-border/40 text-red-600">
                                                <span>{row.account_code} — {row.name}</span>
                                                <span className="font-mono">{formatCurrency(row.amount, true)}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div className="flex justify-between pt-2 font-bold text-sm border-t border-border">
                                    <span>Net Sales Revenue:</span>
                                    <span className="font-mono text-emerald-600">{formatCurrency(report.net_revenue)}</span>
                                </div>
                            </div>
                        </div>

                        {/* 2. Cost of Goods Sold */}
                        <div className="space-y-2">
                            <h3 className="font-bold text-sm uppercase tracking-wider text-muted-foreground border-b pb-1">
                                2. Cost of Goods Sold (COGS)
                            </h3>
                            <div className="space-y-1.5 pl-4">
                                {report.cost_of_goods_sold.rows.map((row) => (
                                    <div key={row.account_id} className="flex justify-between py-1 border-b border-border/40">
                                        <span className="text-foreground font-medium">
                                            {row.account_code} — {row.name}
                                        </span>
                                        <span className="font-mono">{formatCurrency(row.amount)}</span>
                                    </div>
                                ))}

                                <div className="flex justify-between pt-2 font-bold text-sm border-t border-border">
                                    <span>Total Cost of Goods Sold:</span>
                                    <span className="font-mono text-blue-600">{formatCurrency(report.cost_of_goods_sold.total)}</span>
                                </div>
                            </div>
                        </div>

                        {/* 3. Gross Profit */}
                        <div className="p-4 rounded-lg bg-muted/40 border flex justify-between items-center text-sm font-bold">
                            <span className="text-base">Gross Profit (Net Revenue - COGS):</span>
                            <span className="font-mono text-lg text-emerald-600">{formatCurrency(report.gross_profit)}</span>
                        </div>

                        {/* 4. Operating Expenses */}
                        <div className="space-y-2">
                            <h3 className="font-bold text-sm uppercase tracking-wider text-muted-foreground border-b pb-1">
                                3. Operating Expenses & Write-Offs
                            </h3>
                            <div className="space-y-1.5 pl-4">
                                {report.operating_expenses.rows.map((row) => (
                                    <div key={row.account_id} className="flex justify-between py-1 border-b border-border/40">
                                        <span className="text-foreground font-medium">
                                            {row.account_code} — {row.name}
                                        </span>
                                        <span className="font-mono">{formatCurrency(row.amount)}</span>
                                    </div>
                                ))}

                                <div className="flex justify-between pt-2 font-bold text-sm border-t border-border">
                                    <span>Total Operating Expenses:</span>
                                    <span className="font-mono text-red-600">{formatCurrency(report.total_operating_expenses)}</span>
                                </div>
                            </div>
                        </div>

                        {/* 5. Net Income */}
                        <div className={`p-5 rounded-xl border flex justify-between items-center text-base font-bold ${
                            isNetIncomePositive
                                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700'
                                : 'bg-red-500/10 border-red-500/20 text-red-700'
                        }`}>
                            <div className="flex items-center gap-2">
                                <TrendingUp className="w-5 h-5" />
                                <span>Net Operating Income (Loss):</span>
                            </div>
                            <span className="font-mono text-xl">{formatCurrency(report.net_income)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
