import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    FileText,
    Plus,
    Search,
    RotateCcw,
    X,
    Filter,
    Calendar,
    ArrowRight,
    Eye,
    CheckCircle2
} from 'lucide-react';

interface JournalLineItem {
    id: number;
    account_id: number;
    account?: {
        id: number;
        account_code: string;
        name: string;
    };
    debit: string;
    credit: string;
    description: string | null;
}

interface JournalRow {
    id: number;
    journal_number: string;
    entry_type: string;
    source_type: string | null;
    source_number: string | null;
    source_event: string | null;
    status: 'DRAFT' | 'POSTED' | 'REVERSED';
    posting_date: string;
    accounting_date: string;
    total_debit: string;
    total_credit: string;
    description: string;
    reversal_reason: string | null;
    reversal_journal?: {
        id: number;
        journal_number: string;
    };
    reversed_journal?: {
        id: number;
        journal_number: string;
    };
    lines?: JournalLineItem[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedJournals {
    data: JournalRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

interface AccountOption {
    id: number;
    account_code: string;
    name: string;
    type: string;
}

interface Props {
    journals: PaginatedJournals;
    accounts: AccountOption[];
    filters: {
        search?: string;
        status?: string;
        entry_type?: string;
        start_date?: string;
        end_date?: string;
    };
    statuses: string[];
    entry_types: string[];
}

interface ManualLineInput {
    account_id: string;
    debit: string;
    credit: string;
    description: string;
}

export default function JournalsPage({
    journals,
    accounts,
    filters,
    statuses,
    entry_types,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [entryType, setEntryType] = useState(filters.entry_type || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [reversingJournal, setReversingJournal] = useState<JournalRow | null>(null);

    // Manual Journal Form
    const manualForm = useForm({
        description: '',
        accounting_date: new Date().toISOString().split('T')[0],
        notes: '',
        lines: [
            { account_id: '', debit: '', credit: '', description: '' },
            { account_id: '', debit: '', credit: '', description: '' },
        ] as ManualLineInput[],
    });

    // Reversal Form
    const reversalForm = useForm({
        reason: '',
    });

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/accounting/journals', {
            search: search || undefined,
            status: status || undefined,
            entry_type: entryType || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
        }, { preserveState: true });
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatus('');
        setEntryType('');
        setStartDate('');
        setEndDate('');
        router.get('/admin/accounting/journals');
    };

    const handleAddLine = () => {
        manualForm.setData('lines', [
            ...manualForm.data.lines,
            { account_id: '', debit: '', credit: '', description: '' },
        ]);
    };

    const handleRemoveLine = (index: number) => {
        if (manualForm.data.lines.length <= 2) return;
        const newLines = manualForm.data.lines.filter((_, i) => i !== index);
        manualForm.setData('lines', newLines);
    };

    const handleLineChange = (index: number, field: keyof ManualLineInput, value: string) => {
        const newLines = [...manualForm.data.lines];
        newLines[index] = { ...newLines[index], [field]: value };
        manualForm.setData('lines', newLines);
    };

    const totalDebits = manualForm.data.lines.reduce((sum, line) => {
        const val = parseFloat(line.debit || '0');
        return sum + (isNaN(val) ? 0 : val);
    }, 0);

    const totalCredits = manualForm.data.lines.reduce((sum, line) => {
        const val = parseFloat(line.credit || '0');
        return sum + (isNaN(val) ? 0 : val);
    }, 0);

    const isBalanced = Math.abs(totalDebits - totalCredits) < 0.001 && totalDebits > 0;

    const handleManualSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!isBalanced) return;

        manualForm.post('/admin/accounting/journals', {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                manualForm.reset();
            },
        });
    };

    const handleReversalSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!reversingJournal) return;

        reversalForm.post(`/admin/accounting/journals/${reversingJournal.id}/reverse`, {
            onSuccess: () => {
                setReversingJournal(null);
                reversalForm.reset();
            },
        });
    };

    return (
        <AppLayout title="Journal Entries">
            <Head title="Journal Entries" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Journal Entries</h1>
                            <Badge variant="outline" className="text-xs">
                                {journals.total} Total Entries
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Double-entry general ledger transactions from automated events and authorized manual journals.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            onClick={() => setIsCreateModalOpen(true)}
                            className="text-xs"
                        >
                            <Plus className="w-4 h-4 mr-1.5" />
                            New Manual Journal
                        </Button>
                    </div>
                </div>

                {/* Search & Filter Bar */}
                <form onSubmit={handleFilterSubmit} className="rounded-xl border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 text-xs">
                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Search</label>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Journal #, source, description..."
                                    className="pl-8 text-xs h-8"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Status</label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs h-8"
                            >
                                <option value="">All Statuses</option>
                                {statuses.map((s) => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Entry Type</label>
                            <select
                                value={entryType}
                                onChange={(e) => setEntryType(e.target.value)}
                                className="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs h-8"
                            >
                                <option value="">All Types</option>
                                {entry_types.map((t) => (
                                    <option key={t} value={t}>{t}</option>
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
                        <Button type="button" variant="outline" size="sm" onClick={handleResetFilters} className="text-xs h-7">
                            Reset
                        </Button>
                        <Button type="submit" size="sm" className="text-xs h-7">
                            <Filter className="w-3.5 h-3.5 mr-1" />
                            Apply Filters
                        </Button>
                    </div>
                </form>

                {/* Journals Table */}
                <div className="rounded-xl border bg-card shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-muted/50 text-muted-foreground font-medium border-b">
                                <tr>
                                    <th className="py-3 px-4">Journal #</th>
                                    <th className="py-3 px-4">Date</th>
                                    <th className="py-3 px-4">Type</th>
                                    <th className="py-3 px-4">Source Event</th>
                                    <th className="py-3 px-4">Description</th>
                                    <th className="py-3 px-4 text-right">Debit</th>
                                    <th className="py-3 px-4 text-right">Credit</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {journals.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="py-8 text-center text-muted-foreground">
                                            No journal entries found matching criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    journals.data.map((journal) => (
                                        <tr key={journal.id} className="hover:bg-muted/30 transition-colors">
                                            <td className="py-3 px-4 font-mono font-semibold">
                                                <Link
                                                    href={`/admin/accounting/journals/${journal.id}`}
                                                    className="hover:underline text-primary"
                                                >
                                                    {journal.journal_number}
                                                </Link>
                                            </td>
                                            <td className="py-3 px-4 text-muted-foreground whitespace-nowrap">
                                                {journal.accounting_date}
                                            </td>
                                            <td className="py-3 px-4">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        journal.entry_type === 'SYSTEM'
                                                            ? 'bg-blue-500/10 text-blue-600 border-blue-500/20 text-[10px]'
                                                            : journal.entry_type === 'REVERSAL'
                                                            ? 'bg-red-500/10 text-red-600 border-red-500/20 text-[10px]'
                                                            : 'bg-purple-500/10 text-purple-600 border-purple-500/20 text-[10px]'
                                                    }
                                                >
                                                    {journal.entry_type}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4">
                                                {journal.source_number ? (
                                                    <div>
                                                        <span className="font-mono font-medium">{journal.source_number}</span>
                                                        {journal.source_event && (
                                                            <span className="block text-[10px] text-muted-foreground">{journal.source_event}</span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground italic">—</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 max-w-xs truncate" title={journal.description}>
                                                {journal.description}
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono font-medium text-emerald-600 whitespace-nowrap">
                                                ${journal.total_debit}
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono font-medium text-blue-600 whitespace-nowrap">
                                                ${journal.total_credit}
                                            </td>
                                            <td className="py-3 px-4">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        journal.status === 'POSTED'
                                                            ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-[10px]'
                                                            : journal.status === 'REVERSED'
                                                            ? 'bg-red-500/10 text-red-600 border-red-500/20 text-[10px]'
                                                            : 'text-[10px]'
                                                    }
                                                >
                                                    {journal.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Link href={`/admin/accounting/journals/${journal.id}`}>
                                                        <Button variant="ghost" size="sm" className="h-7 text-xs px-2">
                                                            <Eye className="w-3.5 h-3.5 mr-1" />
                                                            View
                                                        </Button>
                                                    </Link>
                                                    {journal.status === 'POSTED' && journal.entry_type !== 'REVERSAL' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setReversingJournal(journal)}
                                                            className="h-7 text-xs px-2 text-red-600 hover:text-red-700 hover:bg-red-50"
                                                            title="Controlled Reversal"
                                                        >
                                                            <RotateCcw className="w-3.5 h-3.5 mr-1" />
                                                            Reverse
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {journals.links && journals.links.length > 3 && (
                        <div className="p-4 border-t flex items-center justify-between">
                            <span className="text-xs text-muted-foreground">
                                Showing page {journals.current_page} of {journals.last_page} ({journals.total} entries)
                            </span>
                            <div className="flex gap-1">
                                {journals.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        preserveState
                                        className={`px-2.5 py-1 text-xs rounded-md border ${
                                            link.active
                                                ? 'bg-primary text-primary-foreground font-semibold'
                                                : 'text-muted-foreground hover:bg-muted'
                                        } ${!link.url ? 'opacity-50 pointer-events-none' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* New Manual Journal Modal */}
                {isCreateModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-3xl rounded-xl bg-card p-6 shadow-xl border space-y-4 max-h-[90vh] overflow-y-auto">
                            <div className="flex items-center justify-between border-b pb-3">
                                <div>
                                    <h2 className="text-base font-semibold">Post Manual Journal Entry</h2>
                                    <p className="text-xs text-muted-foreground">
                                        Must satisfy double-entry balance: Total Debits = Total Credits.
                                    </p>
                                </div>
                                <button
                                    onClick={() => setIsCreateModalOpen(false)}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <form onSubmit={handleManualSubmit} className="space-y-4 text-xs">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block font-medium mb-1">Description *</label>
                                        <Input
                                            value={manualForm.data.description}
                                            onChange={(e) => manualForm.setData('description', e.target.value)}
                                            placeholder="e.g. Monthly rent allocation, manual depreciation"
                                            required
                                        />
                                        {manualForm.errors.description && (
                                            <p className="text-red-500 mt-1">{manualForm.errors.description}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block font-medium mb-1">Accounting Date *</label>
                                        <Input
                                            type="date"
                                            value={manualForm.data.accounting_date}
                                            onChange={(e) => manualForm.setData('accounting_date', e.target.value)}
                                            required
                                        />
                                    </div>
                                </div>

                                {/* Journal Lines */}
                                <div className="space-y-2 border rounded-lg p-3 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-semibold text-xs">Journal Lines</h3>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleAddLine}
                                            className="h-7 text-xs"
                                        >
                                            <Plus className="w-3.5 h-3.5 mr-1" />
                                            Add Line
                                        </Button>
                                    </div>

                                    <div className="space-y-2">
                                        {manualForm.data.lines.map((line, idx) => (
                                            <div key={idx} className="grid grid-cols-12 gap-2 items-center bg-card p-2 rounded-md border">
                                                <div className="col-span-4">
                                                    <select
                                                        value={line.account_id}
                                                        onChange={(e) => handleLineChange(idx, 'account_id', e.target.value)}
                                                        className="w-full rounded-md border border-input bg-background px-2 py-1 text-xs"
                                                        required
                                                    >
                                                        <option value="">Select Account...</option>
                                                        {accounts.map((acc) => (
                                                            <option key={acc.id} value={acc.id}>
                                                                {acc.account_code} - {acc.name} ({acc.type})
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>

                                                <div className="col-span-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={line.debit}
                                                        onChange={(e) => {
                                                            handleLineChange(idx, 'debit', e.target.value);
                                                            if (parseFloat(e.target.value) > 0) {
                                                                handleLineChange(idx, 'credit', '');
                                                            }
                                                        }}
                                                        placeholder="Debit ($)"
                                                        className="text-xs h-7"
                                                    />
                                                </div>

                                                <div className="col-span-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={line.credit}
                                                        onChange={(e) => {
                                                            handleLineChange(idx, 'credit', e.target.value);
                                                            if (parseFloat(e.target.value) > 0) {
                                                                handleLineChange(idx, 'debit', '');
                                                            }
                                                        }}
                                                        placeholder="Credit ($)"
                                                        className="text-xs h-7"
                                                    />
                                                </div>

                                                <div className="col-span-3">
                                                    <Input
                                                        value={line.description}
                                                        onChange={(e) => handleLineChange(idx, 'description', e.target.value)}
                                                        placeholder="Line note..."
                                                        className="text-xs h-7"
                                                    />
                                                </div>

                                                <div className="col-span-1 text-right">
                                                    {manualForm.data.lines.length > 2 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleRemoveLine(idx)}
                                                            className="h-7 w-7 p-0 text-red-500"
                                                        >
                                                            <X className="w-3.5 h-3.5" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Balancing Summary */}
                                    <div className="flex items-center justify-between pt-2 border-t font-mono">
                                        <div className="flex items-center gap-4 text-xs">
                                            <span>Total Debits: <strong className="text-emerald-600">${totalDebits.toFixed(2)}</strong></span>
                                            <span>Total Credits: <strong className="text-blue-600">${totalCredits.toFixed(2)}</strong></span>
                                        </div>
                                        <div>
                                            {isBalanced ? (
                                                <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-xs">
                                                    <CheckCircle2 className="w-3.5 h-3.5 mr-1" />
                                                    Balanced
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline" className="bg-red-500/10 text-red-600 border-red-500/20 text-xs">
                                                    Imbalance: ${Math.abs(totalDebits - totalCredits).toFixed(2)}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label className="block font-medium mb-1">Additional Notes</label>
                                    <textarea
                                        value={manualForm.data.notes}
                                        onChange={(e) => manualForm.setData('notes', e.target.value)}
                                        rows={2}
                                        className="w-full rounded-md border border-input bg-background p-2 text-xs"
                                        placeholder="Audit documentation or reason for manual entry"
                                    />
                                </div>

                                <div className="flex justify-end gap-2 pt-3 border-t">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setIsCreateModalOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={!isBalanced || manualForm.processing}
                                    >
                                        {manualForm.processing ? 'Posting...' : 'Post Journal Entry'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Reversal Confirmation Modal */}
                {reversingJournal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border space-y-4">
                            <div className="flex items-center justify-between border-b pb-3">
                                <div>
                                    <h2 className="text-base font-semibold text-red-600">
                                        Reverse Journal #{reversingJournal.journal_number}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Generates an equal and opposite compensating journal entry.
                                    </p>
                                </div>
                                <button
                                    onClick={() => setReversingJournal(null)}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <div className="p-3 bg-muted/30 rounded-lg text-xs space-y-1">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Original Total:</span>
                                    <span className="font-mono font-medium">${reversingJournal.total_debit}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Description:</span>
                                    <span className="truncate max-w-xs">{reversingJournal.description}</span>
                                </div>
                            </div>

                            <form onSubmit={handleReversalSubmit} className="space-y-4 text-xs">
                                <div>
                                    <label className="block font-medium mb-1">Reversal Reason *</label>
                                    <textarea
                                        value={reversalForm.data.reason}
                                        onChange={(e) => reversalForm.setData('reason', e.target.value)}
                                        rows={3}
                                        required
                                        className="w-full rounded-md border border-input bg-background p-2 text-xs"
                                        placeholder="Document why this journal entry is being reversed..."
                                    />
                                    {reversalForm.errors.reason && (
                                        <p className="text-red-500 mt-1">{reversalForm.errors.reason}</p>
                                    )}
                                </div>

                                <div className="flex justify-end gap-2 pt-3 border-t">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setReversingJournal(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        size="sm"
                                        disabled={reversalForm.processing || !reversalForm.data.reason.trim()}
                                    >
                                        {reversalForm.processing ? 'Reversing...' : 'Execute Reversal'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
