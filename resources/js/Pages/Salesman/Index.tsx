import React, { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { PaginatedResponse, PageProps } from '@/types';
import {
    Users,
    Search,
    Plus,
    Filter,
    ChevronRight,
    Mail,
    ArrowUpDown,
    CheckCircle2,
    Clock,
    ShieldAlert,
    UserX,
    Building2,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface SalesmanListItem {
    id: number;
    name: string;
    email: string;
    status: string;
    status_label: string;
    can_authenticate: boolean;
    can_be_assigned: boolean;
    assigned_customers_count: number;
    created_at: string;
}

interface StatusOption {
    value: string;
    label: string;
    description: string;
}

interface SalesmanIndexProps {
    salesmen: PaginatedResponse<SalesmanListItem>;
    filters: {
        search?: string;
        status?: string;
        sort?: string;
        direction?: string;
    };
    statuses: StatusOption[];
}

export default function SalesmanIndex({ salesmen, filters, statuses }: SalesmanIndexProps) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'ALL');

    const canCreate = auth?.user?.permissions?.includes('user.create') ||
        auth?.user?.role === 'SUPER_ADMIN' || auth?.user?.role === 'ADMIN';

    // Debounced search handler
    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search || '')) {
                applyFilters({ search });
            }
        }, 350);

        return () => clearTimeout(timeout);
    }, [search]);

    const applyFilters = (newFilters: Partial<typeof filters>) => {
        router.get(
            '/salesmen',
            {
                ...filters,
                ...newFilters,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleSort = (field: string) => {
        const newDirection = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        applyFilters({ sort: field, direction: newDirection });
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
        <AppLayout title="Sales Representatives">
            <Head title="Sales Representatives — Master Data" />

            <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header with Title and Create Action */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/80 pb-6">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="h-9 w-9 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                                <Users className="h-5 w-5" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                    Sales Representatives
                                    <span className="text-xs font-mono px-2 py-0.5 rounded-full bg-secondary text-secondary-foreground font-normal">
                                        {salesmen.total} {salesmen.total === 1 ? 'account' : 'accounts'}
                                    </span>
                                </h1>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Manage field sales representatives, portfolio capacity, and account lifecycle states
                                </p>
                            </div>
                        </div>
                    </div>

                    {canCreate && (
                        <div className="flex items-center gap-3">
                            <Link
                                href="/salesmen-create"
                                className={cn(buttonVariants({ variant: 'default', size: 'default' }), 'gap-2 shadow-xs')}
                            >
                                <Plus className="h-4 w-4" />
                                <span>Provision Salesman</span>
                            </Link>
                        </div>
                    )}
                </div>

                {/* Search & Filter Bar */}
                <Card className="bg-card/50 border-border/70 backdrop-blur-xs">
                    <CardContent className="p-4 space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            {/* Search Input */}
                            <div className="relative md:col-span-2">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Search by salesman name or email address..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 bg-background/50 h-9 text-xs"
                                />
                                {search && (
                                    <button
                                        onClick={() => setSearch('')}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        Clear
                                    </button>
                                )}
                            </div>

                            {/* Reset Filters */}
                            <div className="flex justify-end">
                                {(filters.search || (filters.status && filters.status !== 'ALL')) && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSearch('');
                                            setSelectedStatus('ALL');
                                            router.get('/salesmen');
                                        }}
                                        className="text-xs h-9 text-muted-foreground hover:text-foreground"
                                    >
                                        Reset Filters
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Status Filter Tabs */}
                        <div className="flex items-center gap-2 overflow-x-auto pt-2 border-t border-border/40 text-xs">
                            <span className="text-muted-foreground text-[11px] font-medium uppercase tracking-wider mr-1 flex items-center gap-1">
                                <Filter className="h-3 w-3" /> Status:
                            </span>
                            <button
                                onClick={() => {
                                    setSelectedStatus('ALL');
                                    applyFilters({ status: '' });
                                }}
                                className={`px-2.5 py-1 rounded-md transition-colors ${
                                    selectedStatus === 'ALL' || !filters.status
                                        ? 'bg-primary text-primary-foreground font-medium'
                                        : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
                                }`}
                            >
                                All Accounts
                            </button>
                            {statuses.map((s) => (
                                <button
                                    key={s.value}
                                    onClick={() => {
                                        setSelectedStatus(s.value);
                                        applyFilters({ status: s.value });
                                    }}
                                    className={`px-2.5 py-1 rounded-md transition-colors ${
                                        selectedStatus === s.value || filters.status === s.value
                                            ? 'bg-primary text-primary-foreground font-medium'
                                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
                                    }`}
                                >
                                    {s.label}
                                </button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Salesman Directory Table */}
                <Card className="border-border/70 overflow-hidden shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="border-b border-border bg-muted/30 text-muted-foreground font-medium">
                                    <th
                                        onClick={() => handleSort('name')}
                                        className="py-3 px-4 cursor-pointer hover:text-foreground transition-colors"
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <span>Representative Name</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th
                                        onClick={() => handleSort('email')}
                                        className="py-3 px-4 cursor-pointer hover:text-foreground transition-colors"
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <span>Email Address</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th
                                        onClick={() => handleSort('status')}
                                        className="py-3 px-4 cursor-pointer hover:text-foreground transition-colors"
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <span>Account Status</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th
                                        onClick={() => handleSort('assigned_customers_count')}
                                        className="py-3 px-4 cursor-pointer hover:text-foreground transition-colors text-center"
                                    >
                                        <div className="flex items-center justify-center gap-1.5">
                                            <span>Assigned Portfolio</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th
                                        onClick={() => handleSort('created_at')}
                                        className="py-3 px-4 cursor-pointer hover:text-foreground transition-colors"
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <span>Provisioned</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th className="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {salesmen.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-12 text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center space-y-3">
                                                <div className="h-10 w-10 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
                                                    <Users className="h-5 w-5" />
                                                </div>
                                                <p className="font-medium text-foreground text-sm">No sales representatives found</p>
                                                <p className="text-xs max-w-xs text-muted-foreground">
                                                    {filters.search || filters.status
                                                        ? 'Try refining your search terms or status filters.'
                                                        : 'Provision your first field sales representative to get started.'}
                                                </p>
                                                {canCreate && !filters.search && !filters.status && (
                                                    <Link
                                                        href="/salesmen-create"
                                                        className={cn(buttonVariants({ variant: 'default', size: 'sm' }), 'mt-2')}
                                                    >
                                                        <Plus className="h-3.5 w-3.5 mr-1" />
                                                        Provision Salesman
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    salesmen.data.map((salesman) => (
                                        <tr
                                            key={salesman.id}
                                            className="hover:bg-muted/40 transition-colors group"
                                        >
                                            <td className="py-3 px-4 font-medium text-foreground">
                                                <Link
                                                    href={`/salesmen/${salesman.id}`}
                                                    className="hover:underline flex items-center gap-2"
                                                >
                                                    <div className="h-7 w-7 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-semibold shrink-0">
                                                        {salesman.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    <span>{salesman.name}</span>
                                                </Link>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-muted-foreground">
                                                <div className="flex items-center gap-1.5">
                                                    <Mail className="h-3 w-3 text-muted-foreground/60" />
                                                    <span>{salesman.email}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                {getStatusBadge(salesman.status)}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <Badge
                                                    variant="secondary"
                                                    className="font-mono text-xs px-2 py-0.5 inline-flex items-center gap-1"
                                                >
                                                    <Building2 className="h-3 w-3 text-primary" />
                                                    <span>{salesman.assigned_customers_count} accounts</span>
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-muted-foreground font-mono text-[11px]">
                                                {new Date(salesman.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Link
                                                        href={`/salesmen/${salesman.id}`}
                                                        className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'h-7 px-2 text-xs')}
                                                    >
                                                        <span>Manage</span>
                                                        <ChevronRight className="h-3.5 w-3.5 ml-1" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Controls */}
                    {salesmen.total > salesmen.per_page && (
                        <div className="p-4 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                            <div>
                                Showing <span className="font-medium text-foreground">{salesmen.from}</span> to{' '}
                                <span className="font-medium text-foreground">{salesmen.to}</span> of{' '}
                                <span className="font-medium text-foreground">{salesmen.total}</span> representatives
                            </div>
                            <div className="flex items-center gap-1">
                                {salesmen.links.map((link, idx) => (
                                    link.url ? (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            preserveScroll
                                            preserveState
                                            className={cn(
                                                buttonVariants({
                                                    variant: link.active ? 'default' : 'outline',
                                                    size: 'sm',
                                                }),
                                                'h-7 min-w-7 px-2 text-xs'
                                            )}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <button
                                            key={idx}
                                            disabled
                                            className={cn(
                                                buttonVariants({ variant: 'outline', size: 'sm' }),
                                                'h-7 min-w-7 px-2 text-xs opacity-50 cursor-not-allowed'
                                            )}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
