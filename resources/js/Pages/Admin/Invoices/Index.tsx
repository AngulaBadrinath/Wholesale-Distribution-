import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    FileText,
    Search,
    RotateCcw,
    Printer,
    Download,
    Eye,
    Filter,
    Calendar,
    DollarSign,
    CheckCircle2,
    Clock,
    AlertCircle,
    Building2,
    ExternalLink
} from 'lucide-react';

interface InvoiceRow {
    id: number;
    invoice_number: string;
    order_id: number;
    customer_id: number;
    status: 'ISSUED' | 'PAID' | 'VOID';
    payment_status: 'UNPAID' | 'PARTIALLY_PAID' | 'PAID' | 'OVERPAID' | 'REFUNDED';
    invoice_date: string;
    due_date: string;
    payment_terms: string;
    currency: string;
    subtotal: string | number;
    tax_total: string | number;
    grand_total: string | number;
    amount_paid: string | number;
    amount_due: string | number;
    customer_name_snapshot: string;
    customer_code_snapshot: string;
    order?: {
        id: number;
        order_number: string;
        status: string;
    };
    customer?: {
        id: number;
        name: string;
        code: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedInvoices {
    data: InvoiceRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface CustomerOption {
    id: number;
    name: string;
    code: string;
}

interface Props {
    invoices: PaginatedInvoices;
    customers?: CustomerOption[];
    filters: {
        status?: string;
        payment_status?: string;
        customer_id?: string | number;
        search?: string;
        per_page?: number;
    };
    statuses: string[];
    paymentStatuses: string[];
    isSalesmanView?: boolean;
}

export default function InvoiceIndex({
    invoices,
    customers = [],
    filters,
    statuses,
    paymentStatuses,
    isSalesmanView = false,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [paymentStatus, setPaymentStatus] = useState(filters.payment_status || '');
    const [customerId, setCustomerId] = useState(filters.customer_id || '');

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        const basePath = isSalesmanView ? '/salesman/invoices' : '/admin/invoices';
        router.get(
            basePath,
            {
                search: search || undefined,
                status: status || undefined,
                payment_status: paymentStatus || undefined,
                customer_id: customerId || undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        setPaymentStatus('');
        setCustomerId('');
        const basePath = isSalesmanView ? '/salesman/invoices' : '/admin/invoices';
        router.get(basePath, {}, { preserveState: true });
    };

    const getStatusBadge = (invoiceStatus: string) => {
        switch (invoiceStatus) {
            case 'PAID':
                return <Badge variant="default" className="bg-emerald-600 text-white">Paid</Badge>;
            case 'ISSUED':
                return <Badge variant="default">Issued</Badge>;
            case 'VOID':
                return <Badge variant="destructive">Void</Badge>;
            default:
                return <Badge variant="secondary">{invoiceStatus}</Badge>;
        }
    };

    const getPaymentBadge = (paymentSt: string) => {
        switch (paymentSt) {
            case 'PAID':
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Settled</span>;
            case 'PARTIALLY_PAID':
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Partial</span>;
            case 'UNPAID':
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">Unpaid</span>;
            default:
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300">{paymentSt}</span>;
        }
    };

    const getItemUrl = (id: number) => isSalesmanView ? `/salesman/invoices/${id}` : `/admin/invoices/${id}`;

    return (
        <AppLayout>
            <Head title={isSalesmanView ? "My Customer Invoices" : "Invoices & Documents"} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                            <FileText className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
                            {isSalesmanView ? "Customer Invoices" : "Invoices & Documents"}
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Authoritative, historical tax invoices and formal billing documents.
                        </p>
                    </div>
                </div>

                {/* Filter Controls Bar */}
                <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div className="relative">
                            <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                            <Input
                                type="text"
                                placeholder="Search invoice #, customer..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9"
                            />
                        </div>

                        <div>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full h-10 px-3 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-900 dark:text-white"
                            >
                                <option value="">All Document Statuses</option>
                                {statuses.map((st) => (
                                    <option key={st} value={st}>{st}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <select
                                value={paymentStatus}
                                onChange={(e) => setPaymentStatus(e.target.value)}
                                className="w-full h-10 px-3 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-900 dark:text-white"
                            >
                                <option value="">All Payment Statuses</option>
                                {paymentStatuses.map((pst) => (
                                    <option key={pst} value={pst}>{pst}</option>
                                ))}
                            </select>
                        </div>

                        {!isSalesmanView && customers.length > 0 && (
                            <div>
                                <select
                                    value={customerId}
                                    onChange={(e) => setCustomerId(e.target.value)}
                                    className="w-full h-10 px-3 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-900 dark:text-white"
                                >
                                    <option value="">All Customers</option>
                                    {customers.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name} ({c.code})</option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <Button type="submit" className="flex-1">
                                <Filter className="w-4 h-4 mr-1.5" />
                                Filter
                            </Button>
                            <Button type="button" variant="outline" onClick={handleReset}>
                                <RotateCcw className="w-4 h-4" />
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Invoices Table */}
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Invoice Number</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Order #</th>
                                    <th className="px-4 py-3">Dates</th>
                                    <th className="px-4 py-3 text-right">Grand Total</th>
                                    <th className="px-4 py-3 text-right">Balance Due</th>
                                    <th className="px-4 py-3 text-center">Document Status</th>
                                    <th className="px-4 py-3 text-center">Payment</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {invoices.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-12 text-center text-slate-500">
                                            <FileText className="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600 mb-2" />
                                            <p className="font-medium text-slate-600 dark:text-slate-300">No invoices found</p>
                                            <p className="text-xs text-slate-400 mt-1">
                                                Invoices are generated upon order approval or delivery completion.
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    invoices.data.map((inv) => (
                                        <tr key={inv.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                            <td className="px-4 py-3 font-semibold font-mono text-slate-900 dark:text-white">
                                                <Link
                                                    href={getItemUrl(inv.id)}
                                                    className="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1.5"
                                                >
                                                    <FileText className="w-3.5 h-3.5" />
                                                    {inv.invoice_number}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-900 dark:text-white">
                                                    {inv.customer_name_snapshot}
                                                </div>
                                                <div className="text-xs text-slate-500 font-mono">
                                                    {inv.customer_code_snapshot}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                                                {inv.order ? (
                                                    <Link
                                                        href={`/orders/${inv.order.id}`}
                                                        className="hover:underline text-indigo-600 dark:text-indigo-400 flex items-center gap-1"
                                                    >
                                                        {inv.order.order_number}
                                                        <ExternalLink className="w-2.5 h-2.5" />
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                <div>Issued: {new Date(inv.invoice_date).toLocaleDateString()}</div>
                                                <div className="text-slate-400">Due: {new Date(inv.due_date).toLocaleDateString()}</div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold text-slate-900 dark:text-white">
                                                ${Number(inv.grand_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold">
                                                <span className={Number(inv.amount_due) > 0 ? "text-rose-600 dark:text-rose-400" : "text-emerald-600 dark:text-emerald-400"}>
                                                    ${Number(inv.amount_due).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {getStatusBadge(inv.status)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {getPaymentBadge(inv.payment_status)}
                                            </td>
                                            <td className="px-4 py-3 text-right space-x-1 whitespace-nowrap">
                                                <Link
                                                    href={getItemUrl(inv.id)}
                                                    className="inline-flex items-center justify-center h-8 w-8 rounded-md text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                                    title="View Details"
                                                >
                                                    <Eye className="w-4 h-4" />
                                                </Link>
                                                <a
                                                    href={`/invoices/${inv.id}/print`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center justify-center h-8 w-8 rounded-md text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                                    title="Print HTML Document"
                                                >
                                                    <Printer className="w-4 h-4" />
                                                </a>
                                                <a
                                                    href={`/invoices/${inv.id}/pdf`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center justify-center h-8 w-8 rounded-md text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-slate-800"
                                                    title="Download PDF"
                                                >
                                                    <Download className="w-4 h-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {invoices.last_page > 1 && (
                        <div className="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div className="text-xs text-slate-500">
                                Showing {invoices.from} to {invoices.to} of {invoices.total} invoices
                            </div>
                            <div className="flex gap-1">
                                {invoices.links.map((link, i) => (
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            className={`inline-flex items-center justify-center h-8 min-w-[32px] px-2 text-xs rounded-md border ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground border-primary'
                                                    : 'border-input bg-background hover:bg-accent'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            className="inline-flex items-center justify-center h-8 min-w-[32px] px-2 text-xs rounded-md border border-input opacity-50 cursor-not-allowed"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
