import React, { useState, useMemo } from 'react';
import { CustomerSummary } from '@/types/order';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Search, UserCheck, MapPin, CreditCard, ArrowRight, Phone, Mail, Building, X } from 'lucide-react';
import { formatCurrency } from '@/lib/financial';

interface CustomerSelectStepProps {
    customers: CustomerSummary[];
    selectedCustomer: CustomerSummary | null;
    onSelectCustomer: (customer: CustomerSummary) => void;
    onProceed: () => void;
}

export const CustomerSelectStep: React.FC<CustomerSelectStepProps> = ({
    customers,
    selectedCustomer,
    onSelectCustomer,
    onProceed,
}) => {
    const [searchQuery, setSearchQuery] = useState('');

    const filteredCustomers = useMemo(() => {
        if (!searchQuery.trim()) return customers;
        const q = searchQuery.toLowerCase().trim();
        return customers.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.code.toLowerCase().includes(q) ||
                (c.contact_name && c.contact_name.toLowerCase().includes(q)) ||
                (c.email && c.email.toLowerCase().includes(q)) ||
                (c.phone && c.phone.includes(q))
        );
    }, [customers, searchQuery]);

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-foreground">Select Customer Account</h2>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Choose an assigned, active customer account to start drafting a new wholesale order.
                    </p>
                </div>
                {selectedCustomer && (
                    <Button onClick={onProceed} className="shrink-0 gap-2 font-semibold shadow-xs">
                        <span>Continue to Catalogue</span>
                        <ArrowRight className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {/* Search filter */}
            <div className="relative max-w-md">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    type="text"
                    placeholder="Search by customer name, code, contact..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-9 pr-8 text-xs h-10"
                />
                {searchQuery && (
                    <button
                        type="button"
                        onClick={() => setSearchQuery('')}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1 cursor-pointer"
                        aria-label="Clear search"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>

            {filteredCustomers.length === 0 ? (
                <Card className="border-dashed py-12 text-center bg-card/40 rounded-xl">
                    <CardContent className="space-y-3">
                        <Building className="mx-auto h-10 w-10 text-muted-foreground/60 stroke-[1.5]" />
                        <h3 className="text-sm font-semibold text-foreground">No active customers found</h3>
                        <p className="text-xs text-muted-foreground max-w-sm mx-auto leading-relaxed">
                            {searchQuery
                                ? 'No assigned customers match your search query.'
                                : 'You do not have any active customers assigned to your account.'}
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {filteredCustomers.map((customer) => {
                        const isSelected = selectedCustomer?.id === customer.id;

                        return (
                            <Card
                                key={customer.id}
                                onClick={() => onSelectCustomer(customer)}
                                className={`cursor-pointer transition-all duration-200 rounded-xl border ${
                                    isSelected
                                        ? 'border-primary ring-2 ring-primary/20 bg-primary/[0.03] shadow-xs'
                                        : 'border-border/80 hover:border-border hover:shadow-xs'
                                }`}
                            >
                                <CardHeader className="p-4 pb-2 border-b border-border/60">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="space-y-1 min-w-0 flex-1">
                                            <div className="flex items-center gap-1.5">
                                                <Badge variant="outline" className="font-mono text-[10px] py-0 shrink-0">
                                                    {customer.code}
                                                </Badge>
                                                <Badge
                                                    variant={customer.status === 'ACTIVE' ? 'success' : 'secondary'}
                                                    className="text-[10px] py-0"
                                                >
                                                    {customer.status_label || customer.status}
                                                </Badge>
                                            </div>
                                            <CardTitle className="text-sm font-bold truncate text-foreground" title={customer.name}>
                                                {customer.name}
                                            </CardTitle>
                                        </div>
                                        {isSelected && (
                                            <div className="h-6 w-6 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                                                <UserCheck className="h-3.5 w-3.5" />
                                            </div>
                                        )}
                                    </div>
                                    {customer.contact_name && (
                                        <CardDescription className="text-[11px] truncate">
                                            Attn: {customer.contact_name}
                                        </CardDescription>
                                    )}
                                </CardHeader>

                                <CardContent className="p-4 space-y-2.5 text-xs text-muted-foreground">
                                    {customer.phone && (
                                        <div className="flex items-center gap-2">
                                            <Phone className="h-3.5 w-3.5 shrink-0 text-muted-foreground/70" />
                                            <span className="truncate">{customer.phone}</span>
                                        </div>
                                    )}
                                    {customer.email && (
                                        <div className="flex items-center gap-2">
                                            <Mail className="h-3.5 w-3.5 shrink-0 text-muted-foreground/70" />
                                            <span className="truncate">{customer.email}</span>
                                        </div>
                                    )}
                                    <div className="flex items-start gap-2">
                                        <MapPin className="h-3.5 w-3.5 shrink-0 text-muted-foreground/70 mt-0.5" />
                                        <span className="line-clamp-2">
                                            {customer.shipping_address || customer.billing_address || 'No address specified'}
                                        </span>
                                    </div>

                                    <div className="pt-2 border-t border-border/50 flex items-center justify-between font-medium text-[11px]">
                                        <div className="flex items-center gap-1 text-foreground">
                                            <CreditCard className="h-3.5 w-3.5 text-muted-foreground" />
                                            <span>{customer.payment_terms_label || 'Default Terms'}</span>
                                        </div>
                                        <div className="text-muted-foreground font-mono">
                                            Limit: {formatCurrency(customer.credit_limit)}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
};
