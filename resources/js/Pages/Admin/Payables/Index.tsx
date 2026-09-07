import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
    CreditCard,
    DollarSign,
    Search,
    Eye,
    Building2,
    Plus,
    Receipt,
    FileText,
    CheckCircle2,
    Clock,
    X
} from 'lucide-react';

interface SupplierRow {
    id: number;
    supplier_code: string;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    payment_terms_days: number;
    tax_id: string | null;
    status: 'ACTIVE' | 'INACTIVE';
    active_bills_count: number;
    outstanding_balance: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSuppliers {
    data: SupplierRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
}

interface Summary {
    total_ap_outstanding: string;
    total_suppliers: number;
    active_bills_count: number;
    total_paid_bills: number;
}

interface Props {
    suppliers: PaginatedSuppliers;
    summary: Summary;
    filters: {
        search?: string;
        status?: string;
    };
    statuses: string[];
}

export default function PayablesIndex({ suppliers, summary, filters, statuses }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        supplier_code: '',
        contact_person: '',
        email: '',
        phone: '',
        address: '',
        payment_terms_days: 30,
        tax_id: '',
        status: 'ACTIVE',
        notes: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/payables',
            { search: searchTerm, status: selectedStatus || undefined },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleStatusFilter = (statusVal: string) => {
        setSelectedStatus(statusVal);
        router.get(
            '/admin/payables',
            { search: searchTerm, status: statusVal || undefined },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleCreateSupplier = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/payables/suppliers', {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                reset();
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

    return (
        <AppLayout title="Accounts Payable (AP)">
            <Head title="Accounts Payable (AP)" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <CreditCard className="h-6 w-6 text-primary" />
                            Accounts Payable
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Track supplier liabilities, review posted vendor bills, record payments, and manage sub-ledgers.
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Button
                            onClick={() => setIsCreateModalOpen(true)}
                            className="flex items-center gap-1.5"
                        >
                            <Plus className="h-4 w-4" />
                            <span>New Supplier</span>
                        </Button>
                    </div>
                </div>

                {/* Summary Metrics Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Total AP Outstanding
                            </span>
                            <div className="rounded-md bg-destructive/10 p-2 text-destructive">
                                <DollarSign className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-2 text-2xl font-bold font-mono tracking-tight text-destructive">
                            {formatCurrency(summary.total_ap_outstanding)}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Total unpaid supplier liability
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Active Suppliers
                            </span>
                            <div className="rounded-md bg-primary/10 p-2 text-primary">
                                <Building2 className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-2 text-2xl font-bold tracking-tight text-foreground">
                            {summary.total_suppliers}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Registered vendor accounts
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Open Bills Due
                            </span>
                            <div className="rounded-md bg-warning/10 p-2 text-warning">
                                <Clock className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-2 text-2xl font-bold tracking-tight text-foreground">
                            {summary.active_bills_count}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Bills awaiting payment
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Settled Bills
                            </span>
                            <div className="rounded-md bg-success/10 p-2 text-success">
                                <CheckCircle2 className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-2 text-2xl font-bold tracking-tight text-foreground">
                            {summary.total_paid_bills}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Paid in full
                        </p>
                    </div>
                </div>

                {/* Filter & Search Bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-border bg-card p-4 shadow-xs">
                    <form onSubmit={handleSearch} className="flex flex-1 items-center gap-2">
                        <div className="relative flex-1 max-w-md">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search by supplier name, code, contact..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 text-sm"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>

                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground font-medium">Status:</span>
                        <select
                            value={selectedStatus}
                            onChange={(e) => handleStatusFilter(e.target.value)}
                            className="rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium shadow-xs focus:outline-hidden focus:ring-1 focus:ring-ring"
                        >
                            <option value="">All Statuses</option>
                            {statuses.map((st) => (
                                <option key={st} value={st}>
                                    {st}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Suppliers Table */}
                <div className="rounded-xl border border-border bg-card shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead className="border-b border-border bg-muted/40 font-medium text-muted-foreground">
                                <tr>
                                    <th className="py-3 px-4">Supplier Code</th>
                                    <th className="py-3 px-4">Supplier Name</th>
                                    <th className="py-3 px-4">Contact Info</th>
                                    <th className="py-3 px-4">Payment Terms</th>
                                    <th className="py-3 px-4 text-center">Open Bills</th>
                                    <th className="py-3 px-4 text-right">Outstanding AP</th>
                                    <th className="py-3 px-4 text-center">Status</th>
                                    <th className="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {suppliers.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-muted-foreground">
                                            <Building2 className="mx-auto h-8 w-8 text-muted-foreground/50 mb-2" />
                                            <p className="font-medium text-sm">No suppliers found</p>
                                            <p className="text-xs text-muted-foreground mt-0.5">
                                                Click "New Supplier" to register a vendor and manage bills.
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    suppliers.data.map((supplier) => (
                                        <tr key={supplier.id} className="hover:bg-muted/30 transition-colors">
                                            <td className="py-3 px-4 font-mono font-medium text-foreground">
                                                {supplier.supplier_code}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="font-medium text-foreground">{supplier.name}</div>
                                                {supplier.tax_id && (
                                                    <div className="text-[11px] text-muted-foreground font-mono">
                                                        Tax ID: {supplier.tax_id}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-muted-foreground">
                                                {supplier.contact_person && (
                                                    <div className="text-foreground">{supplier.contact_person}</div>
                                                )}
                                                <div className="text-[11px]">
                                                    {supplier.phone || supplier.email || '—'}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-muted text-[11px] font-medium text-foreground">
                                                    Net {supplier.payment_terms_days} days
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                {supplier.active_bills_count > 0 ? (
                                                    <Badge variant="warning" className="text-[11px]">
                                                        {supplier.active_bills_count} open
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">0</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono font-semibold">
                                                <span
                                                    className={
                                                        parseFloat(supplier.outstanding_balance) > 0
                                                            ? 'text-destructive'
                                                            : 'text-muted-foreground'
                                                    }
                                                >
                                                    {formatCurrency(supplier.outstanding_balance)}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <Badge
                                                    variant={supplier.status === 'ACTIVE' ? 'success' : 'secondary'}
                                                    className="text-[10px]"
                                                >
                                                    {supplier.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <Link href={`/admin/payables/${supplier.id}`}>
                                                    <Button size="sm" variant="outline" className="h-7 text-xs gap-1">
                                                        <Eye className="h-3.5 w-3.5" />
                                                        <span>View Ledger</span>
                                                    </Button>
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {suppliers.links && suppliers.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border px-4 py-3 text-xs text-muted-foreground">
                            <div>
                                Showing <span className="font-medium text-foreground">{suppliers.from || 0}</span> to{' '}
                                <span className="font-medium text-foreground">{suppliers.to || 0}</span> of{' '}
                                <span className="font-medium text-foreground">{suppliers.total}</span> suppliers
                            </div>
                            <div className="flex items-center gap-1">
                                {suppliers.links.map((link, idx) => (
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
            </div>

            {/* Create Supplier Modal */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-lg animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between pb-3 border-b border-border">
                            <h2 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <Building2 className="h-5 w-5 text-primary" />
                                Register New Supplier
                            </h2>
                            <button
                                onClick={() => setIsCreateModalOpen(false)}
                                className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateSupplier} className="mt-4 space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <label className="text-xs font-medium text-foreground">
                                        Supplier / Company Name <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        placeholder="e.g. Apex Manufacturing Ltd"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="mt-1 text-sm"
                                        required
                                    />
                                    {errors.name && <p className="text-xs text-destructive mt-1">{errors.name}</p>}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Supplier Code (Optional)</label>
                                    <Input
                                        placeholder="Auto-generated if blank"
                                        value={data.supplier_code}
                                        onChange={(e) => setData('supplier_code', e.target.value)}
                                        className="mt-1 text-sm font-mono"
                                    />
                                    {errors.supplier_code && (
                                        <p className="text-xs text-destructive mt-1">{errors.supplier_code}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Payment Terms (Days)</label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={365}
                                        value={data.payment_terms_days}
                                        onChange={(e) => setData('payment_terms_days', parseInt(e.target.value) || 0)}
                                        className="mt-1 text-sm"
                                    />
                                    {errors.payment_terms_days && (
                                        <p className="text-xs text-destructive mt-1">{errors.payment_terms_days}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Contact Person</label>
                                    <Input
                                        placeholder="e.g. Jane Doe"
                                        value={data.contact_person}
                                        onChange={(e) => setData('contact_person', e.target.value)}
                                        className="mt-1 text-sm"
                                    />
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Tax / GST ID</label>
                                    <Input
                                        placeholder="e.g. GSTIN12345"
                                        value={data.tax_id}
                                        onChange={(e) => setData('tax_id', e.target.value)}
                                        className="mt-1 text-sm font-mono"
                                    />
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Email</label>
                                    <Input
                                        type="email"
                                        placeholder="vendor@example.com"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="mt-1 text-sm"
                                    />
                                    {errors.email && <p className="text-xs text-destructive mt-1">{errors.email}</p>}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-foreground">Phone</label>
                                    <Input
                                        placeholder="+1 (555) 000-0000"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="mt-1 text-sm"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="text-xs font-medium text-foreground">Address</label>
                                    <textarea
                                        rows={2}
                                        placeholder="Billing / warehouse address..."
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        className="w-full mt-1 rounded-md border border-input bg-background p-2 text-xs shadow-xs focus:outline-hidden focus:ring-1 focus:ring-ring"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-3 border-t border-border">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsCreateModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" size="sm" disabled={processing}>
                                    {processing ? 'Registering...' : 'Register Supplier'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
