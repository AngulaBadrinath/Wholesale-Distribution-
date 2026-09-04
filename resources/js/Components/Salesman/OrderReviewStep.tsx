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
    AlertTriangle,
    ShieldCheck,
    Tag,
    FileText,
    Receipt,
} from 'lucide-react';
import { QuantityStepper } from '@/Components/Salesman/QuantityStepper';
import { OrderReviewLineCard } from '@/Components/Salesman/OrderReviewLineCard';
import { calculateOrderPreview, formatCurrency } from '@/lib/financial';

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
    // Non-authoritative client preview calculation (ROUND_HALF_UP parity helper)
    const calculation = useMemo(() => calculateOrderPreview(cart), [cart]);

    return (
        <div className="space-y-6">
            {/* Top Navigation & Header */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-foreground">Order Review & Confirmation</h2>
                    <p className="text-sm text-muted-foreground">
                        Review customer account, product lines, line tax breakdown, and provide any order instructions.
                    </p>
                </div>
                <Button variant="outline" onClick={onBackToCatalog} className="shrink-0 gap-2">
                    <ArrowLeft className="h-4 w-4" />
                    <span>Back to Catalogue</span>
                </Button>
            </div>

            {/* Error Message Banner */}
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
                {/* Left 2 Columns: Customer & Line Items */}
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
                                    Credit Limit: {formatCurrency(customer.credit_limit)}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Order Line Items */}
                    <Card>
                        <CardHeader className="pb-3 border-b flex flex-row items-center justify-between">
                            <CardTitle className="text-base font-bold flex items-center gap-2">
                                <Receipt className="h-4 w-4 text-primary" />
                                <span>Order Lines ({calculation.totalUnits} {calculation.totalUnits === 1 ? 'unit' : 'units'})</span>
                            </CardTitle>
                            <Badge variant="outline" className="font-mono text-xs">
                                {calculation.itemCount} {calculation.itemCount === 1 ? 'line item' : 'line items'}
                            </Badge>
                        </CardHeader>

                        {/* Mobile Card Layout (<640px) */}
                        <div className="p-4 space-y-3 sm:hidden">
                            {calculation.lines.length === 0 ? (
                                <div className="text-center py-8 text-sm text-muted-foreground">
                                    Your order is empty. Please add items from the catalog.
                                </div>
                            ) : (
                                calculation.lines.map((line) => (
                                    <OrderReviewLineCard
                                        key={line.product.id}
                                        line={line}
                                        onUpdateQuantity={onUpdateQuantity}
                                        onRemoveItem={onRemoveItem}
                                        disabled={isSubmitting}
                                    />
                                ))
                            )}
                        </div>

                        {/* Desktop / Tablet Table Layout (>=640px) */}
                        <div className="hidden sm:block overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b bg-muted/40 font-medium text-muted-foreground uppercase tracking-wider text-[11px]">
                                    <tr>
                                        <th scope="col" className="py-3 px-4 w-[32%]">Product / SKU</th>
                                        <th scope="col" className="py-3 px-3 text-right">Unit Price</th>
                                        <th scope="col" className="py-3 px-3 text-center">Quantity</th>
                                        <th scope="col" className="py-3 px-3 text-right">Taxable</th>
                                        <th scope="col" className="py-3 px-3 text-right">Tax Rate / Profile</th>
                                        <th scope="col" className="py-3 px-3 text-right">Tax Amount</th>
                                        <th scope="col" className="py-3 px-4 text-right">Line Total</th>
                                        <th scope="col" className="py-3 px-3 w-[40px]"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {calculation.lines.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="py-8 text-center text-sm text-muted-foreground">
                                                Your order is empty. Please add items from the catalog.
                                            </td>
                                        </tr>
                                    ) : (
                                        calculation.lines.map((line) => (
                                            <tr key={line.product.id} className="hover:bg-muted/20 transition-colors">
                                                <td className="py-3 px-4">
                                                    <div className="font-semibold text-foreground">{line.product.name}</div>
                                                    <div className="text-[11px] font-mono text-muted-foreground flex flex-wrap items-center gap-1.5 mt-0.5">
                                                        <span>SKU: {line.product.sku}</span>
                                                        <span>•</span>
                                                        <span>Unit: {line.product.unit}</span>
                                                        {line.isCustomPrice && (
                                                            <Badge variant="outline" className="text-[9px] py-0 px-1 h-3.5 bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30 gap-0.5 font-sans">
                                                                <Tag className="h-2 w-2" />
                                                                <span>Custom</span>
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-3 text-right font-mono font-medium">
                                                    {formatCurrency(line.unitPrice)}
                                                </td>
                                                <td className="py-3 px-3 text-center">
                                                    <div className="flex justify-center">
                                                        <QuantityStepper
                                                            value={line.quantity}
                                                            min={1}
                                                            max={999999}
                                                            onChange={(qty) => onUpdateQuantity(line.product.id, qty)}
                                                            disabled={isSubmitting}
                                                            size="sm"
                                                            ariaLabel={`Quantity for ${line.product.name}`}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="py-3 px-3 text-right font-mono text-foreground">
                                                    {formatCurrency(line.taxableAmount)}
                                                </td>
                                                <td className="py-3 px-3 text-right font-mono">
                                                    <div className="text-foreground font-medium">{line.formattedTaxRate}</div>
                                                    <div className="text-[10px] text-muted-foreground truncate max-w-[100px] ml-auto">
                                                        {line.taxProfileCode}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-3 text-right font-mono text-foreground font-medium">
                                                    {formatCurrency(line.taxAmount)}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono font-bold text-foreground">
                                                    {formatCurrency(line.lineTotal)}
                                                </td>
                                                <td className="py-3 px-3 text-right">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                                        onClick={() => onRemoveItem(line.product.id)}
                                                        disabled={isSubmitting}
                                                        aria-label={`Remove ${line.product.name}`}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {/* Order Notes / Instructions */}
                    <Card>
                        <CardHeader className="pb-3 flex flex-row items-center gap-2">
                            <FileText className="h-4 w-4 text-primary" />
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
                            <div className="space-y-2.5 text-sm">
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Subtotal (Taxable):</span>
                                    <span className="font-mono font-medium text-foreground">
                                        {formatCurrency(calculation.subtotal)}
                                    </span>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Estimated Line Taxes:</span>
                                    <span className="font-mono font-medium text-foreground">
                                        {formatCurrency(calculation.taxTotal)}
                                    </span>
                                </div>
                                <div className="pt-3 border-t flex justify-between items-baseline">
                                    <span className="text-base font-bold text-foreground">Grand Total:</span>
                                    <span className="text-2xl font-bold font-mono text-primary">
                                        {formatCurrency(calculation.grandTotal)}
                                    </span>
                                </div>
                            </div>

                            <div className="rounded bg-muted/40 p-3 text-[11px] text-muted-foreground space-y-1.5 border border-border/50">
                                <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                    <ShieldCheck className="h-3.5 w-3.5 text-primary" />
                                    <span>Server-Authoritative Calculation</span>
                                </div>
                                <p>
                                    Product prices, tax profile rates, and line totals are permanently snapshotted and recalculated authoritatively on the server upon submission.
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
