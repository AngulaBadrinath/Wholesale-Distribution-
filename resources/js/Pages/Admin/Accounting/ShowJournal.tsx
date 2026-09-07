import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    ArrowLeft,
    FileText,
    CheckCircle2,
    RotateCcw,
    Shield,
    Calendar,
    User as UserIcon,
    Link as LinkIcon
} from 'lucide-react';

interface JournalLineItem {
    id: number;
    line_number: number;
    account_id: number;
    account?: {
        id: number;
        account_code: string;
        name: string;
        type: string;
        category: string;
    };
    debit: string;
    credit: string;
    description: string | null;
}

interface JournalDetail {
    id: number;
    journal_number: string;
    entry_type: string;
    source_type: string | null;
    source_id: number | null;
    source_number: string | null;
    source_event: string | null;
    status: 'DRAFT' | 'POSTED' | 'REVERSED';
    posting_date: string;
    accounting_date: string;
    total_debit: string;
    total_credit: string;
    description: string;
    notes: string | null;
    reversal_reason: string | null;
    reversal_journal?: {
        id: number;
        journal_number: string;
    };
    reversed_journal?: {
        id: number;
        journal_number: string;
    };
    creator?: {
        id: number;
        name: string;
        email: string;
    };
    poster?: {
        id: number;
        name: string;
    };
    lines: JournalLineItem[];
}

interface Props {
    journal: JournalDetail;
}

