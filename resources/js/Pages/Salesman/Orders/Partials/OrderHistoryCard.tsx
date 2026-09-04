import React from 'react';
import { Link } from '@inertiajs/react';
import { OrderHistoryItem } from '@/types/order';
import OrderStatusBadge from './OrderStatusBadge';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { ChevronRight, Calendar, Package, Building2 } from 'lucide-react';

interface OrderHistoryCardProps {
    order: OrderHistoryItem;
}

export default function OrderHistoryCard({ order }: OrderHistoryCardProps) {
    const dateToDisplay = order.submitted_at ? new Date(order.submitted_at) : new Date(order.created_at);

    return (
        <Card className="border shadow-xs hover:border-primary/40 transition-colors">
            <CardContent className="p-4 space-y-3.5">
                {/* Header: Order Number + Grand Total */}
                <div className="flex items-start justify-between gap-2">
                    <div className="space-y-0.5 min-w-0">
                        <Link
                            href={`/salesman/orders/${order.id}`}
                            className="font-mono font-bold text-base text-foreground hover:text-primary transition-colors block truncate"
                        >
                            {order.order_number}
                        </Link>
                        <div className="text-[10px] font-mono text-muted-foreground truncate">
                            Key: {order.idempotency_key.substring(0, 16)}...
                        </div>
                    </div>

                    <div className="text-right shrink-0">
                        <div className="text-base font-bold font-mono text-primary">
                            ${parseFloat(order.grand_total).toFixed(2)}
                        </div>
                        <div className="text-[10px] font-mono text-muted-foreground">
                            {order.item_count} {order.item_count === 1 ? 'item' : 'items'}
                        </div>
                    </div>
                </div>

                {/* Customer Details */}
                <div className="p-2.5 rounded-md bg-muted/30 border space-y-0.5">
                    <div className="flex items-center gap-1.5 font-semibold text-xs text-foreground truncate">
                        <Building2 className="h-3.5 w-3.5 text-primary shrink-0" />
                        <span className="truncate">{order.customer.name}</span>
                    </div>
                    <div className="text-[11px] font-mono text-muted-foreground pl-5 flex items-center gap-2">
                        <span>Code: {order.customer.code}</span>
                        {order.customer.phone && (
                            <>
                                <span>•</span>
                                <span>{order.customer.phone}</span>
                            </>
                        )}
                    </div>
                </div>

                {/* Submitted Timestamp */}
                <div className="flex items-center gap-1.5 text-[11px] font-mono text-muted-foreground">
                    <Calendar className="h-3 w-3 shrink-0" />
                    <span>Submitted: {dateToDisplay.toLocaleDateString()} {dateToDisplay.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                </div>

                {/* Status Badges Group */}
                <div className="flex flex-wrap items-center gap-1.5 pt-1 border-t">
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

                    {order.delivery_status_label && order.delivery_status !== 'PENDING_ASSIGNMENT' && (
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

                {/* View Order Detail Button (min touch target >= 44px) */}
                <div className="pt-1">
                    <Link href={`/salesman/orders/${order.id}`} className="block w-full">
                        <Button
                            variant="outline"
                            size="sm"
                            className="w-full h-11 text-xs justify-between font-medium group"
                        >
                            <span>Inspect Order Details</span>
                            <ChevronRight className="h-4 w-4 text-muted-foreground group-hover:translate-x-0.5 transition-transform" />
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    );
}
