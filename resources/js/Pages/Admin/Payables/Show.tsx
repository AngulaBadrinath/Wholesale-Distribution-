import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    CreditCard,
    DollarSign,
    ArrowLeft,
    Building2,
    Plus,
    Receipt,
    FileText,
    CheckCircle2,
    Clock,
    X,
    RotateCcw,
    Calendar,
    AlertTriangle,
    ShieldCheck,
    Phone,
    Mail,
    MapPin
} from 'lucide-react';

interface Supplier {
    id: number;
    supplier_code: string;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    payment_terms_days: number;
    tax_id: string | null;
    status: 'ACTIVE' | 'INACTIVE';
    notes: string | null;
}

interface BillItem {
    id: number;
    bill_number: string;
    supplier_invoice_number: string | null;
    bill_date: string;
    due_date: string;
    subtotal: string;
    tax_total: string;
    total_amount: string;
    amount_paid: string;
    amount_due: string;
    status: 'DRAFT' | 'POSTED' | 'PARTIALLY_PAID' | 'PAID' | 'CANCELLED';
    description: string | null;
}

interface PaymentItem {
    id: number;
    payment_number: string;
    supplier_bill_id: number | null;
    supplier_bill?: {
        id: number;
        bill_number: string;
    };
    payment_date: string;
    amount: string;
    payment_method: string;
    reference_number: string | null;
    status: 'COMPLETED' | 'REVERSED';
    reversal_reason: string | null;
    notes: string | null;
}

interface TransactionItem {
    id: number;
    transaction_number: string;
    source_type: string;
    source_id: number;
    source_number: string;
    type: 'SUPPLIER_BILL' | 'SUPPLIER_PAYMENT' | 'PAYMENT_REVERSAL';
    amount: string;
    debit_amount: string;
    credit_amount: string;
    running_balance: string | null;
    transaction_date: string;
    due_date: string | null;
    description: string;
}

interface PaginatedData<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
}

interface OpenBill {
    id: number;
    bill_number: string;
    supplier_invoice_number: string | null;
    due_date: string;
    total_amount: string;
    amount_paid: string;
    amount_due: string;
}

interface Props {
    supplier: Supplier;
    outstanding_balance: string;
    bills: PaginatedData<BillItem>;
    payments: PaginatedData<PaymentItem>;
    transactions: PaginatedData<TransactionItem>;
    open_bills: OpenBill[];
    payment_methods: string[];
    filters: {
        start_date?: string;
        end_date?: string;
    };
}

