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
} from 'lucide-react';

interface CustomerShowProps {
    customer: Customer & {
        formatted_billing_address?: string;
        formatted_shipping_address?: string;
        payment_terms_label?: string;
        status_label?: string;
        status_badge?: 'success' | 'warning' | 'destructive' | 'secondary';
    };
    statuses: CustomerStatusOption[];
    eligibleSalesmen?: EligibleSalesman[];
    can: {
        update: boolean;
        assign?: boolean;
    };
}

export default function CustomerShow({ customer, statuses, eligibleSalesmen = [], can }: CustomerShowProps) {
    const { flash } = usePage<PageProps>().props;
    const [statusModalOpen, setStatusModalOpen] = useState(false);
    const [assignModalOpen, setAssignModalOpen] = useState(false);

    const { data, setData, patch, processing, errors } = useForm({
        status: customer.status,
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

    const formatCurrency = (val?: number | string) => {
        const num = typeof val === 'string' ? parseFloat(val) : (val || 0);
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
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
                return <CheckCircle2 className="h-4 w-4 text-emerald-500" />;
            case 'ON_HOLD':
                return <AlertTriangle className="h-4 w-4 text-amber-500" />;
            case 'INACTIVE':
            default:
                return <XCircle className="h-4 w-4 text-rose-500" />;
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
        <AppLayout title={`Customer: ${customer.name}`}>
            <Head title={`${customer.code} — ${customer.name}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Flash Success Message */}
                {flash?.success && (
                    <div className="flex items-center gap-2 p-3 text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-500/10 border border-emerald-500/20 rounded-md">
                        <Check className="h-4 w-4 shrink-0 text-emerald-500" />
                        <span>{flash.success}</span>
                    </div>
                )}

                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
                    <div className="flex items-start sm:items-center gap-3">
                        <Link href="/customers">
                            <Button variant="outline" size="sm" className="h-8 px-2">
                                <ArrowLeft className="h-4 w-4 mr-1" />
                                Customers
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-xl font-bold tracking-tight text-foreground">
                                    {customer.name}
                                </h1>
                                <span className="font-mono text-xs px-2 py-0.5 rounded bg-muted text-muted-foreground font-semibold">
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
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Master wholesale account record &bull; Registered {formatDate(customer.created_at)}
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
                    <Card className="border-primary/30 bg-primary/5">
                        <CardHeader className="py-3 px-4">
                            <div className="flex items-center gap-2">
                                <UserCheck className="h-4 w-4 text-primary" />
                                <CardTitle className="text-xs font-semibold">
                                    {customer.salesman ? 'Reassign Sales Representative' : 'Assign Sales Representative'}
                                </CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                This changes the customer's current sales ownership. Historical transactions are not changed.
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
                    <Card className="border-primary/30 bg-primary/5">
                        <CardHeader className="py-3 px-4">
                            <CardTitle className="text-xs font-semibold">Update Account Lifecycle Status</CardTitle>
                            <CardDescription className="text-xs">
                                Modifying lifecycle state affects order placement eligibility across sales & fulfillment channels.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-4 pb-4">
                            <form onSubmit={handleStatusSubmit} className="flex items-center gap-3 flex-wrap">
                                <div className="min-w-[180px]">
                                    <select
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
                                    {errors.status && <p className="text-xs text-destructive mt-1">{errors.status}</p>}
                                </div>
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
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Grid Overview Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
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
                            <span className="text-xs text-muted-foreground">Authorized balance</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Calendar className="h-3.5 w-3.5 text-primary" />
                                Payment Terms
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-bold text-foreground">
                                {customer.payment_terms_label || customer.payment_terms}
                            </div>
                            <span className="text-xs font-mono text-muted-foreground">{customer.payment_terms}</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Phone className="h-3.5 w-3.5 text-primary" />
                                Primary Contact
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div className="text-sm font-semibold text-foreground truncate">
                                {customer.contact_name}
                            </div>
                            <span className="text-xs text-muted-foreground truncate block">{customer.phone}</span>
                        </CardContent>
                    </Card>

                    <Card>
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
                            <span className="text-xs text-muted-foreground">Order entry capability</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Detail Sections */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Billing Address Card */}
                    <Card>
                        <CardHeader className="pb-3 border-b border-border">
                            <div className="flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-primary" />
                                <CardTitle className="text-sm font-semibold">Physical / Billing Address</CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Registered legal address for accounting invoices & statements.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-4 space-y-2 text-xs">
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
                    <Card>
                        <CardHeader className="pb-3 border-b border-border">
                            <div className="flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-primary" />
                                <CardTitle className="text-sm font-semibold">Shipping / Delivery Destination</CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Receiving warehouse or delivery dock for order fulfillment routing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-4 space-y-2 text-xs">
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
                                    Same as billing address ({customer.formatted_billing_address})
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Sales Representative Card */}
                <Card>
                    <CardHeader className="pb-3 border-b border-border flex flex-row items-center justify-between">
                        <div className="flex items-center gap-2">
                            <UserCheck className="h-4 w-4 text-primary" />
                            <div>
                                <CardTitle className="text-sm font-semibold">Assigned Sales Representative</CardTitle>
                                <CardDescription className="text-xs">
                                    Commercial relationship manager and portfolio owner.
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
                                    <span>No sales representative is currently assigned to this account.</span>
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

                {/* Additional Commercial Details & Notes */}
                <Card>
                    <CardHeader className="pb-3 border-b border-border">
                        <div className="flex items-center gap-2">
                            <FileText className="h-4 w-4 text-primary" />
                            <CardTitle className="text-sm font-semibold">Commercial & Tax Metadata</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                            <div>
                                <span className="text-muted-foreground block mb-0.5">Tax / Resale ID</span>
                                <span className="font-mono font-medium text-foreground">
                                    {customer.tax_id || 'Not on file'}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block mb-0.5">Billing Email</span>
                                <span className="font-medium text-foreground">{customer.email || 'Not on file'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block mb-0.5">Last Record Update</span>
                                <span className="font-mono text-foreground">{formatDate(customer.updated_at)}</span>
                            </div>
                        </div>

                        {customer.notes && (
                            <div className="mt-4 pt-4 border-t border-border">
                                <span className="text-xs font-semibold text-foreground block mb-1">
                                    Internal Account Notes:
                                </span>
                                <p className="text-xs text-muted-foreground bg-muted/40 p-3 rounded-md border border-border">
                                    {customer.notes}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