export default function ShowJournalPage({ journal }: Props) {
    return (
        <AppLayout title={`Journal ${journal.journal_number}`}>
            <Head title={`Journal ${journal.journal_number}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Link href="/admin/accounting/journals">
                            <Button variant="outline" size="sm" className="h-8 w-8 p-0">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold font-mono tracking-tight">{journal.journal_number}</h1>
                                <Badge
                                    variant="outline"
                                    className={
                                        journal.status === 'POSTED'
                                            ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-xs'
                                            : journal.status === 'REVERSED'
                                            ? 'bg-red-500/10 text-red-600 border-red-500/20 text-xs'
                                            : 'text-xs'
                                    }
                                >
                                    {journal.status}
                                </Badge>
                                <Badge variant="outline" className="text-xs">
                                    {journal.entry_type}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Posted on {journal.posting_date} | Accounting Date: {journal.accounting_date}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                    <div className="rounded-xl border bg-card p-4 shadow-xs space-y-1">
                        <span className="text-muted-foreground font-medium uppercase text-[10px]">Description</span>
                        <p className="font-medium text-foreground">{journal.description}</p>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs space-y-1">
                        <span className="text-muted-foreground font-medium uppercase text-[10px]">Source Reference</span>
                        <div>
                            {journal.source_number ? (
                                <p className="font-mono font-medium">{journal.source_number}</p>
                            ) : (
                                <p className="text-muted-foreground italic">None (Manual Entry)</p>
                            )}
                            {journal.source_event && (
                                <span className="text-[10px] text-muted-foreground">{journal.source_event}</span>
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs space-y-1">
                        <span className="text-muted-foreground font-medium uppercase text-[10px]">Total Debits</span>
                        <p className="font-mono font-bold text-base text-emerald-600">${journal.total_debit}</p>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs space-y-1">
                        <span className="text-muted-foreground font-medium uppercase text-[10px]">Total Credits</span>
                        <p className="font-mono font-bold text-base text-blue-600">${journal.total_credit}</p>
                    </div>
                </div>

                {/* Reversal Banner if applicable */}
                {journal.status === 'REVERSED' && (
                    <div className="p-4 rounded-xl border border-red-500/20 bg-red-500/10 text-red-700 text-xs flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <RotateCcw className="w-4 h-4 text-red-600 shrink-0" />
                            <div>
                                <span className="font-semibold">This journal entry has been reversed.</span>
                                {journal.reversal_reason && (
                                    <p className="mt-0.5 text-muted-foreground">Reason: {journal.reversal_reason}</p>
                                )}
                            </div>
                        </div>
                        {journal.reversal_journal && (
                            <Link href={`/admin/accounting/journals/${journal.reversal_journal.id}`}>
                                <Button variant="outline" size="sm" className="text-xs h-7 bg-background text-foreground">
                                    View Reversal #{journal.reversal_journal.journal_number}
                                </Button>
                            </Link>
                        )}
                    </div>
                )}

                {journal.reversed_journal && (
                    <div className="p-4 rounded-xl border border-purple-500/20 bg-purple-500/10 text-purple-700 text-xs flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <RotateCcw className="w-4 h-4 text-purple-600 shrink-0" />
                            <div>
                                <span className="font-semibold">This is an authorized reversal compensating journal.</span>
                                <p className="mt-0.5 text-muted-foreground">Offsets original journal entry.</p>
                            </div>
                        </div>
                        <Link href={`/admin/accounting/journals/${journal.reversed_journal.id}`}>
                            <Button variant="outline" size="sm" className="text-xs h-7 bg-background text-foreground">
                                View Original #{journal.reversed_journal.journal_number}
                            </Button>
                        </Link>
                    </div>
                )}

                {/* Journal Lines Table */}
                <div className="rounded-xl border bg-card shadow-xs overflow-hidden">
                    <div className="p-4 border-b">
                        <h2 className="text-sm font-semibold">Double-Entry Journal Lines</h2>
                        <p className="text-xs text-muted-foreground">Authoritative line-level debit and credit allocations</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-muted/50 text-muted-foreground font-medium border-b">
                                <tr>
                                    <th className="py-3 px-4 w-12 text-center">#</th>
                                    <th className="py-3 px-4">Account Code</th>
                                    <th className="py-3 px-4">Account Name</th>
                                    <th className="py-3 px-4">Classification</th>
                                    <th className="py-3 px-4">Line Memo</th>
                                    <th className="py-3 px-4 text-right font-mono">Debit</th>
                                    <th className="py-3 px-4 text-right font-mono">Credit</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {journal.lines.map((line) => (
                                    <tr key={line.id} className="hover:bg-muted/30 transition-colors">
                                        <td className="py-3 px-4 text-center text-muted-foreground font-mono">
                                            {line.line_number}
                                        </td>
                                        <td className="py-3 px-4 font-mono font-semibold">
                                            {line.account?.account_code}
                                        </td>
                                        <td className="py-3 px-4 font-medium">
                                            <Link
                                                href={`/admin/accounting/general-ledger?account_id=${line.account_id}`}
                                                className="hover:underline text-primary"
                                            >
                                                {line.account?.name}
                                            </Link>
                                        </td>
                                        <td className="py-3 px-4">
                                            <Badge variant="outline" className="text-[10px]">
                                                {line.account?.type}
                                            </Badge>
                                        </td>
                                        <td className="py-3 px-4 text-muted-foreground max-w-sm truncate">
                                            {line.description || '—'}
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono font-medium text-emerald-600">
                                            {parseFloat(line.debit) > 0 ? `$${line.debit}` : '—'}
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono font-medium text-blue-600">
                                            {parseFloat(line.credit) > 0 ? `$${line.credit}` : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-muted/30 font-medium border-t font-mono">
                                <tr>
                                    <td colSpan={5} className="py-3 px-4 text-right text-xs uppercase font-sans tracking-wider">
                                        Total Double-Entry Balance:
                                    </td>
                                    <td className="py-3 px-4 text-right font-bold text-emerald-600">
                                        ${journal.total_debit}
                                    </td>
                                    <td className="py-3 px-4 text-right font-bold text-blue-600">
                                        ${journal.total_credit}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {/* Audit & Notes */}
                {journal.notes && (
                    <div className="rounded-xl border bg-card p-4 shadow-xs text-xs space-y-1">
                        <span className="text-muted-foreground font-medium uppercase text-[10px]">Additional Notes</span>
                        <p className="text-foreground whitespace-pre-wrap">{journal.notes}</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
