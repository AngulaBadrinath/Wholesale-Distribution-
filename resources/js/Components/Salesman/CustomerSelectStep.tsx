import React, { useState, useMemo } from 'react';
import { CustomerSummary } from '@/types/order';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Search, UserCheck, MapPin, CreditCard, ArrowRight, Phone, Mail, Building } from 'lucide-react';

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
                    <p className="text-sm text-muted-foreground">
                        Choose an assigned, active customer account to start drafting a new wholesale order.
                    </p>
                </div>
                {selectedCustomer && (
                    <Button onClick={onProceed} className="shrink-0 gap-2">
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
                    className="pl-9"
                />
            </div>

            {filteredCustomers.length === 0 ? (
                <Card className="border-dashed py-12 text-center">
                    <CardContent className="space-y-3">
                        <Building className="mx-auto h-10 w-10 text-muted-foreground/60" />
                        <h3 className="text-base font-semibold text-foreground">No active customers found</h3>
                        <p className="text-sm text-muted-foreground max-w-sm mx-auto">
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
                                className={`cursor-pointer transition-all duration-200 hover:shadow-md ${
                                    isSelected
                                        ? 'border-primary ring-2 ring-primary/20 bg-primary/5'
                                        : 'hover:border-primary/50'
                                }`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="space-y-1">
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {customer.code}
                                            </Badge>
                                            <CardTitle className="text-base font-bold line-clamp-1">
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
                                        <CardDescription className="text-xs">
                                            Attn: {customer.contact_name}
                                        </CardDescription>
                                    )}
                                </CardHeader>

                                <CardContent className="space-y-3 text-xs text-muted-foreground">
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

                                    <div className="pt-2 border-t flex items-center justify-between font-medium">
                                        <div className="flex items-center gap-1.5 text-foreground">
                                            <CreditCard className="h-3.5 w-3.5 text-muted-foreground" />
                                            <span>{customer.payment_terms_label || 'Default Terms'}</span>
                                        </div>
                                        <div className="text-muted-foreground">
                                            Limit: ${customer.credit_limit.toLocaleString('en-US', { minimumFractionDigits: 2 })}
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
