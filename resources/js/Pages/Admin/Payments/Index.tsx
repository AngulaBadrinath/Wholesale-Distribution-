import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { PaymentEvidencePreviewModal } from '@/Components/Payment/PaymentEvidencePreviewModal';
import { PaymentEvidenceUploader } from '@/Components/Payment/PaymentEvidenceUploader';
import {
    Search,
    RotateCcw,
    CheckCircle2,
    Clock,
    XCircle,
    FileImage,
    Plus,
    Filter,
    ArrowUpDown,
    ShieldAlert,
    AlertTriangle,
    CreditCard,
    DollarSign,
    Landmark,
    Send,
    Loader2,
} from 'lucide-react';

interface CustomerOption {
    id: number;
    name: string;
    code: string;
}

interface PaymentItem {
    id: number;
    payment_number: string;
    customer_id: number;
    order_id?: number | null;
    payment_method: 'CASH' | 'CHEQUE' | 'MONEY_ORDER';
    status: 'PENDING_VERIFICATION' | 'VERIFIED' | 'REJECTED' | 'REVERSED';
    amount: string | number;
    payment_date: string;
    cheque_number?: string | null;
    bank_name?: string | null;
    cheque_date?: string | null;
    money_order_number?: string | null;
    issuer_name?: string | null;
    receipt_reference?: string | null;
    evidence_object_key?: string | null;
    evidence_original_name?: string | null;
    evidence_mime_type?: string | null;
    evidence_size_bytes?: number | null;
    notes?: string | null;
    rejection_reason_code?: string | null;
    rejection_notes?: string | null;
    reversal_reason_code?: string | null;
    reversal_notes?: string | null;
    customer?: {
        id: number;
        name: string;
        code: string;
        contact_name?: string;
    };
    order?: {
        id: number;
        order_number: string;
        grand_total: string | number;
        payment_status: string;
    } | null;
    recorded_by?: {
        id: number;
        name: string;
        role: string;
    };
    verified_by?: {
        id: number;
        name: string;
        role: string;
    };
    rejected_by?: {
        id: number;
        name: string;
        role: string;
    };
    reversed_by?: {
        id: number;
        name: string;
        role: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPayments {
    data: PaymentItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface PaymentsIndexProps {
    payments: PaginatedPayments;
    counts: {
        all: number;
        pending_verification: number;
        verified: number;
        rejected: number;
        reversed: number;
    };
    filters: {
        tab?: string;
        status?: string;
        method?: string;
        search?: string;
        customer_id?: number | string;
        per_page?: number;
    };
    customers: CustomerOption[];
    userPermissions: string[];
}

export default function PaymentsIndex({
    payments,
    counts,
    filters,
    customers,
    userPermissions = [],
}: PaymentsIndexProps) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [previewPayment, setPreviewPayment] = useState<PaymentItem | null>(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isRejectModalOpen, setIsRejectModalOpen] = useState(false);
    const [isReverseModalOpen, setIsReverseModalOpen] = useState(false);
    const [selectedPayment, setSelectedPayment] = useState<PaymentItem | null>(null);

    // Form states
    const [paymentMethod, setPaymentMethod] = useState<'CASH' | 'CHEQUE' | 'MONEY_ORDER'>('CASH');
    const [formData, setFormData] = useState({
        customer_id: '',
        order_id: '',
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        receipt_reference: '',
        bank_name: '',
        cheque_number: '',
        cheque_date: new Date().toISOString().split('T')[0],
        issuer_name: '',
        money_order_number: '',
        notes: '',
    });
    const [evidenceFile, setEvidenceFile] = useState<File | null>(null);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Rejection & Reversal state
    const [rejectionReason, setRejectionReason] = useState('ILLEGIBLE_EVIDENCE');
    const [rejectionNotes, setRejectionNotes] = useState('');
    const [reversalReason, setReversalReason] = useState('BOUNCED_CHEQUE');
    const [reversalNotes, setReversalNotes] = useState('');

    const canVerify = userPermissions.includes('payment.verify');
    const canReverse = userPermissions.includes('payment.reverse');
    const canCreate = userPermissions.includes('payment.create');

    const handleTabChange = (tabKey: string) => {
        router.get(
            '/admin/payments',
            {
                ...filters,
                tab: tabKey,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const handleFilterChange = (newFilters: Record<string, any>) => {
        router.get(
            '/admin/payments',
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

    const handleVerify = (payment: PaymentItem) => {
        if (!confirm(`Are you sure you want to verify and reconcile payment ${payment.payment_number} for $${Number(payment.amount).toFixed(2)}?`)) {
            return;
        }

        router.post(
            `/admin/payments/${payment.id}/verify`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Flash notification automatically handled
                },
            }
        );
    };

    const handleCreatePaymentSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setFormErrors({});

        const endpoint =
            paymentMethod === 'CASH'
                ? '/admin/payments/cash'
                : paymentMethod === 'CHEQUE'
                ? '/admin/payments/cheque'
                : '/admin/payments/money-order';

        const submitPayload = new FormData();
        Object.entries(formData).forEach(([key, val]) => {
            if (val) submitPayload.append(key, val);
        });
        if (evidenceFile) {
            submitPayload.append('evidence', evidenceFile);
        }

        router.post(endpoint, submitPayload as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateModalOpen(false);
                setFormData({
                    customer_id: '',
                    order_id: '',
                    amount: '',
                    payment_date: new Date().toISOString().split('T')[0],
                    receipt_reference: '',
                    bank_name: '',
                    cheque_number: '',
                    cheque_date: new Date().toISOString().split('T')[0],
                    issuer_name: '',
                    money_order_number: '',
                    notes: '',
                });
                setEvidenceFile(null);
                setIsSubmitting(false);
            },
            onError: (errors) => {
                setFormErrors(errors);
                setIsSubmitting(false);
            },
        });
    };

    const handleRejectSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedPayment) return;
        setIsSubmitting(true);

        router.post(
            `/admin/payments/${selectedPayment.id}/reject`,
            {
                rejection_reason_code: rejectionReason,
                rejection_notes: rejectionNotes,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsRejectModalOpen(false);
                    setSelectedPayment(null);
                    setRejectionNotes('');
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    setFormErrors(errors);
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleReverseSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedPayment) return;
        setIsSubmitting(true);

        router.post(
            `/admin/payments/${selectedPayment.id}/reverse`,
            {
                reversal_reason_code: reversalReason,
                reversal_notes: reversalNotes,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsReverseModalOpen(false);
                    setSelectedPayment(null);
                    setReversalNotes('');
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    setFormErrors(errors);
                    setIsSubmitting(false);
                },
            }
        );
    };

