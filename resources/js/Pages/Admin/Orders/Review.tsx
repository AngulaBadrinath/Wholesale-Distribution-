import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { MessageSquare, History, ShieldCheck, AlertOctagon } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import OrderTimeline from '@/Pages/Salesman/Orders/Partials/OrderTimeline';
import { AdminOrderReviewData } from '@/types/order';
import ReviewActionHeader from './Partials/ReviewActionHeader';
import ReviewAlerts from './Partials/ReviewAlerts';
import ReviewCustomerCard from './Partials/ReviewCustomerCard';
import ReviewItemsTable from './Partials/ReviewItemsTable';
import ReviewItemsCards from './Partials/ReviewItemsCards';
import ReviewFinancialSummary from './Partials/ReviewFinancialSummary';
import ApproveOrderModal from './Partials/ApproveOrderModal';
import RejectOrderModal from './Partials/RejectOrderModal';

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

    const [isApproveOpen, setIsApproveOpen] = useState(false);
    const [isRejectOpen, setIsRejectOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [rejectionError, setRejectionError] = useState<string | undefined>(undefined);

    const isReviewable = order.status === 'SUBMITTED' || order.status === 'PENDING_APPROVAL';

    const handleApprove = () => {
        setIsSubmitting(true);
        router.post(
            `/admin/orders/${order.id}/approve`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsApproveOpen(false);
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleReject = (reason: string) => {
        setIsSubmitting(true);
        setRejectionError(undefined);
        router.post(
            `/admin/orders/${order.id}/reject`,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsRejectOpen(false);
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    setIsSubmitting(false);
                    if (errors.reason) {
                        setRejectionError(errors.reason);
                    } else if (errors.order) {
                        setRejectionError(errors.order);
                    } else if (errors.message) {
                        setRejectionError(errors.message);
                    }
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

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
                        {/* Action Readiness Deck */}
                        <Card className="shadow-xs border border-primary/20 bg-card">
                            <CardHeader className="pb-3 border-b bg-primary/5">
                                <div className="flex items-center gap-2">
                                    <ShieldCheck className="h-4 w-4 text-primary" />
                                    <CardTitle className="text-sm font-bold text-foreground">
                                        Review Evaluation Deck
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4 space-y-3.5 text-xs">
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

                                {/* Operational Action Controls */}
                                {can.approve || can.reject ? (
                                    isReviewable ? (
                                        <div className="space-y-2 pt-1">
                                            {can.approve && (
                                                <Button
                                                    type="button"
                                                    className="w-full h-9 gap-2 font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                                                    disabled={has_blockers || isSubmitting}
                                                    onClick={() => setIsApproveOpen(true)}
                                                >
                                                    <ShieldCheck className="h-4 w-4" />
                                                    <span>Approve Order</span>
                                                </Button>
                                            )}
                                            {can.reject && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="w-full h-9 gap-2 font-semibold text-destructive hover:bg-destructive/10 border-destructive/30"
                                                    disabled={isSubmitting}
                                                    onClick={() => setIsRejectOpen(true)}
                                                >
                                                    <AlertOctagon className="h-4 w-4" />
                                                    <span>Reject Order</span>
                                                </Button>
                                            )}
                                            {has_blockers && (
                                                <p className="text-[11px] text-destructive text-center leading-tight pt-0.5">
                                                    Approval is blocked by active operational conditions above.
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="p-3 bg-muted/40 rounded-md border text-[11px] text-muted-foreground space-y-1 leading-relaxed">
                                            <p className="font-semibold text-foreground">
                                                Finalized Order State:
                                            </p>
                                            <p>
                                                This order is finalized as <strong>{order.status_label}</strong> and cannot be modified.
                                            </p>
                                        </div>
                                    )
                                ) : (
                                    <div className="p-3 bg-muted/40 rounded-md border text-[11px] text-muted-foreground space-y-1 leading-relaxed">
                                        <p className="font-semibold text-foreground">
                                            Read-Only Verification Mode:
                                        </p>
                                        <p>
                                            Your role is permitted to review commercial and customer details in read-only mode. Operational decisions are restricted to administrators.
                                        </p>
                                    </div>
                                )}

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

            {/* Modal Dialogs */}
            <ApproveOrderModal
                isOpen={isApproveOpen}
                onClose={() => setIsApproveOpen(false)}
                onConfirm={handleApprove}
                isProcessing={isSubmitting}
                orderNumber={order.order_number}
                grandTotal={order.grand_total}
                currency={order.currency}
                warnings={warnings}
            />

            <RejectOrderModal
                isOpen={isRejectOpen}
                onClose={() => setIsRejectOpen(false)}
                onConfirm={handleReject}
                isProcessing={isSubmitting}
                orderNumber={order.order_number}
                errorMessage={rejectionError}
            />
        </AppLayout>
    );
}
