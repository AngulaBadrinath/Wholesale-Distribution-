import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { TaxProfileStatusOption } from '@/types';
import {
    Receipt,
    ArrowLeft,
    CheckCircle2,
    Percent,
    AlertCircle,
    Info,
} from 'lucide-react';

interface TaxProfileCreateProps {
    statuses: TaxProfileStatusOption[];
}

export default function TaxProfileCreate({ statuses }: TaxProfileCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: '',
        rate: '',
        description: '',
        status: 'ACTIVE',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/tax-profiles');
    };

    // Rate validation preview
    const rateNum = parseFloat(data.rate);
    const isValidRate = !isNaN(rateNum) && rateNum >= 0 && rateNum <= 100;

    return (
        <AppLayout title="Create Tax Profile">
            <Head title="Create Tax Profile" />

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
                        <span>Financial Configuration / FEAT-TAX-001</span>
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Create Tax Profile
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Define a reusable, product-specific tax rate with exact 4-decimal precision and line-level ROUND_HALF_UP rounding.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-4">
                            <CardTitle className="text-base flex items-center gap-2">
                                <Percent className="h-4 w-4 text-primary" />
                                Tax Profile Specification
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Configure the authoritative percentage rate applied to product line totals during future checkout / orders.
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
                                        placeholder="e.g. TAX-STD-600, TAX-ZERO"
                                        className="font-mono mt-1 uppercase"
                                    />
                                    <p className="text-[11px] text-muted-foreground mt-1">Unique alphanumeric identifier.</p>
                                    {errors.code && (
                                        <p className="text-destructive text-xs mt-1">{errors.code}</p>
                                    )}
                                </div>

                                {/* Status */}
                                <div>
                                    <Label htmlFor="status" className="text-xs font-medium">
                                        Initial Status <span className="text-destructive">*</span>
                                    </Label>
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                        className="w-full h-9 mt-1 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        {statuses.map((s) => (
                                            <option key={s.value} value={s.value}>
                                                {s.label}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-[11px] text-muted-foreground mt-1">Only ACTIVE profiles may be assigned to products.</p>
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
                                    placeholder="e.g. Standard State Sales Tax 6.25%, Zero-Rated Medical"
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
                                        placeholder="0.0000"
                                        className="font-mono pr-8 text-sm font-semibold"
                                    />
                                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-mono text-muted-foreground">%</span>
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    Range: 0.0000% to 100.0000%. Maximum 4 decimal places. Stored as exact DECIMAL(7,4).
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
                                    placeholder="Optional notes, tax jurisdiction references, statutory exemptions, or category guidance..."
                                    className="w-full mt-1 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                {errors.description && (
                                    <p className="text-destructive text-xs mt-1">{errors.description}</p>
                                )}
                            </div>

                            {/* Invariant Guidance Alert */}
                            <div className="p-3.5 rounded-md border border-border/80 bg-muted/20 text-xs space-y-2">
                                <div className="flex items-center gap-2 font-semibold text-foreground">
                                    <Info className="h-4 w-4 text-primary shrink-0" />
                                    <span>Financial Authority & Calculation Invariants</span>
                                </div>
                                <ul className="list-disc list-inside space-y-1 text-muted-foreground text-[11px] pl-1">
                                    <li>Tax is computed per product order line item: <code className="font-mono text-foreground">ROUND_HALF_UP(taxable_amount × rate / 100, 2)</code>.</li>
                                    <li>Order header tax total is strictly the sum of rounded line tax amounts.</li>
                                    <li>Modifying a profile in the future will only apply to new order snapshots. Historical transactions remain immutable.</li>
                                </ul>
                            </div>
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
                            <CheckCircle2 className="h-4 w-4" />
                            {processing ? 'Saving Profile...' : 'Create Tax Profile'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