    const activeTab = filters.tab || 'all';

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'PENDING_VERIFICATION':
                return (
                    <Badge variant="warning" className="gap-1">
                        <Clock className="h-3 w-3" /> Pending Verification
                    </Badge>
                );
            case 'VERIFIED':
                return (
                    <Badge variant="success" className="gap-1">
                        <CheckCircle2 className="h-3 w-3" /> Verified & Confirmed
                    </Badge>
                );
            case 'REJECTED':
                return (
                    <Badge variant="destructive" className="gap-1">
                        <XCircle className="h-3 w-3" /> Rejected
                    </Badge>
                );
            case 'REVERSED':
                return (
                    <Badge variant="secondary" className="gap-1">
                        <RotateCcw className="h-3 w-3" /> Reversed / Bounced
                    </Badge>
                );
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getMethodBadge = (method: string) => {
        switch (method) {
            case 'CASH':
                return (
                    <Badge variant="outline" className="gap-1 border-emerald-300 text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40">
                        <DollarSign className="h-3 w-3" /> Cash
                    </Badge>
                );
            case 'CHEQUE':
                return (
                    <Badge variant="outline" className="gap-1 border-indigo-300 text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40">
                        <Landmark className="h-3 w-3" /> Cheque
                    </Badge>
                );
            case 'MONEY_ORDER':
                return (
                    <Badge variant="outline" className="gap-1 border-amber-300 text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40">
                        <Send className="h-3 w-3" /> Money Order
                    </Badge>
                );
            default:
                return <Badge variant="outline">{method}</Badge>;
        }
    };

