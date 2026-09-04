import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Category, CategoryStatusOption, CategoryStatus, Product } from '@/types';
import {
    FolderTree,
    ArrowLeft,
    Edit,
    Trash2,
    CheckCircle2,
    XCircle,
    Package,
    Folder,
    FolderOpen,
    Layers,
    ChevronRight,
    Tag,
    Clock,
    AlertCircle,
    Info,
    RefreshCw,
    X,
} from 'lucide-react';

interface CategoryShowProps {
    category: Category & {
        products?: Product[];
        children?: Category[];
        ancestors?: Category[];
    };
    statuses: CategoryStatusOption[];
    can: {
        update: boolean;
        delete: boolean;
        create?: boolean;
    };
}

interface CategoryStatusFormData {
    status: CategoryStatus;
    reason: string;
}

export default function CategoryShow({ category, statuses, can }: CategoryShowProps) {
    const [statusModalOpen, setStatusModalOpen] = useState(false);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);

    // Status form
    const {
        data: statusData,
        setData: setStatusData,
        put: putStatus,
        processing: statusProcessing,
        errors: statusErrors,
        reset: resetStatus,
    } = useForm<CategoryStatusFormData>({
        status: category.status,
        reason: '',
    });

    // Delete form
    const {
        delete: destroyCategory,
        processing: deleteProcessing,
    } = useForm();

    const handleStatusSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        putStatus(`/categories/${category.id}/status`, {
            onSuccess: () => {
                setStatusModalOpen(false);
                resetStatus();
            },
        });
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        destroyCategory(`/categories/${category.id}`, {
            onSuccess: () => {
                setDeleteModalOpen(false);
            },
        });
    };

    const hasProducts = (category.products_count ?? 0) > 0 || (category.products && category.products.length > 0);
    const hasChildren = (category.children_count ?? 0) > 0 || (category.children && category.children.length > 0);
    const canDelete = !hasProducts && !hasChildren && can.delete;

    const attachedProducts = category.products || [];
    const directChildren = category.children || [];
    const ancestors = category.ancestors || [];

    return (
        <AppLayout title={`Category: ${category.name} (${category.code})`}>
            <Head title={`Category ${category.code}`} />

            <div className="space-y-6">
                {/* Navigation & Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="space-y-1">
                        <Link
                            href="/categories"
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors mb-1"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back to Categories Directory
                        </Link>
                        <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground uppercase tracking-wider">
                            <FolderTree className="h-3.5 w-3.5 text-primary" />
                            <span>Taxonomic Entity / Code: {category.code}</span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-3">
                            <span>{category.name}</span>
                            <Badge
                                variant={category.status === 'ACTIVE' ? 'default' : 'secondary'}
                                className="text-xs uppercase font-mono px-2.5 py-0.5"
                            >
                                {category.status}
                            </Badge>
                        </h1>
                    </div>

                    <div className="flex items-center gap-2">
                        {can.update && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setStatusData('status', category.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE');
                                        setStatusModalOpen(true);
                                    }}
                                    className="gap-1.5 text-xs shadow-xs"
                                >
                                    <RefreshCw className="h-3.5 w-3.5" />
                                    <span>Change Status</span>
                                </Button>
                                <Link href={`/categories/${category.id}/edit`}>
                                    <Button size="sm" className="gap-1.5 text-xs shadow-xs">
                                        <Edit className="h-3.5 w-3.5" />
                                        <span>Edit Category</span>
                                    </Button>
                                </Link>
                            </>
                        )}
                        {can.delete && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setDeleteModalOpen(true)}
                                className="gap-1.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive border-destructive/20 shadow-xs"
                                disabled={!canDelete}
                                title={!canDelete ? 'Cannot delete categories with children or products' : 'Delete category'}
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                <span>Delete</span>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Hierarchy Breadcrumb Strip */}
                <Card className="border-border shadow-xs bg-muted/20">
                    <CardContent className="p-3.5 flex flex-wrap items-center gap-1.5 text-xs">
                        <span className="font-semibold text-muted-foreground flex items-center gap-1">
                            <Layers className="h-3.5 w-3.5 text-primary" />
                            Hierarchy:
                        </span>
                        <Link
                            href="/categories"
                            className="text-muted-foreground hover:text-foreground transition-colors font-medium"
                        >
                            Root
                        </Link>
                        {ancestors.map((anc) => (
                            <React.Fragment key={anc.id}>
                                <ChevronRight className="h-3 w-3 text-muted-foreground/60" />
                                <Link
                                    href={`/categories/${anc.id}`}
                                    className="text-muted-foreground hover:text-foreground font-mono transition-colors"
                                >
                                    {anc.name} ({anc.code})
                                </Link>
                            </React.Fragment>
                        ))}
                        <ChevronRight className="h-3 w-3 text-muted-foreground/60" />
                        <span className="font-semibold text-foreground font-mono bg-card px-2 py-0.5 rounded border border-border">
                            {category.name} ({category.code})
                        </span>
                    </CardContent>
                </Card>

                {/* Overview Details Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* General Metadata */}
                    <Card className="border-border shadow-xs md:col-span-2">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base flex items-center gap-2">
                                <Tag className="h-4 w-4 text-primary" />
                                Category Metadata & Placement
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-xs">
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                <div>
                                    <span className="text-muted-foreground block">Category Code</span>
                                    <span className="font-mono font-semibold text-sm text-foreground">
                                        {category.code}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Sibling Sort Order</span>
                                    <span className="font-mono font-medium text-sm text-foreground">
                                        #{category.sort_order}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Parent Entity</span>
                                    {category.parent ? (
                                        <Link
                                            href={`/categories/${category.parent.id}`}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {category.parent.name} ({category.parent.code})
                                        </Link>
                                    ) : (
                                        <span className="font-mono text-muted-foreground italic">
                                            Top-level Root Category
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="border-t border-border/60 pt-3">
                                <span className="text-muted-foreground block mb-1">Description & Scope Notes</span>
                                <p className="text-foreground leading-relaxed">
                                    {category.description || (
                                        <span className="italic text-muted-foreground">No description provided.</span>
                                    )}
                                </p>
                            </div>

                            <div className="border-t border-border/60 pt-3 flex flex-wrap items-center justify-between text-muted-foreground text-[11px] font-mono">
                                <span>Created: {category.created_at ? new Date(category.created_at).toLocaleString() : '—'}</span>
                                <span>Updated: {category.updated_at ? new Date(category.updated_at).toLocaleString() : '—'}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Quick Metric Statistics */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base flex items-center gap-2">
                                <Package className="h-4 w-4 text-primary" />
                                Taxonomic Metrics
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="p-3 rounded-lg bg-muted/40 border border-border/60 flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-muted-foreground">Direct Subcategories</p>
                                    <p className="text-xl font-bold font-mono text-foreground mt-0.5">
                                        {category.children_count ?? directChildren.length}
                                    </p>
                                </div>
                                <Folder className="h-6 w-6 text-primary/60" />
                            </div>

                            <div className="p-3 rounded-lg bg-muted/40 border border-border/60 flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-muted-foreground">Attached Catalog Items</p>
                                    <p className="text-xl font-bold font-mono text-foreground mt-0.5">
                                        {category.products_count ?? attachedProducts.length}
                                    </p>
                                </div>
                                <Package className="h-6 w-6 text-primary/60" />
                            </div>

                            {category.status === 'INACTIVE' && (
                                <div className="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-700 dark:text-amber-300">
                                    <div className="flex items-center gap-1.5 font-semibold">
                                        <Info className="h-3.5 w-3.5" />
                                        <span>Inactive Category</span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Unavailable for new product assignments. Existing assigned products remain unaffected.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Direct Subcategories List */}
                <Card className="border-border shadow-xs">
                    <CardHeader className="pb-3 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Folder className="h-4 w-4 text-primary" />
                                Direct Subcategories ({directChildren.length})
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Sibling classifications immediately nested under this category.
                            </CardDescription>
                        </div>
                        {can.create && (
                            <Link href="/categories/create">
                                <Button size="sm" variant="outline" className="gap-1.5 text-xs shadow-xs">
                                    <FolderTree className="h-3.5 w-3.5" />
                                    <span>Add Subcategory</span>
                                </Button>
                            </Link>
                        )}
                    </CardHeader>
                    <CardContent className="p-0">
                        {directChildren.length === 0 ? (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                <Folder className="h-6 w-6 mx-auto mb-1.5 opacity-40" />
                                <p>No subcategories directly under this category.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-muted/40 text-muted-foreground font-mono uppercase text-[11px] border-y border-border">
                                        <tr>
                                            <th className="px-4 py-2.5">Code</th>
                                            <th className="px-4 py-2.5">Subcategory Name</th>
                                            <th className="px-4 py-2.5 text-center">Sort Order</th>
                                            <th className="px-4 py-2.5 text-center">Products</th>
                                            <th className="px-4 py-2.5 text-center">Status</th>
                                            <th className="px-4 py-2.5 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {directChildren.map((sub) => (
                                            <tr key={sub.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-2.5 font-mono font-medium">
                                                    <Link
                                                        href={`/categories/${sub.id}`}
                                                        className="text-foreground hover:text-primary transition-colors flex items-center gap-1.5"
                                                    >
                                                        <Folder className="h-3 w-3 text-primary" />
                                                        <span>{sub.code}</span>
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <Link
                                                        href={`/categories/${sub.id}`}
                                                        className="font-medium text-foreground hover:text-primary transition-colors"
                                                    >
                                                        {sub.name}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2.5 text-center font-mono text-muted-foreground">
                                                    #{sub.sort_order}
                                                </td>
                                                <td className="px-4 py-2.5 text-center font-mono">
                                                    {sub.products_count ?? 0}
                                                </td>
                                                <td className="px-4 py-2.5 text-center">
                                                    <Badge
                                                        variant={sub.status === 'ACTIVE' ? 'default' : 'secondary'}
                                                        className="text-[10px] uppercase font-mono px-1.5 py-0"
                                                    >
                                                        {sub.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2.5 text-right">
                                                    <Link href={`/categories/${sub.id}`}>
                                                        <Button variant="ghost" size="sm" className="h-6 text-xs px-2">
                                                            View
                                                        </Button>
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

                {/* Attached Products Table */}
                <Card className="border-border shadow-xs">
                    <CardHeader className="pb-3 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Package className="h-4 w-4 text-primary" />
                                Attached Products ({attachedProducts.length})
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Master catalog items currently classified under this category.
                            </CardDescription>
                        </div>
                        <Link href={`/products?category_id=${category.id}`}>
                            <Button size="sm" variant="outline" className="gap-1.5 text-xs shadow-xs">
                                <span>Filter Product Catalog</span>
                                <ChevronRight className="h-3.5 w-3.5" />
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent className="p-0">
                        {attachedProducts.length === 0 ? (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                <Package className="h-6 w-6 mx-auto mb-1.5 opacity-40" />
                                <p>No products currently assigned to this category.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-muted/40 text-muted-foreground font-mono uppercase text-[11px] border-y border-border">
                                        <tr>
                                            <th className="px-4 py-2.5">SKU</th>
                                            <th className="px-4 py-2.5">Product Title</th>
                                            <th className="px-4 py-2.5 text-center">UOM</th>
                                            <th className="px-4 py-2.5 text-center">Status</th>
                                            <th className="px-4 py-2.5 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {attachedProducts.map((prod) => (
                                            <tr key={prod.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-2.5 font-mono font-medium">
                                                    <Link
                                                        href={`/products/${prod.id}`}
                                                        className="text-foreground hover:text-primary transition-colors flex items-center gap-1.5"
                                                    >
                                                        <Package className="h-3 w-3 text-primary" />
                                                        <span>{prod.sku}</span>
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <Link
                                                        href={`/products/${prod.id}`}
                                                        className="font-medium text-foreground hover:text-primary transition-colors"
                                                    >
                                                        {prod.name}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2.5 text-center font-mono text-muted-foreground">
                                                    {prod.unit}
                                                </td>
                                                <td className="px-4 py-2.5 text-center">
                                                    <Badge
                                                        variant={prod.status === 'ACTIVE' ? 'default' : 'secondary'}
                                                        className="text-[10px] uppercase font-mono px-1.5 py-0"
                                                    >
                                                        {prod.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2.5 text-right">
                                                    <Link href={`/products/${prod.id}`}>
                                                        <Button variant="ghost" size="sm" className="h-6 text-xs px-2">
                                                            View Product
                                                        </Button>
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

            {/* Status Change Modal */}
            {statusModalOpen && (
                <div className="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-card border border-border rounded-lg max-w-md w-full p-6 shadow-lg space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <RefreshCw className="h-4 w-4 text-primary" />
                                Change Category Lifecycle Status
                            </h3>
                            <button
                                type="button"
                                onClick={() => setStatusModalOpen(false)}
                                className="text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleStatusSubmit} className="space-y-4">
                            <div>
                                <Label htmlFor="target_status" className="text-xs font-medium">
                                    Target Status
                                </Label>
                                <select
                                    id="target_status"
                                    value={statusData.status}
                                    onChange={(e) => setStatusData('status', e.target.value as CategoryStatus)}
                                    className="w-full h-9 mt-1 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    {statuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                                {statusErrors.status && (
                                    <p className="text-destructive text-xs mt-1">{statusErrors.status}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="status_reason" className="text-xs font-medium">
                                    Status Transition Reason (Optional)
                                </Label>
                                <textarea
                                    id="status_reason"
                                    rows={3}
                                    value={statusData.reason}
                                    onChange={(e) => setStatusData('reason', e.target.value)}
                                    placeholder="Enter documentation reason for audit log..."
                                    maxLength={500}
                                    className="w-full mt-1 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                {statusErrors.reason && (
                                    <p className="text-destructive text-xs mt-1">{statusErrors.reason}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setStatusModalOpen(false)}
                                    disabled={statusProcessing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={statusProcessing}
                                    className="shadow-xs"
                                >
                                    {statusProcessing ? 'Updating...' : 'Confirm Status Change'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Delete Category Modal */}
            {deleteModalOpen && (
                <div className="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-card border border-destructive/30 rounded-lg max-w-md w-full p-6 shadow-lg space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="h-10 w-10 rounded-full bg-destructive/10 flex items-center justify-center text-destructive shrink-0">
                                <Trash2 className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-semibold text-foreground">
                                    Delete Leaf Category
                                </h3>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Permanently remove {category.name} ({category.code}).
                                </p>
                            </div>
                        </div>

                        {!canDelete ? (
                            <div className="p-3 rounded-lg bg-destructive/10 border border-destructive/20 text-xs text-destructive">
                                <p className="font-semibold flex items-center gap-1.5">
                                    <AlertCircle className="h-4 w-4" />
                                    Deletion Restricted by Domain Invariants
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    This category cannot be deleted because it currently has{' '}
                                    {category.products_count ?? 0} attached product(s) and{' '}
                                    {category.children_count ?? 0} direct subcategory(ies). Categories may only be deleted when completely empty.
                                </p>
                            </div>
                        ) : (
                            <p className="text-xs text-muted-foreground leading-relaxed">
                                Are you sure you want to permanently delete this empty leaf category? This action cannot be undone and will be recorded in the audit trail.
                            </p>
                        )}

                        <div className="flex items-center justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setDeleteModalOpen(false)}
                                disabled={deleteProcessing}
                            >
                                Cancel
                            </Button>
                            {canDelete && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={handleDeleteSubmit}
                                    disabled={deleteProcessing}
                                    className="shadow-xs"
                                >
                                    {deleteProcessing ? 'Deleting...' : 'Delete Permanently'}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
