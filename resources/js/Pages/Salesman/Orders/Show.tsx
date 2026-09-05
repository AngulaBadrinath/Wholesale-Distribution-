import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { OrderDetail } from '@/types/order';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import OrderStatusBadge from './Partials/OrderStatusBadge';
import OrderTimeline from './Partials/OrderTimeline';
import {
    CheckCircle2,
    Building,
    MapPin,
    CreditCard,
    Plus,
    User,
    Calendar,
    Receipt,
    ShieldCheck,
    FileText,
    ArrowLeft,
    ListFilter,
} from 'lucide-react';

interface OrderShowPageProps {
    order: OrderDetail;
    backUrl?: string;
    backLabel?: string;
}

export default function OrderShow({ order, backUrl = '/salesman/orders', backLabel = 'Back to Order History' }: OrderShowPageProps) {
    const totalUnits = order.items.reduce((sum, item) => sum + item.ordered_quantity, 0);
    const submittedDate = order.submitted_at ? new Date(order.submitted_at) : new Date(order.created_at);

    return (
        <AppLayout title={`Order ${order.order_number}`}>
            <Head title={`Order ${order.order_number} — Detail & Timeline`} />

            <div className="max-w-6xl mx-auto space-y-6 pb-16">
                {/* Navigation Breadcrumb & Actions Bar */}
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
                        <Link href={backUrl}>
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs">
                                <ListFilter className="h-3.5 w-3.5" />
                                <span>{backUrl === '/admin/orders' ? 'Order Queue' : 'All Orders'}</span>
                            </Button>
                        </Link>
                        <Link href="/salesman/orders/create">
                            <Button size="sm" className="gap-1.5 text-xs">
                                <Plus className="h-3.5 w-3.5" />
                                <span>New Order</span>
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Status Notice Banner (When freshly submitted or active) */}
                <div className="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <CheckCircle2 className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-emerald-900 dark:text-emerald-200">
                                Order Record Committed
                            </h2>
                            <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                Order <span className="font-mono font-bold">{order.order_number}</span> is persisted with immutable pricing and tax snapshots.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/customers">
                            <Button variant="outline" size="sm" className="text-xs">
                                Customer Accounts
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Header Information Card with Independent Multi-State Dimensions */}
                <Card>
                    <CardHeader className="pb-4 border-b">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Receipt className="h-5 w-5 text-primary" />
                                    <CardTitle className="text-xl font-bold font-mono">
                                        {order.order_number}
                                    </CardTitle>
                                </div>
                                <CardDescription className="flex items-center gap-2 text-xs">
                                    <Calendar className="h-3.5 w-3.5" />
                                    <span>
                                        Committed on {submittedDate.toLocaleString()}
                                    </span>
                                </CardDescription>
                            </div>

                            {/* Independent Status Dimensions Badges */}
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
                    </CardHeader>

                    {/* Customer & Salesman Context */}
                    <CardContent className="pt-4 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs text-muted-foreground">
                        {/* Customer */}
                        <div className="space-y-2">
                            <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                <Building className="h-3.5 w-3.5 text-primary" />
                                <span>Customer Account</span>
                            </div>
                            <div className="p-3 rounded-md bg-muted/30 border space-y-1">
                                <div className="font-bold text-foreground text-sm flex items-center justify-between">
                                    <span>{order.customer.name}</span>
                                    <Badge variant="outline" className="font-mono text-[10px]">
                                        {order.customer.code}
                                    </Badge>
                                </div>
                                {order.customer.contact_name && (
                                    <div>Attn: {order.customer.contact_name}</div>
                                )}
                                <div className="flex items-start gap-1 pt-1">
                                    <MapPin className="h-3 w-3 shrink-0 mt-0.5" />
                                    <span className="line-clamp-2">
                                        {order.customer.shipping_address || order.customer.billing_address}
                                    </span>
                                </div>
                                {order.customer.payment_terms && (
                                    <div className="flex items-center gap-1 pt-1">
                                        <CreditCard className="h-3 w-3" />
                                        <span>Terms: {order.customer.payment_terms}</span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Salesman & Order Details */}
                        <div className="space-y-2">
                            <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                <User className="h-3.5 w-3.5 text-primary" />
                                <span>Sales Account & Identification</span>
                            </div>
                            <div className="p-3 rounded-md bg-muted/30 border space-y-1">
                                <div className="font-medium text-foreground">
                                    Salesman: {order.salesman.name}
                                </div>
                                <div>Email: {order.salesman.email}</div>
                                <div className="pt-1 font-mono text-[11px] truncate">
                                    Idempotency Token: {order.idempotency_key}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Multi-State Timeline Section */}
                {order.timeline && order.timeline.length > 0 && (
                    <OrderTimeline timeline={order.timeline} />
                )}

                {/* Order Items Table */}
                <Card>
                    <CardHeader className="pb-3 border-b">
                        <CardTitle className="text-base font-bold">
                            Committed Order Items ({totalUnits} units total)
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0 overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b bg-muted/40 font-medium text-muted-foreground uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th scope="col" className="py-3 px-4 w-[35%]">Product / SKU</th>
                                    <th scope="col" className="py-3 px-4 text-right">Unit Price</th>
                                    <th scope="col" className="py-3 px-4 text-center">Quantity</th>
                                    <th scope="col" className="py-3 px-4 text-right">Taxable</th>
                                    <th scope="col" className="py-3 px-4 text-right">Tax Rate / Amt</th>
                                    <th scope="col" className="py-3 px-4 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {order.items.map((item) => (
                                    <tr key={item.id} className="hover:bg-muted/20 transition-colors">
                                        <td className="py-3 px-4">
                                            <div className="font-semibold text-foreground">
                                                {item.product_name}
                                            </div>
                                            <div className="text-[11px] font-mono text-muted-foreground flex items-center gap-2">
                                                <span>SKU: {item.sku}</span>
                                                <span>•</span>
                                                <span>Unit: {item.unit}</span>
                                            </div>
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono font-medium">
                                            ${parseFloat(item.unit_price).toFixed(2)}
                                        </td>
                                        <td className="py-3 px-4 text-center font-mono font-bold">
                                            {item.ordered_quantity}
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono">
                                            ${parseFloat(item.taxable_amount).toFixed(2)}
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono">
                                            <div>${parseFloat(item.tax_amount).toFixed(2)}</div>
                                            <div className="text-[10px] text-muted-foreground">
                                                {item.tax_profile_code ? `${item.tax_profile_code} (${item.formatted_tax_rate})` : item.formatted_tax_rate}
                                            </div>
                                        </td>
                                        <td className="py-3 px-4 text-right font-mono font-bold text-foreground">
                                            ${parseFloat(item.line_total).toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* Notes & Totals Layout */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Notes */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-semibold flex items-center gap-1.5">
                                <FileText className="h-4 w-4 text-primary" />
                                <span>Order Instructions / Notes</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            {order.notes ? (
                                <p className="whitespace-pre-line p-3 bg-muted/20 rounded border">
                                    {order.notes}
                                </p>
                            ) : (
                                <p className="italic">No special order instructions provided.</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Financial Totals */}
                    <Card className="border-primary/30">
                        <CardHeader className="pb-3 border-b bg-muted/20">
                            <CardTitle className="text-base font-bold">Financial Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-4 space-y-3">
                            <div className="flex justify-between text-sm text-muted-foreground">
                                <span>Subtotal:</span>
                                <span className="font-mono font-medium text-foreground">
                                    ${parseFloat(order.subtotal).toFixed(2)}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm text-muted-foreground">
                                <span>Tax Total:</span>
                                <span className="font-mono font-medium text-foreground">
                                    ${parseFloat(order.tax_total).toFixed(2)}
                                </span>
                            </div>
                            {parseFloat(order.adjustment_total) !== 0 && (
                                <div className="flex justify-between text-sm text-muted-foreground">
                                    <span>Adjustments:</span>
                                    <span className="font-mono font-medium text-foreground">
                                        ${parseFloat(order.adjustment_total).toFixed(2)}
                                    </span>
                                </div>
                            )}
                            <div className="pt-3 border-t flex justify-between items-baseline">
                                <span className="text-base font-bold text-foreground">Grand Total:</span>
                                <span className="text-2xl font-bold font-mono text-primary">
                                    ${parseFloat(order.grand_total).toFixed(2)}
                                </span>
                            </div>

                            <div className="pt-2 text-[11px] text-muted-foreground flex items-center gap-1">
                                <ShieldCheck className="h-3.5 w-3.5 text-primary" />
                                <span>Snapshot-backed immutable record</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
