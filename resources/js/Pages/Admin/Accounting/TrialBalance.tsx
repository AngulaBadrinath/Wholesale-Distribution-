import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    Scale,
    CheckCircle2,
    AlertTriangle,
    Filter,
    Calendar,
    ArrowLeft,
    Eye
} from 'lucide-react';

interface TrialBalanceAccountRow {
    account_id: number;
    account_code: string;
    name: string;
    type: string;
    category: string;
    normal_balance: string;
    debit: string;
    credit: string;
    net_debit: string;
    net_credit: string;
}

interface TrialBalanceReport {
    as_of_date: string;
    start_date: string | null;
    is_balanced: boolean;
    difference: string;
    total_debits: string;
    total_credits: string;
    total_net_debits: string;
    total_net_credits: string;
    accounts: TrialBalanceAccountRow[];
}

interface Props {
    report: TrialBalanceReport;
    filters: {
        as_of_date?: string;
        start_date?: string;
    };
}

export default function TrialBalancePage({ report, filters }: Props) {
    const [asOfDate, setAsOfDate] = useState(filters.as_of_date || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/accounting/trial-balance', {
            as_of_date: asOfDate || undefined,
            start_date: startDate || undefined,
        }, { preserveState: true });
    };

    return (
        <AppLayout title="Trial Balance">
            <Head title="Trial Balance Report" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Trial Balance Report</h1>
                            {report.is_balanced ? (
                                <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-xs py-0.5">
                                    <CheckCircle2 className="w-3.5 h-3.5 mr-1" />
                                    Balanced: Total Debits = Total Credits
                                </Badge>
                            ) : (
                                <Badge variant="outline" className="bg-red-500/10 text-red-600 border-red-500/20 text-xs py-0.5">
                                    <AlertTriangle className="w-3.5 h-3.5 mr-1" />
                                    Imbalance: ${report.difference}
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Fundamental double-entry accounting integrity assertion as of {report.as_of_date}.
                        </p>
                    </div>
                </div>

                {/* Filter Controls */}
                <form onSubmit={handleFilterSubmit} className="rounded-xl border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 text-xs">
                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">As Of Date</label>
                            <Input
                                type="date"
                                value={asOfDate}
                                onChange={(e) => setAsOfDate(e.target.value)}
                                className="text-xs h-8"
                            />
                        </div>

                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Optional Start Date</label>
                            <Input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="text-xs h-8"
                            />
                        </div>

                        <div className="flex items-end">
                            <Button type="submit" size="sm" className="text-xs h-8">
                                <Filter className="w-3.5 h-3.5 mr-1" />
                                Generate Report
                            </Button>
                        </div>
                    </div>
                </form>

                {/* Trial Balance Table */}
                <div className="rounded-xl border bg-card shadow-xs overflow-hidden">
                    <div className="p-4 border-b flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-semibold">Chart of Accounts Balances</h2>
                            <p className="text-xs text-muted-foreground">Cumulative and net balance per account</p>
                        </div>
                        <div className="text-right text-xs font-mono">
                            <span>Status: </span>
                            <strong className={report.is_balanced ? 'text-emerald-600' : 'text-red-600'}>
                                {report.is_balanced ? 'Balanced (Zero Discrepancy)' : `Imbalanced (Diff: $${report.difference})`}
                            </strong>
                        </div>
                    </div>

                    {/* Desktop / Tablet Table View (>=768px) */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-muted/50 text-muted-foreground font-medium border-b">
                                <tr>
                                    <th className="py-3 px-4">Account Code</th>
                                    <th className="py-3 px-4">Account Name</th>
                                    <th className="py-3 px-4">Type</th>
                                    <th className="py-3 px-4 text-right font-mono">Total Debits</th>
                                    <th className="py-3 px-4 text-right font-mono">Total Credits</th>
                                    <th className="py-3 px-4 text-right font-mono">Net Debit Balance</th>
                                    <th className="py-3 px-4 text-right font-mono">Net Credit Balance</th>
                                    <th className="py-3 px-4 text-right">Ledger</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {report.accounts.map((acc) => (
                                    <tr key={acc.account_id} className="hover:bg-muted/30 transition-colors">
                                        <td className="py-3 px-4 font-mono font-semibold">
                                            {acc.account_code}
                                        </td>
                                        <td className="py-3 px-4 font-medium">
                                            <Link
                                                href={`/admin/accounting/general-ledger?account_id=${acc.account_id}`}
                                                className="hover:underline text-primary"
                                            >
                                                {acc.name}
                                            </Link>
                                        </td>
                                        <td className="py-3 px-4">
                                            <Badge variant="outline" className="text-[10px]">
                                                {acc.type}
                                            </Badge>
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono text-muted-foreground">
                                            ${acc.debit}
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono text-muted-foreground">
                                            ${acc.credit}
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono font-bold text-emerald-600">
                                            {parseFloat(acc.net_debit) > 0 ? `$${acc.net_debit}` : '—'}
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono font-bold text-blue-600">
                                            {parseFloat(acc.net_credit) > 0 ? `$${acc.net_credit}` : '—'}
                                        </td>
                                        <td className="py-3 px-4 text-right">
                                            <Link href={`/admin/accounting/general-ledger?account_id=${acc.account_id}`}>
                                                <Button variant="ghost" size="sm" className="h-6 text-[11px] px-2">
                                                    View
                                                </Button>
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-muted/40 font-medium border-t font-mono">
                                <tr>
                                    <td colSpan={3} className="py-3 px-4 text-right uppercase text-xs font-sans tracking-wider">
                                        Total Trial Balance:
                                    </td>
                                    <td className="py-3 px-4 text-right font-bold text-muted-foreground">
                                        ${report.total_debits}
                                    </td>
                                    <td className="py-3 px-4 text-right font-bold text-muted-foreground">
                                        ${report.total_credits}
                                    </td>
                                    <td className="py-3 px-4 text-right font-bold text-base text-emerald-600">
                                        ${report.total_net_debits}
                                    </td>
                                    <td className="py-3 px-4 text-right font-bold text-base text-blue-600">
                                        ${report.total_net_credits}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {/* Mobile Card Stack View (<768px) */}
                    <div className="md:hidden divide-y divide-border">
                        {report.accounts.length === 0 ? (
                            <div className="py-8 px-4 text-center text-xs text-muted-foreground">
                                No account balances found.
                            </div>
                        ) : (
                            report.accounts.map((acc) => (
                                <div key={acc.account_id} className="p-3.5 space-y-2.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2 min-w-0">
                                            <span className="font-mono font-bold text-xs text-foreground shrink-0">
                                                {acc.account_code}
                                            </span>
                                            <Badge variant="outline" className="text-[10px] shrink-0">
                                                {acc.type}
                                            </Badge>
                                        </div>
                                        <Link href={`/admin/accounting/general-ledger?account_id=${acc.account_id}`}>
                                            <Button variant="outline" size="sm" className="h-6 text-[11px] px-2 gap-1">
                                                <Eye className="w-3 h-3" />
                                                <span>Ledger</span>
                                            </Button>
                                        </Link>
                                    </div>

                                    <div>
                                        <Link
                                            href={`/admin/accounting/general-ledger?account_id=${acc.account_id}`}
                                            className="text-xs font-semibold text-primary hover:underline"
                                        >
                                            {acc.name}
                                        </Link>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 pt-2 border-t border-border/60 text-xs">
                                        <div className="rounded-lg bg-muted/30 p-2">
                                            <span className="text-[10px] text-muted-foreground block uppercase font-medium">Activity</span>
                                            <div className="font-mono text-[11px] mt-0.5 space-y-0.5">
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Dr:</span>
                                                    <span className="text-foreground">${acc.debit}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Cr:</span>
                                                    <span className="text-foreground">${acc.credit}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded-lg bg-primary/5 border border-primary/15 p-2">
                                            <span className="text-[10px] text-primary/80 block uppercase font-medium">Net Position</span>
                                            <div className="font-mono text-[11px] mt-0.5 space-y-0.5">
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Net Dr:</span>
                                                    <span className="font-bold text-emerald-600">
                                                        {parseFloat(acc.net_debit) > 0 ? `$${acc.net_debit}` : '—'}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Net Cr:</span>
                                                    <span className="font-bold text-blue-600">
                                                        {parseFloat(acc.net_credit) > 0 ? `$${acc.net_credit}` : '—'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}

                        {/* Mobile Total Balance Card */}
                        <div className="p-3.5 bg-muted/30 border-t space-y-2">
                            <div className="flex justify-between text-xs">
                                <span className="text-muted-foreground">Total Debits:</span>
                                <span className="font-mono font-semibold text-foreground">${report.total_debits}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span className="text-muted-foreground">Total Credits:</span>
                                <span className="font-mono font-semibold text-foreground">${report.total_credits}</span>
                            </div>
                            <div className="grid grid-cols-2 gap-2 pt-1.5 border-t border-border/80 text-xs">
                                <div>
                                    <span className="text-[10px] text-muted-foreground block uppercase font-bold">Total Net Debits</span>
                                    <span className="font-mono font-bold text-sm text-emerald-600">${report.total_net_debits}</span>
                                </div>
                                <div className="text-right">
                                    <span className="text-[10px] text-muted-foreground block uppercase font-bold">Total Net Credits</span>
                                    <span className="font-mono font-bold text-sm text-blue-600">${report.total_net_credits}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
