import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Customer, CustomerStatusOption, EligibleSalesman, PageProps } from '@/types';
import {
    Building2,
    MapPin,
    Phone,
    Mail,
    CreditCard,
    ArrowLeft,
    Edit3,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    Calendar,
    FileText,
    Shield,
    Loader2,
    Check,
    UserCheck,
    UserX,
    UserPlus,
    Info,
    Clock,
    ShoppingBag,
    Receipt,
    Wallet,
    DollarSign,
    Layers,
} from 'lucide-react';

interface CustomerShowProps {
    customer: Customer;
    statuses: CustomerStatusOption[];
    eligibleSalesmen?: EligibleSalesman[];
    can: {
        update: boolean;
        assign?: boolean;
    };
}

type ActiveTab = 'overview' | 'commercial' | 'orders' | 'payments';

export default function CustomerShow({ customer, statuses, eligibleSalesmen = [], can }: CustomerShowProps) {
    const { flash, company } = usePage<PageProps>().props;
    const [activeTab, setActiveTab] = useState<ActiveTab>('overview');
    const [statusModalOpen, setStatusModalOpen] = useState(false);
    const [assignModalOpen, setAssignModalOpen] = useState(false);

    const currencyCode = company?.currency || 'USD';

    const { data, setData, patch, processing, errors } = useForm({
        status: customer.status,
        reason: '',
    });

    const {
        data: assignData,
        setData: setAssignData,
        patch: patchAssign,
        processing: assignProcessing,
        errors: assignErrors,
    } = useForm({
        salesman_id: customer.salesman_id ? String(customer.salesman_id) : '',
        reason: '',
    });

    const handleStatusSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/customers/${customer.id}/status`, {
            onSuccess: () => setStatusModalOpen(false),
        });
    };

    const handleAssignSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patchAssign(`/customers/${customer.id}/assign`, {
            onSuccess: () => setAssignModalOpen(false),
        });
    };

    const formatCurrency = (val?: number | string | null) => {
        if (val === null || val === undefined) return 'Not yet available';
        const num = typeof val === 'string' ? parseFloat(val) : val;
        try {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: currencyCode }).format(num);
        } catch {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
        }
    };

    const formatDate = (dateStr?: string) => {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'ACTIVE':
                return <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0" />;
            case 'ON_HOLD':
                return <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0" />;
            case 'INACTIVE':
            default:
                return <XCircle className="h-4 w-4 text-rose-500 shrink-0" />;
        }
    };

    const getBadgeStyle = (status: string) => {
        switch (status) {
            case 'ACTIVE':
                return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20';
            case 'ON_HOLD':
                return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20';
            case 'INACTIVE':
            default:
                return 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20';
        }
    };

    return (
        <AppLayout title={`Customer Profile: ${customer.name}`}>
            <Head title={`${customer.code} — ${customer.name}`} />

            <div className="max-w-6xl mx-auto space-y-6 pb-12">
                {/* Flash Feedback Banner */}
                {flash?.success && (
                    <div className="flex items-center gap-2 p-3 text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-500/10 border border-emerald-500/20 rounded-lg shadow-xs">
                        <Check className="h-4 w-4 shrink-0 text-emerald-500" />
                        <span>{flash.success}</span>
                    </div>
                )}

                {/* Header Profile Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
                    <div className="flex items-start sm:items-center gap-3">
                        <Link href="/customers">
                            <Button variant="outline" size="sm" className="h-8 px-2.5 text-xs">
                                <ArrowLeft className="h-4 w-4 mr-1" />
                                Customers
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-xl font-bold tracking-tight text-foreground">
                                    {customer.name}
                                </h1>
                                <span className="font-mono text-xs px-2.5 py-0.5 rounded-md bg-muted text-muted-foreground font-semibold border border-border">
                                    {customer.code}
                                </span>
                                <span
                                    className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border ${getBadgeStyle(
                                        customer.status
                                    )}`}
                                >
                                    {getStatusIcon(customer.status)}
                                    {customer.status_label || customer.status}
                                </span>
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Master wholesale account &bull; Registered {formatDate(customer.created_at)} &bull; Representative:{' '}
                                <span className="font-medium text-foreground">
                                    {customer.salesman ? customer.salesman.name : 'Unassigned'}
                                </span>
                            </p>
                        </div>
                    </div>

                    {(can.assign ?? can.update) && (
                        <div className="flex items-center gap-2 self-start sm:self-auto flex-wrap">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setAssignModalOpen(!assignModalOpen);
                                    setStatusModalOpen(false);
                                }}
                                className="h-8 text-xs"
                            >
                                <UserCheck className="h-3.5 w-3.5 mr-1.5" />
                                {customer.salesman ? 'Reassign Rep' : 'Assign Rep'}
                            </Button>
                            {can.update && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setStatusModalOpen(!statusModalOpen);
                                        setAssignModalOpen(false);
                                    }}
                                    className="h-8 text-xs"
                                >
                                    Change Status
                                </Button>
                            )}
                            {can.update && (
                                <Link href={`/customers/${customer.id}/edit`}>
                                    <Button size="sm" className="h-8 text-xs">
                                        <Edit3 className="h-3.5 w-3.5 mr-1.5" />
                                        Edit Account
                                    </Button>
                                </Link>
                            )}
                        </div>
                    )}
                </div>

                {/* Salesman Assignment Drawer/Card */}
                {assignModalOpen && (can.assign ?? can.update) && (
                    <Card className="border-primary/30 bg-primary/5 shadow-sm">
                        <CardHeader className="py-3 px-4">
                            <div className="flex items-center gap-2">
                                <UserCheck className="h-4 w-4 text-primary" />
                                <CardTitle className="text-xs font-semibold">
                                    {customer.salesman ? 'Reassign Sales Representative' : 'Assign Sales Representative'}
                                </CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Changes the customer's sales representative portfolio ownership. Historical transaction attribution remains unaffected.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-4 pb-4">
                            <form onSubmit={handleAssignSubmit} className="space-y-3">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div className="space-y-1">
                                        <label htmlFor="assign_salesman_id" className="text-xs font-medium text-foreground">
                                            Select Active Sales Representative
                                        </label>
                                        <select
                                            id="assign_salesman_id"
                                            value={assignData.salesman_id}
                                            onChange={(e) => setAssignData('salesman_id', e.target.value)}
                                            disabled={assignProcessing}
                                            className="w-full h-8 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                        >
                                            <option value="">Unassigned (Remove Assignment)</option>
                                            {eligibleSalesmen.map((slm) => (
                                                <option key={slm.id} value={slm.id}>
                                                    {slm.name} ({slm.email})
                                                </option>
                                            ))}
                                        </select>
                                        {assignErrors.salesman_id && (
                                            <p className="text-xs text-destructive">{assignErrors.salesman_id}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1">
                                        <label htmlFor="assign_reason" className="text-xs font-medium text-foreground">
                                            Reason for Change (Optional)
                                        </label>
                                        <input
                                            id="assign_reason"
                                            type="text"
                                            placeholder="e.g. Territory realignment, portfolio rebalancing"
                                            value={assignData.reason}
                                            onChange={(e) => setAssignData('reason', e.target.value)}
                                            disabled={assignProcessing}
                                            className="w-full h-8 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                        />
                                        {assignErrors.reason && (
                                            <p className="text-xs text-destructive">{assignErrors.reason}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center gap-2 pt-1">
                                    <Button type="submit" size="sm" disabled={assignProcessing} className="h-8 text-xs">
                                        {assignProcessing ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" /> : null}
                                        Apply Assignment
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setAssignModalOpen(false)}
                                        className="h-8 text-xs"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Status Switcher Drawer/Card */}
                {statusModalOpen && can.update && (
                    <Card className="border-primary/30 bg-primary/5 shadow-sm">
                        <CardHeader className="py-3 px-4">
                            <CardTitle className="text-xs font-semibold">Update Account Lifecycle Status</CardTitle>
                            <CardDescription className="text-xs">
                                Modifying lifecycle state affects order placement eligibility across sales & fulfillment channels.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-4 pb-4">
                            <form onSubmit={handleStatusSubmit} className="space-y-3">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div className="space-y-1">
                                        <label htmlFor="customer_status_select" className="text-xs font-medium text-foreground">
                                            Target Lifecycle Status
                                        </label>
                                        <select
                                            id="customer_status_select"
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value as any)}
                                            disabled={processing}
                                            className="w-full h-8 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                        >
                                            {statuses.map((s) => (
                                                <option key={s.value} value={s.value}>
                                                    {s.label}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.status && <p className="text-xs text-destructive">{errors.status}</p>}
                                    </div>

                                    <div className="space-y-1">
                                        <label htmlFor="status_reason_input" className="text-xs font-medium text-foreground">
                                            Reason for Status Transition (Optional)
                                        </label>
                                        <input
                                            id="status_reason_input"
                                            type="text"
                                            placeholder="e.g. Credit review, compliance hold, seasonal reactivation"
                                            value={data.reason}
                                            onChange={(e) => setData('reason', e.target.value)}
                                            disabled={processing}
                                            className="w-full h-8 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                        />
                                        {errors.reason && <p className="text-xs text-destructive">{errors.reason}</p>}
                                    </div>
                                </div>

                                <div className="p-2.5 rounded-md bg-muted/60 border border-border text-[11px] text-muted-foreground flex items-center gap-2">
                                    <Info className="h-4 w-4 text-primary shrink-0" />
                                    <span>
                                        {data.status === 'ACTIVE' && (
                                            <>
                                                <strong className="text-foreground">Active Status:</strong> Customer is authorized to place new wholesale orders and participate in standard commercial operations.
                                            </>
                                        )}
                                        {data.status === 'ON_HOLD' && (
                                            <>
                                                <strong className="text-foreground">On Hold Status:</strong> Customer is temporarily restricted from placing new orders pending credit, compliance, or management review.
                                            </>
                                        )}
                                        {data.status === 'INACTIVE' && (
                                            <>
                                                <strong className="text-foreground">Inactive Status:</strong> Customer account is deactivated. New order placement is strictly prohibited. Historical transactions remain preserved.
                                            </>
                                        )}
                                    </span>
                                </div>

                                <div className="flex items-center gap-2 pt-1">
                                    <Button type="submit" size="sm" disabled={processing} className="h-8 text-xs">
                                        {processing ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" /> : null}
                                        Apply Lifecycle Transition
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setStatusModalOpen(false)}
                                        className="h-8 text-xs"
                                    >
                                        Dismiss
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Key Commercial KPI Metrics Strip */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* KPI 1: Credit Limit */}
                    <Card className="shadow-xs border-border">
                        <CardHeader className="pb-2">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <CreditCard className="h-3.5 w-3.5 text-primary" />
                                Credit Limit
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-bold font-mono text-foreground">
                                {formatCurrency(customer.credit_limit)}
                            </div>
                            <span className="text-xs text-muted-foreground">
                                Terms: {customer.payment_terms_label || customer.payment_terms}
                            </span>
                        </CardContent>
                    </Card>

                    {/* KPI 2: Outstanding Balance */}
                    <Card className="shadow-xs border-border">
                        <CardHeader className="pb-2">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <DollarSign className="h-3.5 w-3.5 text-primary" />
                                Outstanding Balance
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div className="text-sm font-semibold text-muted-foreground">
                                {customer.financial_summary?.outstanding_balance !== null &&
                                customer.financial_summary?.outstanding_balance !== undefined
                                    ? formatCurrency(customer.financial_summary.outstanding_balance)
                                    : 'Not yet available'}
                            </div>
                            <span className="inline-flex items-center text-[11px] text-amber-600 dark:text-amber-400 mt-0.5">
                                <Info className="h-3 w-3 mr-1" />
                                Deferred (Pending Live AR)
                            </span>
                        </CardContent>
                    </Card>

                    {/* KPI 3: Available Credit Exposure */}
                    <Card className="shadow-xs border-border">
                        <CardHeader className="pb-2">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Wallet className="h-3.5 w-3.5 text-primary" />
                                Available Credit
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div className="text-sm font-semibold text-muted-foreground">
                                {customer.financial_summary?.available_credit !== null &&
                                customer.financial_summary?.available_credit !== undefined
                                    ? formatCurrency(customer.financial_summary.available_credit)
                                    : 'Not yet available'}
                            </div>
                            <span className="text-xs text-muted-foreground">
                                Exposure calculated on ledger sync
                            </span>
                        </CardContent>
                    </Card>

                    {/* KPI 4: Order Placement Eligibility */}
                    <Card className="shadow-xs border-border">
                        <CardHeader className="pb-2">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Shield className="h-3.5 w-3.5 text-primary" />
                                Order Placement
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-1.5">
                                {customer.status === 'ACTIVE' ? (
                                    <>
                                        <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                        <span className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                            Authorized
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <AlertTriangle className="h-4 w-4 text-rose-500" />
                                        <span className="text-sm font-semibold text-rose-600 dark:text-rose-400">
                                            Restricted ({customer.status})
                                        </span>
                                    </>
                                )}
                            </div>
                            <span className="text-xs text-muted-foreground">Commercial status enforcement</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Tabbed Navigation Bar */}
                <div className="border-b border-border">
                    <nav className="flex space-x-2 overflow-x-auto py-1" role="tablist" aria-label="Customer Profile Sections">
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeTab === 'overview'}
                            aria-controls="panel-overview"
                            id="tab-overview"
                            onClick={() => setActiveTab('overview')}
                            className={`inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-md transition-colors whitespace-nowrap focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring ${
                                activeTab === 'overview'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            }`}
                        >
                            <Building2 className="h-3.5 w-3.5" />
                            Account Overview
                        </button>

                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeTab === 'commercial'}
                            aria-controls="panel-commercial"
                            id="tab-commercial"
                            onClick={() => setActiveTab('commercial')}
                            className={`inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-md transition-colors whitespace-nowrap focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring ${
                                activeTab === 'commercial'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            }`}
                        >
                            <Clock className="h-3.5 w-3.5" />
                            Commercial & Aging
                        </button>

                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeTab === 'orders'}
                            aria-controls="panel-orders"
                            id="tab-orders"
                            onClick={() => setActiveTab('orders')}
                            className={`inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-md transition-colors whitespace-nowrap focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring ${
                                activeTab === 'orders'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            }`}
                        >
                            <ShoppingBag className="h-3.5 w-3.5" />
                            Purchase History
                        </button>

                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeTab === 'payments'}
                            aria-controls="panel-payments"
                            id="tab-payments"
                            onClick={() => setActiveTab('payments')}
                            className={`inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-md transition-colors whitespace-nowrap focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring ${
                                activeTab === 'payments'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            }`}
                        >
                            <Receipt className="h-3.5 w-3.5" />
                            Payment History
                        </button>
                    </nav>
                </div>

                {/* Tab 1: Account Overview */}
                {activeTab === 'overview' && (
                    <div id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" className="space-y-6">
                        {/* Address Cards Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Billing Address Card */}
                            <Card className="shadow-xs border-border">
                                <CardHeader className="pb-3 border-b border-border">
                                    <div className="flex items-center gap-2">
                                        <MapPin className="h-4 w-4 text-primary" />
                                        <CardTitle className="text-sm font-semibold">Physical / Billing Address</CardTitle>
                                    </div>
                                    <CardDescription className="text-xs">
                                        Registered legal address for invoices & formal financial statements.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="pt-4 space-y-1.5 text-xs">
                                    <div className="font-semibold text-foreground">{customer.name}</div>
                                    <div className="text-muted-foreground">{customer.billing_address_line1}</div>
                                    {customer.billing_address_line2 && (
                                        <div className="text-muted-foreground">{customer.billing_address_line2}</div>
                                    )}
                                    <div className="text-muted-foreground">
                                        {customer.billing_city}, {customer.billing_state} {customer.billing_postal_code}
                                    </div>
                                    <div className="font-mono text-muted-foreground">{customer.billing_country}</div>
                                </CardContent>
                            </Card>

                            {/* Shipping Address Card */}
                            <Card className="shadow-xs border-border">
                                <CardHeader className="pb-3 border-b border-border">
                                    <div className="flex items-center gap-2">
                                        <MapPin className="h-4 w-4 text-primary" />
                                        <CardTitle className="text-sm font-semibold">Shipping / Delivery Destination</CardTitle>
                                    </div>
                                    <CardDescription className="text-xs">
                                        Receiving warehouse or dock destination for fulfillment routing.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="pt-4 space-y-1.5 text-xs">
                                    {customer.shipping_address_line1 ? (
                                        <>
                                            <div className="font-semibold text-foreground">{customer.name}</div>
                                            <div className="text-muted-foreground">{customer.shipping_address_line1}</div>
                                            {customer.shipping_address_line2 && (
                                                <div className="text-muted-foreground">{customer.shipping_address_line2}</div>
                                            )}
                                            <div className="text-muted-foreground">
                                                {customer.shipping_city}, {customer.shipping_state}{' '}
                                                {customer.shipping_postal_code}
                                            </div>
                                            <div className="font-mono text-muted-foreground">{customer.shipping_country}</div>
                                        </>
                                    ) : (
                                        <div className="text-muted-foreground italic">
                                            Same as registered billing address ({customer.formatted_billing_address})
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Representative & Commercial Identity Row */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Primary Contact & Metadata */}
                            <Card className="shadow-xs border-border">
                                <CardHeader className="pb-3 border-b border-border">
                                    <div className="flex items-center gap-2">
                                        <FileText className="h-4 w-4 text-primary" />
                                        <CardTitle className="text-sm font-semibold">Contact & Business Identity</CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4 space-y-3 text-xs">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <span className="text-muted-foreground block text-[11px] mb-0.5">Primary Contact</span>
                                            <span className="font-semibold text-foreground">{customer.contact_name}</span>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground block text-[11px] mb-0.5">Contact Phone</span>
                                            <span className="font-mono text-foreground">{customer.phone}</span>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <span className="text-muted-foreground block text-[11px] mb-0.5">Billing Email</span>
                                            <span className="font-medium text-foreground">{customer.email || 'Not on file'}</span>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground block text-[11px] mb-0.5">Tax / Resale ID</span>
                                            <span className="font-mono font-medium text-foreground">
                                                {customer.tax_id || 'Not on file'}
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Assigned Sales Representative */}
                            <Card className="shadow-xs border-border">
                                <CardHeader className="pb-3 border-b border-border flex flex-row items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <UserCheck className="h-4 w-4 text-primary" />
                                        <div>
                                            <CardTitle className="text-sm font-semibold">Assigned Sales Representative</CardTitle>
                                            <CardDescription className="text-xs">
                                                Portfolio owner & relationship manager.
                                            </CardDescription>
                                        </div>
                                    </div>
                                    {(can.assign ?? can.update) && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setAssignModalOpen(true);
                                                setStatusModalOpen(false);
                                            }}
                                            className="h-7 text-xs"
                                        >
                                            <UserPlus className="h-3 w-3 mr-1" />
                                            {customer.salesman ? 'Reassign' : 'Assign Rep'}
                                        </Button>
                                    )}
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {customer.salesman ? (
                                        <div className="flex items-start justify-between gap-4 text-xs">
                                            <div className="space-y-1">
                                                <div className="font-semibold text-sm text-foreground">{customer.salesman.name}</div>
                                                <div className="text-muted-foreground">{customer.salesman.email}</div>
                                                <div className="text-[11px] text-muted-foreground font-mono">User ID: #{customer.salesman.id}</div>
                                            </div>
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                                <CheckCircle2 className="h-3 w-3" />
                                                Active Sales Rep
                                            </span>
                                        </div>
                                    ) : (
                                        <div className="flex items-center justify-between text-xs py-2">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <UserX className="h-4 w-4 text-muted-foreground" />
                                                <span>No sales representative is currently assigned.</span>
                                            </div>
                                            {(can.assign ?? can.update) && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => {
                                                        setAssignModalOpen(true);
                                                        setStatusModalOpen(false);
                                                    }}
                                                    className="h-7 text-xs"
                                                >
                                                    Assign Sales Rep
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Internal Notes */}
                        {customer.notes && (
                            <Card className="shadow-xs border-border">
                                <CardHeader className="pb-2 border-b border-border">
                                    <CardTitle className="text-xs font-semibold text-foreground">Internal Account Notes</CardTitle>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <p className="text-xs text-muted-foreground bg-muted/40 p-3 rounded-md border border-border whitespace-pre-wrap">
                                        {customer.notes}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Tab 2: Commercial & Aging */}
                {activeTab === 'commercial' && (
                    <div id="panel-commercial" role="tabpanel" aria-labelledby="tab-commercial" className="space-y-6">
                        {/* Notice Banner */}
                        <div className="flex items-start gap-3 p-4 rounded-lg bg-muted/50 border border-border text-xs text-muted-foreground">
                            <Info className="h-4 w-4 text-primary shrink-0 mt-0.5" />
                            <div>
                                <span className="font-semibold text-foreground block mb-0.5">
                                    Authoritative Financial Reconciliation Notice
                                </span>
                                {customer.financial_summary?.source_notice ||
                                    'Financial balances and aging will be calculated from authoritative transaction data once Orders, Payments, and Receivables are implemented.'}
                            </div>
                        </div>

                        {/* Commercial Terms Summary Card */}
                        <Card className="shadow-xs border-border">
                            <CardHeader className="pb-3 border-b border-border">
                                <div className="flex items-center gap-2">
                                    <CreditCard className="h-4 w-4 text-primary" />
                                    <CardTitle className="text-sm font-semibold">Commercial Credit & Payment Parameters</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                    <div>
                                        <span className="text-muted-foreground block text-[11px] mb-0.5">Authorized Credit Limit</span>
                                        <span className="text-base font-bold font-mono text-foreground">
                                            {formatCurrency(customer.credit_limit)}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground block text-[11px] mb-0.5">Payment Terms</span>
                                        <span className="font-semibold text-foreground">
                                            {customer.payment_terms_label || customer.payment_terms}
                                        </span>
                                        <span className="block font-mono text-[11px] text-muted-foreground mt-0.5">
                                            Code: {customer.payment_terms}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground block text-[11px] mb-0.5">Currency & Invoicing</span>
                                        <span className="font-mono font-semibold text-foreground">{currencyCode}</span>
                                        <span className="block text-[11px] text-muted-foreground mt-0.5">
                                            Standard Wholesale Billing
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Receivables Aging Breakdown Grid */}
                        <Card className="shadow-xs border-border">
                            <CardHeader className="pb-3 border-b border-border">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-primary" />
                                    <div>
                                        <CardTitle className="text-sm font-semibold">Accounts Receivable Aging Schedule</CardTitle>
                                        <CardDescription className="text-xs">
                                            Unpaid invoice aging distribution by payment maturity date.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                                    <div className="p-3 rounded-lg border border-border bg-muted/20">
                                        <span className="text-[11px] font-medium text-muted-foreground block mb-1">
                                            Current (0–30 Days)
                                        </span>
                                        <span className="text-xs font-semibold text-muted-foreground italic">
                                            — Pending
                                        </span>
                                    </div>
                                    <div className="p-3 rounded-lg border border-border bg-muted/20">
                                        <span className="text-[11px] font-medium text-muted-foreground block mb-1">
                                            31–60 Days Overdue
                                        </span>
                                        <span className="text-xs font-semibold text-muted-foreground italic">
                                            — Pending
                                        </span>
                                    </div>
                                    <div className="p-3 rounded-lg border border-border bg-muted/20">
                                        <span className="text-[11px] font-medium text-muted-foreground block mb-1">
                                            61–90 Days Overdue
                                        </span>
                                        <span className="text-xs font-semibold text-muted-foreground italic">
                                            — Pending
                                        </span>
                                    </div>
                                    <div className="p-3 rounded-lg border border-border bg-muted/20">
                                        <span className="text-[11px] font-medium text-muted-foreground block mb-1">
                                            90+ Days Overdue
                                        </span>
                                        <span className="text-xs font-semibold text-muted-foreground italic">
                                            — Pending
                                        </span>
                                    </div>
                                    <div className="p-3 rounded-lg border border-primary/20 bg-primary/5 col-span-2 sm:col-span-1">
                                        <span className="text-[11px] font-medium text-foreground block mb-1">
                                            Total Outstanding
                                        </span>
                                        <span className="text-xs font-semibold text-muted-foreground italic">
                                            — Pending
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Tab 3: Purchase History */}
                {activeTab === 'orders' && (
                    <div id="panel-orders" role="tabpanel" aria-labelledby="tab-orders" className="space-y-6">
                        <Card className="shadow-xs border-border">
                            <CardContent className="py-12 flex flex-col items-center justify-center text-center space-y-3">
                                <div className="p-3 rounded-full bg-muted text-muted-foreground">
                                    <ShoppingBag className="h-8 w-8 text-primary/70" />
                                </div>
                                <div className="max-w-md space-y-1">
                                    <h3 className="text-sm font-semibold text-foreground">
                                        No Order Transactions Recorded Yet
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Sales orders, order line items, pricing snapshots, and fulfillment milestones will be tracked here once orders are placed.
                                    </p>
                                </div>
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted text-muted-foreground border border-border">
                                    <Layers className="h-3 w-3" />
                                    Phase 05 — Order Management Integration Container
                                </span>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Tab 4: Payment History */}
                {activeTab === 'payments' && (
                    <div id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" className="space-y-6">
                        <Card className="shadow-xs border-border">
                            <CardContent className="py-12 flex flex-col items-center justify-center text-center space-y-3">
                                <div className="p-3 rounded-full bg-muted text-muted-foreground">
                                    <Receipt className="h-8 w-8 text-primary/70" />
                                </div>
                                <div className="max-w-md space-y-1">
                                    <h3 className="text-sm font-semibold text-foreground">
                                        No Payment Receipts Recorded Yet
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Cash collections, verified cheques with JPEG evidence, and money order settlements will display here once payments are received.
                                    </p>
                                </div>
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted text-muted-foreground border border-border">
                                    <Layers className="h-3 w-3" />
                                    Phase 07 — Payment Management Integration Container
                                </span>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
