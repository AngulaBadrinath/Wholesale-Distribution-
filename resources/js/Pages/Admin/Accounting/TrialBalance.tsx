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

                    <div className="overflow-x-auto">
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
                </div>
            </div>
        </AppLayout>
    );
}
