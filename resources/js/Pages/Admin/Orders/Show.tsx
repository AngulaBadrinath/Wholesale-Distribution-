import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { AdminOrderDetailData } from '@/types/order';
import OrderTimeline from '@/Pages/Salesman/Orders/Partials/OrderTimeline';
import OrderDetailHeader from './Partials/OrderDetailHeader';
import OrderDetailCustomerCard from './Partials/OrderDetailCustomerCard';
import OrderDetailItemsTable from './Partials/OrderDetailItemsTable';
import OrderDetailItemsCards from './Partials/OrderDetailItemsCards';
import OrderDetailFinancialSummary from './Partials/OrderDetailFinancialSummary';
import OrderDetailOperationalCards from './Partials/OrderDetailOperationalCards';
import PendingAdjustmentBanner from './Partials/PendingAdjustmentBanner';
import RequestAdjustmentModal from './Partials/RequestAdjustmentModal';
import { MessageSquare, ShieldCheck, Clock } from 'lucide-react';

interface AdminOrderShowPageProps {
    orderData: AdminOrderDetailData;
    backUrl?: string;
    backLabel?: string;
}

export default function Show({
    orderData,
    backUrl = '/admin/orders',
    backLabel = 'Back to Order Queue',
}: AdminOrderShowPageProps) {
    const [isAdjustmentModalOpen, setIsAdjustmentModalOpen] = useState(false);

    const {
        order,
        customer,
        salesman,
        creator,
        items,
        tax_breakdown,
        fulfillment_summary,
        timeline,
        active_adjustment,
        can,
    } = orderData;

    return (
        <AppLayout title={`Order ${order.order_number}`}>
            <Head title={`Order ${order.order_number} — Detail Workspace`} />

            <div className="max-w-7xl mx-auto space-y-6 pb-20 px-4 sm:px-6 lg:px-8">
                {/* Header, Actions & Status Dimension Bar */}
                <OrderDetailHeader
                    order={order}
                    customer={customer}
                    salesman={salesman}
                    creator={creator}
                    can={can}
                    backUrl={orderData.backUrl || backUrl}
                    backLabel={orderData.backLabel || backLabel}
                    onRequestAdjustment={() => setIsAdjustmentModalOpen(true)}
                />

                {/* Pending Adjustment Banner (When an active submitted adjustment exists) */}
                {active_adjustment && (
                    <PendingAdjustmentBanner
                        orderId={order.id}
                        orderNumber={order.order_number}
                        activeAdjustment={active_adjustment}
                    />
                )}

                {/* Request Adjustment Modal */}
                <RequestAdjustmentModal
                    isOpen={isAdjustmentModalOpen}
                    orderId={order.id}
                    orderNumber={order.order_number}
                    items={items}
                    onClose={() => setIsAdjustmentModalOpen(false)}
                />

                {/* 12-Column Responsive Command Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    {/* Left Main Operational Stream (8 Cols on Desktop) */}
                    <div className="lg:col-span-8 space-y-6">
                        {/* Operational Summaries Grid (Fulfillment, Payment, Delivery, Adjustments) */}
                        <OrderDetailOperationalCards
                            order={order}
                            customer={customer}
                            fulfillmentSummary={fulfillment_summary}
                        />

                        {/* Desktop High-Density Line Items Table */}
                        <div className="hidden md:block">
                            <OrderDetailItemsTable items={items} />
                        </div>

                        {/* Mobile Purpose-Built Cards */}
                        <div className="md:hidden">
                            <OrderDetailItemsCards items={items} />
                        </div>

                        {/* Order Notes (When entered during checkout) */}
                        {order.notes && (
                            <Card className="border shadow-sm">
                                <CardHeader className="pb-2.5 border-b bg-muted/20">
                                    <div className="flex items-center gap-2">
                                        <MessageSquare className="h-4 w-4 text-primary" />
                                        <CardTitle className="text-xs font-bold uppercase tracking-wider">
                                            Order & Operational Notes
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-3 text-xs text-foreground whitespace-pre-line leading-relaxed font-mono bg-muted/10">
                                    {order.notes}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right Side Rail: Commercial Profile, Financials, Timeline (4 Cols on Desktop) */}
                    <div className="lg:col-span-4 space-y-6">
                        {/* Customer Commercial Profile */}
                        <OrderDetailCustomerCard
                            customer={customer}
                            salesman={salesman}
                        />

                        {/* Financial Summary & Tax Breakdown */}
                        <OrderDetailFinancialSummary
                            order={order}
                            taxBreakdown={tax_breakdown}
                        />

                        {/* Verifiable Multi-State Order Timeline */}
                        <OrderTimeline timeline={timeline} />

                        {/* Audit & Integrity Context Card */}
                        <Card className="border shadow-sm text-xs bg-muted/20">
                            <CardHeader className="pb-2 border-b">
                                <div className="flex items-center gap-2">
                                    <ShieldCheck className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                    <CardTitle className="text-xs font-bold uppercase tracking-wider">
                                        Audit & Integrity Context
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-3 space-y-2 text-[11px] text-muted-foreground font-mono">
                                <div className="flex justify-between">
                                    <span>Record Version:</span>
                                    <span className="font-semibold text-foreground">v{order.version}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Operating Currency:</span>
                                    <span className="font-semibold text-foreground">{order.currency}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Initialized:</span>
                                    <span className="font-semibold text-foreground">
                                        {new Date(order.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                                {order.submitted_at && (
                                    <div className="flex justify-between">
                                        <span>Committed:</span>
                                        <span className="font-semibold text-foreground">
                                            {new Date(order.submitted_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
