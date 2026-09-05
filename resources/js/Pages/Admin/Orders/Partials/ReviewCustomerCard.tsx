import React from 'react';
import { Building2, User, Phone, Mail, MapPin, CreditCard, ShieldAlert, AlertCircle } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { AdminOrderReviewData } from '@/types/order';

interface ReviewCustomerCardProps {
    customer: AdminOrderReviewData['customer'];
    salesman: AdminOrderReviewData['salesman'];
}

export default function ReviewCustomerCard({ customer, salesman }: ReviewCustomerCardProps) {
    const formattedCreditLimit = customer.credit_limit > 0
        ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(customer.credit_limit)
        : 'No Credit Limit';

    return (
        <Card className="shadow-xs border">
            <CardHeader className="pb-3 border-b bg-muted/20">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Building2 className="h-4 w-4 text-primary" />
                        <CardTitle className="text-sm font-bold text-foreground">
                            Customer Account
                        </CardTitle>
                    </div>
                    <Badge
                        variant={customer.is_active ? 'secondary' : 'destructive'}
                        className="text-[10px] font-semibold"
                    >
                        {customer.status_label}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="pt-4 space-y-4 text-xs">
                {/* Hold or Inactive Warning Callout */}
                {customer.is_on_hold && (
                    <div className="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-md text-amber-900 dark:text-amber-200 flex items-start gap-2">
                        <AlertCircle className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div>
                            <span className="font-bold">Account On Hold:</span> This customer account is on administrative hold. Review with finance before proceeding.
                        </div>
                    </div>
                )}

                {/* Customer Identity */}
                <div className="space-y-1">
                    <div className="font-bold text-sm text-foreground flex items-center justify-between">
                        <span>{customer.name}</span>
                        <Badge variant="outline" className="font-mono text-[10px]">
                            {customer.code}
                        </Badge>
                    </div>
                    {customer.contact_name && (
                        <div className="text-muted-foreground flex items-center gap-1.5 pt-0.5">
                            <User className="h-3 w-3 text-muted-foreground/70" />
                            <span>Attn: {customer.contact_name}</span>
                        </div>
                    )}
                </div>

                {/* Contact Information */}
                <div className="grid grid-cols-1 gap-1.5 pt-1 text-muted-foreground border-t pt-3">
                    {customer.phone && (
                        <div className="flex items-center gap-2">
                            <Phone className="h-3 w-3 shrink-0 text-muted-foreground/70" />
                            <span>{customer.phone}</span>
                        </div>
                    )}
                    {customer.email && (
                        <div className="flex items-center gap-2">
                            <Mail className="h-3 w-3 shrink-0 text-muted-foreground/70" />
                            <span className="truncate">{customer.email}</span>
                        </div>
                    )}
                    {customer.tax_id && (
                        <div className="text-[11px] text-muted-foreground/80">
                            Tax ID: <span className="font-mono">{customer.tax_id}</span>
                        </div>
                    )}
                </div>

                {/* Credit & Terms Context */}
                <div className="space-y-2 border-t pt-3">
                    <div className="flex items-center gap-1.5 font-semibold text-foreground">
                        <CreditCard className="h-3.5 w-3.5 text-primary" />
                        <span>Commercial Terms</span>
                    </div>
                    <div className="grid grid-cols-2 gap-2 bg-muted/30 p-2.5 rounded-md border text-[11px]">
                        <div>
                            <span className="text-muted-foreground block">Credit Limit</span>
                            <span className="font-bold text-foreground font-mono">
                                {formattedCreditLimit}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground block">Payment Terms</span>
                            <span className="font-bold text-foreground">
                                {customer.payment_terms || 'Due on Receipt'}
                            </span>
                        </div>
                    </div>
                    <p className="text-[10px] text-muted-foreground/80 italic">
                        * Note: Ledger receivables balances are deferred to Phase 10.
                    </p>
                </div>

                {/* Current Account Addresses */}
                <div className="space-y-2 border-t pt-3">
                    <div className="flex items-center gap-1.5 font-semibold text-foreground">
                        <MapPin className="h-3.5 w-3.5 text-primary" />
                        <span>Current Account Addresses</span>
                    </div>
                    <div className="space-y-2 text-[11px] text-muted-foreground">
                        {customer.shipping_address && (
                            <div className="bg-muted/20 p-2 rounded-md border">
                                <span className="font-medium text-foreground block text-[10px] uppercase tracking-wider mb-0.5">Shipping Destination</span>
                                <span className="whitespace-pre-line leading-relaxed">{customer.shipping_address}</span>
                            </div>
                        )}
                        {customer.billing_address && (
                            <div className="bg-muted/20 p-2 rounded-md border">
                                <span className="font-medium text-foreground block text-[10px] uppercase tracking-wider mb-0.5">Billing Address</span>
                                <span className="whitespace-pre-line leading-relaxed">{customer.billing_address}</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Assigned Salesman Context */}
                <div className="border-t pt-3 space-y-1">
                    <span className="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider block">
                        Order Placed By Sales Representative
                    </span>
                    <div className="flex items-center justify-between text-xs">
                        <span className="font-bold text-foreground">{salesman.name}</span>
                        <span className="text-muted-foreground text-[11px]">{salesman.email}</span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
