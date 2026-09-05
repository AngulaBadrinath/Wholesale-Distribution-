import React from 'react';
import { Calculator, Receipt, Percent } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { AdminOrderReviewData } from '@/types/order';

interface ReviewFinancialSummaryProps {
    order: AdminOrderReviewData['order'];
    taxBreakdown: AdminOrderReviewData['tax_breakdown'];
}

export default function ReviewFinancialSummary({ order, taxBreakdown }: ReviewFinancialSummaryProps) {
    const formatMoney = (amount: string | number) => {
        const num = typeof amount === 'string' ? parseFloat(amount) : amount;
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: order.currency || 'USD',
        }).format(num || 0);
    };

    return (
        <Card className="shadow-xs border">
            <CardHeader className="pb-3 border-b bg-muted/20">
                <div className="flex items-center gap-2">
                    <Receipt className="h-4 w-4 text-primary" />
                    <CardTitle className="text-sm font-bold text-foreground">
                        Financial Summary
                    </CardTitle>
                </div>
            </CardHeader>

            <CardContent className="pt-4 space-y-4 text-xs">
                {/* Line Tax Profiles Breakdown */}
                {taxBreakdown && taxBreakdown.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-1.5 font-semibold text-foreground text-[11px] uppercase tracking-wider text-muted-foreground">
                            <Percent className="h-3 w-3 text-primary" />
                            <span>Tax Breakdown by Profile</span>
                        </div>
                        <div className="space-y-1.5 bg-muted/20 p-2.5 rounded-md border text-[11px]">
                            {taxBreakdown.map((tb) => (
                                <div key={tb.code} className="flex items-center justify-between text-muted-foreground">
                                    <span className="truncate max-w-[180px]" title={tb.name}>
                                        {tb.code} ({tb.formatted_rate})
                                    </span>
                                    <span className="font-mono text-foreground font-semibold">
                                        {formatMoney(tb.tax_amount)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Subtotals & Grand Total */}
                <div className="space-y-2 border-t pt-3">
                    <div className="flex items-center justify-between text-muted-foreground">
                        <span>Items Subtotal</span>
                        <span className="font-mono text-foreground font-semibold">
                            {formatMoney(order.subtotal)}
                        </span>
                    </div>

                    <div className="flex items-center justify-between text-muted-foreground">
                        <span>Total Line Taxes</span>
                        <span className="font-mono text-foreground font-semibold">
                            {formatMoney(order.tax_total)}
                        </span>
                    </div>

                    {parseFloat(order.adjustment_total || '0') !== 0 && (
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span>Adjustments</span>
                            <span className="font-mono text-foreground font-semibold">
                                {formatMoney(order.adjustment_total)}
                            </span>
                        </div>
                    )}

                    <div className="border-t pt-3 flex items-baseline justify-between">
                        <div className="space-y-0.5">
                            <span className="text-sm font-bold text-foreground block">
                                Grand Total
                            </span>
                            <span className="text-[10px] text-muted-foreground">
                                Currency: {order.currency}
                            </span>
                        </div>
                        <span className="text-xl font-bold font-mono text-primary">
                            {formatMoney(order.grand_total)}
                        </span>
                    </div>
                </div>

                {/* Persisted Snapshot Invariant Notice */}
                <div className="p-2 rounded bg-muted/30 border text-[10px] text-muted-foreground/80 leading-relaxed italic">
                    All financial values reflect immutable transaction snapshots persisted upon order submission.
                </div>
            </CardContent>
        </Card>
    );
}
