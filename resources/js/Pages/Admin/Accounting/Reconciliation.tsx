import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    Receipt,
    CheckCircle2,
    AlertTriangle,
    Plus,
    X,
    Filter,
    Calendar,
    ArrowRight,
    Scale,
    DollarSign
} from 'lucide-react';

interface AccountOption {
    id: number;
    account_code: string;
    name: string;
}

interface ReconciliationSummary {
    account: {
        id: number;
        account_code: string;
        name: string;
    };
    as_of_date: string;
    gl_balance: string;
    operational_receipts: string;
    operational_disbursements: string;
    operational_net: string;
    difference: string;
    is_reconciled: boolean;
}

interface ReconciliationSessionRow {
    id: number;
    reconciliation_number: string;
    account?: {
        account_code: string;
        name: string;
    };
    statement_date: string;
    starting_balance: string;
    ending_balance: string;
    ledger_balance: string;
    difference: string;
    status: 'IN_PROGRESS' | 'RECONCILED' | 'DISCREPANCY';
    reconciled_at: string | null;
    creator?: {
        name: string;
    };
}

interface Props {
    accounts: AccountOption[];
    selected_account_id: number;
    summary: ReconciliationSummary | null;
    recent_reconciliations: ReconciliationSessionRow[];
    filters: {
        account_id?: number;
        as_of_date?: string;
    };
}

