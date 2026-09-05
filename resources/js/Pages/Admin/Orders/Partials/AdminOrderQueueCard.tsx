import React from 'react';
import { Link } from '@inertiajs/react';
import { AdminOrderQueueItem } from '@/types/order';
import OrderStatusBadge from '@/Pages/Salesman/Orders/Partials/OrderStatusBadge';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    ArrowRight,
    User,
    Package,
    Clock,
    AlertTriangle,
    Receipt,
} from 'lucide-react';

interface AdminOrderQueueCardProps {
    order: AdminOrderQueueItem;
}

export default function AdminOrderQueueCard({ order }: AdminOrderQueueCardProps) {
    const hasAlerts = order.attention_flags && order.attention_flags.length > 0;

    return (
        <Card className={`overflow-hidden border transition-shadow hover:shadow-xs ${hasAlerts ? 'border-amber-300 dark:border-amber-800' : ''}`}>
            <CardContent className="p-4 space-y-3">
                {/* Header: Order # + Submitted Age */}
                <div className="flex items-center justify-between gap-2 border-b border-border/60 pb-2.5">
                    <div className="flex items-center gap-1.5">
                        <Receipt className="h-4 w-4 text-primary" />
                        <span className="font-mono text-sm font-bold text-foreground">
                            {order.order_number}
                        </span>
                    </div>

                    <div className="text-right">
                        <span className="text-[11px] text-muted-foreground block font-medium">
                            {order.submitted_at_relative || 'Not submitted'}
                        </span>
                    </div>
                </div>

                {/* Customer Identity */}
                <div className="space-y-0.5">
                    <div className="text-sm font-semibold text-foreground truncate">
                        {order.customer?.name || 'Unknown Customer'}
                    </div>
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span className="font-mono text-[11px]">{order.customer?.code}</span>
                        {order.customer?.phone && <span>{order.customer.phone}</span>}
                    </div>
                </div>

                {/* Salesman & Items Count */}
                <div className="flex items-center justify-between text-xs text-muted-foreground pt-1 border-t border-border/40">
                    <div className="flex items-center gap-1.5 truncate max-w-[200px]">
                        <User className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        <span className="truncate">{order.salesman?.name || 'Unassigned'}</span>
                    </div>

                    <div className="flex items-center gap-1 shrink-0 font-medium">
                        <Package className="h-3.5 w-3.5 text-muted-foreground" />
                        <span>{order.item_count} items</span>
                    </div>
                </div>

                {/* Status Badges Cluster */}
                <div className="flex flex-wrap gap-1 pt-1">
                    <OrderStatusBadge
                        dimension="order"
                        label={order.status_label}
                        variant={order.status_badge_variant}
                        size="sm"
                    />

                    {order.fulfillment_status_label && (
                        <OrderStatusBadge
                            dimension="fulfillment"
                            label={order.fulfillment_status_label}
                            variant={order.fulfillment_badge_variant}
                            size="sm"
                        />
                    )}

                    {order.payment_status_label && (
                        <OrderStatusBadge
                            dimension="payment"
                            label={order.payment_status_label}
                            variant={order.payment_badge_variant}
                            size="sm"
                        />
                    )}

                    {order.delivery_status_label && (
                        <OrderStatusBadge
                            dimension="delivery"
                            label={order.delivery_status_label}
                            variant={order.delivery_badge_variant}
                            size="sm"
                        />
                    )}

                    {order.adjustment_status && order.adjustment_status !== 'NONE' && order.adjustment_status_label && (
                        <OrderStatusBadge
                            dimension="adjustment"
                            label={order.adjustment_status_label}
                            variant={order.adjustment_badge_variant}
                            size="sm"
                        />
                    )}
                </div>

                {/* Alerts / Exception Flags */}
                {hasAlerts && (
                    <div className="flex flex-wrap gap-1 pt-1">
                        {order.attention_flags.includes('aging_submission') && (
                            <Badge variant="warning" className="text-[10px] h-5 gap-1">
                                <Clock className="h-3 w-3" />
                                <span>Pending &gt;24h</span>
                            </Badge>
                        )}
                        {order.attention_flags.includes('delivery_exception') && (
                            <Badge variant="destructive" className="text-[10px] h-5 gap-1">
                                <AlertTriangle className="h-3 w-3" />
                                <span>Delivery Exception</span>
                            </Badge>
                        )}
                        {order.attention_flags.includes('adjustment_pending') && (
                            <Badge variant="secondary" className="text-[10px] h-5">
                                <span>Adjustment Req.</span>
                            </Badge>
                        )}
                        {order.attention_flags.includes('payment_overdue') && (
                            <Badge variant="destructive" className="text-[10px] h-5">
                                <span>Payment Overdue</span>
                            </Badge>
                        )}
                    </div>
                )}

                {/* Footer: Grand Total & Action */}
                <div className="flex items-center justify-between pt-2 border-t border-border/60">
                    <div>
                        <span className="text-[10px] text-muted-foreground block uppercase tracking-wider font-medium">
                            Grand Total
                        </span>
                        <span className="font-mono text-base font-bold text-foreground">
                            ${Number(order.grand_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                    </div>

                    <Link href={`/admin/orders/${order.id}`}>
                        <Button
                            size="sm"
                            className="min-h-[44px] px-4 text-xs gap-1.5 font-medium"
                        >
                            <span>Open Order</span>
                            <ArrowRight className="h-3.5 w-3.5" />
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    );
}
