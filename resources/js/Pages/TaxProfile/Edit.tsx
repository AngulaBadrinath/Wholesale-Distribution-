import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { TaxProfile, TaxProfileStatus, TaxProfileStatusOption } from '@/types';
import {
    Receipt,
    ArrowLeft,
    Save,
    Percent,
    AlertCircle,
    Info,
    Package,
    ShieldAlert,
} from 'lucide-react';

interface TaxProfileEditProps {
    taxProfile: TaxProfile;
    statuses: TaxProfileStatusOption[];
}

export default function TaxProfileEdit({
    taxProfile,
    statuses,
}: TaxProfileEditProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: taxProfile.name || '',
        code: taxProfile.code || '',
        rate: taxProfile.rate ? parseFloat(taxProfile.rate).toString() : '',
        description: taxProfile.description || '',
        status: taxProfile.status || 'ACTIVE',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/tax-profiles/${taxProfile.id}`);
    };

    return (
        <AppLayout title={`Edit Tax Profile: ${taxProfile.code}`}>
            <Head title={`Edit Tax Profile ${taxProfile.code}`} />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Top Navigation */}
                <div className="flex items-center justify-between">
                    <Link
                        href="/tax-profiles"
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Tax Profiles
                    </Link>
                </div>

                {/* Header */}
                <div>
                    <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground uppercase tracking-wider mb-1">
                        <Receipt className="h-3.5 w-3.5 text-primary" />
                        <span>Financial Configuration / Code: {taxProfile.code}</span>
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Edit Tax Profile
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Update rate configuration, lifecycle status, and tax profile descriptive metadata.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Percent className="h-4 w-4 text-primary" />
                                    Tax Profile Details
                                </CardTitle>
                                <span className="text-xs font-mono text-muted-foreground flex items-center gap-1 bg-muted px-2.5 py-1 rounded">
                                    <Package className="h-3.5 w-3.5" />
                                    {taxProfile.products_count ?? 0} Attached Products
                                </span>
                            </div>
                            <CardDescription className="text-xs">
                                Modifying the rate will only affect FUTURE calculations and order snapshots. Historical orders retain their transacted snapshot rate.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* Profile Code */}
                                <div>
                                    <Label htmlFor="code" className="text-xs font-medium">
                                        Tax Code <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                        className="font-mono mt-1 uppercase"
                                    />
                                    {errors.code && (
                                        <p className="text-destructive text-xs mt-1">{errors.code}</p>
                                    )}
                                </div>

                                {/* Status */}
                                <div>
                                    <Label htmlFor="status" className="text-xs font-medium">
                                        Lifecycle Status <span className="text-destructive">*</span>
                                    </Label>
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value as TaxProfileStatus)}
                                        className="w-full h-9 mt-1 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        {statuses.map((s) => (
                                            <option key={s.value} value={s.value}>
                                                {s.label}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-[11px] text-muted-foreground mt-1">
                                        Deactivating preserves existing product assignments but blocks new selections.
                                    </p>
                                    {errors.status && (
                                        <p className="text-destructive text-xs mt-1">{errors.status}</p>
                                    )}
                                </div>
                            </div>

                            {/* Profile Name */}
                            <div>
                                <Label htmlFor="name" className="text-xs font-medium">
                                    Profile Name / Label <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1"
                                />
                                {errors.name && (
                                    <p className="text-destructive text-xs mt-1">{errors.name}</p>
                                )}
                            </div>

                            {/* Tax Rate */}
                            <div>
                                <Label htmlFor="rate" className="text-xs font-medium">
                                    Authoritative Tax Rate (%) <span className="text-destructive">*</span>
                                </Label>
                                <div className="relative mt-1">
                                    <Input
                                        id="rate"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        max="100"
                                        value={data.rate}
                                        onChange={(e) => setData('rate', e.target.value)}
                                        className="font-mono pr-8 text-sm font-semibold"
                                    />
                                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-mono text-muted-foreground">%</span>
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    Range: 0.0000% to 100.0000%. Stored as exact DECIMAL(7,4).
                                </p>
                                {errors.rate && (
                                    <p className="text-destructive text-xs mt-1">{errors.rate}</p>
                                )}
                            </div>

                            {/* Description */}
                            <div>
                                <Label htmlFor="description" className="text-xs font-medium">
                                    Description & Statutory References
                                </Label>
                                <textarea
                                    id="description"
                                    rows={3}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className="w-full mt-1 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                {errors.description && (
                                    <p className="text-destructive text-xs mt-1">{errors.description}</p>
                                )}
                            </div>

                            {/* Impact Warning */}
                            {(taxProfile.products_count ?? 0) > 0 && (
                                <div className="p-3.5 rounded-md border border-amber-800/40 bg-amber-950/20 text-xs flex items-start gap-2.5 text-amber-300">
                                    <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-amber-400" />
                                    <div>
                                        <p className="font-semibold text-amber-400">Attached Product Impact Notice</p>
                                        <p className="mt-0.5 text-[11px] text-amber-300/90 leading-relaxed">
                                            This profile is actively assigned to <span className="font-bold">{taxProfile.products_count}</span> catalog product(s).
                                            Any rate change will immediately apply to future order creations. Existing historical transactions and invoices will remain intact.
                                        </p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link href="/tax-profiles">
                            <Button variant="outline" type="button" disabled={processing}>
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing} className="shadow-xs gap-2">
                            <Save className="h-4 w-4" />
                            {processing ? 'Saving Changes...' : 'Save Profile Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
