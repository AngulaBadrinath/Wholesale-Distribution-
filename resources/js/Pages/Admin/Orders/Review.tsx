import React from 'react';
import { Head } from '@inertiajs/react';
import { FileText, MessageSquare, History, ShieldCheck, AlertOctagon } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import OrderTimeline from '@/Pages/Salesman/Orders/Partials/OrderTimeline';
import { AdminOrderReviewData } from '@/types/order';
import ReviewActionHeader from './Partials/ReviewActionHeader';
import ReviewAlerts from './Partials/ReviewAlerts';
import ReviewCustomerCard from './Partials/ReviewCustomerCard';
import ReviewItemsTable from './Partials/ReviewItemsTable';
import ReviewItemsCards from './Partials/ReviewItemsCards';
import ReviewFinancialSummary from './Partials/ReviewFinancialSummary';

interface AdminOrderReviewPageProps {
    reviewData: AdminOrderReviewData;
    backUrl: string;
    backLabel: string;
}

export default function Review({
    reviewData,
    backUrl = '/admin/orders?queue=new',
    backLabel = 'Back to New Orders Queue',
}: AdminOrderReviewPageProps) {
    const { order, customer, salesman, items, tax_breakdown, warnings, has_blockers, timeline, can } = reviewData;

    return (
        <AppLayout title={`Review Order ${order.order_number}`}>
            <Head title={`Review Order ${order.order_number}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
                {/* Header with Navigation & 5 Independent Status Badges */}
                <ReviewActionHeader
                    order={order}
                    hasBlockers={has_blockers}
                    can={can}
                    backUrl={backUrl}
                    backLabel={backLabel}
                />

                {/* Review Alerts & Blockers Banner */}
                {warnings && warnings.length > 0 && (
                    <ReviewAlerts warnings={warnings} />
                )}

                {/* Main 2-Column Responsive Workspace */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    {/* Primary Left Column: Items, Notes, Timeline (8 cols on lg) */}
                    <div className="lg:col-span-8 space-y-6">
                        {/* Desktop Line Items Table */}
                        <div className="hidden md:block">
                            <ReviewItemsTable items={items} currency={order.currency} />
                        </div>

                        {/* Mobile Line Items Cards */}
                        <div className="block md:hidden">
                            <ReviewItemsCards items={items} currency={order.currency} />
                        </div>

                        {/* Customer & Order Notes */}
                        {order.notes && (
                            <Card className="shadow-xs border">
                                <CardHeader className="pb-3 border-b bg-muted/20">
                                    <div className="flex items-center gap-2">
                                        <MessageSquare className="h-4 w-4 text-primary" />
                                        <CardTitle className="text-sm font-bold text-foreground">
                                            Order Notes & Special Instructions
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4 text-xs">
                                    <div className="p-3 rounded-md bg-muted/30 border whitespace-pre-line text-foreground leading-relaxed">
                                        {order.notes}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Authentic Milestone Timeline */}
                        <Card className="shadow-xs border">
                            <CardHeader className="pb-3 border-b bg-muted/20">
                                <div className="flex items-center gap-2">
                                    <History className="h-4 w-4 text-primary" />
                                    <CardTitle className="text-sm font-bold text-foreground">
                                        Order Lifecycle Milestones
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <OrderTimeline timeline={timeline} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Secondary Right Sidebar: Customer, Financials, Action Readiness (4 cols on lg) */}
                    <div className="lg:col-span-4 space-y-6">
                        {/* Action Readiness Deck (FEAT-ORD-011 Evaluation Deck) */}
                        <Card className="shadow-xs border border-primary/20 bg-card">
                            <CardHeader className="pb-3 border-b bg-primary/5">
                                <div className="flex items-center gap-2">
                                    <ShieldCheck className="h-4 w-4 text-primary" />
                                    <CardTitle className="text-sm font-bold text-foreground">
                                        Review Evaluation Deck
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4 space-y-3 text-xs">
                                <div className="space-y-1.5">
                                    <span className="text-muted-foreground block text-[11px] uppercase tracking-wider font-semibold">
                                        Approval Readiness Status
                                    </span>
                                    {has_blockers ? (
                                        <div className="flex items-center gap-2 text-destructive font-bold text-sm">
                                            <AlertOctagon className="h-4 w-4 shrink-0" />
                                            <span>Blocked by Operational Conditions</span>
                                        </div>
                                    ) : (
                                        <div className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold text-sm">
                                            <ShieldCheck className="h-4 w-4 shrink-0" />
                                            <span>Ready for Operational Decision</span>
                                        </div>
                                    )}
                                </div>

                                <div className="p-3 bg-muted/40 rounded-md border text-[11px] text-muted-foreground space-y-1 leading-relaxed">
                                    <p className="font-semibold text-foreground">
                                        Decision Workflow Notice:
                                    </p>
                                    <p>
                                        This workspace is scoped strictly to operational review and verification. Order approval, rejection with mandatory reason capture, and inventory allocations are executed in the downstream <strong>FEAT-ORD-012</strong> workflow.
                                    </p>
                                </div>

                                <div className="pt-2 flex items-center justify-between text-[11px] border-t">
                                    <span className="text-muted-foreground">Your Role Capability:</span>
                                    <span className="font-semibold font-mono text-foreground">
                                        {can.approve ? 'Approve & Reject Authorized' : 'Read-Only Verification'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Customer Commercial Account Context */}
                        <ReviewCustomerCard customer={customer} salesman={salesman} />

                        {/* Financial Breakdown & Totals */}
                        <ReviewFinancialSummary order={order} taxBreakdown={tax_breakdown} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
