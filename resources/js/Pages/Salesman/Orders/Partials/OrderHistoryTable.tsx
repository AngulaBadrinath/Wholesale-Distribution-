import React from 'react';
import { Link } from '@inertiajs/react';
import { OrderHistoryItem } from '@/types/order';
import OrderStatusBadge from './OrderStatusBadge';
import { Button } from '@/Components/ui/button';
import { Eye, Building2, Calendar, Hash } from 'lucide-react';

interface OrderHistoryTableProps {
    orders: OrderHistoryItem[];
}

export default function OrderHistoryTable({ orders }: OrderHistoryTableProps) {
    if (orders.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-card overflow-hidden shadow-xs">
            <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                    <thead className="bg-muted/50 border-b font-medium text-muted-foreground uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" className="py-3 px-4 w-[18%]">
                                <div className="flex items-center gap-1.5">
                                    <Hash className="h-3.5 w-3.5" />
                                    <span>Order Number</span>
                                </div>
                            </th>
                            <th scope="col" className="py-3 px-4 w-[22%]">
                                <div className="flex items-center gap-1.5">
                                    <Building2 className="h-3.5 w-3.5" />
                                    <span>Customer Account</span>
                                </div>
                            </th>
                            <th scope="col" className="py-3 px-4 w-[14%]">
                                <div className="flex items-center gap-1.5">
                                    <Calendar className="h-3.5 w-3.5" />
                                    <span>Submitted Date</span>
                                </div>
                            </th>
                            <th scope="col" className="py-3 px-3 text-center w-[8%]">
                                Items
                            </th>
                            <th scope="col" className="py-3 px-4 w-[24%]">
                                Multi-State Status
                            </th>
                            <th scope="col" className="py-3 px-4 text-right w-[10%]">
                                Grand Total
                            </th>
                            <th scope="col" className="py-3 px-4 text-right w-[4%]">
                                <span className="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {orders.map((order) => {
                            const dateToDisplay = order.submitted_at ? new Date(order.submitted_at) : new Date(order.created_at);

                            return (
                                <tr
                                    key={order.id}
                                    className="hover:bg-muted/30 transition-colors group"
                                >
                                    {/* Order Number */}
                                    <td className="py-3.5 px-4 font-medium">
                                        <Link
                                            href={`/salesman/orders/${order.id}`}
                                            className="font-mono font-bold text-foreground hover:text-primary transition-colors flex items-center gap-1.5"
                                        >
                                            <span>{order.order_number}</span>
                                        </Link>
                                        <div className="text-[10px] font-mono text-muted-foreground truncate max-w-[160px]" title={order.idempotency_key}>
                                            Key: {order.idempotency_key.substring(0, 16)}...
                                        </div>
                                    </td>

                                    {/* Customer Account */}
                                    <td className="py-3.5 px-4">
                                        <div className="font-semibold text-foreground truncate max-w-[200px]" title={order.customer.name}>
                                            {order.customer.name}
                                        </div>
                                        <div className="text-[11px] font-mono text-muted-foreground flex items-center gap-2">
                                            <span>{order.customer.code}</span>
                                            {order.customer.contact_name && (
                                                <>
                                                    <span>•</span>
                                                    <span className="truncate max-w-[100px]">{order.customer.contact_name}</span>
                                                </>
                                            )}
                                        </div>
                                    </td>

                                    {/* Submitted Date */}
                                    <td className="py-3.5 px-4 font-mono text-muted-foreground text-[11px]">
                                        <div className="text-foreground font-medium">
                                            {dateToDisplay.toLocaleDateString()}
                                        </div>
                                        <div className="text-[10px] text-muted-foreground">
                                            {dateToDisplay.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                        </div>
                                    </td>

                                    {/* Items Count */}
                                    <td className="py-3.5 px-3 text-center font-mono font-bold text-foreground">
                                        {order.item_count}
                                    </td>

                                    {/* Multi-State Status Dimension Badges */}
                                    <td className="py-3.5 px-4">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {/* Order Lifecycle Status */}
                                            <OrderStatusBadge
                                                dimension="order"
                                                label={order.status_label}
                                                variant={order.status_badge_variant}
                                                size="sm"
                                            />

                                            {/* Fulfillment Status */}
                                            {order.fulfillment_status_label && (
                                                <OrderStatusBadge
                                                    dimension="fulfillment"
                                                    label={order.fulfillment_status_label}
                                                    variant={order.fulfillment_badge_variant}
                                                    size="sm"
                                                />
                                            )}

                                            {/* Payment Status */}
                                            {order.payment_status_label && (
                                                <OrderStatusBadge
                                                    dimension="payment"
                                                    label={order.payment_status_label}
                                                    variant={order.payment_badge_variant}
                                                    size="sm"
                                                />
                                            )}

                                            {/* Delivery Status */}
                                            {order.delivery_status_label && order.delivery_status !== 'PENDING_ASSIGNMENT' && (
                                                <OrderStatusBadge
                                                    dimension="delivery"
                                                    label={order.delivery_status_label}
                                                    variant={order.delivery_badge_variant}
                                                    size="sm"
                                                />
                                            )}

                                            {/* Adjustment Status (Shown only if not NONE) */}
                                            {order.adjustment_status && order.adjustment_status !== 'NONE' && order.adjustment_status_label && (
                                                <OrderStatusBadge
                                                    dimension="adjustment"
                                                    label={order.adjustment_status_label}
                                                    variant={order.adjustment_badge_variant}
                                                    size="sm"
                                                />
                                            )}
                                        </div>
                                    </td>

                                    {/* Grand Total */}
                                    <td className="py-3.5 px-4 text-right font-mono font-bold text-sm text-foreground">
                                        ${parseFloat(order.grand_total).toFixed(2)}
                                    </td>

                                    {/* Action Link */}
                                    <td className="py-3.5 px-4 text-right">
                                        <Link href={`/salesman/orders/${order.id}`}>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-8 w-8 p-0 text-muted-foreground hover:text-foreground hover:bg-muted"
                                                aria-label={`View order ${order.order_number}`}
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
