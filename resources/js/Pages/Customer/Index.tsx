import React, { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { Customer, CustomerStatusOption, PaginatedResponse, PageProps } from '@/types';
import {
    Users,
    Search,
    Plus,
    Filter,
    ChevronRight,
    MapPin,
    Phone,
    Mail,
    CreditCard,
    ArrowUpDown,
    CheckCircle2,
    Clock,
    AlertCircle,
    Building2,
    SlidersHorizontal,
} from 'lucide-react';

interface CustomerIndexProps {
    customers: PaginatedResponse<Customer>;
    filters: {
        search: string;
        status: string;
        sort_by: string;
        sort_order: string;
    };
    statuses: CustomerStatusOption[];
}

export default function CustomerIndex({ customers, filters, statuses }: CustomerIndexProps) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'ALL');

    const canCreateCustomer = auth?.user?.permissions?.includes('customer.create') ||
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
            '/customers',
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
        const newOrder = filters.sort_by === field && filters.sort_order === 'asc' ? 'desc' : 'asc';
        applyFilters({ sort_by: field, sort_order: newOrder });
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
            case 'ON_HOLD':
                return (
                    <Badge variant="outline" className="bg-amber-950/40 text-amber-400 border-amber-800/60 text-xs font-mono">
                        <Clock className="h-3 w-3 mr-1" />
                        On Hold
                    </Badge>
                );
            case 'INACTIVE':
            default:
                return (
                    <Badge variant="outline" className="bg-muted text-muted-foreground border-border text-xs font-mono">
                        <AlertCircle className="h-3 w-3 mr-1" />
                        Inactive
                    </Badge>
                );
        }
    };

    return (
        <AppLayout title="Customer Management">
            <Head title="Customer Directory" />

            <div className="space-y-6">
                {/* Header with Title and Primary Action */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-border">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Customer Accounts
                            </h1>
                            <Badge variant="outline" className="text-xs font-mono">
                                {customers.total} Total
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Directory of authorized wholesale customer accounts, physical locations, credit terms, and status.
                        </p>
                    </div>

                    {canCreateCustomer && (
                        <Link href="/customers-create">
                            <Button className="w-full sm:w-auto font-medium">
                                <Plus className="h-4 w-4 mr-2" />
                                Add Customer
                            </Button>
                        </Link>
                    )}
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col sm:flex-row items-center gap-3">
                    <div className="relative flex-1 w-full">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Search by account name, code, contact person, email, or phone..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9 w-full bg-card"
                        />
                    </div>

                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <div className="flex items-center gap-1.5 px-3 py-2 border border-border bg-card rounded-md text-xs w-full sm:w-auto">
                            <Filter className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <select
                                value={selectedStatus}
                                onChange={(e) => {
                                    setSelectedStatus(e.target.value);
                                    applyFilters({ status: e.target.value });
                                }}
                                className="bg-transparent text-foreground border-none text-xs focus:outline-none cursor-pointer w-full"
                                aria-label="Filter by customer status"
                            >
                                <option value="ALL">All Statuses</option>
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Desktop View: Dense Data Table */}
                <div className="hidden md:block rounded-lg border border-border bg-card overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/40 font-medium text-muted-foreground uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th
                                        scope="col"
                                        className="py-3 px-4 cursor-pointer select-none hover:text-foreground"
                                        onClick={() => handleSort('code')}
                                    >
                                        <div className="flex items-center gap-1">
                                            <span>Code</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-3 px-4 cursor-pointer select-none hover:text-foreground"
                                        onClick={() => handleSort('name')}
                                    >
                                        <div className="flex items-center gap-1">
                                            <span>Account Name</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th scope="col" className="py-3 px-4">Contact Person</th>
                                    <th scope="col" className="py-3 px-4">Location</th>
                                    <th
                                        scope="col"
                                        className="py-3 px-4 text-right cursor-pointer select-none hover:text-foreground"
                                        onClick={() => handleSort('credit_limit')}
                                    >
                                        <div className="flex items-center justify-end gap-1">
                                            <span>Credit Limit</span>
                                            <ArrowUpDown className="h-3 w-3" />
                                        </div>
                                    </th>
                                    <th scope="col" className="py-3 px-4">Terms</th>
                                    <th scope="col" className="py-3 px-4 text-center">Status</th>
                                    <th scope="col" className="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {customers.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center space-y-2">
                                                <Building2 className="h-8 w-8 text-muted-foreground/50" />
                                                <p className="font-medium text-sm text-foreground">No customers found</p>
                                                <p className="text-xs text-muted-foreground max-w-sm">
                                                    No customer records match your filter criteria or no accounts have been registered yet.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    customers.data.map((cust) => (
                                        <tr key={cust.id} className="hover:bg-muted/30 transition-colors">
                                            <td className="py-3.5 px-4 font-mono font-medium text-primary">
                                                <Link href={`/customers/${cust.id}`} className="hover:underline">
                                                    {cust.code}
                                                </Link>
                                            </td>
                                            <td className="py-3.5 px-4 font-semibold text-foreground">
                                                <Link href={`/customers/${cust.id}`} className="hover:underline block truncate max-w-[200px]">
                                                    {cust.name}
                                                </Link>
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <div className="space-y-0.5">
                                                    <div className="text-foreground">{cust.contact_name}</div>
                                                    <div className="text-[11px] text-muted-foreground font-mono">{cust.phone}</div>
                                                </div>
                                            </td>
                                            <td className="py-3.5 px-4 text-muted-foreground">
                                                <span>{cust.billing_city}, {cust.billing_state}</span>
                                            </td>
                                            <td className="py-3.5 px-4 text-right font-mono font-medium text-foreground">
                                                {formatCurrency(cust.credit_limit)}
                                            </td>
                                            <td className="py-3.5 px-4 font-mono text-[11px] text-muted-foreground">
                                                {cust.payment_terms}
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                {getStatusBadge(cust.status)}
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <Link
                                                    href={`/customers/${cust.id}`}
                                                    className="inline-flex items-center text-xs font-medium text-primary hover:underline"
                                                >
                                                    View Details
                                                    <ChevronRight className="h-3.5 w-3.5 ml-0.5" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Mobile / Tablet View: Touch Cards */}
                <div className="md:hidden space-y-3">
                    {customers.data.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center text-muted-foreground space-y-2">
                                <Building2 className="h-8 w-8 mx-auto text-muted-foreground/50" />
                                <p className="font-medium text-sm text-foreground">No customers found</p>
                                <p className="text-xs text-muted-foreground">
                                    No customer records match your filter criteria.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        customers.data.map((cust) => (
                            <Link
                                key={cust.id}
                                href={`/customers/${cust.id}`}
                                className="block"
                            >
                                <Card className="hover:border-primary/50 transition-colors">
                                    <CardContent className="p-4 space-y-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="space-y-0.5 min-w-0">
                                                <span className="text-[11px] font-mono font-semibold text-primary">
                                                    {cust.code}
                                                </span>
                                                <h2 className="font-semibold text-sm text-foreground truncate">
                                                    {cust.name}
                                                </h2>
                                                <p className="text-xs text-muted-foreground">
                                                    {cust.contact_name}
                                                </p>
                                            </div>
                                            {getStatusBadge(cust.status)}
                                        </div>

                                        <div className="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-border/60 text-muted-foreground">
                                            <div className="flex items-center gap-1.5 truncate">
                                                <MapPin className="h-3.5 w-3.5 shrink-0" />
                                                <span className="truncate">{cust.billing_city}, {cust.billing_state}</span>
                                            </div>
                                            <div className="flex items-center gap-1.5 truncate">
                                                <Phone className="h-3.5 w-3.5 shrink-0" />
                                                <span className="truncate">{cust.phone}</span>
                                            </div>
                                            <div className="flex items-center gap-1.5 truncate font-mono">
                                                <CreditCard className="h-3.5 w-3.5 shrink-0" />
                                                <span>Limit: {formatCurrency(cust.credit_limit)}</span>
                                            </div>
                                            <div className="flex items-center gap-1.5 truncate font-mono">
                                                <span>Terms: {cust.payment_terms}</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))
                    )}
                </div>

                {/* Pagination Controls */}
                {customers.total > customers.per_page && (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-border text-xs text-muted-foreground">
                        <div>
                            Showing <span className="font-medium text-foreground">{customers.from || 0}</span> to{' '}
                            <span className="font-medium text-foreground">{customers.to || 0}</span> of{' '}
                            <span className="font-medium text-foreground">{customers.total}</span> accounts
                        </div>

                        <div className="flex items-center gap-1">
                            {customers.links.map((link, idx) => (
                                <button
                                    key={idx}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                    disabled={!link.url || link.active}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground font-semibold'
                                            : link.url
                                            ? 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                            : 'text-muted-foreground/40 cursor-not-allowed'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