    return (
        <AppLayout title="Payments & Collections Workspace">
            <Head title="Payments & Collections" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            Payments & Collections Workspace
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Operational payment registry, multi-method collections verification, and evidence auditing.
                        </p>
                    </div>

                    {canCreate && (
                        <Button
                            type="button"
                            onClick={() => setIsCreateModalOpen(true)}
                            className="gap-2 min-h-[44px]"
                        >
                            <Plus className="h-4 w-4" /> Record New Payment
                        </Button>
                    )}
                </div>

                {/* Workspace Navigation Tabs */}
                <div className="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                    {[
                        { id: 'all', label: 'All Payments', count: counts.all },
                        { id: 'pending_verification', label: 'Pending Verification', count: counts.pending_verification, badge: 'warning' },
                        { id: 'verified', label: 'Verified & Confirmed', count: counts.verified, badge: 'success' },
                        { id: 'rejected', label: 'Rejected', count: counts.rejected, badge: 'destructive' },
                        { id: 'reversed', label: 'Reversed / Bounced', count: counts.reversed, badge: 'secondary' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => handleTabChange(tab.id)}
                            className={`flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors min-h-[44px] ${
                                activeTab === tab.id
                                    ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold border border-indigo-200 dark:border-indigo-800'
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                            }`}
                        >
                            <span>{tab.label}</span>
                            <span
                                className={`text-xs px-2 py-0.5 rounded-full font-mono font-bold ${
                                    activeTab === tab.id
                                        ? 'bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200'
                                        : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                }`}
                            >
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Filters and Search Bar */}
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
                    <form onSubmit={handleSearchSubmit} className="flex-1 flex gap-2">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                            <Input
                                type="text"
                                placeholder="Search by payment #, customer, cheque/MO #, receipt ref..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 min-h-[44px]"
                            />
                        </div>
                        <Button type="submit" variant="secondary" className="min-h-[44px] min-w-[80px]">
                            Search
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            value={filters.method || 'ALL'}
                            onChange={(e) => handleFilterChange({ method: e.target.value })}
                            className="h-10 px-3 py-2 text-sm rounded-md border border-input bg-background focus:ring-2 focus:ring-indigo-500 min-h-[44px]"
                            aria-label="Filter by payment method"
                        >
                            <option value="ALL">All Payment Methods</option>
                            <option value="CASH">Cash</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="MONEY_ORDER">Money Order</option>
                        </select>

