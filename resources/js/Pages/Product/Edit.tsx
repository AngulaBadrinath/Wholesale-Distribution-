import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Category, Product, ProductStatusOption } from '@/types';
import {
    Package,
    ArrowLeft,
    CheckCircle2,
    AlertCircle,
    DollarSign,
    Tag,
    Lock,
    Save,
} from 'lucide-react';

interface ProductEditProps {
    product: Product;
    categories: Category[];
    statuses: ProductStatusOption[];
    can: {
        updatePrice: boolean;
        updateTax: boolean;
    };
}

export default function ProductEdit({
    product,
    categories,
    statuses,
    can,
}: ProductEditProps) {
    const { data, setData, put, processing, errors } = useForm({
        sku: product.sku || '',
        name: product.name || '',
        description: product.description || '',
        category_id: product.category_id ? product.category_id.toString() : '',
        unit: product.unit || 'PCS',
        status: product.status || 'ACTIVE',
        cost_price: product.cost_price !== null && product.cost_price !== undefined ? product.cost_price.toString() : '',
        minimum_allowed_price: product.minimum_allowed_price ? product.minimum_allowed_price.toString() : '',
        default_selling_price: product.default_selling_price ? product.default_selling_price.toString() : '',
        mrp: product.mrp ? product.mrp.toString() : '',
    });

    const standardUnits = ['PCS', 'BOX', 'CASE', 'KG', 'LTR', 'PACK', 'DOZEN', 'SET', 'MTR', 'BAG'];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/products/${product.id}`);
    };

    // Helper calculations for visual feedback
    const costNum = parseFloat(data.cost_price) || 0;
    const minNum = parseFloat(data.minimum_allowed_price) || 0;
    const sellNum = parseFloat(data.default_selling_price) || 0;
    const mrpNum = parseFloat(data.mrp) || 0;

    const isPricingValid =
        minNum > 0 &&
        sellNum >= minNum &&
        mrpNum >= sellNum &&
        costNum >= 0;

    return (
        <AppLayout title={`Edit Product: ${product.sku}`}>
            <Head title={`Edit Product ${product.sku}`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Top Navigation */}
                <div className="flex items-center justify-between">
                    <Link
                        href={`/products/${product.id}`}
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Product Details
                    </Link>
                </div>

                {/* Header */}
                <div>
                    <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground uppercase tracking-wider mb-1">
                        <Package className="h-3.5 w-3.5 text-primary" />
                        <span>Master Data Update / SKU: {product.sku}</span>
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Edit Master Product
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Modify product attributes, categorization, status, or commercial pricing structure.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* General Information Card */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-4">
                            <CardTitle className="text-base flex items-center gap-2">
                                <Tag className="h-4 w-4 text-primary" />
                                Product Identity & Metadata
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Unique SKU, product naming, classification, and unit specification.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* SKU */}
                                <div>
                                    <Label htmlFor="sku" className="text-xs font-medium">
                                        Stock Keeping Unit (SKU) <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="sku"
                                        type="text"
                                        value={data.sku}
                                        onChange={(e) => setData('sku', e.target.value.toUpperCase())}
                                        className="font-mono mt-1 uppercase"
                                    />
                                    {errors.sku && (
                                        <p className="text-destructive text-xs mt-1">{errors.sku}</p>
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
                                        onChange={(e) => setData('status', e.target.value as any)}
                                        className="w-full h-9 mt-1 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        {statuses.map((s) => (
                                            <option key={s.value} value={s.value}>
                                                {s.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.status && (
                                        <p className="text-destructive text-xs mt-1">{errors.status}</p>
                                    )}
                                </div>
                            </div>

                            {/* Product Name */}
                            <div>
                                <Label htmlFor="name" className="text-xs font-medium">
                                    Product Name / Title <span className="text-destructive">*</span>
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

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* Unit of Measure */}
                                <div>
                                    <Label htmlFor="unit" className="text-xs font-medium">
                                        Unit of Measure (UOM) <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="unit"
                                        type="text"
                                        value={data.unit}
                                        onChange={(e) => setData('unit', e.target.value.toUpperCase())}
                                        className="font-mono uppercase mt-1"
                                    />
                                    <div className="flex flex-wrap gap-1 mt-1.5">
                                        {standardUnits.map((u) => (
                                            <button
                                                key={u}
                                                type="button"
                                                onClick={() => setData('unit', u)}
                                                className={`text-[10px] font-mono px-1.5 py-0.5 rounded border transition-colors ${
                                                    data.unit === u
                                                        ? 'bg-primary/20 text-primary border-primary/40'
                                                        : 'bg-muted/40 text-muted-foreground border-border hover:bg-muted'
                                                }`}
                                            >
                                                {u}
                                            </button>
                                        ))}
                                    </div>
                                    {errors.unit && (
                                        <p className="text-destructive text-xs mt-1">{errors.unit}</p>
                                    )}
                                </div>

                                {/* Category */}
                                <div>
                                    <Label htmlFor="category_id" className="text-xs font-medium">
                                        Category Classification
                                    </Label>
                                    <select
                                        id="category_id"
                                        value={data.category_id}
                                        onChange={(e) => setData('category_id', e.target.value)}
                                        className="w-full h-9 mt-1 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="">-- Uncategorized / None --</option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id.toString()}>
                                                {c.name} ({c.code})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.category_id && (
                                        <p className="text-destructive text-xs mt-1">{errors.category_id}</p>
                                    )}
                                </div>
                            </div>

                            {/* Description */}
                            <div>
                                <Label htmlFor="description" className="text-xs font-medium">
                                    Description & Specifications
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
                        </CardContent>
                    </Card>

                    {/* Commercial Pricing Hierarchy Card */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <DollarSign className="h-4 w-4 text-primary" />
                                    Commercial Pricing Hierarchy (RULE-PRI-002)
                                </CardTitle>
                                {!can.updatePrice && (
                                    <span className="text-[10px] font-mono text-amber-400 bg-amber-950/40 border border-amber-800/60 px-2 py-0.5 rounded flex items-center gap-1">
                                        <Lock className="h-3 w-3" /> Requires product.price.update
                                    </span>
                                )}
                            </div>
                            <CardDescription className="text-xs">
                                Enforces: 0 ≤ Cost Price | 0 &lt; Min Allowed Price ≤ Default Selling Price ≤ MRP / List Price.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                {/* Cost Price */}
                                <div>
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="cost_price" className="text-xs font-medium">
                                            Cost Price <span className="text-destructive">*</span>
                                        </Label>
                                        <span className="text-[10px] font-mono text-amber-500/80 flex items-center gap-0.5">
                                            <Lock className="h-2.5 w-2.5" /> Confidential
                                        </span>
                                    </div>
                                    <div className="relative mt-1">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-mono text-muted-foreground">$</span>
                                        <Input
                                            id="cost_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            disabled={!can.updatePrice}
                                            value={data.cost_price}
                                            onChange={(e) => setData('cost_price', e.target.value)}
                                            className="font-mono pl-7 disabled:opacity-60"
                                        />
                                    </div>
                                    {errors.cost_price && (
                                        <p className="text-destructive text-xs mt-1">{errors.cost_price}</p>
                                    )}
                                </div>

                                {/* Minimum Allowed Price */}
                                <div>
                                    <Label htmlFor="minimum_allowed_price" className="text-xs font-medium">
                                        Min Allowed Price <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="relative mt-1">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-mono text-muted-foreground">$</span>
                                        <Input
                                            id="minimum_allowed_price"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            disabled={!can.updatePrice}
                                            value={data.minimum_allowed_price}
                                            onChange={(e) => setData('minimum_allowed_price', e.target.value)}
                                            className="font-mono pl-7 disabled:opacity-60"
                                        />
                                    </div>
                                    {errors.minimum_allowed_price && (
                                        <p className="text-destructive text-xs mt-1">{errors.minimum_allowed_price}</p>
                                    )}
                                </div>

                                {/* Default Selling Price */}
                                <div>
                                    <Label htmlFor="default_selling_price" className="text-xs font-medium">
                                        Default Selling Price <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="relative mt-1">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-mono text-muted-foreground">$</span>
                                        <Input
                                            id="default_selling_price"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            disabled={!can.updatePrice}
                                            value={data.default_selling_price}
                                            onChange={(e) => setData('default_selling_price', e.target.value)}
                                            className="font-mono pl-7 font-semibold disabled:opacity-60"
                                        />
                                    </div>
                                    {errors.default_selling_price && (
                                        <p className="text-destructive text-xs mt-1">{errors.default_selling_price}</p>
                                    )}
                                </div>

                                {/* MRP / List Price */}
                                <div>
                                    <Label htmlFor="mrp" className="text-xs font-medium">
                                        MRP / List Price <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="relative mt-1">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-mono text-muted-foreground">$</span>
                                        <Input
                                            id="mrp"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            disabled={!can.updatePrice}
                                            value={data.mrp}
                                            onChange={(e) => setData('mrp', e.target.value)}
                                            className="font-mono pl-7 disabled:opacity-60"
                                        />
                                    </div>
                                    {errors.mrp && (
                                        <p className="text-destructive text-xs mt-1">{errors.mrp}</p>
                                    )}
                                </div>
                            </div>

                            {/* Pricing Feedback */}
                            {can.updatePrice && (data.minimum_allowed_price || data.default_selling_price || data.mrp) && (
                                <div
                                    className={`p-3 rounded-md border text-xs flex items-start gap-2.5 transition-colors ${
                                        isPricingValid
                                            ? 'bg-emerald-950/20 border-emerald-800/40 text-emerald-400'
                                            : 'bg-amber-950/20 border-amber-800/40 text-amber-300'
                                    }`}
                                >
                                    {isPricingValid ? (
                                        <CheckCircle2 className="h-4 w-4 shrink-0 mt-0.5" />
                                    ) : (
                                        <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
                                    )}
                                    <div>
                                        <div className="font-semibold font-mono text-[11px]">
                                            Pricing Hierarchy Validation:
                                        </div>
                                        <div className="font-mono text-[11px] mt-0.5">
                                            Cost (${costNum.toFixed(2)}) ≤ Floor [${minNum.toFixed(2)}] ≤ Selling [${sellNum.toFixed(2)}] ≤ Ceiling [MRP: ${mrpNum.toFixed(2)}]
                                        </div>
                                        {!isPricingValid && (
                                            <p className="text-[11px] text-amber-400 mt-1">
                                                Ensure: Min Allowed &gt; $0.00, Selling Price ≥ Min Allowed, and MRP ≥ Selling Price.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Form Actions */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link href={`/products/${product.id}`}>
                            <Button variant="outline" type="button" disabled={processing}>
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing} className="shadow-xs gap-2">
                            <Save className="h-4 w-4" />
                            {processing ? 'Saving Changes...' : 'Save Product Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
