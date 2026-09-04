import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { PageProps, Product, ProductStatusOption } from '@/types';
import {
    Package,
    ArrowLeft,
    Edit,
    CheckCircle2,
    Clock,
    AlertCircle,
    DollarSign,
    Lock,
    Tag,
    Layers,
    Boxes,
    ShieldCheck,
    Calendar,
    FileText,
    TrendingUp,
    ShieldAlert,
} from 'lucide-react';

interface ProductShowProps {
    product: Product;
    statuses: ProductStatusOption[];
    can: {
        update: boolean;
        updatePrice: boolean;
        updateTax: boolean;
    };
}

export default function ProductShow({
    product,
    statuses,
    can,
}: ProductShowProps) {
    const { auth } = usePage<PageProps>().props;
    const [statusModalOpen, setStatusModalOpen] = useState(false);

    const isPrivileged = auth?.user?.role === 'SUPER_ADMIN' || auth?.user?.role === 'ADMIN';

    const statusForm = useForm({
        status: product.status,
        reason: '',
    });

    const formatCurrency = (amount: number | null | undefined) => {
        if (amount === null || amount === undefined) {
            return null;
        }
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    };

    const formatDate = (dateString?: string) => {
        if (!dateString) return '—';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const handleStatusSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        statusForm.patch(`/products/${product.id}/status`, {
            onSuccess: () => {
                setStatusModalOpen(false);
                statusForm.reset('reason');
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'ACTIVE':
                return (
                    <Badge variant="outline" className="bg-emerald-950/40 text-emerald-400 border-emerald-800/60 text-xs font-mono">
                        <CheckCircle2 className="h-3.5 w-3.5 mr-1" />
                        Active Catalog Product
                    </Badge>
                );
            case 'INACTIVE':
                return (
                    <Badge variant="outline" className="bg-zinc-800/60 text-zinc-400 border-zinc-700 text-xs font-mono">
                        <Clock className="h-3.5 w-3.5 mr-1" />
                        Inactive / Blocked
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="text-xs font-mono">
                        {status}
                    </Badge>
                );
        }
    };

    // Calculate margins if cost price is available
    const cost = product.cost_price;
    const sellingPrice = product.default_selling_price;
    const grossMargin = cost !== null && cost !== undefined && sellingPrice > 0
        ? ((sellingPrice - cost) / sellingPrice) * 100
        : null;

    return (
        <AppLayout title={`Product: ${product.sku} — ${product.name}`}>
            <Head title={`Product ${product.sku} — ${product.name}`} />

            <div className="space-y-6">
                {/* Top Navigation */}
                <div className="flex items-center justify-between">
                    <Link
                        href="/products"
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Product Catalog
                    </Link>

                    <div className="flex items-center gap-2">
                        {can.update && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setStatusModalOpen(true)}
                                    className="h-8 text-xs font-mono"
                                >
                                    Change Status
                                </Button>
                                <Link href={`/products/${product.id}/edit`}>
                                    <Button size="sm" className="h-8 text-xs gap-1.5 shadow-xs">
                                        <Edit className="h-3.5 w-3.5" />
                                        Edit Product
                                    </Button>
                                </Link>
                            </>
                        )}
                    </div>
                </div>

                {/* Hero Product Card */}
                <div className="rounded-lg border border-border bg-card p-6 shadow-xs">
                    <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-mono text-sm font-bold text-primary bg-primary/10 px-2.5 py-1 rounded border border-primary/20">
                                    {product.sku}
                                </span>
                                {getStatusBadge(product.status)}
                                <span className="text-xs font-mono text-muted-foreground bg-muted px-2 py-0.5 rounded">
                                    Unit: {product.unit}
                                </span>
                                {product.category ? (
                                    <Badge variant="outline" className="text-xs font-mono">
                                        Category: {product.category.name} ({product.category.code})
                                    </Badge>
                                ) : (
                                    <span className="text-xs text-muted-foreground italic font-mono">
                                        Uncategorized
                                    </span>
                                )}
                            </div>

                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {product.name}
                            </h1>

                            {product.description ? (
                                <p className="text-sm text-muted-foreground max-w-3xl leading-relaxed">
                                    {product.description}
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground italic">
                                    No description or technical specifications provided.
                                </p>
                            )}
                        </div>

                        {/* Order Eligibility Callout */}
                        <div className="p-4 rounded-md border border-border/80 bg-muted/20 shrink-0 min-w-[240px]">
                            <div className="text-[11px] font-mono uppercase tracking-wider text-muted-foreground mb-1">
                                Order Readiness Contract
                            </div>
                            <div className="flex items-center gap-2">
                                {product.status === 'ACTIVE' ? (
                                    <>
                                        <CheckCircle2 className="h-4 w-4 text-emerald-400" />
                                        <span className="text-xs font-semibold text-emerald-400 font-mono">
                                            Eligible for Selection
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <AlertCircle className="h-4 w-4 text-amber-400" />
                                        <span className="text-xs font-semibold text-amber-400 font-mono">
                                            Ordering Disabled
                                        </span>
                                    </>
                                )}
                            </div>
                            <p className="text-[11px] text-muted-foreground mt-1">
                                {product.status === 'ACTIVE'
                                    ? 'Product may participate in new orders.'
                                    : 'Product will fail ensureCanOrder() validation.'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Commercial Pricing Hierarchy Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Cost Price */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-xs font-mono uppercase tracking-wider text-muted-foreground">
                                Cost Price
                            </CardTitle>
                            <Lock className="h-3.5 w-3.5 text-muted-foreground/60" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            {product.cost_price !== null ? (
                                <>
                                    <div className="text-xl font-bold font-mono text-foreground">
                                        {formatCurrency(product.cost_price)}
                                    </div>
                                    <p className="text-[11px] text-muted-foreground mt-1">
                                        Confidential procurement cost.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <div className="text-sm font-mono text-muted-foreground flex items-center gap-1.5 py-1">
                                        <Lock className="h-3.5 w-3.5 text-amber-500/80" />
                                        <span>Masked / Confidential</span>
                                    </div>
                                    <p className="text-[11px] text-muted-foreground mt-1">
                                        Hidden for non-administrative roles.
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Minimum Allowed Price */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-xs font-mono uppercase tracking-wider text-muted-foreground">
                                Min Allowed Price
                            </CardTitle>
                            <ShieldAlert className="h-3.5 w-3.5 text-amber-500/60" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-xl font-bold font-mono text-foreground">
                                {formatCurrency(product.minimum_allowed_price)}
                            </div>
                            <p className="text-[11px] text-muted-foreground mt-1">
                                Strict minimum selling floor.
                            </p>
                        </CardContent>
                    </Card>

                    {/* Default Selling Price */}
                    <Card className="border-primary/40 bg-primary/5 shadow-xs">
                        <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-xs font-mono uppercase tracking-wider text-primary font-semibold">
                                Default Selling Price
                            </CardTitle>
                            <DollarSign className="h-3.5 w-3.5 text-primary" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-xl font-bold font-mono text-primary">
                                {formatCurrency(product.default_selling_price)}
                            </div>
                            <p className="text-[11px] text-muted-foreground mt-1">
                                Standard catalog invoice rate.
                            </p>
                        </CardContent>
                    </Card>

                    {/* MRP / List Price */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-xs font-mono uppercase tracking-wider text-muted-foreground">
                                MRP / List Price
                            </CardTitle>
                            <Tag className="h-3.5 w-3.5 text-muted-foreground/60" />
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-xl font-bold font-mono text-foreground">
                                {formatCurrency(product.mrp)}
                            </div>
                            <p className="text-[11px] text-muted-foreground mt-1">
                                Maximum retail ceiling.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Technical Metadata & Invariants Card */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Metadata & Attribution */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                <FileText className="h-4 w-4 text-primary" />
                                Master Record Attribution
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-xs">
                            <div className="flex justify-between py-1.5 border-b border-border/60">
                                <span className="text-muted-foreground">Internal Product ID:</span>
                                <span className="font-mono font-medium text-foreground">#{product.id}</span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-border/60">
                                <span className="text-muted-foreground">Stock Keeping Unit (SKU):</span>
                                <span className="font-mono font-semibold text-primary">{product.sku}</span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-border/60">
                                <span className="text-muted-foreground">Category Classification:</span>
                                <span className="font-medium text-foreground">
                                    {product.category?.name || 'Uncategorized'}
                                </span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-border/60">
                                <span className="text-muted-foreground">Unit of Measure:</span>
                                <span className="font-mono text-foreground">{product.unit}</span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-border/60">
                                <span className="text-muted-foreground">Future Tax Profile Hook:</span>
                                <span className="font-mono text-muted-foreground">
                                    {product.tax_profile_id ? `#${product.tax_profile_id}` : 'None (V1 Hook)'}
                                </span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-border/60">
                                <span className="text-muted-foreground">Created At:</span>
                                <span className="font-mono text-muted-foreground">{formatDate(product.created_at)}</span>
                            </div>
                            <div className="flex justify-between py-1.5">
                                <span className="text-muted-foreground">Last Updated:</span>
                                <span className="font-mono text-muted-foreground">{formatDate(product.updated_at)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Commercial Pricing Analysis (Privileged) */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                <TrendingUp className="h-4 w-4 text-primary" />
                                Commercial Pricing Rules & Boundaries
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-xs">
                            <div className="p-3 rounded-md bg-muted/40 border border-border space-y-2">
                                <div className="font-mono text-[11px] font-semibold text-foreground">
                                    Authoritative Pricing Invariant (RULE-PRI-002):
                                </div>
                                <div className="font-mono text-[11px] text-muted-foreground">
                                    0 ≤ Cost Price (${product.cost_price !== null ? Number(product.cost_price).toFixed(2) : '***'})
                                    <br />
                                    0 &lt; Min Price (${Number(product.minimum_allowed_price).toFixed(2)}) ≤ Selling (${Number(product.default_selling_price).toFixed(2)}) ≤ MRP (${Number(product.mrp).toFixed(2)})
                                </div>
                            </div>

                            {grossMargin !== null && isPrivileged && (
                                <div className="flex items-center justify-between p-3 rounded-md bg-emerald-950/20 border border-emerald-800/40 text-emerald-400">
                                    <span className="font-medium">Estimated Default Gross Margin:</span>
                                    <span className="font-mono font-bold text-sm">
                                        {grossMargin.toFixed(1)}%
                                    </span>
                                </div>
                            )}

                            <div className="space-y-1.5 text-muted-foreground text-[11px]">
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-3.5 w-3.5 text-primary" />
                                    <span>Historical transaction snapshotting enabled for future orders.</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-3.5 w-3.5 text-primary" />
                                    <span>Physical deletion prohibited — master records remain immutable.</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Lifecycle Status Change Dialog */}
            {statusModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                    <div className="w-full max-w-md bg-card border border-border rounded-lg shadow-lg overflow-hidden animate-in fade-in-50 zoom-in-95">
                        <div className="px-6 py-4 border-b border-border">
                            <h3 className="text-base font-semibold text-foreground">
                                Update Product Lifecycle Status
                            </h3>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Change catalog availability and order-eligibility state.
                            </p>
                        </div>

                        <form onSubmit={handleStatusSubmit} className="p-6 space-y-4">
                            <div>
                                <Label htmlFor="dialog_status" className="text-xs font-medium">
                                    Target Status <span className="text-destructive">*</span>
                                </Label>
                                <select
                                    id="dialog_status"
                                    value={statusForm.data.status}
                                    onChange={(e) => statusForm.setData('status', e.target.value as any)}
                                    className="w-full h-9 mt-1 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    {statuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                                {statusForm.errors.status && (
                                    <p className="text-destructive text-xs mt-1">{statusForm.errors.status}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="dialog_reason" className="text-xs font-medium">
                                    Change Reason / Operational Note
                                </Label>
                                <textarea
                                    id="dialog_reason"
                                    rows={2}
                                    value={statusForm.data.reason}
                                    onChange={(e) => statusForm.setData('reason', e.target.value)}
                                    placeholder="Optional note for audit log..."
                                    className="w-full mt-1 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                {statusForm.errors.reason && (
                                    <p className="text-destructive text-xs mt-1">{statusForm.errors.reason}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setStatusModalOpen(false)}
                                    disabled={statusForm.processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={statusForm.processing}
                                    className="shadow-xs"
                                >
                                    {statusForm.processing ? 'Updating...' : 'Confirm Status Transition'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
