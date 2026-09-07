import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    FileSpreadsheet,
    Filter,
    ArrowLeft,
    Calendar,
    Eye,
    Receipt,
    DollarSign
} from 'lucide-react';

interface AccountOption {
    id: number;
    account_code: string;
    name: string;
    type: string;
    category: string;
    normal_balance: string;
}

interface LedgerTransaction {
    id: number;
    journal_entry_id: number;
    journal_number: string;
    entry_type: string;
    accounting_date: string;
    posting_date: string;
    source_type: string | null;
    source_number: string | null;
    source_event: string | null;
    description: string;
    debit: string;
    credit: string;
    running_balance: string;
}

interface LedgerData {
    account: {
        id: number;
        account_code: string;
        name: string;
        type: string;
        category: string;
        normal_balance: string;
        is_reconcilable: boolean;
    };
    start_date: string | null;
    end_date: string | null;
    opening_balance: string;
    period_debits: string;
    period_credits: string;
    closing_balance: string;
    transactions: LedgerTransaction[];
}

interface Props {
    accounts: AccountOption[];
    selected_account_id: number;
    ledger: LedgerData | null;
    filters: {
        account_id?: number;
        start_date?: string;
        end_date?: string;
    };
}

export default function GeneralLedgerPage({
    accounts,
    selected_account_id,
    ledger,
    filters,
}: Props) {
    const [accountId, setAccountId] = useState(String(selected_account_id || accounts[0]?.id || ''));
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/accounting/general-ledger', {
            account_id: accountId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
        }, { preserveState: true });
    };

    return (
        <AppLayout title="General Ledger">
            <Head title="General Ledger Account Statement" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">General Ledger Statement</h1>
                            {ledger && (
                                <Badge variant="outline" className="text-xs">
                                    {ledger.account.account_code} - {ledger.account.name}
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Chronological journal line ledger with opening balance, period activity, and authoritative running balance.
                        </p>
                    </div>
                </div>

                {/* Filter Controls */}
                <form onSubmit={handleFilterSubmit} className="rounded-xl border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-4 text-xs">
                        <div className="sm:col-span-2">
                            <label className="block font-medium text-muted-foreground mb-1">Select Account *</label>
                            <select
                                value={accountId}
                                onChange={(e) => setAccountId(e.target.value)}
                                className="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs h-8"
                                required
                            >
                                {accounts.map((acc) => (
                                    <option key={acc.id} value={acc.id}>
                                        {acc.account_code} — {acc.name} ({acc.type} / {acc.normal_balance})
                                    </option>
                                ))}
                            </select>
                        </div>

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
                    </div>

                    <div className="mt-3 flex justify-end gap-2">
                        <Button type="submit" size="sm" className="text-xs h-7">
                            <Filter className="w-3.5 h-3.5 mr-1" />
                            View Account Ledger
                        </Button>
                    </div>
                </form>

                {/* Ledger View */}
                {ledger ? (
                    <div className="space-y-4">
                        {/* Summary Metrics */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-xl border bg-card p-4 shadow-xs">
                                <span className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Opening Balance</span>
                                <p className="mt-2 text-xl font-bold font-mono">${ledger.opening_balance}</p>
                                <span className="text-[11px] text-muted-foreground">Prior to {ledger.start_date || 'period start'}</span>
                            </div>

                            <div className="rounded-xl border bg-card p-4 shadow-xs">
                                <span className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Period Debits</span>
                                <p className="mt-2 text-xl font-bold font-mono text-emerald-600">${ledger.period_debits}</p>
                                <span className="text-[11px] text-muted-foreground">Total debits posted</span>
                            </div>

                            <div className="rounded-xl border bg-card p-4 shadow-xs">
                                <span className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Period Credits</span>
                                <p className="mt-2 text-xl font-bold font-mono text-blue-600">${ledger.period_credits}</p>
                                <span className="text-[11px] text-muted-foreground">Total credits posted</span>
                            </div>

                            <div className="rounded-xl border bg-card p-4 shadow-xs">
                                <span className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Closing Balance</span>
                                <p className="mt-2 text-xl font-bold font-mono text-primary">${ledger.closing_balance}</p>
                                <span className="text-[11px] text-muted-foreground">Normal Balance: {ledger.account.normal_balance}</span>
                            </div>
                        </div>

                        {/* Statement Table */}
                        <div className="rounded-xl border bg-card shadow-xs overflow-hidden">
                            <div className="p-4 border-b flex items-center justify-between">
                                <div>
                                    <h2 className="text-sm font-semibold">
                                        Account Activity: {ledger.account.account_code} — {ledger.account.name}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {ledger.transactions.length} transaction lines
                                    </p>
                                </div>
                            </div>

                            {/* Desktop / Tablet Table View (>=768px) */}
                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-muted/50 text-muted-foreground font-medium border-b">
                                        <tr>
                                            <th className="py-3 px-4">Date</th>
                                            <th className="py-3 px-4">Journal #</th>
                                            <th className="py-3 px-4">Type</th>
                                            <th className="py-3 px-4">Source</th>
                                            <th className="py-3 px-4">Description</th>
                                            <th className="py-3 px-4 text-right font-mono">Debit</th>
                                            <th className="py-3 px-4 text-right font-mono">Credit</th>
                                            <th className="py-3 px-4 text-right font-mono">Running Balance</th>
                                            <th className="py-3 px-4 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {/* Opening Balance Row */}
                                        <tr className="bg-muted/20 italic text-muted-foreground font-medium">
                                            <td className="py-2.5 px-4">{ledger.start_date || '—'}</td>
                                            <td className="py-2.5 px-4 font-mono">—</td>
                                            <td className="py-2.5 px-4">—</td>
                                            <td className="py-2.5 px-4">—</td>
                                            <td className="py-2.5 px-4">Opening Balance</td>
                                            <td className="py-2.5 px-4 text-right font-mono">—</td>
                                            <td className="py-2.5 px-4 text-right font-mono">—</td>
                                            <td className="py-2.5 px-4 text-right font-mono font-bold not-italic text-foreground">
                                                ${ledger.opening_balance}
                                            </td>
                                            <td className="py-2.5 px-4 text-right">—</td>
                                        </tr>

                                        {ledger.transactions.length === 0 ? (
                                            <tr>
                                                <td colSpan={9} className="py-8 text-center text-muted-foreground">
                                                    No journal activity for this account within the selected period.
                                                </td>
                                            </tr>
                                        ) : (
                                            ledger.transactions.map((tx) => (
                                                <tr key={tx.id} className="hover:bg-muted/30 transition-colors">
                                                    <td className="py-3 px-4 text-muted-foreground whitespace-nowrap">
                                                        {tx.accounting_date}
                                                    </td>
                                                    <td className="py-3 px-4 font-mono font-semibold">
                                                        <Link
                                                            href={`/admin/accounting/journals/${tx.journal_entry_id}`}
                                                            className="hover:underline text-primary"
                                                        >
                                                            {tx.journal_number}
                                                        </Link>
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <Badge variant="outline" className="text-[10px]">
                                                            {tx.entry_type}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4 font-mono text-muted-foreground">
                                                        {tx.source_number || '—'}
                                                    </td>
                                                    <td className="py-3 px-4 max-w-xs truncate" title={tx.description}>
                                                        {tx.description}
                                                    </td>
                                                    <td className="py-3 px-4 text-right font-mono font-medium text-emerald-600 whitespace-nowrap">
                                                        {parseFloat(tx.debit) > 0 ? `$${tx.debit}` : '—'}
                                                    </td>
                                                    <td className="py-3 px-4 text-right font-mono font-medium text-blue-600 whitespace-nowrap">
                                                        {parseFloat(tx.credit) > 0 ? `$${tx.credit}` : '—'}
                                                    </td>
                                                    <td className="py-3 px-4 text-right font-mono font-bold text-foreground whitespace-nowrap">
                                                        ${tx.running_balance}
                                                    </td>
                                                    <td className="py-3 px-4 text-right">
                                                        <Link href={`/admin/accounting/journals/${tx.journal_entry_id}`}>
                                                            <Button variant="ghost" size="sm" className="h-7 text-xs px-2">
                                                                <Eye className="w-3.5 h-3.5 mr-1" />
                                                                View
                                                            </Button>
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                    <tfoot className="bg-muted/40 font-medium border-t font-mono">
                                        <tr>
                                            <td colSpan={5} className="py-3 px-4 text-right uppercase text-xs font-sans tracking-wider">
                                                Closing Balance:
                                            </td>
                                            <td className="py-3 px-4 text-right font-bold text-emerald-600">
                                                ${ledger.period_debits}
                                            </td>
                                            <td className="py-3 px-4 text-right font-bold text-blue-600">
                                                ${ledger.period_credits}
                                            </td>
                                            <td className="py-3 px-4 text-right font-bold text-base text-primary">
                                                ${ledger.closing_balance}
                                            </td>
                                            <td className="py-3 px-4"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {/* Mobile Card Stack View (<768px) */}
                            <div className="md:hidden divide-y divide-border">
                                {/* Opening Balance Card */}
                                <div className="p-3.5 bg-muted/20 flex items-center justify-between text-xs">
                                    <div>
                                        <span className="font-semibold text-foreground block">Opening Balance</span>
                                        <span className="text-[11px] text-muted-foreground">{ledger.start_date || 'Period Start'}</span>
                                    </div>
                                    <span className="font-mono font-bold text-sm text-foreground">
                                        ${ledger.opening_balance}
                                    </span>
                                </div>

                                {ledger.transactions.length === 0 ? (
                                    <div className="py-8 px-4 text-center text-xs text-muted-foreground">
                                        No journal activity for this account within the selected period.
                                    </div>
                                ) : (
                                    ledger.transactions.map((tx) => (
                                        <div key={tx.id} className="p-3.5 space-y-2.5">
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-2 min-w-0">
                                                    <Link
                                                        href={`/admin/accounting/journals/${tx.journal_entry_id}`}
                                                        className="font-mono font-bold text-xs text-primary hover:underline truncate"
                                                    >
                                                        {tx.journal_number}
                                                    </Link>
                                                    <Badge variant="outline" className="text-[10px] shrink-0">
                                                        {tx.entry_type}
                                                    </Badge>
                                                </div>
                                                <span className="text-[11px] text-muted-foreground whitespace-nowrap">
                                                    {tx.accounting_date}
                                                </span>
                                            </div>

                                            <div className="text-xs text-foreground leading-relaxed">
                                                {tx.description}
                                            </div>

                                            {tx.source_number && (
                                                <div className="text-[11px] font-mono text-muted-foreground">
                                                    Ref: {tx.source_number}
                                                </div>
                                            )}

                                            <div className="grid grid-cols-3 gap-2 pt-2 border-t border-border/60 text-center">
                                                <div className="rounded-lg bg-muted/30 p-2">
                                                    <span className="text-[10px] text-muted-foreground block uppercase font-medium">Debit</span>
                                                    <span className="font-mono font-semibold text-xs text-emerald-600 block mt-0.5">
                                                        {parseFloat(tx.debit) > 0 ? `$${tx.debit}` : '—'}
                                                    </span>
                                                </div>
                                                <div className="rounded-lg bg-muted/30 p-2">
                                                    <span className="text-[10px] text-muted-foreground block uppercase font-medium">Credit</span>
                                                    <span className="font-mono font-semibold text-xs text-blue-600 block mt-0.5">
                                                        {parseFloat(tx.credit) > 0 ? `$${tx.credit}` : '—'}
                                                    </span>
                                                </div>
                                                <div className="rounded-lg bg-primary/5 border border-primary/15 p-2">
                                                    <span className="text-[10px] text-primary/80 block uppercase font-medium">Balance</span>
                                                    <span className="font-mono font-bold text-xs text-primary block mt-0.5">
                                                        ${tx.running_balance}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="pt-1 flex justify-end">
                                                <Link href={`/admin/accounting/journals/${tx.journal_entry_id}`} className="w-full sm:w-auto">
                                                    <Button variant="outline" size="sm" className="w-full text-xs h-7 gap-1">
                                                        <Eye className="w-3.5 h-3.5" />
                                                        <span>View Journal</span>
                                                    </Button>
                                                </Link>
                                            </div>
                                        </div>
                                    ))
                                )}

                                {/* Closing Balance Mobile Card */}
                                <div className="p-3.5 bg-muted/30 border-t space-y-2">
                                    <div className="flex justify-between text-xs">
                                        <span className="text-muted-foreground">Total Period Debits:</span>
                                        <span className="font-mono font-semibold text-emerald-600">${ledger.period_debits}</span>
                                    </div>
                                    <div className="flex justify-between text-xs">
                                        <span className="text-muted-foreground">Total Period Credits:</span>
                                        <span className="font-mono font-semibold text-blue-600">${ledger.period_credits}</span>
                                    </div>
                                    <div className="flex justify-between text-xs font-bold pt-1.5 border-t border-border/80">
                                        <span className="text-foreground uppercase tracking-wider">Closing Balance:</span>
                                        <span className="font-mono text-sm text-primary">${ledger.closing_balance}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="p-12 text-center border rounded-xl bg-card text-muted-foreground">
                        Select an account from the menu above to generate its general ledger statement.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
