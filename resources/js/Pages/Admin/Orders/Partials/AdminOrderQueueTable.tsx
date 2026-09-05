import React from 'react';
import { Link } from '@inertiajs/react';
import { AdminOrderQueueItem } from '@/types/order';
import OrderStatusBadge from '@/Pages/Salesman/Orders/Partials/OrderStatusBadge';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Eye,
    Receipt,
    AlertTriangle,
    Clock,
    User,
    Building2,
    Package,
    FileText,
} from 'lucide-react';

interface AdminOrderQueueTableProps {
    orders: AdminOrderQueueItem[];
    sortBy?: string;
    sortDirection?: string;
    onSortChange: (column: string) => void;
}

export default function AdminOrderQueueTable({
    orders,
    sortBy,
    sortDirection,
    onSortChange,
}: AdminOrderQueueTableProps) {
    const renderSortHeader = (label: string, column: string, align: 'left' | 'right' | 'center' = 'left') => {
        const isCurrentSort = sortBy === column;
        const Icon = isCurrentSort
            ? sortDirection === 'asc'
                ? ArrowUp
                : ArrowDown
            : ArrowUpDown;

        return (
            <th
                scope="col"
                className={`py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground ${
                    align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left'
                }`}
            >
                <button
                    type="button"
                    onClick={() => onSortChange(column)}
                    className={`inline-flex items-center gap-1 hover:text-foreground transition-colors group focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary rounded-xs ${
                        align === 'right' ? 'justify-end' : ''
                    }`}
                >
                    <span>{label}</span>
                    <Icon className={`h-3 w-3 ${isCurrentSort ? 'text-primary' : 'text-muted-foreground/50 group-hover:text-foreground'}`} />
                </button>
            </th>
        );
    };

    if (orders.length === 0) {
        return (
            <div className="bg-card border border-border rounded-lg p-12 text-center space-y-3 my-4">
                <div className="h-12 w-12 rounded-full bg-muted/60 text-muted-foreground flex items-center justify-center mx-auto">
                    <Receipt className="h-6 w-6" />
                </div>
                <h3 className="text-base font-semibold text-foreground">No orders in this queue</h3>
                <p className="text-xs text-muted-foreground max-w-sm mx-auto">
                    There are currently no orders matching the active operational queue or filter criteria.
                </p>
            </div>
        );
    }

    return (
        <div className="bg-card border border-border rounded-lg overflow-hidden shadow-xs my-4">
            <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse" aria-label="Operational Order Queue">
                    <thead>
                        <tr className="border-b border-border bg-muted/30">
                            {renderSortHeader('Order #', 'order_number')}
                            {renderSortHeader('Customer', 'customer_name')}
                            <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Salesman
                            </th>
                            <th scope="col" className="py-3 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground text-center">
                                Items
                            </th>
                            {renderSortHeader('Total', 'grand_total', 'right')}
                            <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Status Dimensions
                            </th>
                            {renderSortHeader('Submitted', 'submitted_at')}
                            <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Attention / Alerts
                            </th>
                            <th scope="col" className="py-3 px-3.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground text-right">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {orders.map((order) => {
                            const hasAlerts = order.attention_flags && order.attention_flags.length > 0;

                            return (
                                <tr
                                    key={order.id}
                                    className={`hover:bg-muted/40 transition-colors ${
                                        hasAlerts ? 'bg-amber-500/5' : ''
                                    }`}
                                >
                                    {/* Order Number */}
                                    <td className="py-3 px-3.5 whitespace-nowrap">
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={`/admin/orders/${order.id}`}
                                                className="font-mono text-xs font-bold text-primary hover:underline"
                                            >
                                                {order.order_number}
                                            </Link>
                                        </div>
                                    </td>

                                    {/* Customer */}
                                    <td className="py-3 px-3.5">
                                        <div className="flex flex-col min-w-[140px] max-w-[200px]">
                                            <span className="text-xs font-medium text-foreground truncate" title={order.customer?.name}>
                                                {order.customer?.name || 'Unknown Customer'}
                                            </span>
                                            <span className="font-mono text-[10px] text-muted-foreground">
                                                {order.customer?.code}
                                            </span>
                                        </div>
                                    </td>

                                    {/* Salesman */}
                                    <td className="py-3 px-3.5 whitespace-nowrap">
                                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <User className="h-3.5 w-3.5 shrink-0" />
                                            <span className="truncate max-w-[130px]" title={order.salesman?.name}>
                                                {order.salesman?.name || 'Unassigned'}
                                            </span>
                                        </div>
                                    </td>

                                    {/* Items Count */}
                                    <td className="py-3 px-3 text-center whitespace-nowrap">
                                        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                            <Package className="h-3 w-3 shrink-0" />
                                            <span>{order.item_count}</span>
                                        </span>
                                    </td>

                                    {/* Grand Total */}
                                    <td className="py-3 px-3.5 text-right whitespace-nowrap">
                                        <span className="font-mono text-xs font-semibold text-foreground">
                                            ${Number(order.grand_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </span>
                                    </td>

                                    {/* Multi-Dimensional Statuses */}
                                    <td className="py-3 px-3.5">
                                        <div className="flex flex-wrap items-center gap-1 max-w-[280px]">
                                            {/* Order Status */}
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
                                            {order.delivery_status_label && (
                                                <OrderStatusBadge
                                                    dimension="delivery"
                                                    label={order.delivery_status_label}
                                                    variant={order.delivery_badge_variant}
                                                    size="sm"
                                                />
                                            )}

                                            {/* Adjustment Status (Only shown when not NONE) */}
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

                                    {/* Submitted Timestamp & Relative Age */}
                                    <td className="py-3 px-3.5 whitespace-nowrap">
                                        <div className="flex flex-col text-xs">
                                            <span className="text-foreground font-medium">
                                                {order.submitted_at_formatted || 'Not Submitted'}
                                            </span>
                                            {order.submitted_at_relative && (
                                                <span className="text-[10px] text-muted-foreground">
                                                    {order.submitted_at_relative}
                                                </span>
                                            )}
                                        </div>
                                    </td>

                                    {/* Attention / Alerts */}
                                    <td className="py-3 px-3.5">
                                        {hasAlerts ? (
                                            <div className="flex flex-col gap-1">
                                                {order.attention_flags.includes('aging_submission') && (
                                                    <Badge variant="warning" className="text-[10px] h-5 gap-1 w-fit">
                                                        <Clock className="h-3 w-3" />
                                                        <span>Pending &gt;24h</span>
                                                    </Badge>
                                                )}
                                                {order.attention_flags.includes('delivery_exception') && (
                                                    <Badge variant="destructive" className="text-[10px] h-5 gap-1 w-fit">
                                                        <AlertTriangle className="h-3 w-3" />
                                                        <span>Delivery Exception</span>
                                                    </Badge>
                                                )}
                                                {order.attention_flags.includes('adjustment_pending') && (
                                                    <Badge variant="secondary" className="text-[10px] h-5 gap-1 w-fit">
                                                        <span>Adjustment Req.</span>
                                                    </Badge>
                                                )}
                                                {order.attention_flags.includes('payment_overdue') && (
                                                    <Badge variant="destructive" className="text-[10px] h-5 gap-1 w-fit">
                                                        <span>Payment Overdue</span>
                                                    </Badge>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-xs text-muted-foreground/60">—</span>
                                        )}
                                    </td>

                                    {/* Action */}
                                    <td className="py-3 px-3.5 text-right whitespace-nowrap">
                                        {['SUBMITTED', 'PENDING_APPROVAL'].includes(order.status) ? (
                                            <Link href={`/admin/orders/${order.id}/review`}>
                                                <Button
                                                    variant="default"
                                                    size="sm"
                                                    className="h-8 min-h-[32px] px-2.5 text-xs gap-1 shadow-sm"
                                                >
                                                    <FileText className="h-3.5 w-3.5" />
                                                    <span>Review</span>
                                                </Button>
                                            </Link>
                                        ) : (
                                            <Link href={`/admin/orders/${order.id}`}>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 min-h-[32px] px-2.5 text-xs gap-1 hover:bg-primary hover:text-primary-foreground"
                                                >
                                                    <Eye className="h-3.5 w-3.5" />
                                                    <span>View</span>
                                                </Button>
                                            </Link>
                                        )}
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