                        <select
                            value={filters.customer_id || ''}
                            onChange={(e) => handleFilterChange({ customer_id: e.target.value })}
                            className="h-10 px-3 py-2 text-sm rounded-md border border-input bg-background focus:ring-2 focus:ring-indigo-500 max-w-[200px] truncate min-h-[44px]"
                            aria-label="Filter by customer account"
                        >
                            <option value="">All Customers</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} ({c.code})
                                </option>
                            ))}
                        </select>

                        {(filters.search || filters.method || filters.customer_id) && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => router.get('/admin/payments', { tab: activeTab })}
                                className="text-xs min-h-[44px] text-slate-500"
                            >
                                Reset Filters
                            </Button>
                        )}
                    </div>
                </div>

                {/* Table View (Desktop) */}
                <div className="hidden lg:block bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-xs uppercase font-semibold text-slate-500">
                            <tr>
                                <th className="px-4 py-3.5">Payment Number</th>
                                <th className="px-4 py-3.5">Date</th>
                                <th className="px-4 py-3.5">Customer & Order</th>
                                <th className="px-4 py-3.5">Method & Instrument</th>
                                <th className="px-4 py-3.5">Evidence</th>
                                <th className="px-4 py-3.5 text-right">Amount</th>
                                <th className="px-4 py-3.5">Status</th>
                                <th className="px-4 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {payments.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-12 text-center text-slate-500">
                                        <div className="flex flex-col items-center gap-2">
                                            <CreditCard className="h-8 w-8 text-slate-400" />
                                            <p className="font-medium text-slate-700 dark:text-slate-300">No payment transactions found</p>
                                            <p className="text-xs text-slate-400">Try adjusting your active tab or search filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                payments.data.map((payment) => (
                                    <tr key={payment.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td className="px-4 py-3.5 font-mono font-medium text-slate-900 dark:text-slate-100">
                                            {payment.payment_number}
                                        </td>
                                        <td className="px-4 py-3.5 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                            {payment.payment_date}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <div className="font-medium text-slate-900 dark:text-slate-100 truncate max-w-[200px]">
                                                {payment.customer?.name || 'Customer Account'}
                                            </div>
                                            <div className="text-xs text-slate-400">
                                                {payment.customer?.code}
                                                {payment.order && (
                                                    <span className="ml-1 text-indigo-600 dark:text-indigo-400 font-mono">
                                                        • {payment.order.order_number}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <div className="flex items-center gap-1.5">
                                                {getMethodBadge(payment.payment_method)}
                                            </div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400 mt-1 font-mono">
                                                {payment.payment_method === 'CHEQUE' && (
                                                    <span>{payment.cheque_number} ({payment.bank_name})</span>
                                                )}
                                                {payment.payment_method === 'MONEY_ORDER' && (
                                                    <span>{payment.money_order_number} ({payment.issuer_name})</span>
                                                )}
                                                {payment.payment_method === 'CASH' && payment.receipt_reference && (
                                                    <span>Ref: {payment.receipt_reference}</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3.5">
                                            {payment.evidence_object_key ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setPreviewPayment(payment)}
                                                    className="h-8 gap-1.5 text-xs text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50 min-h-[36px]"
                                                >
                                                    <FileImage className="h-3.5 w-3.5" /> View Scan
                                                </Button>
                                            ) : (
                                                <span className="text-xs text-slate-400 italic">No Scan</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                                            ${Number(payment.amount).toFixed(2)}
                                        </td>
                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                            {getStatusBadge(payment.status)}
                                        </td>
                                        <td className="px-4 py-3.5 text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                {payment.status === 'PENDING_VERIFICATION' && canVerify && (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() => handleVerify(payment)}
                                                            className="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white min-h-[36px]"
                                                        >
                                                            Verify
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() => {
                                                                setSelectedPayment(payment);
                                                                setIsRejectModalOpen(true);
                                                            }}
                                                            className="h-8 text-xs min-h-[36px]"
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                )}

                                                {payment.status === 'VERIFIED' && canReverse && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => {
                                                            setSelectedPayment(payment);
                                                            setIsReverseModalOpen(true);
                                                        }}
                                                        className="h-8 text-xs text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 min-h-[36px]"
                                                    >
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

                {/* Card View (Mobile / Tablet) */}
                <div className="grid grid-cols-1 gap-4 lg:hidden">
                    {payments.data.length === 0 ? (
                        <div className="bg-white dark:bg-slate-900 p-8 rounded-xl border border-slate-200 dark:border-slate-800 text-center text-slate-500">
                            No payments found.
                        </div>
                    ) : (
                        payments.data.map((payment) => (
                            <div
                                key={payment.id}
                                className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="font-mono font-bold text-sm text-slate-900 dark:text-slate-100">
                                            {payment.payment_number}
                                        </div>
                                        <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            {payment.payment_date} • {payment.customer?.name}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-mono font-bold text-base text-slate-900 dark:text-slate-100">
                                            ${Number(payment.amount).toFixed(2)}
                                        </div>
                                        <div className="mt-1">{getStatusBadge(payment.status)}</div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between text-xs pt-2 border-t border-slate-100 dark:border-slate-800">
                                    <div>{getMethodBadge(payment.payment_method)}</div>
                                    {payment.evidence_object_key && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPreviewPayment(payment)}
                                            className="h-8 gap-1 text-xs text-indigo-600 min-h-[44px]"
                                        >
                                            <FileImage className="h-3.5 w-3.5" /> View Scan
                                        </Button>
                                    )}
                                </div>

                                {payment.status === 'PENDING_VERIFICATION' && canVerify && (
                                    <div className="flex gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                                        <Button
                                            type="button"
                                            onClick={() => handleVerify(payment)}
                                            className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white min-h-[44px]"
                                        >
                                            Verify & Settle
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() => {
                                                setSelectedPayment(payment);
                                                setIsRejectModalOpen(true);
                                            }}
                                            className="flex-1 min-h-[44px]"
                                        >
                                            Reject
                                        </Button>
                                    </div>
                                )}

                                {payment.status === 'VERIFIED' && canReverse && (
                                    <div className="pt-2 border-t border-slate-100 dark:border-slate-800">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setSelectedPayment(payment);
                                                setIsReverseModalOpen(true);
                                            }}
                                            className="w-full text-red-600 dark:text-red-400 border-red-200 min-h-[44px]"
                                        >
                                            Reverse / Bounce
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>

                {/* Pagination */}
                {payments.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 dark:border-slate-800 pt-4">
                        <div className="text-xs text-slate-500">
                            Showing {payments.from} to {payments.to} of {payments.total} transactions
                        </div>
                        <div className="flex gap-1">
                            {payments.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveScroll
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium min-h-[36px] flex items-center justify-center ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : !link.url
                                            ? 'text-slate-300 dark:text-slate-600 pointer-events-none'
                                            : 'border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Evidence Preview Modal */}
            <PaymentEvidencePreviewModal
                isOpen={!!previewPayment}
                onClose={() => setPreviewPayment(null)}
                payment={
                    previewPayment
                        ? {
                              id: previewPayment.id,
                              payment_number: previewPayment.payment_number,
                              payment_method: previewPayment.payment_method,
                              amount: previewPayment.amount,
                              cheque_number: previewPayment.cheque_number,
                              bank_name: previewPayment.bank_name,
                              money_order_number: previewPayment.money_order_number,
                              issuer_name: previewPayment.issuer_name,
                              payment_date: previewPayment.payment_date,
                              customer_name: previewPayment.customer?.name,
                              evidence_original_name: previewPayment.evidence_original_name,
                          }
                        : null
                }
            />

            {/* Record Payment Entry Modal */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs animate-in fade-in duration-150">
                    <div className="relative w-full max-w-xl bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-h-[90vh] overflow-y-auto p-6">
                        <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100">
                            Record Inbound Payment
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Capture collection details. Cheque and Money Order entries require mandatory JPEG evidence.
                        </p>

                        {/* Payment Method Selector Tabs */}
                        <div className="grid grid-cols-3 gap-2 mt-4 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg">
                            {(['CASH', 'CHEQUE', 'MONEY_ORDER'] as const).map((m) => (
                                <button
                                    key={m}
                                    type="button"
                                    onClick={() => {
                                        setPaymentMethod(m);
                                        setFormErrors({});
                                    }}
                                    className={`py-2 text-xs font-semibold rounded-md transition-colors min-h-[44px] ${
                                        paymentMethod === m
                                            ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                            : 'text-slate-600 dark:text-slate-400'
                                    }`}
                                >
                                    {m === 'CASH' ? 'Cash' : m === 'CHEQUE' ? 'Cheque' : 'Money Order'}
                                </button>
                            ))}
                        </div>

                        <form onSubmit={handleCreatePaymentSubmit} className="space-y-4 mt-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Customer Account <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={formData.customer_id}
                                    onChange={(e) => setFormData({ ...formData, customer_id: e.target.value })}
                                    required
                                    className="w-full mt-1 px-3 py-2 text-sm rounded-md border border-input bg-background min-h-[44px]"
                                >
                                    <option value="">Select customer...</option>
                                    {customers.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name} ({c.code})
                                        </option>
                                    ))}
                                </select>
                                {formErrors.customer_id && (
                                    <p className="text-xs text-red-500 mt-1">{formErrors.customer_id}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Amount ($) <span className="text-red-500">*</span>
                                    </label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        value={formData.amount}
                                        onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                        required
                                        className="mt-1 min-h-[44px]"
                                    />
                                    {formErrors.amount && (
                                        <p className="text-xs text-red-500 mt-1">{formErrors.amount}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Payment Date <span className="text-red-500">*</span>
                                    </label>
                                    <Input
                                        type="date"
                                        value={formData.payment_date}
                                        max={new Date().toISOString().split('T')[0]}
                                        onChange={(e) => setFormData({ ...formData, payment_date: e.target.value })}
                                        required
                                        className="mt-1 min-h-[44px]"
                                    />
                                    {formErrors.payment_date && (
                                        <p className="text-xs text-red-500 mt-1">{formErrors.payment_date}</p>
                                    )}
                                </div>
                            </div>

                            {/* Method-specific fields */}
                            {paymentMethod === 'CASH' && (
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Receipt Reference
                                    </label>
                                    <Input
                                        type="text"
                                        placeholder="e.g. RCPT-12345"
                                        value={formData.receipt_reference}
                                        onChange={(e) => setFormData({ ...formData, receipt_reference: e.target.value })}
                                        className="mt-1 min-h-[44px]"
                                    />
                                </div>
                            )}

                            {paymentMethod === 'CHEQUE' && (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                                Bank Name <span className="text-red-500">*</span>
                                            </label>
                                            <Input
                                                type="text"
                                                placeholder="e.g. Chase, Wells Fargo"
                                                value={formData.bank_name}
                                                onChange={(e) => setFormData({ ...formData, bank_name: e.target.value })}
                                                required
                                                className="mt-1 min-h-[44px]"
                                            />
                                            {formErrors.bank_name && (
                                                <p className="text-xs text-red-500 mt-1">{formErrors.bank_name}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                                Cheque Number <span className="text-red-500">*</span>
                                            </label>
                                            <Input
                                                type="text"
                                                placeholder="e.g. 100234"
                                                value={formData.cheque_number}
                                                onChange={(e) => setFormData({ ...formData, cheque_number: e.target.value })}
                                                required
                                                className="mt-1 min-h-[44px]"
                                            />
                                            {formErrors.cheque_number && (
                                                <p className="text-xs text-red-500 mt-1">{formErrors.cheque_number}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                            Cheque Issue Date <span className="text-red-500">*</span>
                                        </label>
                                        <Input
                                            type="date"
                                            value={formData.cheque_date}
                                            onChange={(e) => setFormData({ ...formData, cheque_date: e.target.value })}
                                            required
                                            className="mt-1 min-h-[44px]"
                                        />
                                    </div>

                                    <PaymentEvidenceUploader
                                        value={evidenceFile}
                                        onChange={setEvidenceFile}
                                        required
                                        error={formErrors.evidence}
                                        label="Cheque Photo / Scan (JPEG Only)"
                                    />
                                </div>
                            )}

                            {paymentMethod === 'MONEY_ORDER' && (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                                Issuer Name <span className="text-red-500">*</span>
                                            </label>
                                            <Input
                                                type="text"
                                                placeholder="e.g. USPS, Western Union"
                                                value={formData.issuer_name}
                                                onChange={(e) => setFormData({ ...formData, issuer_name: e.target.value })}
                                                required
                                                className="mt-1 min-h-[44px]"
                                            />
                                            {formErrors.issuer_name && (
                                                <p className="text-xs text-red-500 mt-1">{formErrors.issuer_name}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                                Money Order Number <span className="text-red-500">*</span>
                                            </label>
                                            <Input
                                                type="text"
                                                placeholder="e.g. MO-88776655"
                                                value={formData.money_order_number}
                                                onChange={(e) => setFormData({ ...formData, money_order_number: e.target.value })}
                                                required
                                                className="mt-1 min-h-[44px]"
                                            />
                                            {formErrors.money_order_number && (
                                                <p className="text-xs text-red-500 mt-1">{formErrors.money_order_number}</p>
                                            )}
                                        </div>
                                    </div>

                                    <PaymentEvidenceUploader
                                        value={evidenceFile}
                                        onChange={setEvidenceFile}
                                        required
                                        error={formErrors.evidence}
                                        label="Money Order Receipt Scan (JPEG Only)"
                                    />
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Internal Audit Notes
                                </label>
                                <textarea
                                    rows={2}
                                    value={formData.notes}
                                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                    placeholder="Optional notes or remarks..."
                                    className="w-full mt-1 px-3 py-2 text-sm rounded-md border border-input bg-background"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsCreateModalOpen(false)}
                                    disabled={isSubmitting}
                                    className="min-h-[44px]"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="min-h-[44px]"
                                >
                                    {isSubmitting ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin mr-2" /> Submitting...
                                        </>
                                    ) : (
                                        'Save Payment Entry'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Rejection Modal */}
            {isRejectModalOpen && selectedPayment && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs animate-in fade-in duration-150">
                    <div className="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6">
                        <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                            <AlertTriangle className="h-5 w-5" />
                            <h2 className="text-lg font-bold">Reject Payment Entry</h2>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Reject payment {selectedPayment.payment_number} (${Number(selectedPayment.amount).toFixed(2)}).
                        </p>

                        <form onSubmit={handleRejectSubmit} className="space-y-4 mt-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Rejection Reason Code <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                    required
                                    className="w-full mt-1 px-3 py-2 text-sm rounded-md border border-input bg-background min-h-[44px]"
                                >
                                    <option value="ILLEGIBLE_EVIDENCE">Illegible / Blurry Evidence Photo</option>
                                    <option value="CHEQUE_DATE_INVALID">Invalid / Post-Dated / Stale Cheque Date</option>
                                    <option value="AMOUNT_MISMATCH">Amount Mismatch (Written vs Declared)</option>
                                    <option value="SIGNATURE_MISSING">Missing / Invalid Authorized Signature</option>
                                    <option value="INCOMPLETE_DETAILS">Incomplete Bank / Issuer Details</option>
                                    <option value="DUPLICATE_ENTRY">Duplicate Payment Submission</option>
                                    <option value="OTHER">Other Verification Failure</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Operational Rejection Notes <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    value={rejectionNotes}
                                    onChange={(e) => setRejectionNotes(e.target.value)}
                                    placeholder="Explain why this payment is rejected..."
                                    required
                                    className="w-full mt-1 px-3 py-2 text-sm rounded-md border border-input bg-background"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setIsRejectModalOpen(false);
                                        setSelectedPayment(null);
                                    }}
                                    disabled={isSubmitting}
                                    className="min-h-[44px]"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={isSubmitting}
                                    className="min-h-[44px]"
                                >
                                    {isSubmitting ? 'Rejecting...' : 'Confirm Rejection'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reversal Modal */}
            {isReverseModalOpen && selectedPayment && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs animate-in fade-in duration-150">
                    <div className="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6">
                        <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                            <ShieldAlert className="h-5 w-5" />
                            <h2 className="text-lg font-bold">Reverse Verified Payment</h2>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Reverse {selectedPayment.payment_number} (${Number(selectedPayment.amount).toFixed(2)}). This is a terminal financial operation.
                        </p>

                        <form onSubmit={handleReverseSubmit} className="space-y-4 mt-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Reversal Reason Code <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={reversalReason}
                                    onChange={(e) => setReversalReason(e.target.value)}
                                    required
                                    className="w-full mt-1 px-3 py-2 text-sm rounded-md border border-input bg-background min-h-[44px]"
                                >
                                    <option value="BOUNCED_CHEQUE">Bounced Cheque / Returned Item</option>
                                    <option value="INSUFFICIENT_FUNDS">Non-Sufficient Funds (NSF)</option>
                                    <option value="STOP_PAYMENT">Customer Stop Payment Order</option>
                                    <option value="DATA_ENTRY_ERROR">Data Entry Error / Duplicate Recording</option>
                                    <option value="FRAUDULENT_PAYMENT">Fraudulent / Unauthorized Transaction</option>
                                    <option value="ADMIN_CORRECTION">Administrative Correction</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Reversal Notes & Bank Reference <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    value={reversalNotes}
                                    onChange={(e) => setReversalNotes(e.target.value)}
                                    placeholder="Document bank memo, NSF notice, or correction details..."
                                    required
                                    className="w-full mt-1 px-3 py-2 text-sm rounded-md border border-input bg-background"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setIsReverseModalOpen(false);
                                        setSelectedPayment(null);
                                    }}
                                    disabled={isSubmitting}
                                    className="min-h-[44px]"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={isSubmitting}
                                    className="min-h-[44px]"
                                >
                                    {isSubmitting ? 'Reversing...' : 'Confirm Reversal'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
