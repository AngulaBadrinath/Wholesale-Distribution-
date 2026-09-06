import React, { useState } from 'react';
import { usePage, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import { 
    Menu, 
    X, 
    Layers, 
    Activity, 
    ShieldCheck, 
    Terminal, 
    Settings,
    Building2,
    Users,
    KeyRound,
    Package,
    FolderTree,
    Receipt,
    Shield,
    FileText
} from 'lucide-react';

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { appName, identity, company, auth } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const displayName = identity?.name || appName || 'Wholesale Distribution Management System';
    const displayCompany = company?.display_name || identity?.company_name || 'Wholesale Distribution';
    const initials = displayName.split(' ').map((w) => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || 'WD';

    const hasRoleManage = auth?.user?.permissions?.includes('role.manage') || auth?.user?.role === 'SUPER_ADMIN' || auth?.user?.role === 'ADMIN';
    const hasCustomerView = auth?.user?.permissions?.includes('customer.view') || ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT', 'SALESMAN'].includes(auth?.user?.role || '');
    const hasUserView = auth?.user?.permissions?.includes('user.view') || ['SUPER_ADMIN', 'ADMIN'].includes(auth?.user?.role || '');
    const hasProductView = auth?.user?.permissions?.includes('product.view') || ['SUPER_ADMIN', 'ADMIN', 'SALESMAN', 'WAREHOUSE_MANAGER'].includes(auth?.user?.role || '');
    const hasTaxManage = auth?.user?.permissions?.includes('product.tax.update') || ['SUPER_ADMIN', 'ADMIN'].includes(auth?.user?.role || '');
    const hasOrderCreate = auth?.user?.permissions?.includes('order.create') || ['SUPER_ADMIN', 'ADMIN', 'SALESMAN'].includes(auth?.user?.role || '');
    const hasOrderView = auth?.user?.permissions?.includes('order.view') || ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT', 'SALESMAN'].includes(auth?.user?.role || '');
    const hasAdminOrderQueue = (hasOrderView && ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT'].includes(auth?.user?.role || '')) || false;
    const hasAdjustReview = auth?.user?.permissions?.includes('order.adjust.review') || ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT'].includes(auth?.user?.role || '');
    const hasPaymentView = auth?.user?.permissions?.includes('payment.view') || ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT', 'SALESMAN'].includes(auth?.user?.role || '');

    return (
        <div className="min-h-screen bg-background text-foreground flex flex-col antialiased">
            {/* Mobile Sidebar Overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/40 backdrop-blur-xs lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                    aria-hidden="true"
                />
            )}

            <div className="flex flex-1 w-full">
                {/* Sidebar Navigation */}
                <aside
                    className={`fixed inset-y-0 left-0 z-50 w-64 border-r border-border bg-card flex flex-col transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 ${
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                    }`}
                >
                    {/* Brand header */}
                    <div className="h-16 flex items-center justify-between px-6 border-b border-border">
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground font-semibold text-sm tracking-tight shadow-xs">
                                {initials}
                            </div>
                            <div className="flex flex-col min-w-0">
                                <span className="font-semibold text-sm leading-tight truncate">
                                    {displayName}
                                </span>
                                <span className="text-[11px] text-muted-foreground font-mono truncate">
                                    {displayCompany}
                                </span>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(false)}
                            className="lg:hidden rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="Close navigation"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Navigation Items */}
                    <div className="flex-1 overflow-y-auto px-3 py-4">
                        {(hasOrderView || hasOrderCreate) && (
                            <>
                                <div className="mb-2 px-3 text-[11px] font-medium tracking-wider uppercase text-muted-foreground">
                                    Orders & Sales
                                </div>
                                <nav className="space-y-1 mb-6">
                                    {hasAdminOrderQueue && (
                                        <Link
                                            href="/admin/orders"
                                            className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <Layers className="h-4 w-4 text-primary" />
                                            <span>Order Queue</span>
                                        </Link>
                                    )}
                                    {hasAdjustReview && (
                                        <Link
                                            href="/admin/adjustments"
                                            className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <FileText className="h-4 w-4 text-primary" />
                                            <span>Adjustment Queue</span>
                                        </Link>
                                    )}
                                    {hasPaymentView && (
                                        <Link
                                            href="/admin/payments"
                                            className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <Receipt className="h-4 w-4 text-primary" />
                                            <span>Payments & Collections</span>
                                        </Link>
                                    )}
                                    {hasOrderView && (
                                        <Link
                                            href="/salesman/orders"
                                            className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <Receipt className="h-4 w-4 text-primary" />
                                            <span>Order History</span>
                                        </Link>
                                    )}
                                    {hasOrderCreate && (
                                        <>
                                            <Link
                                                href="/salesman/orders/create"
                                                className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                            >
                                                <Package className="h-4 w-4 text-primary" />
                                                <span>New Sales Order</span>
                                            </Link>
                                            <Link
                                                href="/salesman/orders/drafts"
                                                className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                            >
                                                <Receipt className="h-4 w-4 text-muted-foreground" />
                                                <span>Draft Orders</span>
                                            </Link>
                                        </>
                                    )}
                                </nav>
                            </>
                        )}
                        {(hasCustomerView || hasUserView || hasProductView) && (
                            <>
                                <div className="mb-2 px-3 text-[11px] font-medium tracking-wider uppercase text-muted-foreground">
                                    Commercial & Products
                                </div>
                                <nav className="space-y-1 mb-6">
                                    {hasProductView && (
                                        <>
                                            <Link
                                                href="/products"
                                                className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                            >
                                                <Package className="h-4 w-4 text-primary" />
                                                <span>Product Catalog</span>
                                            </Link>
                                            <Link
                                                href="/categories"
                                                className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                            >
                                                <FolderTree className="h-4 w-4 text-primary" />
                                                <span>Product Categories</span>
                                            </Link>
                                        </>
                                    )}
                                    {hasTaxManage && (
                                        <Link
                                            href="/tax-profiles"
                                            className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <Receipt className="h-4 w-4 text-primary" />
                                            <span>Tax Profiles</span>
                                        </Link>
                                    )}
                                    {hasCustomerView && (
                                        <Link
                                            href="/customers"
                                            className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <Building2 className="h-4 w-4 text-primary" />
                                            <span>Customer Accounts</span>
                                        </Link>
                                    )}
                                    {hasUserView && (
                                        <Link
                                            href="/salesmen"
                                            className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <Users className="h-4 w-4 text-primary" />
                                            <span>Sales Representatives</span>
                                        </Link>
                                    )}
                                </nav>
                            </>
                        )}
                        {hasRoleManage && (
                            <>
                                <div className="mb-2 px-3 text-[11px] font-medium tracking-wider uppercase text-muted-foreground">
                                    System & Admin
                                </div>
                                <nav className="space-y-1 mb-6">
                                    <Link
                                        href="/system/company"
                                        className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    >
                                        <Building2 className="h-4 w-4 text-primary" />
                                        <span>Company Settings</span>
                                    </Link>
                                    <Link
                                        href="/security/roles"
                                        className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    >
                                        <Users className="h-4 w-4 text-primary" />
                                        <span>Role Management</span>
                                    </Link>
                                </nav>
                            </>
                        )}

                        {auth?.user && (
                            <>
                                <div className="mb-2 px-3 text-[11px] font-medium tracking-wider uppercase text-muted-foreground">
                                    User Security
                                </div>
                                <nav className="space-y-1 mb-6">
                                    <Link
                                        href="/security/mfa"
                                        className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    >
                                        <Shield className="h-4 w-4" />
                                        <span>Two-Factor Auth</span>
                                    </Link>
                                    <Link
                                        href="/security/sessions"
                                        className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    >
                                        <KeyRound className="h-4 w-4" />
                                        <span>Active Sessions</span>
                                    </Link>
                                </nav>
                            </>
                        )}

                        <div className="mb-2 px-3 text-[11px] font-medium tracking-wider uppercase text-muted-foreground">
                            Platform Foundation
                        </div>
                        <nav className="space-y-1">
                            <a
                                href="/"
                                className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md bg-secondary text-secondary-foreground"
                            >
                                <Layers className="h-4 w-4 text-primary" />
                                <span>Architecture Overview</span>
                            </a>
                            <a
                                href="/health"
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                            >
                                <Activity className="h-4 w-4" />
                                <span>Health API</span>
                            </a>
                        </nav>

                        <div className="mt-6 mb-2 px-3 text-[11px] font-medium tracking-wider uppercase text-muted-foreground">
                            Infrastructure Specs
                        </div>
                        <nav className="space-y-1 text-xs">
                            <div className="flex items-center gap-3 px-3 py-2 text-muted-foreground">
                                <Terminal className="h-4 w-4" />
                                <span>Laravel 13 + PHP 8.5</span>
                            </div>
                            <div className="flex items-center gap-3 px-3 py-2 text-muted-foreground">
                                <ShieldCheck className="h-4 w-4" />
                                <span>PostgreSQL 18 + Redis</span>
                            </div>
                            <div className="flex items-center gap-3 px-3 py-2 text-muted-foreground">
                                <Settings className="h-4 w-4" />
                                <span>React 19 + Inertia 3</span>
                            </div>
                        </nav>
                    </div>

                    {/* Sidebar Footer */}
                    <div className="p-4 border-t border-border bg-muted/30 text-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground font-mono text-[11px]">PHASE 00</span>
                            <span className="inline-flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400 text-[11px]">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                Healthy
                            </span>
                        </div>
                    </div>
                </aside>

                {/* Main Content Area */}
                <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
                    {/* Top App Header */}
                    <header className="h-16 border-b border-border bg-card/60 backdrop-blur-md px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setSidebarOpen(true)}
                                className="lg:hidden rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                aria-label="Open navigation"
                            >
                                <Menu className="h-5 w-5" />
                            </button>
                            <h1 className="text-sm font-semibold text-foreground tracking-tight">
                                {title || 'Foundation Application Shell'}
                            </h1>
                        </div>

                        <div className="flex items-center gap-3 text-xs">
                            <div className="hidden sm:flex items-center gap-2 px-2.5 py-1 rounded-full border border-border bg-secondary/50 font-mono text-[11px] text-muted-foreground">
                                <span>ENV:</span>
                                <span className="font-semibold text-foreground">local</span>
                            </div>
                        </div>
                    </header>

                    {/* Page Content */}
                    <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                        <div className="max-w-7xl mx-auto w-full">
                            {children}
                        </div>
                    </main>

                    {/* Subdued Footer */}
                    <footer className="border-t border-border py-3 px-4 sm:px-6 text-xs text-muted-foreground flex flex-col sm:flex-row items-center justify-between gap-2">
                        <div className="font-mono text-[11px]">
                            {identity?.footer_text || displayName} &bull; Phase 00 Infrastructure
                        </div>
                        <div className="flex items-center gap-4 text-[11px]">
                            <span>Tailwind CSS 4</span>
                            <span>&bull;</span>
                            <span>shadcn/ui Foundation</span>
                            <span>&bull;</span>
                            <span>Inertia 3</span>
                        </div>
                    </footer>
                </div>
            </div>
        </div>
    );
}
