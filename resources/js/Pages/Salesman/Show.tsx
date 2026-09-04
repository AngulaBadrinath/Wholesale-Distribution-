import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { PageProps } from '@/types';
import {
    Users,
    ArrowLeft,
    Mail,
    CheckCircle2,
    Clock,
    ShieldAlert,
    UserX,
    Building2,
    Edit,
    Shield,
    SlidersHorizontal,
    Info,
    X,
    Loader2,
    ExternalLink,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface AssignedCustomerSummary {
    id: number;
    code: string;
    name: string;
    status: string;
    status_label: string;
    credit_limit: number;
    payment_terms: string;
    payment_terms_label: string;
    city: string;
    state: string;
}

interface SalesmanProfile {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    status: string;
    status_label: string;
    can_authenticate: boolean;
    can_be_assigned: boolean;
    created_at: string;
    updated_at: string;
    assigned_customers_count: number;
}

interface StatusOption {
    value: string;
    label: string;
    description: string;
    can_transition: boolean;
}

interface SalesmanShowProps {
    salesman: SalesmanProfile;
    assigned_customers: AssignedCustomerSummary[];
    statuses: StatusOption[];
    canEdit: boolean;
    canSuspend: boolean;
}

export default function SalesmanShow({
    salesman,
    assigned_customers,
    statuses,
    canEdit,
    canSuspend,
}: SalesmanShowProps) {
    const { flash } = usePage<PageProps>().props;
    const [statusDrawerOpen, setStatusDrawerOpen] = useState(false);

    const {
        data: statusData,
        setData: setStatusData,
        patch: patchStatus,
        processing: statusProcessing,
        errors: statusErrors,
        reset: resetStatusForm,
    } = useForm({
        status: salesman.status,
        reason: '',
    });

    const handleStatusSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patchStatus(`/salesmen/${salesman.id}/status`, {
            onSuccess: () => {
                setStatusDrawerOpen(false);
                resetStatusForm('reason');
            },
        });
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 2,
        }).format(amount);
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'ACTIVE':
                return (
                    <Badge variant="outline" className="bg-emerald-950/40 text-emerald-400 border-emerald-800/60 text-xs font-mono">
                        <CheckCircle2 className="h-3 w-3 mr-1" />
                        Active
                    </Badge>
                );
            case 'INVITED':
                return (
                    <Badge variant="outline" className="bg-amber-950/40 text-amber-400 border-amber-800/60 text-xs font-mono">
                        <Clock className="h-3 w-3 mr-1" />
                        Invited
                    </Badge>
                );
            case 'SUSPENDED':
                return (
                    <Badge variant="outline" className="bg-red-950/40 text-red-400 border-red-800/60 text-xs font-mono">
                        <ShieldAlert className="h-3 w-3 mr-1" />
                        Suspended
                    </Badge>
                );
            case 'DISABLED':
                return (
                    <Badge variant="outline" className="bg-zinc-800 text-zinc-400 border-zinc-700 text-xs font-mono">
                        <UserX className="h-3 w-3 mr-1" />
                        Disabled
                    </Badge>
                );
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AppLayout title={`${salesman.name} — Sales Representative`}>
            <Head title={`${salesman.name} — Representative Profile`} />

            <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Flash Message */}
                {flash?.success && (
                    <div className="p-3 rounded-lg bg-emerald-950/30 border border-emerald-800/60 text-emerald-300 text-xs flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-400" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-3 rounded-lg bg-red-950/30 border border-red-800/60 text-red-300 text-xs flex items-center gap-2">
                        <ShieldAlert className="h-4 w-4 shrink-0 text-red-400" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Header with Title and Quick Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/80 pb-6">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/salesmen"
                            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'h-8 w-8 p-0')}
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    {salesman.name}
                                </h1>
                                {getStatusBadge(salesman.status)}
                            </div>
                            <p className="text-xs text-muted-foreground font-mono mt-0.5">
                                {salesman.email} • {salesman.role_label}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5">
                        {canSuspend && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setStatusData('status', salesman.status);
                                    setStatusDrawerOpen(true);
                                }}
                                className="text-xs gap-1.5"
                            >
                                <SlidersHorizontal className="h-3.5 w-3.5 text-primary" />
                                <span>Lifecycle Controls</span>
                            </Button>
                        )}
                        {canEdit && (
                            <Link
                                href={`/salesmen/${salesman.id}/edit`}
                                className={cn(buttonVariants({ variant: 'default', size: 'sm' }), 'text-xs gap-1.5')}
                            >
                                <Edit className="h-3.5 w-3.5" />
                                <span>Edit Profile</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Grid Overview: Account Standing & Assigned Customer Portfolio */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column: Account Details & Security Standing */}
                    <div className="space-y-6 lg:col-span-1">
                        <Card className="border-border/70 shadow-xs">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                    <Shield className="h-4 w-4 text-primary" />
                                    <span>Account & System Security</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3.5 text-xs">
                                <div className="flex items-center justify-between py-1.5 border-b border-border/50">
                                    <span className="text-muted-foreground">Account Status</span>
                                    <div>{getStatusBadge(salesman.status)}</div>
                                </div>
                                <div className="flex items-center justify-between py-1.5 border-b border-border/50">
                                    <span className="text-muted-foreground">Can Authenticate</span>
                                    <Badge
                                        variant="outline"
                                        className={
                                            salesman.can_authenticate
                                                ? 'bg-emerald-950/30 text-emerald-400 border-emerald-800/50'
                                                : 'bg-red-950/30 text-red-400 border-red-800/50'
                                        }
                                    >
                                        {salesman.can_authenticate ? 'Permitted' : 'Blocked'}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between py-1.5 border-b border-border/50">
                                    <span className="text-muted-foreground">New Assignment Eligibility</span>
                                    <Badge
                                        variant="outline"
                                        className={
                                            salesman.can_be_assigned
                                                ? 'bg-emerald-950/30 text-emerald-400 border-emerald-800/50'
                                                : 'bg-amber-950/30 text-amber-400 border-amber-800/50'
                                        }
                                    >
                                        {salesman.can_be_assigned ? 'Eligible' : 'Ineligible'}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between py-1.5 border-b border-border/50">
                                    <span className="text-muted-foreground">Role Scope</span>
                                    <span className="font-mono text-foreground">{salesman.role}</span>
                                </div>
                                <div className="flex items-center justify-between py-1.5 border-b border-border/50">
                                    <span className="text-muted-foreground">Portfolio Size</span>
                                    <span className="font-semibold text-foreground">{salesman.assigned_customers_count} accounts</span>
                                </div>
                                <div className="flex items-center justify-between py-1.5 border-b border-border/50">
                                    <span className="text-muted-foreground">Provisioned Date</span>
                                    <span className="font-mono text-foreground">
                                        {new Date(salesman.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-1.5">
                                    <span className="text-muted-foreground">Last Updated</span>
                                    <span className="font-mono text-foreground">
                                        {new Date(salesman.updated_at).toLocaleDateString()}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Operational Notice */}
                        <Card className="bg-muted/40 border-border/70 shadow-xs">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-semibold flex items-center gap-1.5 text-foreground">
                                    <Info className="h-3.5 w-3.5 text-primary" />
                                    <span>Portfolio Preservation Policy</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-[11px] text-muted-foreground leading-relaxed">
                                Suspending or deactivating a sales representative blocks system login immediately, while preserving all customer account assignments and historical transaction attribution.
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Assigned Customer Portfolio */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="border-border/70 shadow-xs">
                            <CardHeader className="pb-3 border-b border-border/60">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                                            <Building2 className="h-4 w-4 text-primary" />
                                            <span>Assigned Customer Portfolio</span>
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Wholesale customer accounts currently assigned to {salesman.name}
                                        </CardDescription>
                                    </div>
                                    <Badge variant="secondary" className="font-mono text-xs">
                                        {assigned_customers.length} {assigned_customers.length === 1 ? 'Customer' : 'Customers'}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                {assigned_customers.length === 0 ? (
                                    <div className="p-8 text-center text-muted-foreground text-xs">
                                        <div className="h-9 w-9 rounded-full bg-muted flex items-center justify-center text-muted-foreground mx-auto mb-2">
                                            <Building2 className="h-4 w-4" />
                                        </div>
                                        <p className="font-medium text-foreground">No customer accounts assigned</p>
                                        <p className="text-[11px] text-muted-foreground mt-0.5">
                                            Assign customers to this representative from the Customer Management workspace.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border/60 overflow-x-auto">
                                        <table className="w-full text-left text-xs border-collapse">
                                            <thead>
                                                <tr className="bg-muted/30 text-muted-foreground font-medium">
                                                    <th className="py-2.5 px-4">Customer Code & Name</th>
                                                    <th className="py-2.5 px-4">Location</th>
                                                    <th className="py-2.5 px-4">Terms & Limit</th>
                                                    <th className="py-2.5 px-4">Status</th>
                                                    <th className="py-2.5 px-4 text-right">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border/50">
                                                {assigned_customers.map((c) => (
                                                    <tr key={c.id} className="hover:bg-muted/30 transition-colors">
                                                        <td className="py-3 px-4">
                                                            <div className="font-medium text-foreground">{c.name}</div>
                                                            <div className="text-[11px] font-mono text-muted-foreground">{c.code}</div>
                                                        </td>
                                                        <td className="py-3 px-4 text-muted-foreground">
                                                            {c.city && c.state ? `${c.city}, ${c.state}` : '—'}
                                                        </td>
                                                        <td className="py-3 px-4 font-mono text-muted-foreground">
                                                            <div>{formatCurrency(c.credit_limit)}</div>
                                                            <div className="text-[11px] text-muted-foreground/70">{c.payment_terms_label}</div>
                                                        </td>
                                                        <td className="py-3 px-4">
                                                            <Badge variant="outline" className="text-[10px]">
                                                                {c.status_label}
                                                            </Badge>
                                                        </td>
                                                        <td className="py-3 px-4 text-right">
                                                            <Link
                                                                href={`/customers/${c.id}`}
                                                                className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'h-7 px-2 text-xs')}
                                                            >
                                                                <span>View</span>
                                                                <ExternalLink className="h-3 w-3 ml-1" />
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Status Transition Modal / Drawer */}
                {statusDrawerOpen && (
                    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-card border border-border rounded-xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in-50 zoom-in-95 duration-150">
                            <div className="flex items-center justify-between p-4 border-b border-border bg-muted/40">
                                <div className="flex items-center gap-2 text-foreground font-semibold text-sm">
                                    <SlidersHorizontal className="h-4 w-4 text-primary" />
                                    <span>Update Salesman Lifecycle State</span>
                                </div>
                                <button
                                    onClick={() => setStatusDrawerOpen(false)}
                                    className="rounded-md p-1 text-muted-foreground hover:text-foreground hover:bg-muted"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>

                            <form onSubmit={handleStatusSubmit} className="p-5 space-y-4 text-xs">
                                <div className="space-y-1.5">
                                    <label className="font-medium text-foreground block">
                                        Select Target Lifecycle State
                                    </label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        {statuses.map((status) => (
                                            <div
                                                key={status.value}
                                                onClick={() => setStatusData('status', status.value)}
                                                className={`p-2.5 rounded-lg border cursor-pointer transition-all ${
                                                    statusData.status === status.value
                                                        ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary'
                                                        : 'border-border/70 hover:border-border bg-card text-muted-foreground'
                                                }`}
                                            >
                                                <div className="font-medium text-foreground flex items-center gap-1.5">
                                                    {status.value === 'ACTIVE' && <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />}
                                                    {status.value === 'INVITED' && <Clock className="h-3.5 w-3.5 text-amber-500" />}
                                                    {status.value === 'SUSPENDED' && <ShieldAlert className="h-3.5 w-3.5 text-red-500" />}
                                                    {status.value === 'DISABLED' && <UserX className="h-3.5 w-3.5 text-zinc-400" />}
                                                    <span>{status.label}</span>
                                                </div>
                                                <p className="text-[10px] text-muted-foreground mt-0.5 line-clamp-2">
                                                    {status.description}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                    {statusErrors.status && (
                                        <p className="text-[11px] text-red-500 font-medium">{statusErrors.status}</p>
                                    )}
                                </div>

                                {/* Dynamic Consequence Warnings */}
                                <div className="p-3 rounded-md bg-muted/60 border border-border text-[11px] text-muted-foreground space-y-1.5">
                                    <div className="flex items-center gap-1.5 font-medium text-foreground">
                                        <Info className="h-3.5 w-3.5 text-primary shrink-0" />
                                        <span>Operational & Security Consequence:</span>
                                    </div>
                                    {statusData.status === 'ACTIVE' && (
                                        <p>
                                            Salesman is permitted to authenticate, browse the product catalogue, submit wholesale orders, and receive customer assignments.
                                        </p>
                                    )}
                                    {statusData.status === 'SUSPENDED' && (
                                        <p className="text-amber-400">
                                            Account authentication is immediately blocked and all existing active sessions are terminated. Customer assignments remain preserved.
                                        </p>
                                    )}
                                    {statusData.status === 'DISABLED' && (
                                        <p className="text-red-400">
                                            Account is deactivated. Authentication is strictly blocked and active sessions are revoked. Historical assignments remain intact.
                                        </p>
                                    )}
                                    {statusData.status === 'INVITED' && (
                                        <p>
                                            Account is placed in invited status pending initial credential activation.
                                        </p>
                                    )}
                                </div>

                                {/* Reason Field */}
                                <div className="space-y-1.5">
                                    <label className="font-medium text-foreground block">
                                        Operational Reason for Transition (Optional)
                                    </label>
                                    <Input
                                        type="text"
                                        placeholder="e.g. Disciplinary review, leave of absence, termination of contract..."
                                        value={statusData.reason}
                                        onChange={(e) => setStatusData('reason', e.target.value)}
                                        className="bg-background/50 h-9 text-xs"
                                        maxLength={500}
                                    />
                                    {statusErrors.reason && (
                                        <p className="text-[11px] text-red-500 font-medium">{statusErrors.reason}</p>
                                    )}
                                </div>

                                {/* Modal Actions */}
                                <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-border">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setStatusDrawerOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={statusProcessing}
                                        className="gap-1.5"
                                    >
                                        {statusProcessing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
                                        <span>Confirm Transition</span>
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
