import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, AlertOctagon, ShieldAlert, ShieldCheck, Clock, FileText } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import OrderStatusBadge from '@/Pages/Salesman/Orders/Partials/OrderStatusBadge';
import { AdminOrderReviewData } from '@/types/order';

interface ReviewActionHeaderProps {
    order: AdminOrderReviewData['order'];
    hasBlockers: boolean;
    can: AdminOrderReviewData['can'];
    backUrl: string;
    backLabel: string;
}

export default function ReviewActionHeader({
    order,
    hasBlockers,
    can,
    backUrl,
    backLabel,
}: ReviewActionHeaderProps) {
    return (
        <div className="space-y-4">
            {/* Top Navigation & Breadcrumb */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-4">
                <div className="flex items-center gap-2">
                    <Link href={backUrl}>
                        <Button variant="ghost" size="sm" className="gap-1.5 text-xs text-muted-foreground hover:text-foreground">
                            <ArrowLeft className="h-3.5 w-3.5" />
                            <span>{backLabel}</span>
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center gap-2">
                    <Badge variant="outline" className="text-xs px-2.5 py-1 font-mono gap-1.5">
                        <FileText className="h-3 w-3 text-muted-foreground" />
                        <span>Review Mode</span>
                    </Badge>
                    {can.approve ? (
                        <Badge variant="secondary" className="text-xs px-2.5 py-1 gap-1 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800">
                            <ShieldCheck className="h-3.5 w-3.5" />
                            <span>Approval Authorized</span>
                        </Badge>
                    ) : (
                        <Badge variant="secondary" className="text-xs px-2.5 py-1 gap-1 text-muted-foreground">
                            <ShieldAlert className="h-3.5 w-3.5" />
                            <span>Read-Only Review</span>
                        </Badge>
                    )}
                </div>
            </div>

            {/* Order Title & Five Independent Status Badges */}
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-card border rounded-lg p-5 shadow-xs">
                <div className="space-y-1.5">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-bold font-mono text-foreground tracking-tight">
                            {order.order_number}
                        </h1>
                        <Badge variant="outline" className="text-xs font-normal text-muted-foreground">
                            ID #{order.id}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1">
                            <Clock className="h-3.5 w-3.5" />
                            <span>Submitted {order.submitted_at_formatted || 'Recently'}</span>
                        </span>
                        {order.submitted_at_relative && (
                            <span className="text-muted-foreground/70">
                                ({order.submitted_at_relative})
                            </span>
                        )}
                    </div>
                </div>

                {/* 5 Status Dimensions Badges */}
                <div className="flex flex-wrap items-center gap-2">
                    <OrderStatusBadge
                        dimension="order"
                        label={order.status_label}
                        variant={order.status_badge_variant}
                        showDimensionLabel
                        size="md"
                    />
                    {order.fulfillment_status_label && (
                        <OrderStatusBadge
                            dimension="fulfillment"
                            label={order.fulfillment_status_label}
                            variant={order.fulfillment_badge_variant}
                            showDimensionLabel
                            size="md"
                        />
                    )}
                    {order.payment_status_label && (
                        <OrderStatusBadge
                            dimension="payment"
                            label={order.payment_status_label}
                            variant={order.payment_badge_variant}
                            showDimensionLabel
                            size="md"
                        />
                    )}
                    {order.delivery_status_label && (
                        <OrderStatusBadge
                            dimension="delivery"
                            label={order.delivery_status_label}
                            variant={order.delivery_badge_variant}
                            showDimensionLabel
                            size="md"
                        />
                    )}
                    {order.adjustment_status && order.adjustment_status !== 'NONE' && order.adjustment_status_label && (
                        <OrderStatusBadge
                            dimension="adjustment"
                            label={order.adjustment_status_label}
                            variant={order.adjustment_badge_variant}
                            showDimensionLabel
                            size="md"
                        />
                    )}
                </div>
            </div>

            {/* Workflow Action Readiness Banner */}
            {hasBlockers ? (
                <div className="bg-destructive/10 border border-destructive/30 rounded-lg p-4 flex items-start gap-3 text-xs">
                    <AlertOctagon className="h-5 w-5 text-destructive shrink-0 mt-0.5" />
                    <div className="space-y-1">
                        <div className="font-semibold text-destructive text-sm">
                            Review Blockers Detected
                        </div>
                        <p className="text-muted-foreground leading-relaxed">
                            This order has one or more critical blocking conditions (e.g. customer account on hold or deactivated catalog products). These blockers must be resolved before this order can proceed to approval in FEAT-ORD-012.
                        </p>
                    </div>
                </div>
            ) : (
                <div className="bg-primary/5 border border-primary/20 rounded-lg p-4 flex items-start gap-3 text-xs">
                    <CheckCircle2 className="h-5 w-5 text-primary shrink-0 mt-0.5" />
                    <div className="space-y-1">
                        <div className="font-semibold text-foreground text-sm">
                            Ready for Operational Review
                        </div>
                        <p className="text-muted-foreground leading-relaxed">
                            Order details, immutable pricing, and line-item tax breakdowns are ready for evaluation. Formal approval and rejection actions are isolated to the downstream FEAT-ORD-012 workflow.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