export default function ReconciliationPage({
    accounts,
    selected_account_id,
    summary,
    recent_reconciliations,
    filters,
}: Props) {
    const [accountId, setAccountId] = useState(String(selected_account_id || accounts[0]?.id || ''));
    const [asOfDate, setAsOfDate] = useState(filters.as_of_date || '');

    const [isSessionModalOpen, setIsSessionModalOpen] = useState(false);
    const [isAdjustmentModalOpen, setIsAdjustmentModalOpen] = useState(false);
    const [selectedRecForAdj, setSelectedRecForAdj] = useState<ReconciliationSessionRow | null>(null);

    const sessionForm = useForm({
        account_id: accountId,
        statement_date: new Date().toISOString().split('T')[0],
        starting_balance: '0.00',
        ending_balance: summary?.gl_balance || '0.00',
        notes: '',
    });

    const adjustmentForm = useForm({
        amount: '',
        reason: '',
    });

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/accounting/reconciliation', {
            account_id: accountId || undefined,
            as_of_date: asOfDate || undefined,
        }, { preserveState: true });
    };

    const handleCreateSession = (e: React.FormEvent) => {
        e.preventDefault();
        sessionForm.post('/admin/accounting/reconciliation', {
            onSuccess: () => {
                setIsSessionModalOpen(false);
            },
        });
    };

    const handleFinalize = (rec: ReconciliationSessionRow) => {
        if (confirm(`Finalize reconciliation session ${rec.reconciliation_number}?`)) {
            router.post(`/admin/accounting/reconciliation/${rec.id}/finalize`);
        }
    };

    const handleOpenAdjustment = (rec: ReconciliationSessionRow) => {
        setSelectedRecForAdj(rec);
        adjustmentForm.setData({
            amount: Math.abs(parseFloat(rec.difference)).toFixed(2),
            reason: '',
        });
        setIsAdjustmentModalOpen(true);
    };

    const handleAdjustmentSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedRecForAdj) return;

        adjustmentForm.post(`/admin/accounting/reconciliation/${selectedRecForAdj.id}/adjustment`, {
            onSuccess: () => {
                setIsAdjustmentModalOpen(false);
                setSelectedRecForAdj(null);
                adjustmentForm.reset();
            },
        });
    };

    return (
        <AppLayout title="Cash & Bank Reconciliation">
            <Head title="Cash & Bank Reconciliation" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Cash & Bank Reconciliation</h1>
                            {summary && (
                                <Badge
                                    variant="outline"
                                    className={
                                        summary.is_reconciled
                                            ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-xs py-0.5'
                                            : 'bg-amber-500/10 text-amber-600 border-amber-500/20 text-xs py-0.5'
                                    }
                                >
                                    {summary.is_reconciled ? (
                                        <>
                                            <CheckCircle2 className="w-3.5 h-3.5 mr-1" />
                                            Reconciled
                                        </>
                                    ) : (
                                        <>
                                            <AlertTriangle className="w-3.5 h-3.5 mr-1" />
                                            Discrepancy: ${summary.difference}
                                        </>
                                    )}
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Reconcile General Ledger cash and bank balances against verified payment transaction ledgers.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            onClick={() => {
                                sessionForm.setData('account_id', accountId);
                                sessionForm.setData('ending_balance', summary?.gl_balance || '0.00');
                                setIsSessionModalOpen(true);
                            }}
                            className="text-xs"
                        >
                            <Plus className="w-4 h-4 mr-1.5" />
                            Start Reconciliation Session
                        </Button>
                    </div>
                </div>

                {/* Filter Controls */}
                <form onSubmit={handleFilterSubmit} className="rounded-xl border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 text-xs">
                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Select Cash / Bank Account *</label>
                            <select
                                value={accountId}
                                onChange={(e) => setAccountId(e.target.value)}
                                className="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs h-8"
                                required
                            >
                                {accounts.map((acc) => (
                                    <option key={acc.id} value={acc.id}>
                                        {acc.account_code} — {acc.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">As Of Date</label>
                            <Input
                                type="date"
                                value={asOfDate}
                                onChange={(e) => setAsOfDate(e.target.value)}
                                className="text-xs h-8"
                            />
                        </div>

                        <div className="flex items-end">
                            <Button type="submit" size="sm" className="text-xs h-8">
                                <Filter className="w-3.5 h-3.5 mr-1" />
                                Check Reconciliation
                            </Button>
                        </div>
                    </div>
                </form>

                {/* Real-time Comparison Cards */}
                {summary && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                        <div className="rounded-xl border bg-card p-4 shadow-xs">
                            <span className="text-muted-foreground font-medium uppercase text-[10px]">GL Ledger Balance</span>
                            <p className="mt-2 text-xl font-bold font-mono text-primary">${summary.gl_balance}</p>
                            <span className="text-[11px] text-muted-foreground">General Ledger posted balance</span>
                        </div>

                        <div className="rounded-xl border bg-card p-4 shadow-xs">
                            <span className="text-muted-foreground font-medium uppercase text-[10px]">Verified Operational Receipts</span>
                            <p className="mt-2 text-xl font-bold font-mono text-emerald-600">${summary.operational_receipts}</p>
                            <span className="text-[11px] text-muted-foreground">Verified customer payments</span>
                        </div>

                        <div className="rounded-xl border bg-card p-4 shadow-xs">
                            <span className="text-muted-foreground font-medium uppercase text-[10px]">Operational Disbursements</span>
                            <p className="mt-2 text-xl font-bold font-mono text-blue-600">${summary.operational_disbursements}</p>
                            <span className="text-[11px] text-muted-foreground">Completed supplier payments</span>
                        </div>

                        <div className="rounded-xl border bg-card p-4 shadow-xs">
                            <span className="text-muted-foreground font-medium uppercase text-[10px]">Discrepancy / Variance</span>
                            <p className={`mt-2 text-xl font-bold font-mono ${summary.is_reconciled ? 'text-emerald-600' : 'text-red-600'}`}>
                                ${summary.difference}
                            </p>
                            <span className="text-[11px] text-muted-foreground">
                                {summary.is_reconciled ? 'Exact Match (0 Variance)' : 'Unreconciled Difference'}
                            </span>
                        </div>
                    </div>
                )}

                {/* Recent Reconciliation Sessions */}
                <div className="rounded-xl border bg-card shadow-xs overflow-hidden">
                    <div className="p-4 border-b">
                        <h2 className="text-sm font-semibold">Reconciliation Sessions & History</h2>
                        <p className="text-xs text-muted-foreground">Formal reconciliation snapshots and discrepancy write-offs</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-muted/50 text-muted-foreground font-medium border-b">
                                <tr>
                                    <th className="py-3 px-4">Session #</th>
                                    <th className="py-3 px-4">Account</th>
                                    <th className="py-3 px-4">Statement Date</th>
                                    <th className="py-3 px-4 text-right font-mono">Statement Ending</th>
                                    <th className="py-3 px-4 text-right font-mono">Ledger Balance</th>
                                    <th className="py-3 px-4 text-right font-mono">Difference</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {recent_reconciliations.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-8 text-center text-muted-foreground">
                                            No reconciliation sessions recorded yet.
                                        </td>
                                    </tr>
                                ) : (
                                    recent_reconciliations.map((rec) => (
                                        <tr key={rec.id} className="hover:bg-muted/30 transition-colors">
                                            <td className="py-3 px-4 font-mono font-semibold">
                                                {rec.reconciliation_number}
                                            </td>
                                            <td className="py-3 px-4">
                                                {rec.account ? `${rec.account.account_code} - ${rec.account.name}` : '—'}
                                            </td>
                                            <td className="py-3 px-4 text-muted-foreground">
                                                {rec.statement_date}
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono">
                                                ${rec.ending_balance}
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono">
                                                ${rec.ledger_balance}
                                            </td>
                                            <td className={`py-3 px-4 text-right font-mono font-bold ${
                                                parseFloat(rec.difference) === 0 ? 'text-emerald-600' : 'text-red-600'
                                            }`}>
                                                ${rec.difference}
                                            </td>
                                            <td className="py-3 px-4">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        rec.status === 'RECONCILED'
                                                            ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 text-[10px]'
                                                            : rec.status === 'DISCREPANCY'
                                                            ? 'bg-red-500/10 text-red-600 border-red-500/20 text-[10px]'
                                                            : 'bg-blue-500/10 text-blue-600 border-blue-500/20 text-[10px]'
                                                    }
                                                >
                                                    {rec.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {rec.status !== 'RECONCILED' && (
                                                        <>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => handleFinalize(rec)}
                                                                className="h-6 text-[11px] px-2"
                                                            >
                                                                Finalize
                                                            </Button>
                                                            {parseFloat(rec.difference) !== 0 && (
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => handleOpenAdjustment(rec)}
                                                                    className="h-6 text-[11px] px-2 text-amber-600 border-amber-500/20 hover:bg-amber-50"
                                                                >
                                                                    Adjust
                                                                </Button>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Start Session Modal */}
                {isSessionModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border space-y-4">
                            <div className="flex items-center justify-between border-b pb-3">
                                <h2 className="text-base font-semibold">Start Reconciliation Session</h2>
                                <button
                                    onClick={() => setIsSessionModalOpen(false)}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <form onSubmit={handleCreateSession} className="space-y-4 text-xs">
                                <div>
                                    <label className="block font-medium mb-1">Account *</label>
                                    <select
                                        value={sessionForm.data.account_id}
                                        onChange={(e) => sessionForm.setData('account_id', e.target.value)}
                                        className="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs"
                                        required
                                    >
                                        {accounts.map((acc) => (
                                            <option key={acc.id} value={acc.id}>
                                                {acc.account_code} - {acc.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-medium mb-1">Statement As Of Date *</label>
                                    <Input
                                        type="date"
                                        value={sessionForm.data.statement_date}
                                        onChange={(e) => sessionForm.setData('statement_date', e.target.value)}
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block font-medium mb-1">Target / Statement Ending Balance ($) *</label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={sessionForm.data.ending_balance}
                                        onChange={(e) => sessionForm.setData('ending_balance', e.target.value)}
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block font-medium mb-1">Session Memo / Notes</label>
                                    <textarea
                                        value={sessionForm.data.notes}
                                        onChange={(e) => sessionForm.setData('notes', e.target.value)}
                                        rows={2}
                                        className="w-full rounded-md border border-input bg-background p-2 text-xs"
                                        placeholder="Bank statement ID, reconciliation cycle notes"
                                    />
                                </div>

                                <div className="flex justify-end gap-2 pt-3 border-t">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setIsSessionModalOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" size="sm" disabled={sessionForm.processing}>
                                        {sessionForm.processing ? 'Creating...' : 'Start Session'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Adjustment Journal Modal */}
                {isAdjustmentModalOpen && selectedRecForAdj && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border space-y-4">
                            <div className="flex items-center justify-between border-b pb-3">
                                <div>
                                    <h2 className="text-base font-semibold">Record Reconciliation Adjustment</h2>
                                    <p className="text-xs text-muted-foreground">
                                        Posts adjustment entry to Bank Fees & Processing (5040)
                                    </p>
                                </div>
                                <button
                                    onClick={() => setIsAdjustmentModalOpen(false)}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-muted"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <form onSubmit={handleAdjustmentSubmit} className="space-y-4 text-xs">
                                <div>
                                    <label className="block font-medium mb-1">Adjustment Amount ($) *</label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={adjustmentForm.data.amount}
                                        onChange={(e) => adjustmentForm.setData('amount', e.target.value)}
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block font-medium mb-1">Adjustment Reason *</label>
                                    <textarea
                                        value={adjustmentForm.data.reason}
                                        onChange={(e) => adjustmentForm.setData('reason', e.target.value)}
                                        rows={3}
                                        required
                                        className="w-full rounded-md border border-input bg-background p-2 text-xs"
                                        placeholder="Document reason for adjustment (e.g. Monthly bank service fee, wire transfer fee)"
                                    />
                                </div>

                                <div className="flex justify-end gap-2 pt-3 border-t">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setIsAdjustmentModalOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" size="sm" disabled={adjustmentForm.processing}>
                                        {adjustmentForm.processing ? 'Posting...' : 'Post Adjustment Journal'}
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
