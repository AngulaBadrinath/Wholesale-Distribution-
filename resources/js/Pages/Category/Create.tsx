import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { CategorySelectOption, CategoryStatusOption, CategoryStatus } from '@/types';
import {
    FolderTree,
    ArrowLeft,
    CheckCircle2,
    Tag,
    Layers,
    AlignLeft,
    Hash,
    HelpCircle,
} from 'lucide-react';

interface CategoryCreateProps {
    suggestedCode: string;
    selectableParents: CategorySelectOption[];
    statuses: CategoryStatusOption[];
}

interface CategoryCreateFormData {
    name: string;
    code: string;
    parent_id: string;
    status: CategoryStatus;
    sort_order: number;
    description: string;
}

export default function CategoryCreate({
    suggestedCode,
    selectableParents,
    statuses,
}: CategoryCreateProps) {
    const { data, setData, post, processing, errors } = useForm<CategoryCreateFormData>({
        name: '',
        code: suggestedCode || '',
        parent_id: '',
        status: 'ACTIVE',
        sort_order: 0,
        description: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/categories');
    };

    return (
        <AppLayout title="Create Product Category">
            <Head title="Create Product Category" />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Navigation Header */}
                <div className="flex items-center justify-between">
                    <Link
                        href="/categories"
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Categories Directory
                    </Link>
                </div>

                {/* Title */}
                <div>
                    <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground uppercase tracking-wider mb-1">
                        <FolderTree className="h-3.5 w-3.5 text-primary" />
                        <span>Master Classification / Create</span>
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Create New Category
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Establish a top-level root category or nested subcategory taxonomy for organizing catalog items.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Category Details Card */}
                    <Card className="border-border shadow-xs">
                        <CardHeader className="pb-4">
                            <CardTitle className="text-base flex items-center gap-2">
                                <Tag className="h-4 w-4 text-primary" />
                                Category Identification & Placement
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Unique system identifier, category title, hierarchical parentage, and sibling display sequence.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* Category Code */}
                                <div>
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="code" className="text-xs font-medium">
                                            Category Code <span className="text-destructive">*</span>
                                        </Label>
                                        <span className="text-[11px] font-mono text-muted-foreground">Auto-generated</span>
                                    </div>
                                    <Input
                                        id="code"
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                        placeholder="e.g. CAT-00001"
                                        maxLength={32}
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
                                        onChange={(e) => setData('status', e.target.value as CategoryStatus)}
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

                            {/* Category Name */}
                            <div>
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="name" className="text-xs font-medium">
                                        Category Name <span className="text-destructive">*</span>
                                    </Label>
                                    <span className="text-[11px] text-muted-foreground">Unique within sibling level</span>
                                </div>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Fasteners, Hand Tools, Electrical Supplies"
                                    maxLength={255}
                                    className="mt-1"
                                />
                                {errors.name && (
                                    <p className="text-destructive text-xs mt-1">{errors.name}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* Parent Category */}
                                <div>
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="parent_id" className="text-xs font-medium">
                                            Parent Hierarchy
                                        </Label>
                                        <span className="text-[11px] text-muted-foreground">Optional</span>
                                    </div>
                                    <select
                                        id="parent_id"
                                        value={data.parent_id}
                                        onChange={(e) => setData('parent_id', e.target.value)}
                                        className="w-full h-9 mt-1 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-sans"
                                    >
                                        <option value="">-- Top-Level Root Category (None) --</option>
                                        {selectableParents.map((p) => (
                                            <option key={p.id} value={p.id.toString()}>
                                                {p.hierarchy_path || p.name} ({p.code})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.parent_id && (
                                        <p className="text-destructive text-xs mt-1">{errors.parent_id}</p>
                                    )}
                                </div>

                                {/* Sort Order */}
                                <div>
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="sort_order" className="text-xs font-medium">
                                            Sibling Sort Order
                                        </Label>
                                        <span className="text-[11px] font-mono text-muted-foreground">Default 0</span>
                                    </div>
                                    <Input
                                        id="sort_order"
                                        type="number"
                                        value={data.sort_order}
                                        onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                        min={0}
                                        max={99999}
                                        className="font-mono mt-1"
                                    />
                                    {errors.sort_order && (
                                        <p className="text-destructive text-xs mt-1">{errors.sort_order}</p>
                                    )}
                                </div>
                            </div>

                            {/* Description */}
                            <div>
                                <Label htmlFor="description" className="text-xs font-medium">
                                    Description & Taxonomy Notes
                                </Label>
                                <textarea
                                    id="description"
                                    rows={3}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Optional category description or classification scope notes..."
                                    maxLength={1000}
                                    className="w-full mt-1 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                {errors.description && (
                                    <p className="text-destructive text-xs mt-1">{errors.description}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link href="/categories">
                            <Button variant="outline" type="button" disabled={processing}>
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing} className="gap-2 shadow-xs">
                            <CheckCircle2 className="h-4 w-4" />
                            <span>{processing ? 'Saving...' : 'Create Category'}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