export default function PayablesShow({
    supplier,
    outstanding_balance,
    bills,
    payments,
    transactions,
    open_bills,
    payment_methods,
    filters,
}: Props) {
    const [activeTab, setActiveTab] = useState<'ledger' | 'bills' | 'payments'>('ledger');
    const [isBillModalOpen, setIsBillModalOpen] = useState(false);
    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
    const [reversingPayment, setReversingPayment] = useState<PaymentItem | null>(null);

    // Bill Form
    const billForm = useForm({
        supplier_id: supplier.id,
        bill_number: '',
        supplier_invoice_number: '',
        bill_date: new Date().toISOString().split('T')[0],
        due_date: '',
        subtotal: '',
        tax_total: '0.00',
        total_amount: '',
        description: '',
        notes: '',
        post_immediately: true,
    });

    // Payment Form
    const paymentForm = useForm({
        supplier_id: supplier.id,
        supplier_bill_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: '',
        payment_method: 'BANK_TRANSFER',
        reference_number: '',
        notes: '',
    });

    // Reversal Form
    const reversalForm = useForm({
        reversal_reason: '',
    });

    const handleCreateBill = (e: React.FormEvent) => {
        e.preventDefault();
        billForm.post('/admin/payables/bills', {
            onSuccess: () => {
                setIsBillModalOpen(false);
                billForm.reset();
            },
        });
    };

    const handleBillAmountChange = (subtotalVal: string, taxVal: string) => {
        const sub = parseFloat(subtotalVal) || 0;
        const tax = parseFloat(taxVal) || 0;
        const tot = (sub + tax).toFixed(2);
        billForm.setData((prev) => ({
            ...prev,
            subtotal: subtotalVal,
            tax_total: taxVal,
            total_amount: tot,
        }));
    };

    const handleSelectBillForPayment = (billIdStr: string) => {
        const bill = open_bills.find((b) => b.id.toString() === billIdStr);
        paymentForm.setData((prev) => ({
            ...prev,
            supplier_bill_id: billIdStr,
            amount: bill ? bill.amount_due : prev.amount,
        }));
    };

    const handleRecordPayment = (e: React.FormEvent) => {
        e.preventDefault();
        paymentForm.post('/admin/payables/payments', {
            onSuccess: () => {
                setIsPaymentModalOpen(false);
                paymentForm.reset();
            },
        });
    };

    const handleReversePayment = (e: React.FormEvent) => {
        e.preventDefault();
        if (!reversingPayment) return;

        reversalForm.post(`/admin/payables/payments/${reversingPayment.id}/reverse`, {
            onSuccess: () => {
                setReversingPayment(null);
                reversalForm.reset();
            },
        });
    };

    const formatCurrency = (val: string | number) => {
        const num = typeof val === 'string' ? parseFloat(val) : val;
        if (isNaN(num)) return '$0.00';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
        }).format(num);
    };

    const getTxnBadge = (type: string) => {
        switch (type) {
            case 'SUPPLIER_BILL':
                return <Badge variant="default" className="text-[10px]">SUPPLIER BILL</Badge>;
            case 'SUPPLIER_PAYMENT':
                return <Badge variant="success" className="text-[10px]">PAYMENT</Badge>;
            case 'PAYMENT_REVERSAL':
                return <Badge variant="destructive" className="text-[10px]">REVERSAL</Badge>;
            default:
                return <Badge variant="outline" className="text-[10px]">{type}</Badge>;
        }
    };

    const getBillStatusBadge = (status: string) => {
        switch (status) {
            case 'DRAFT':
                return <Badge variant="secondary" className="text-[10px]">DRAFT</Badge>;
            case 'POSTED':
                return <Badge variant="warning" className="text-[10px]">POSTED</Badge>;
            case 'PARTIALLY_PAID':
                return <Badge variant="info" className="text-[10px]">PARTIAL</Badge>;
            case 'PAID':
                return <Badge variant="success" className="text-[10px]">PAID</Badge>;
            case 'CANCELLED':
                return <Badge variant="destructive" className="text-[10px]">CANCELLED</Badge>;
            default:
                return <Badge variant="outline" className="text-[10px]">{status}</Badge>;
        }
    };

    return (
        <AppLayout title={`Supplier AP — ${supplier.name}`}>
            <Head title={`Supplier AP — ${supplier.name}`} />

            <div className="space-y-6">
                {/* Top Back & Header */}
                <div>
                    <Link
                        href="/admin/payables"
                        className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground mb-3 transition-colors"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        <span>Back to All Suppliers</span>
                    </Link>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Building2 className="h-5 w-5" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-xl font-bold tracking-tight text-foreground">{supplier.name}</h1>
                                    <span className="font-mono text-xs px-2 py-0.5 rounded-md bg-muted font-medium text-foreground">
                                        {supplier.supplier_code}
                                    </span>
                                    <Badge
                                        variant={supplier.status === 'ACTIVE' ? 'success' : 'secondary'}
                                        className="text-[10px]"
                                    >
                                        {supplier.status}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Payment Terms: <span className="font-medium text-foreground">Net {supplier.payment_terms_days} days</span>
                                    {supplier.tax_id && (
                                        <span className="ml-3 font-mono">Tax ID: {supplier.tax_id}</span>
                                    )}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIsBillModalOpen(true)}
                                className="flex items-center gap-1.5"
                            >
                                <Plus className="h-4 w-4" />
                                <span>Record Bill</span>
                            </Button>
                            <Button
                                size="sm"
                                onClick={() => setIsPaymentModalOpen(true)}
                                className="flex items-center gap-1.5"
                            >
                                <CreditCard className="h-4 w-4" />
                                <span>Record Payment</span>
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Supplier Detail Banner */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Outstanding AP Liability
                        </span>
                        <div className="mt-1 text-2xl font-bold font-mono text-destructive">
                            {formatCurrency(outstanding_balance)}
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-0.5">
                            Total unsettled balance
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Contact Info
                        </span>
                        <div className="mt-1 text-xs text-foreground space-y-0.5">
                            {supplier.contact_person && (
                                <div className="font-medium">{supplier.contact_person}</div>
                            )}
                            {supplier.phone && (
                                <div className="flex items-center gap-1 text-muted-foreground">
                                    <Phone className="h-3 w-3" />
                                    <span>{supplier.phone}</span>
                                </div>
                            )}
                            {supplier.email && (
                                <div className="flex items-center gap-1 text-muted-foreground">
                                    <Mail className="h-3 w-3" />
                                    <span>{supplier.email}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Billing Address
                        </span>
                        <div className="mt-1 text-xs text-muted-foreground flex items-start gap-1">
                            <MapPin className="h-3.5 w-3.5 shrink-0 mt-0.5 text-muted-foreground" />
                            <span>{supplier.address || 'No physical address recorded.'}</span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Ledger Summary
                        </span>
                        <div className="mt-1 text-xs text-muted-foreground space-y-1">
                            <div>Total Recorded Bills: <span className="font-medium text-foreground">{bills.total}</span></div>
                            <div>Total Completed Payments: <span className="font-medium text-foreground">{payments.total}</span></div>
                        </div>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="border-b border-border flex items-center gap-4">
                    <button
                        onClick={() => setActiveTab('ledger')}
                        className={`pb-3 text-xs font-semibold tracking-wide border-b-2 transition-colors ${
                            activeTab === 'ledger'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        AP Transaction Ledger ({transactions.total})
                    </button>
                    <button
                        onClick={() => setActiveTab('bills')}
                        className={`pb-3 text-xs font-semibold tracking-wide border-b-2 transition-colors ${
                            activeTab === 'bills'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Vendor Bills ({bills.total})
                    </button>
                    <button
                        onClick={() => setActiveTab('payments')}
                        className={`pb-3 text-xs font-semibold tracking-wide border-b-2 transition-colors ${
                            activeTab === 'payments'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Payments & Reversals ({payments.total})
                    </button>
                </div>

                {/* TAB 1: AP Transaction Ledger */}
                {activeTab === 'ledger' && (
                    <div className="rounded-xl border border-border bg-card shadow-xs overflow-hidden">
                        <div className="p-4 border-b border-border bg-muted/20 flex items-center justify-between">
                            <div>
                                <h3 className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                    Immutable Sub-Ledger History
                                </h3>
                                <p className="text-[11px] text-muted-foreground">
                                    Chronological financial record of liabilities, payments, and reversals.
                                </p>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead className="border-b border-border bg-muted/40 font-medium text-muted-foreground">
                                    <tr>
                                        <th className="py-3 px-4">Txn #</th>
                                        <th className="py-3 px-4">Date</th>
                                        <th className="py-3 px-4">Type</th>
                                        <th className="py-3 px-4">Source Reference</th>
                                        <th className="py-3 px-4">Description</th>
                                        <th className="py-3 px-4 text-right">Debit (Payment)</th>
                                        <th className="py-3 px-4 text-right">Credit (Liability)</th>
                                        <th className="py-3 px-4 text-right">Running Balance</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {transactions.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="py-10 text-center text-muted-foreground">
                                                No payable transactions posted yet for this supplier.
                                            </td>
                                        </tr>
                                    ) : (
                                        transactions.data.map((txn) => (
                                            <tr key={txn.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="py-3 px-4 font-mono font-medium text-foreground">
                                                    {txn.transaction_number}
                                                </td>
                                                <td className="py-3 px-4 text-muted-foreground font-mono">
                                                    {txn.transaction_date}
                                                </td>
                                                <td className="py-3 px-4">{getTxnBadge(txn.type)}</td>
                                                <td className="py-3 px-4 font-mono text-muted-foreground">
                                                    {txn.source_number}
                                                </td>
                                                <td className="py-3 px-4 text-muted-foreground max-w-xs truncate">
                                                    {txn.description}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono text-success">
                                                    {parseFloat(txn.debit_amount) > 0 ? formatCurrency(txn.debit_amount) : '—'}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono text-destructive">
                                                    {parseFloat(txn.credit_amount) > 0 ? formatCurrency(txn.credit_amount) : '—'}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono font-semibold text-foreground">
                                                    {txn.running_balance !== null ? formatCurrency(txn.running_balance) : '—'}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {transactions.links && transactions.links.length > 3 && (
                            <div className="flex items-center justify-between border-t border-border px-4 py-3 text-xs text-muted-foreground">
                                <div>
                                    Showing {transactions.from || 0} to {transactions.to || 0} of {transactions.total} transactions
                                </div>
                                <div className="flex items-center gap-1">
                                    {transactions.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            preserveScroll
                                            preserveState
                                            className={`px-2.5 py-1 rounded-md text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground font-semibold'
                                                    : link.url
                                                    ? 'hover:bg-muted text-muted-foreground hover:text-foreground'
                                                    : 'opacity-40 cursor-not-allowed text-muted-foreground'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* TAB 2: Vendor Bills */}
                {activeTab === 'bills' && (
                    <div className="rounded-xl border border-border bg-card shadow-xs overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead className="border-b border-border bg-muted/40 font-medium text-muted-foreground">
                                    <tr>
                                        <th className="py-3 px-4">Bill #</th>
                                        <th className="py-3 px-4">Vendor Inv #</th>
                                        <th className="py-3 px-4">Bill Date</th>
                                        <th className="py-3 px-4">Due Date</th>
                                        <th className="py-3 px-4 text-right">Total Amount</th>
                                        <th className="py-3 px-4 text-right">Amount Paid</th>
                                        <th className="py-3 px-4 text-right">Amount Due</th>
                                        <th className="py-3 px-4 text-center">Status</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {bills.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="py-10 text-center text-muted-foreground">
                                                No bills recorded for this supplier. Click "Record Bill" to add one.
                                            </td>
                                        </tr>
                                    ) : (
                                        bills.data.map((bill) => (
                                            <tr key={bill.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="py-3 px-4 font-mono font-medium text-foreground">
                                                    {bill.bill_number}
                                                </td>
                                                <td className="py-3 px-4 font-mono text-muted-foreground">
                                                    {bill.supplier_invoice_number || '—'}
                                                </td>
                                                <td className="py-3 px-4 text-muted-foreground font-mono">{bill.bill_date}</td>
                                                <td className="py-3 px-4 text-muted-foreground font-mono">{bill.due_date}</td>
                                                <td className="py-3 px-4 text-right font-mono font-medium text-foreground">
                                                    {formatCurrency(bill.total_amount)}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono text-success">
                                                    {formatCurrency(bill.amount_paid)}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono font-bold text-destructive">
                                                    {formatCurrency(bill.amount_due)}
                                                </td>
                                                <td className="py-3 px-4 text-center">{getBillStatusBadge(bill.status)}</td>
                                                <td className="py-3 px-4 text-right">
                                                    {bill.status === 'DRAFT' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 text-xs"
                                                            onClick={() => router.post(`/admin/payables/bills/${bill.id}/post`)}
                                                        >
                                                            Post to AP
                                                        </Button>
                                                    )}
                                                    {['POSTED', 'PARTIALLY_PAID'].includes(bill.status) && (
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            className="h-7 text-xs"
                                                            onClick={() => {
                                                                paymentForm.setData({
                                                                    ...paymentForm.data,
                                                                    supplier_bill_id: bill.id.toString(),
                                                                    amount: bill.amount_due,
                                                                });
                                                                setIsPaymentModalOpen(true);
                                                            }}
                                                        >
                                                            Pay
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {bills.links && bills.links.length > 3 && (
                            <div className="flex items-center justify-between border-t border-border px-4 py-3 text-xs text-muted-foreground">
                                <div>
                                    Showing {bills.from || 0} to {bills.to || 0} of {bills.total} bills
                                </div>
                                <div className="flex items-center gap-1">
                                    {bills.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            preserveScroll
                                            preserveState
                                            className={`px-2.5 py-1 rounded-md text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground font-semibold'
                                                    : link.url
                                                    ? 'hover:bg-muted text-muted-foreground hover:text-foreground'
                                                    : 'opacity-40 cursor-not-allowed text-muted-foreground'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* TAB 3: Payments & Reversals */}
                {activeTab === 'payments' && (
                    <div className="rounded-xl border border-border bg-card shadow-xs overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead className="border-b border-border bg-muted/40 font-medium text-muted-foreground">
                                    <tr>
                                        <th className="py-3 px-4">Payment #</th>
                                        <th className="py-3 px-4">Date</th>
                                        <th className="py-3 px-4">Applied Bill</th>
                                        <th className="py-3 px-4">Method</th>
                                        <th className="py-3 px-4">Reference #</th>
                                        <th className="py-3 px-4 text-right">Amount</th>
                                        <th className="py-3 px-4 text-center">Status</th>
                                        <th className="py-3 px-4">Notes / Reversal Reason</th>
                                        <th className="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {payments.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="py-10 text-center text-muted-foreground">
                                                No payments recorded for this supplier.
                                            </td>
                                        </tr>
                                    ) : (
                                        payments.data.map((payment) => (
                                            <tr key={payment.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="py-3 px-4 font-mono font-medium text-foreground">
                                                    {payment.payment_number}
                                                </td>
                                                <td className="py-3 px-4 text-muted-foreground font-mono">
                                                    {payment.payment_date}
                                                </td>
                                                <td className="py-3 px-4 font-mono text-muted-foreground">
                                                    {payment.supplier_bill?.bill_number || 'On Account'}
                                                </td>
                                                <td className="py-3 px-4">
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-muted text-[11px] font-medium text-foreground">
                                                        {payment.payment_method.replace('_', ' ')}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4 font-mono text-muted-foreground">
                                                    {payment.reference_number || '—'}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono font-bold text-success">
                                                    {formatCurrency(payment.amount)}
                                                </td>
                                                <td className="py-3 px-4 text-center">
                                                    <Badge
                                                        variant={payment.status === 'COMPLETED' ? 'success' : 'destructive'}
                                                        className="text-[10px]"
                                                    >
                                                        {payment.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 px-4 text-muted-foreground max-w-xs truncate">
                                                    {payment.reversal_reason ? (
                                                        <span className="text-destructive font-medium">
                                                            Rev: {payment.reversal_reason}
                                                        </span>
                                                    ) : (
                                                        payment.notes || '—'
                                                    )}
                                                </td>
                                                <td className="py-3 px-4 text-right">
                                                    {payment.status === 'COMPLETED' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 text-xs text-destructive hover:bg-destructive/10"
                                                            onClick={() => {
                                                                setReversingPayment(payment);
                                                                reversalForm.setData('reversal_reason', '');
                                                            }}
                                                        >
                                                            <RotateCcw className="h-3 w-3 mr-1" />
                                                            Reverse
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {payments.links && payments.links.length > 3 && (
                            <div className="flex items-center justify-between border-t border-border px-4 py-3 text-xs text-muted-foreground">
                                <div>
                                    Showing {payments.from || 0} to {payments.to || 0} of {payments.total} payments
                                </div>
                                <div className="flex items-center gap-1">
                                    {payments.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            preserveScroll
                                            preserveState
                                            className={`px-2.5 py-1 rounded-md text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground font-semibold'
                                                    : link.url
                                                    ? 'hover:bg-muted text-muted-foreground hover:text-foreground'
                                                    : 'opacity-40 cursor-not-allowed text-muted-foreground'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Modal: Record Supplier Bill */}
            {isBillModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between pb-3 border-b border-border">
                            <h2 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <Receipt className="h-5 w-5 text-primary" />
                                Record Vendor Bill
                            </h2>
                            <button
                                onClick={() => setIsBillModalOpen(false)}
                                className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateBill} className="mt-4 space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="text-xs font-medium text-foreground">
                                        Bill Date <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        type="date"
                                        value={billForm.data.bill_date}
                                        onChange={(e) => billForm.setData('bill_date', e.target.value)}
                                        className="mt-1 text-sm"
                                        required
                                    />
                                    {billForm.errors.bill_date && (
                                        <p className="text-xs text-destructive mt-1">{billForm.errors.bill_date}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Due Date (Optional)</label>
                                    <Input
                                        type="date"
                                        value={billForm.data.due_date}
                                        onChange={(e) => billForm.setData('due_date', e.target.value)}
                                        className="mt-1 text-sm"
                                    />
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Vendor Invoice # (Optional)</label>
                                    <Input
                                        placeholder="e.g. INV-98765"
                                        value={billForm.data.supplier_invoice_number}
                                        onChange={(e) => billForm.setData('supplier_invoice_number', e.target.value)}
                                        className="mt-1 text-sm font-mono"
                                    />
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Bill # (Optional)</label>
                                    <Input
                                        placeholder="Auto-generated if blank"
                                        value={billForm.data.bill_number}
                                        onChange={(e) => billForm.setData('bill_number', e.target.value)}
                                        className="mt-1 text-sm font-mono"
                                    />
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">
                                        Subtotal ($) <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        value={billForm.data.subtotal}
                                        onChange={(e) => handleBillAmountChange(e.target.value, billForm.data.tax_total)}
                                        className="mt-1 text-sm font-mono"
                                        required
                                    />
                                    {billForm.errors.subtotal && (
                                        <p className="text-xs text-destructive mt-1">{billForm.errors.subtotal}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Tax Total ($)</label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        value={billForm.data.tax_total}
                                        onChange={(e) => handleBillAmountChange(billForm.data.subtotal, e.target.value)}
                                        className="mt-1 text-sm font-mono"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="text-xs font-medium text-foreground">
                                        Total Amount ($) <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        value={billForm.data.total_amount}
                                        onChange={(e) => billForm.setData('total_amount', e.target.value)}
                                        className="mt-1 text-sm font-mono font-bold"
                                        required
                                    />
                                    {billForm.errors.total_amount && (
                                        <p className="text-xs text-destructive mt-1">{billForm.errors.total_amount}</p>
                                    )}
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="text-xs font-medium text-foreground">Description</label>
                                    <Input
                                        placeholder="e.g. Raw materials delivery shipment #402"
                                        value={billForm.data.description}
                                        onChange={(e) => billForm.setData('description', e.target.value)}
                                        className="mt-1 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2">
                                <input
                                    type="checkbox"
                                    id="post_immediately"
                                    checked={billForm.data.post_immediately}
                                    onChange={(e) => billForm.setData('post_immediately', e.target.checked)}
                                    className="rounded border-input text-primary focus:ring-ring"
                                />
                                <label htmlFor="post_immediately" className="text-xs text-foreground font-medium">
                                    Post immediately to Accounts Payable Ledger
                                </label>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-3 border-t border-border">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsBillModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" size="sm" disabled={billForm.processing}>
                                    {billForm.processing ? 'Saving...' : 'Record Bill'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Record Supplier Payment */}
            {isPaymentModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between pb-3 border-b border-border">
                            <h2 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <CreditCard className="h-5 w-5 text-primary" />
                                Record Supplier Payment
                            </h2>
                            <button
                                onClick={() => setIsPaymentModalOpen(false)}
                                className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleRecordPayment} className="mt-4 space-y-4">
                            <div>
                                <label className="text-xs font-medium text-foreground">Apply to Open Bill (Optional)</label>
                                <select
                                    value={paymentForm.data.supplier_bill_id}
                                    onChange={(e) => handleSelectBillForPayment(e.target.value)}
                                    className="w-full mt-1 rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:outline-hidden focus:ring-1 focus:ring-ring font-mono"
                                >
                                    <option value="">On Account (Unallocated Payment)</option>
                                    {open_bills.map((b) => (
                                        <option key={b.id} value={b.id.toString()}>
                                            {b.bill_number} (Due: {b.due_date} | Remaining: {formatCurrency(b.amount_due)})
                                        </option>
                                    ))}
                                </select>
                                {paymentForm.errors.supplier_bill_id && (
                                    <p className="text-xs text-destructive mt-1">{paymentForm.errors.supplier_bill_id}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="text-xs font-medium text-foreground">
                                        Payment Date <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        type="date"
                                        value={paymentForm.data.payment_date}
                                        onChange={(e) => paymentForm.setData('payment_date', e.target.value)}
                                        className="mt-1 text-sm"
                                        required
                                    />
                                    {paymentForm.errors.payment_date && (
                                        <p className="text-xs text-destructive mt-1">{paymentForm.errors.payment_date}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">
                                        Payment Amount ($) <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        value={paymentForm.data.amount}
                                        onChange={(e) => paymentForm.setData('amount', e.target.value)}
                                        className="mt-1 text-sm font-mono font-bold"
                                        required
                                    />
                                    {paymentForm.errors.amount && (
                                        <p className="text-xs text-destructive mt-1">{paymentForm.errors.amount}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Payment Method</label>
                                    <select
                                        value={paymentForm.data.payment_method}
                                        onChange={(e) => paymentForm.setData('payment_method', e.target.value)}
                                        className="w-full mt-1 rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:outline-hidden focus:ring-1 focus:ring-ring"
                                    >
                                        {payment_methods.map((method) => (
                                            <option key={method} value={method}>
                                                {method.replace('_', ' ')}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Reference / Cheque #</label>
                                    <Input
                                        placeholder="e.g. CHQ-440192"
                                        value={paymentForm.data.reference_number}
                                        onChange={(e) => paymentForm.setData('reference_number', e.target.value)}
                                        className="mt-1 text-sm font-mono"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="text-xs font-medium text-foreground">Notes / Memo</label>
                                    <Input
                                        placeholder="Payment memo or bank transaction notes..."
                                        value={paymentForm.data.notes}
                                        onChange={(e) => paymentForm.setData('notes', e.target.value)}
                                        className="mt-1 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-3 border-t border-border">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsPaymentModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" size="sm" disabled={paymentForm.processing}>
                                    {paymentForm.processing ? 'Recording...' : 'Record Payment'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Reverse Payment Confirmation */}
            {reversingPayment && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-lg animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center gap-3 pb-3 border-b border-border text-destructive">
                            <AlertTriangle className="h-5 w-5" />
                            <h2 className="text-base font-semibold text-foreground">
                                Reverse Payment {reversingPayment.payment_number}
                            </h2>
                        </div>

                        <form onSubmit={handleReversePayment} className="mt-4 space-y-4">
                            <p className="text-xs text-muted-foreground">
                                You are about to reverse payment <span className="font-mono font-bold text-foreground">{reversingPayment.payment_number}</span> of{' '}
                                <span className="font-mono font-bold text-foreground">{formatCurrency(reversingPayment.amount)}</span>.
                                This will reopen the supplier liability and post an immutable <span className="font-bold text-destructive">PAYMENT_REVERSAL</span> transaction to the AP ledger.
                            </p>

                            <div>
                                <label className="text-xs font-medium text-foreground">
                                    Reason for Reversal <span className="text-destructive">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder="e.g. Bounced cheque / payment stopped by bank..."
                                    value={reversalForm.data.reversal_reason}
                                    onChange={(e) => reversalForm.setData('reversal_reason', e.target.value)}
                                    className="w-full mt-1 rounded-md border border-input bg-background p-2 text-xs shadow-xs focus:outline-hidden focus:ring-1 focus:ring-ring"
                                    required
                                />
                                {reversalForm.errors.reversal_reason && (
                                    <p className="text-xs text-destructive mt-1">{reversalForm.errors.reversal_reason}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-3 border-t border-border">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setReversingPayment(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    size="sm"
                                    disabled={reversalForm.processing || !reversalForm.data.reversal_reason}
                                >
                                    {reversalForm.processing ? 'Reversing...' : 'Confirm Reversal'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
