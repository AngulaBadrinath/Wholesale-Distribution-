import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    CreditCard,
    Search,
    Eye,
    Receipt,
    RefreshCw,
    FileText,
} from 'lucide-react';

interface CreditNoteRow {
    id: number;
    credit_number: string;
    customer_id: number;
    order_id: number;
    invoice_id: number | null;
    return_request_id: number | null;
    status: 'ISSUED' | 'APPLIED' | 'PARTIALLY_REFUNDED' | 'FULLY_REFUNDED' | 'CLOSED';
    currency: string;
    subtotal: string | number;
    tax_total: string | number;
    total_amount: string | number;
    allocated_to_refunds: string | number;
    remaining_balance: string | number;
    issued_at: string;
    customer_name_snapshot: string;
    customer_code_snapshot: string;
    customer?: {
        id: number;
        name: string;
        code: string;
    };
    order?: {
        id: number;
        order_number: string;
    };
    return_request?: {
        id: number;
        return_number: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedCredits {
    data: CreditNoteRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    creditNotes: PaginatedCredits;
    filters: {
        status?: string;
        customer_id?: string | number;
        search?: string;
        per_page?: number;
    };
    statuses?: Array<{ value: string; label: string }>;
}

export default function Index({ creditNotes, filters, statuses = [] }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    const handleFilter = (newStatus?: string) => {
        const queryParams: Record<string, any> = {};
        const s = newStatus !== undefined ? newStatus : status;
        if (s) queryParams.status = s;
        if (search) queryParams.search = search;

        router.get('/admin/credits', queryParams, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        handleFilter();
    };

    const getStatusBadge = (creditStatus: string) => {
        switch (creditStatus) {
            case 'ISSUED':
                return <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200">ISSUED (FULL)</Badge>;
            case 'PARTIALLY_REFUNDED':
                return <Badge className="bg-amber-50 text-amber-700 border-amber-200">PARTIALLY REFUNDED</Badge>;
            case 'FULLY_REFUNDED':
                return <Badge className="bg-slate-100 text-slate-700 border-slate-300">FULLY REFUNDED</Badge>;
            case 'APPLIED':
                return <Badge className="bg-blue-50 text-blue-700 border-blue-200">APPLIED TO INVOICE</Badge>;
            case 'CLOSED':
                return <Badge className="bg-rose-50 text-rose-700 border-rose-200">CLOSED</Badge>;
            default:
                return <Badge variant="outline">{creditStatus}</Badge>;
        }
    };

    return (
        <AppLayout title="Customer Credit Notes">
            <Head title="Customer Credit Notes" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                            Customer Credit Notes
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Authoritative customer credit balances generated from approved product returns.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href="/admin/returns">
                            <Button variant="outline" className="gap-2">
                                <Receipt className="w-4 h-4 text-slate-500" />
                                Return Requests
                            </Button>
                        </Link>
                        <Link href="/admin/refunds">
                            <Button className="gap-2 bg-slate-900 hover:bg-slate-800 text-white">
                                <RefreshCw className="w-4 h-4" />
                                View Refund Requests
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Filters & Search */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-4">
                    <div className="flex flex-col md:flex-row gap-4 justify-between">
                        <form onSubmit={handleSearchSubmit} className="flex-1 flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                <Input
                                    placeholder="Search by Credit #, Customer name/code, or Order #..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 h-10 w-full"
                                />
                            </div>
                            <Button type="submit" variant="secondary" className="h-10">
                                Search
                            </Button>
                        </form>

                        <div className="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0">
                            <Button
                                type="button"
                                variant={status === '' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => {
                                    setStatus('');
                                    handleFilter('');
                                }}
                                className="h-9"
                            >
                                All Statuses
                            </Button>
                            <Button
                                type="button"
                                variant={status === 'ISSUED' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => {
                                    setStatus('ISSUED');
                                    handleFilter('ISSUED');
                                }}
                                className="h-9"
                            >
                                Issued (Active)
                            </Button>
                            <Button
                                type="button"
                                variant={status === 'PARTIALLY_REFUNDED' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => {
                                    setStatus('PARTIALLY_REFUNDED');
                                    handleFilter('PARTIALLY_REFUNDED');
                                }}
                                className="h-9"
                            >
                                Partial
                            </Button>
                            <Button
                                type="button"
                                variant={status === 'FULLY_REFUNDED' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => {
                                    setStatus('FULLY_REFUNDED');
                                    handleFilter('FULLY_REFUNDED');
                                }}
                                className="h-9"
                            >
                                Fully Settled
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Desktop Table View */}
                <div className="hidden lg:block bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-600">
                            <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase font-semibold text-slate-500">
                                <tr>
                                    <th className="px-6 py-4">Credit Note #</th>
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4">Source Return</th>
                                    <th className="px-6 py-4">Source Order</th>
                                    <th className="px-6 py-4">Issued Date</th>
                                    <th className="px-6 py-4 text-right">Total Amount</th>
                                    <th className="px-6 py-4 text-right">Remaining Balance</th>
                                    <th className="px-6 py-4 text-center">Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-medium">
                                {creditNotes.data.length > 0 ? (
                                    creditNotes.data.map((cn) => (
                                        <tr key={cn.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="px-6 py-4 font-mono font-semibold text-slate-900">
                                                {cn.credit_number}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-semibold text-slate-900">{cn.customer_name_snapshot}</div>
                                                <div className="text-xs text-slate-400 font-mono">{cn.customer_code_snapshot}</div>
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs text-slate-500">
                                                {cn.return_request?.return_number || 'N/A'}
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs text-slate-500">
                                                {cn.order?.order_number || 'N/A'}
                                            </td>
                                            <td className="px-6 py-4 text-xs text-slate-500">
                                                {new Date(cn.issued_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono font-semibold text-slate-900">
                                                ${parseFloat(String(cn.total_amount)).toFixed(2)}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono font-bold text-emerald-700">
                                                ${parseFloat(String(cn.remaining_balance)).toFixed(2)}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {getStatusBadge(cn.status)}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <Link href={`/admin/credits/${cn.id}`}>
                                                    <Button variant="ghost" size="sm" className="h-8 gap-1 text-slate-600 hover:text-slate-900">
                                                        <Eye className="w-4 h-4" />
                                                        View
                                                    </Button>
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={9} className="px-6 py-12 text-center text-slate-400">
                                            <CreditCard className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                                            <p className="text-base font-semibold text-slate-700">No credit notes found</p>
                                            <p className="text-xs text-slate-400 mt-1">
                                                Credit notes are automatically generated when return requests are approved.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Mobile / Tablet Cards View */}
                <div className="lg:hidden space-y-4">
                    {creditNotes.data.length > 0 ? (
                        creditNotes.data.map((cn) => (
                            <div key={cn.id} className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-mono font-bold text-slate-900 text-base">
                                        {cn.credit_number}
                                    </span>
                                    {getStatusBadge(cn.status)}
                                </div>

                                <div className="border-t border-slate-100 pt-2 space-y-1">
                                    <div className="text-sm font-semibold text-slate-800">{cn.customer_name_snapshot}</div>
                                    <div className="text-xs text-slate-400 font-mono">Code: {cn.customer_code_snapshot}</div>
                                </div>

                                <div className="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-lg text-xs font-mono">
                                    <div>
                                        <span className="text-slate-400 block">Total Issued</span>
                                        <span className="font-semibold text-slate-900">${parseFloat(String(cn.total_amount)).toFixed(2)}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 block">Remaining</span>
                                        <span className="font-bold text-emerald-700">${parseFloat(String(cn.remaining_balance)).toFixed(2)}</span>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-2 border-t border-slate-100 text-xs text-slate-500">
                                    <span>Issued: {new Date(cn.issued_at).toLocaleDateString()}</span>
                                    <Link href={`/admin/credits/${cn.id}`}>
                                        <Button variant="outline" size="sm" className="h-8 gap-1">
                                            <Eye className="w-3.5 h-3.5" />
                                            Details
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-white p-12 text-center rounded-xl border border-slate-200 text-slate-400">
                            <CreditCard className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                            <p className="text-base font-semibold text-slate-700">No credit notes found</p>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {creditNotes.last_page > 1 && (
                    <div className="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200">
                        <div className="text-sm text-slate-500">
                            Showing <span className="font-medium">{creditNotes.from}</span> to{' '}
                            <span className="font-medium">{creditNotes.to}</span> of{' '}
                            <span className="font-medium">{creditNotes.total}</span> credit notes
                        </div>
                        <div className="flex gap-1">
                            {creditNotes.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    className={`px-3 py-1.5 text-xs font-medium rounded-md border ${
                                        link.active
                                            ? 'bg-slate-900 text-white border-slate-900'
                                            : link.url
                                            ? 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200'
                                            : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
