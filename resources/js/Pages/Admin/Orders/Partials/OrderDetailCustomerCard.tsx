import React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { AdminOrderDetailData } from '@/types/order';
import {
    Building2,
    Mail,
    Phone,
    MapPin,
    CreditCard,
    ExternalLink,
    AlertTriangle,
    ShieldCheck,
    UserCheck,
} from 'lucide-react';

interface OrderDetailCustomerCardProps {
    customer: AdminOrderDetailData['customer'];
    salesman: AdminOrderDetailData['salesman'];
}

export default function OrderDetailCustomerCard({ customer, salesman }: OrderDetailCustomerCardProps) {
    const isProblematic = customer.status === 'ON_HOLD' || customer.status === 'INACTIVE';

    return (
        <Card className="border shadow-sm">
            <CardHeader className="pb-3 border-b bg-muted/20">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Building2 className="h-4 w-4 text-primary" />
                        <CardTitle className="text-sm font-bold">Customer Account</CardTitle>
                    </div>
                    <Link href={`/customers/${customer.id}`}>
                        <Button variant="ghost" size="sm" className="h-7 px-2 text-xs gap-1 text-muted-foreground hover:text-foreground">
                            <span>Profile</span>
                            <ExternalLink className="h-3 w-3" />
                        </Button>
                    </Link>
                </div>
            </CardHeader>

            <CardContent className="pt-4 space-y-4 text-xs">
                {/* Status Alert if account is problematic */}
                {isProblematic && (
                    <div className="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded p-2.5 flex items-start gap-2 text-amber-800 dark:text-amber-200">
                        <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
                        <div>
                            <span className="font-semibold">Account Warning:</span> Customer account is currently{' '}
                            <span className="font-bold uppercase">{customer.status_label}</span>.
                        </div>
                    </div>
                )}

                {/* Name & Account Status */}
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <div className="font-semibold text-sm text-foreground">{customer.name}</div>
                        <div className="text-muted-foreground font-mono text-[11px] mt-0.5">
                            Code: {customer.code}
                        </div>
                    </div>
                    <Badge
                        variant="outline"
                        className={
                            customer.is_active
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                        }
                    >
                        {customer.status_label}
                    </Badge>
                </div>

                {/* Contact Information */}
                <div className="space-y-1.5 pt-1 border-t border-border/60">
                    <div className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                        Primary Contact
                    </div>
                    {customer.contact_name && (
                        <div className="text-foreground font-medium">{customer.contact_name}</div>
                    )}
                    {customer.email && (
                        <div className="flex items-center gap-1.5 text-muted-foreground">
                            <Mail className="h-3.5 w-3.5 shrink-0" />
                            <a href={`mailto:${customer.email}`} className="hover:underline text-foreground/80">
                                {customer.email}
                            </a>
                        </div>
                    )}
                    {customer.phone && (
                        <div className="flex items-center gap-1.5 text-muted-foreground">
                            <Phone className="h-3.5 w-3.5 shrink-0" />
                            <a href={`tel:${customer.phone}`} className="hover:underline text-foreground/80">
                                {customer.phone}
                            </a>
                        </div>
                    )}
                </div>

                {/* Commercial Terms & Credit */}
                <div className="space-y-2 pt-2 border-t border-border/60">
                    <div className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                        Commercial Terms
                    </div>
                    <div className="grid grid-cols-2 gap-2 bg-muted/30 p-2.5 rounded border border-border/40">
                        <div>
                            <div className="text-muted-foreground text-[10px]">Payment Terms</div>
                            <div className="font-semibold text-foreground mt-0.5">
                                {customer.payment_terms || 'Due on Receipt'}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground text-[10px]">Credit Limit</div>
                            <div className="font-semibold font-mono text-foreground mt-0.5">
                                ${customer.credit_limit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Addresses */}
                <div className="space-y-2 pt-2 border-t border-border/60">
                    <div className="flex items-center justify-between text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                        <span>Addresses</span>
                        <span className="text-[10px] font-normal normal-case text-muted-foreground/80">
                            (Current Account Address)
                        </span>
                    </div>

                    <div className="space-y-2">
                        <div className="bg-muted/20 p-2 rounded border border-border/30">
                            <div className="flex items-center gap-1 text-[11px] font-medium text-muted-foreground mb-1">
                                <MapPin className="h-3 w-3 text-muted-foreground" />
                                <span>Shipping Address</span>
                            </div>
                            <div className="text-foreground whitespace-pre-line text-[11px]">
                                {customer.shipping_address || 'No shipping address recorded.'}
                            </div>
                        </div>

                        <div className="bg-muted/20 p-2 rounded border border-border/30">
                            <div className="flex items-center gap-1 text-[11px] font-medium text-muted-foreground mb-1">
                                <Building2 className="h-3 w-3 text-muted-foreground" />
                                <span>Billing Address</span>
                            </div>
                            <div className="text-foreground whitespace-pre-line text-[11px]">
                                {customer.billing_address || 'No billing address recorded.'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Assigned Salesman */}
                <div className="pt-2 border-t border-border/60">
                    <div className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">
                        Order Salesman Attribution
                    </div>
                    <div className="flex items-center gap-2 bg-muted/30 p-2 rounded border border-border/40">
                        <div className="h-7 w-7 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs">
                            {salesman.name.charAt(0)}
                        </div>
                        <div className="overflow-hidden">
                            <div className="font-semibold text-foreground text-xs truncate">{salesman.name}</div>
                            <div className="text-muted-foreground text-[10px] truncate">{salesman.email}</div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
