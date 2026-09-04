import React, { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { CompanyInformation } from '@/types';
import {
    Building2,
    MapPin,
    Phone,
    Mail,
    Globe,
    FileText,
    Receipt,
    Globe2,
    CheckCircle2,
    AlertCircle,
    Loader2,
    Save,
    RotateCcw,
} from 'lucide-react';

interface CompanyInformationIndexProps {
    company: CompanyInformation;
    status?: string;
}

export default function CompanyInformationIndex({ company, status }: CompanyInformationIndexProps) {
    const { data, setData, put, processing, errors, recentlySuccessful, reset } = useForm({
        legal_name: company.legal_name || '',
        dba_name: company.dba_name || '',
        address_line1: company.address_line1 || '',
        address_line2: company.address_line2 || '',
        city: company.city || '',
        state: company.state || '',
        postal_code: company.postal_code || '',
        country: company.country || 'US',
        phone: company.phone || '',
        email: company.email || '',
        website: company.website || '',
        tax_id: company.tax_id || '',
        state_tax_id: company.state_tax_id || '',
        currency: company.currency || 'USD',
        timezone: company.timezone || 'America/New_York',
        invoice_footer_note: company.invoice_footer_note || '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        put('/system/company', {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="Company Settings">
            <Head title="Company Information & Business Settings" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Page Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-border">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Company Information
                            </h1>
                            <Badge variant="outline" className="text-xs font-mono font-normal">
                                Singleton Entity
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Authoritative legal entity details, registered operating address, tax identifiers, and invoicing defaults.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {company.updated_at && (
                            <span className="text-xs text-muted-foreground font-mono">
                                Last updated: {new Date(company.updated_at).toLocaleDateString()}
                            </span>
                        )}
                    </div>
                </div>

                {/* Feedback Alerts */}
                {(status || recentlySuccessful) && (
                    <div className="flex items-center gap-2 p-4 text-sm text-emerald-400 bg-emerald-950/40 border border-emerald-800/60 rounded-lg">
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-400" />
                        <span>{status || 'Company information updated successfully.'}</span>
                    </div>
                )}

                {Object.keys(errors).length > 0 && (
                    <div className="flex items-start gap-2 p-4 text-sm text-destructive bg-destructive/10 border border-destructive/20 rounded-lg">
                        <AlertCircle className="h-4 w-4 shrink-0 text-destructive mt-0.5" />
                        <div className="space-y-1">
                            <span className="font-semibold">Please correct the following errors:</span>
                            <ul className="list-disc list-inside text-xs space-y-0.5">
                                {Object.entries(errors).map(([key, err]) => (
                                    <li key={key}>{err}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Section 1: Legal & Commercial Identity */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Building2 className="h-4 w-4 text-primary" />
                                <CardTitle className="text-base font-semibold">Legal & Commercial Identity</CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Registered corporate entity name and optional trade / DBA name.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label htmlFor="legal_name" className="text-xs font-medium text-foreground">
                                        Legal Entity Name <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="legal_name"
                                        type="text"
                                        value={data.legal_name}
                                        onChange={(e) => setData('legal_name', e.target.value)}
                                        placeholder="e.g. Wholesale Distribution LLC"
                                        required
                                        disabled={processing}
                                        className={errors.legal_name ? 'border-destructive' : ''}
                                    />
                                    {errors.legal_name && (
                                        <p className="text-xs text-destructive">{errors.legal_name}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="dba_name" className="text-xs font-medium text-foreground">
                                        Trade / DBA Name (Optional)
                                    </label>
                                    <Input
                                        id="dba_name"
                                        type="text"
                                        value={data.dba_name}
                                        onChange={(e) => setData('dba_name', e.target.value)}
                                        placeholder="e.g. Apex Wholesale Logistics"
                                        disabled={processing}
                                        className={errors.dba_name ? 'border-destructive' : ''}
                                    />
                                    {errors.dba_name && (
                                        <p className="text-xs text-destructive">{errors.dba_name}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <label htmlFor="website" className="text-xs font-medium text-foreground flex items-center gap-1.5">
                                    <Globe className="h-3.5 w-3.5 text-muted-foreground" />
                                    Corporate Website (Optional)
                                </label>
                                <Input
                                    id="website"
                                    type="url"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    placeholder="https://example.com"
                                    disabled={processing}
                                    className={errors.website ? 'border-destructive' : ''}
                                />
                                {errors.website && (
                                    <p className="text-xs text-destructive">{errors.website}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Section 2: Operating Address */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-primary" />
                                <CardTitle className="text-base font-semibold">Operating Address</CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Registered physical headquarters address appearing on formal business documents and invoices.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5 sm:col-span-2">
                                    <label htmlFor="address_line1" className="text-xs font-medium text-foreground">
                                        Street Address <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="address_line1"
                                        type="text"
                                        value={data.address_line1}
                                        onChange={(e) => setData('address_line1', e.target.value)}
                                        placeholder="e.g. 100 Distribution Blvd"
                                        required
                                        disabled={processing}
                                        className={errors.address_line1 ? 'border-destructive' : ''}
                                    />
                                    {errors.address_line1 && (
                                        <p className="text-xs text-destructive">{errors.address_line1}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5 sm:col-span-2">
                                    <label htmlFor="address_line2" className="text-xs font-medium text-foreground">
                                        Suite / Unit / Building (Optional)
                                    </label>
                                    <Input
                                        id="address_line2"
                                        type="text"
                                        value={data.address_line2}
                                        onChange={(e) => setData('address_line2', e.target.value)}
                                        placeholder="e.g. Suite 400"
                                        disabled={processing}
                                        className={errors.address_line2 ? 'border-destructive' : ''}
                                    />
                                    {errors.address_line2 && (
                                        <p className="text-xs text-destructive">{errors.address_line2}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="city" className="text-xs font-medium text-foreground">
                                        City <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="city"
                                        type="text"
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="e.g. Atlanta"
                                        required
                                        disabled={processing}
                                        className={errors.city ? 'border-destructive' : ''}
                                    />
                                    {errors.city && (
                                        <p className="text-xs text-destructive">{errors.city}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="state" className="text-xs font-medium text-foreground">
                                        State / Province <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="state"
                                        type="text"
                                        value={data.state}
                                        onChange={(e) => setData('state', e.target.value)}
                                        placeholder="e.g. GA"
                                        required
                                        disabled={processing}
                                        className={errors.state ? 'border-destructive' : ''}
                                    />
                                    {errors.state && (
                                        <p className="text-xs text-destructive">{errors.state}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="postal_code" className="text-xs font-medium text-foreground">
                                        Postal / ZIP Code <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="postal_code"
                                        type="text"
                                        value={data.postal_code}
                                        onChange={(e) => setData('postal_code', e.target.value)}
                                        placeholder="e.g. 30301"
                                        required
                                        disabled={processing}
                                        className={errors.postal_code ? 'border-destructive' : ''}
                                    />
                                    {errors.postal_code && (
                                        <p className="text-xs text-destructive">{errors.postal_code}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="country" className="text-xs font-medium text-foreground">
                                        Country Code (2-letter ISO) <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="country"
                                        type="text"
                                        maxLength={2}
                                        value={data.country}
                                        onChange={(e) => setData('country', e.target.value.toUpperCase())}
                                        placeholder="US"
                                        required
                                        disabled={processing}
                                        className={errors.country ? 'border-destructive font-mono' : 'font-mono'}
                                    />
                                    {errors.country && (
                                        <p className="text-xs text-destructive">{errors.country}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Section 3: Business Contact Details */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Phone className="h-4 w-4 text-primary" />
                                <CardTitle className="text-base font-semibold">Business Contact Details</CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Primary communication channels for accounting, customer service, and dispatch.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label htmlFor="phone" className="text-xs font-medium text-foreground flex items-center gap-1.5">
                                        <Phone className="h-3.5 w-3.5 text-muted-foreground" />
                                        Primary Phone <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="e.g. +1 (800) 555-0199"
                                        required
                                        disabled={processing}
                                        className={errors.phone ? 'border-destructive' : ''}
                                    />
                                    {errors.phone && (
                                        <p className="text-xs text-destructive">{errors.phone}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="email" className="text-xs font-medium text-foreground flex items-center gap-1.5">
                                        <Mail className="h-3.5 w-3.5 text-muted-foreground" />
                                        Support / Billing Email <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="e.g. support@example.com"
                                        required
                                        disabled={processing}
                                        className={errors.email ? 'border-destructive' : ''}
                                    />
                                    {errors.email && (
                                        <p className="text-xs text-destructive">{errors.email}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Section 4: Tax & Registration Identifiers */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Receipt className="h-4 w-4 text-primary" />
                                <CardTitle className="text-base font-semibold">Tax & Business Registration</CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Federal and state tax registration numbers for compliance and invoices.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label htmlFor="tax_id" className="text-xs font-medium text-foreground">
                                        Federal Tax ID / EIN (Optional)
                                    </label>
                                    <Input
                                        id="tax_id"
                                        type="text"
                                        value={data.tax_id}
                                        onChange={(e) => setData('tax_id', e.target.value)}
                                        placeholder="e.g. 12-3456789"
                                        disabled={processing}
                                        className={errors.tax_id ? 'border-destructive font-mono' : 'font-mono'}
                                    />
                                    {errors.tax_id && (
                                        <p className="text-xs text-destructive">{errors.tax_id}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="state_tax_id" className="text-xs font-medium text-foreground">
                                        State Tax / Reseller ID (Optional)
                                    </label>
                                    <Input
                                        id="state_tax_id"
                                        type="text"
                                        value={data.state_tax_id}
                                        onChange={(e) => setData('state_tax_id', e.target.value)}
                                        placeholder="e.g. GA-987654"
                                        disabled={processing}
                                        className={errors.state_tax_id ? 'border-destructive font-mono' : 'font-mono'}
                                    />
                                    {errors.state_tax_id && (
                                        <p className="text-xs text-destructive">{errors.state_tax_id}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Section 5: Regional & Invoicing Defaults */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Globe2 className="h-4 w-4 text-primary" />
                                <CardTitle className="text-base font-semibold">Regional & Invoicing Defaults</CardTitle>
                            </div>
                            <CardDescription className="text-xs">
                                Default system currency, server timezone, and standard invoice footer terms.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label htmlFor="currency" className="text-xs font-medium text-foreground">
                                        Base Currency Code <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="currency"
                                        type="text"
                                        maxLength={3}
                                        value={data.currency}
                                        onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                                        placeholder="USD"
                                        required
                                        disabled={processing}
                                        className={errors.currency ? 'border-destructive font-mono' : 'font-mono'}
                                    />
                                    {errors.currency && (
                                        <p className="text-xs text-destructive">{errors.currency}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="timezone" className="text-xs font-medium text-foreground">
                                        Operational Timezone <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        id="timezone"
                                        type="text"
                                        value={data.timezone}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                        placeholder="America/New_York"
                                        required
                                        disabled={processing}
                                        className={errors.timezone ? 'border-destructive' : ''}
                                    />
                                    {errors.timezone && (
                                        <p className="text-xs text-destructive">{errors.timezone}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <label htmlFor="invoice_footer_note" className="text-xs font-medium text-foreground flex items-center gap-1.5">
                                    <FileText className="h-3.5 w-3.5 text-muted-foreground" />
                                    Default Invoice Footer Terms (Optional)
                                </label>
                                <textarea
                                    id="invoice_footer_note"
                                    rows={3}
                                    value={data.invoice_footer_note}
                                    onChange={(e) => setData('invoice_footer_note', e.target.value)}
                                    placeholder="e.g. Thank you for your business. Invoices are payable within 30 days."
                                    disabled={processing}
                                    className={`w-full rounded-md border bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${
                                        errors.invoice_footer_note ? 'border-destructive' : 'border-input'
                                    }`}
                                />
                                {errors.invoice_footer_note && (
                                    <p className="text-xs text-destructive">{errors.invoice_footer_note}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Action Buttons */}
                    <div className="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => reset()}
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            <RotateCcw className="h-4 w-4 mr-2" />
                            Reset Changes
                        </Button>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Saving Settings...
                                </>
                            ) : (
                                <>
                                    <Save className="h-4 w-4 mr-2" />
                                    Save Company Settings
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
