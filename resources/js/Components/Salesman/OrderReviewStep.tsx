import React, { useMemo } from 'react';
import { CustomerSummary, CartLineItem } from '@/types/order';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Building,
    MapPin,
    CreditCard,
    ArrowLeft,
    CheckCircle2,
    Trash2,
    Plus,
    Minus,
    AlertTriangle,
    ShieldCheck,
} from 'lucide-react';

interface OrderReviewStepProps {
    customer: CustomerSummary;
    cart: CartLineItem[];
    notes: string;
    onNotesChange: (notes: string) => void;
    onUpdateQuantity: (productId: number, quantity: number) => void;
    onRemoveItem: (productId: number) => void;
    onBackToCatalog: () => void;
    onSubmitOrder: () => void;
    isSubmitting: boolean;
    errorMessage: string | null;
}

export const OrderReviewStep: React.FC<OrderReviewStepProps> = ({
    customer,
    cart,
    notes,
    onNotesChange,
    onUpdateQuantity,
    onRemoveItem,
    onBackToCatalog,
    onSubmitOrder,
    isSubmitting,
    errorMessage,
}) => {
    // Calculate client-side visual previews (ROUND_HALF_UP equivalent for preview)
    const calculation = useMemo(() => {
        let subtotal = 0;
        let taxTotal = 0;

        const lines = cart.map((item) => {
            const price = parseFloat(item.unit_price) || 0;
            const taxable = price * item.quantity;
            const taxRate = item.product.tax_profile
                ? parseFloat(item.product.tax_profile.rate) || 0
                : 0;
            const tax = Math.round((taxable * (taxRate / 100)) * 100) / 100;
            const lineTotal = taxable + tax;

            subtotal += taxable;
            taxTotal += tax;

            return {
                ...item,
                taxable: taxable.toFixed(2),
                taxRate: taxRate.toFixed(4),
                tax: tax.toFixed(2),
                lineTotal: lineTotal.toFixed(2),
            };
        });

        const grandTotal = subtotal + taxTotal;

        return {
            lines,
            subtotal: subtotal.toFixed(2),
            taxTotal: taxTotal.toFixed(2),
            grandTotal: grandTotal.toFixed(2),
            itemCount: cart.reduce((acc, item) => acc + item.quantity, 0),
        };
    }, [cart]);

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-foreground">Order Review & Confirmation</h2>
                    <p className="text-sm text-muted-foreground">
                        Review customer information, product lines, tax breakdown, and provide any order instructions.
                    </p>
                </div>
                <Button variant="outline" onClick={onBackToCatalog} className="shrink-0 gap-2">
                    <ArrowLeft className="h-4 w-4" />
                    <span>Back to Catalogue</span>
                </Button>
            </div>

            {errorMessage && (
                <div className="p-4 rounded-lg bg-destructive/10 border border-destructive/20 text-destructive text-sm flex items-start gap-3">
                    <AlertTriangle className="h-5 w-5 shrink-0 mt-0.5" />
                    <div>
                        <p className="font-semibold">Submission Failed</p>
                        <p>{errorMessage}</p>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left 2 Columns: Order Lines & Customer */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Customer Information Card */}
                    <Card>
                        <CardHeader className="pb-3 border-b">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Building className="h-4 w-4 text-primary" />
                                    <CardTitle className="text-base font-bold">{customer.name}</CardTitle>
                                    <Badge variant="outline" className="font-mono text-xs">
                                        {customer.code}
                                    </Badge>
                                </div>
                                <Badge variant="secondary" className="text-xs">
                                    {customer.status_label}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-muted-foreground">
                            <div>
                                <span className="font-semibold text-foreground block mb-1">Shipping Address:</span>
                                <div className="flex items-start gap-1.5">
                                    <MapPin className="h-3.5 w-3.5 shrink-0 text-muted-foreground mt-0.5" />
                                    <span>{customer.shipping_address || customer.billing_address}</span>
                                </div>
                            </div>
                            <div>
                                <span className="font-semibold text-foreground block mb-1">Commercial Terms:</span>
                                <div className="flex items-center gap-1.5">
                                    <CreditCard className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                    <span>Terms: {customer.payment_terms_label || 'Standard'}</span>
                                </div>
                                <div className="mt-1 text-muted-foreground">
                                    Credit Limit: ${customer.credit_limit.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Order Items Table */}
                    <Card>
                        <CardHeader className="pb-3 border-b">
                            <CardTitle className="text-base font-bold">
                                Order Items ({calculation.itemCount} total units)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0 overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b bg-muted/40 font-medium text-muted-foreground uppercase tracking-wider text-[11px]">
                                    <tr>
                                        <th scope="col" className="py-3 px-4 w-[40%]">Product / SKU</th>
                                        <th scope="col" className="py-3 px-4 text-right">Unit Price</th>
                                        <th scope="col" className="py-3 px-4 text-center">Quantity</th>
                                        <th scope="col" className="py-3 px-4 text-right">Tax</th>
                                        <th scope="col" className="py-3 px-4 text-right">Line Total</th>
                                        <th scope="col" className="py-3 px-4 w-[40px]"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {calculation.lines.map((line) => (
                                        <tr key={line.product.id} className="hover:bg-muted/20 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-semibold text-foreground">{line.product.name}</div>
                                                <div className="text-[11px] font-mono text-muted-foreground flex items-center gap-2">
                                                    <span>SKU: {line.product.sku}</span>
                                                    <span>•</span>
                                                    <span>Unit: {line.product.unit}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono font-medium">
                                                ${parseFloat(line.unit_price).toFixed(2)}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <div className="inline-flex items-center border rounded bg-background">
                                                    <button
                                                        type="button"
                                                        onClick={() => onUpdateQuantity(line.product.id, line.quantity - 1)}
                                                        className="h-6 w-6 flex items-center justify-center text-muted-foreground hover:text-foreground"
                                                        disabled={isSubmitting}
                                                    >
                                                        <Minus className="h-3 w-3" />
                                                    </button>
                                                    <span className="w-8 text-center font-mono font-bold">
                                                        {line.quantity}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => onUpdateQuantity(line.product.id, line.quantity + 1)}
                                                        className="h-6 w-6 flex items-center justify-center text-muted-foreground hover:text-foreground"
                                                        disabled={isSubmitting}
                                                    >
                                                        <Plus className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono">
                                                <div>${line.tax}</div>
                                                {line.product.tax_profile && (
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {line.product.tax_profile.formatted_rate}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono font-bold text-foreground">
                                                ${line.lineTotal}
                                            </td>
                                            <td className="py-3 px-4">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                    onClick={() => onRemoveItem(line.product.id)}
                                                    disabled={isSubmitting}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    {/* Order Notes */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-semibold">Order Instructions / Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <textarea
                                placeholder="Add optional customer PO reference, delivery notes, or specific handling instructions..."
                                value={notes}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => onNotesChange(e.target.value)}
                                rows={3}
                                maxLength={1000}
                                disabled={isSubmitting}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                            />
                            <div className="flex justify-end text-[11px] text-muted-foreground mt-1">
                                {notes.length} / 1000 characters
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Right Column: Financial Summary & Submit */}
                <div className="space-y-6">
                    <Card className="sticky top-6 border-primary/40 shadow-sm">
                        <CardHeader className="pb-3 border-b bg-muted/20">
                            <CardTitle className="text-base font-bold">Order Financial Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-4 space-y-4">
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Subtotal (Taxable):</span>
                                    <span className="font-mono font-medium text-foreground">${calculation.subtotal}</span>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Estimated Line Taxes:</span>
                                    <span className="font-mono font-medium text-foreground">${calculation.taxTotal}</span>
                                </div>
                                <div className="pt-3 border-t flex justify-between items-baseline">
                                    <span className="text-base font-bold text-foreground">Grand Total:</span>
                                    <span className="text-2xl font-bold font-mono text-primary">${calculation.grandTotal}</span>
                                </div>
                            </div>

                            <div className="rounded bg-muted/40 p-3 text-[11px] text-muted-foreground space-y-1.5">
                                <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                    <ShieldCheck className="h-3.5 w-3.5 text-primary" />
                                    <span>Server-Authoritative Calculation</span>
                                </div>
                                <p>
                                    All product prices, tax rates, and line totals are permanently snapshotted and recalculated server-side upon submission.
                                </p>
                            </div>

                            <Button
                                type="button"
                                className="w-full h-11 text-base font-semibold gap-2 shadow"
                                onClick={onSubmitOrder}
                                disabled={isSubmitting || cart.length === 0}
                            >
                                <CheckCircle2 className="h-5 w-5" />
                                <span>{isSubmitting ? 'Placing Order...' : 'Submit Order'}</span>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
};
