import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    FileText,
    Printer,
    Download,
    ArrowLeft,
    Building2,
    Calendar,
    DollarSign,
    CheckCircle2,
    Clock,
    AlertCircle,
    User,
    MapPin,
    CreditCard,
    ExternalLink
} from 'lucide-react';

interface InvoiceItemRow {
    id: number;
    product_name_snapshot: string;
    sku_snapshot: string;
    unit_snapshot: string;
    quantity: number;
    unit_price: string | number;
    tax_profile_code_snapshot?: string | null;
    tax_profile_name_snapshot?: string | null;
    tax_rate_snapshot: string | number;
    taxable_amount: string | number;
    tax_amount: string | number;
    line_total: string | number;
}

interface PaymentRow {
    id: number;
    payment_number: string;
    payment_method: string;
    amount: string | number;
    status: string;
    transaction_date: string;
    reference_number?: string | null;
}

interface InvoiceDetail {
    id: number;
    invoice_number: string;
    order_id: number;
    customer_id: number;
    status: 'ISSUED' | 'PAID' | 'VOID';
    payment_status: 'UNPAID' | 'PARTIALLY_PAID' | 'PAID' | 'OVERPAID' | 'REFUNDED';
    invoice_date: string;
    due_date: string;
    payment_terms: string;
    currency: string;
    subtotal: string | number;
    tax_total: string | number;
    adjustment_total: string | number;
    grand_total: string | number;
    amount_paid: string | number;
    amount_due: string | number;
    customer_name_snapshot: string;
    customer_code_snapshot: string;
    customer_contact_snapshot?: string | null;
    customer_email_snapshot?: string | null;
    customer_phone_snapshot?: string | null;
    customer_tax_id_snapshot?: string | null;
    billing_address_line1_snapshot: string;
    billing_address_line2_snapshot?: string | null;
    billing_city_snapshot: string;
    billing_state_snapshot: string;
    billing_postal_code_snapshot: string;
    billing_country_snapshot: string;
    shipping_address_line1_snapshot: string;
    shipping_address_line2_snapshot?: string | null;
    shipping_city_snapshot: string;
    shipping_state_snapshot: string;
    shipping_postal_code_snapshot: string;
    shipping_country_snapshot: string;
    company_legal_name_snapshot: string;
    company_dba_name_snapshot?: string | null;
    company_address_snapshot: string;
    company_phone_snapshot?: string | null;
    company_email_snapshot?: string | null;
    company_tax_id_snapshot?: string | null;
    company_state_tax_id_snapshot?: string | null;
    invoice_footer_note_snapshot?: string | null;
    pdf_path?: string | null;
    pdf_generated_at?: string | null;
    created_at: string;
    items: InvoiceItemRow[];
    order?: {
        id: number;
        order_number: string;
        status: string;
        payments?: PaymentRow[];
    };
    customer?: {
        id: number;
        name: string;
        code: string;
    };
    creator?: {
        id: number;
        name: string;
        email: string;
    };
}

interface Props {
    invoice: InvoiceDetail;
    isSalesmanView?: boolean;
}

export default function InvoiceShow({ invoice, isSalesmanView = false }: Props) {
    const getStatusBadge = (st: string) => {
        switch (st) {
            case 'PAID':
                return <Badge variant="success">Paid</Badge>;
            case 'ISSUED':
                return <Badge variant="default">Issued</Badge>;
            case 'VOID':
                return <Badge variant="destructive">Void</Badge>;
            default:
                return <Badge variant="secondary">{st}</Badge>;
        }
    };

    const getPaymentBadge = (pst: string) => {
        switch (pst) {
            case 'PAID':
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Settled</span>;
            case 'PARTIALLY_PAID':
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Partially Paid</span>;
            case 'UNPAID':
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Unpaid</span>;
            default:
                return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300">{pst}</span>;
        }
    };

    const verifiedPayments = invoice.order?.payments?.filter(p => p.status === 'VERIFIED') || [];

    return (
        <AppLayout>
            <Head title={`Invoice ${invoice.invoice_number}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Navigation and Top Actions Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="outline" size="sm">
                            <Link href={isSalesmanView ? route('salesman.invoices.index') : route('admin.invoices.index')}>
                                <ArrowLeft className="w-4 h-4 mr-1.5" />
                                {isSalesmanView ? "Customer Invoices" : "All Invoices"}
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold font-mono text-slate-900 dark:text-white">
                                    {invoice.invoice_number}
                                </h1>
                                {getStatusBadge(invoice.status)}
                                {getPaymentBadge(invoice.payment_status)}
                            </div>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Issued on {new Date(invoice.invoice_date).toLocaleDateString()} &bull; Payment Terms: {invoice.payment_terms}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a href={route('invoices.print', invoice.id)} target="_blank" rel="noopener noreferrer">
                                <Printer className="w-4 h-4 mr-1.5" />
                                Print HTML
                            </a>
                        </Button>
                        <Button asChild size="sm" className="bg-indigo-600 hover:bg-indigo-700 text-white">
                            <a href={`/invoices/${invoice.id}/pdf`} target="_blank" rel="noopener noreferrer">
                                <Download className="w-4 h-4 mr-1.5" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </div>

                {/* Main Invoice Card (Document Presentation) */}
                <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sm:p-8 space-y-8">
                    {/* Company and Document Meta Header */}
                    <div className="flex flex-col sm:flex-row justify-between items-start gap-6 border-b border-slate-200 dark:border-slate-800 pb-6">
                        <div>
                            <h2 className="text-lg font-bold uppercase tracking-wide text-slate-900 dark:text-white">
                                {invoice.company_legal_name_snapshot}
                            </h2>
                            {invoice.company_dba_name_snapshot && (
                                <p className="text-xs font-semibold text-slate-600 dark:text-slate-400">
                                    d/b/a {invoice.company_dba_name_snapshot}
                                </p>
                            )}
                            <div className="text-xs text-slate-500 mt-2 space-y-0.5">
                                <div>{invoice.company_address_snapshot}</div>
                                {invoice.company_phone_snapshot && <div>Phone: {invoice.company_phone_snapshot}</div>}
                                {invoice.company_email_snapshot && <div>Email: {invoice.company_email_snapshot}</div>}
                                {invoice.company_tax_id_snapshot && <div>Tax ID / EIN: {invoice.company_tax_id_snapshot}</div>}
                                {invoice.company_state_tax_id_snapshot && <div>State Tax ID: {invoice.company_state_tax_id_snapshot}</div>}
                            </div>
                        </div>

                        <div className="text-left sm:text-right">
                            <span className="text-2xl font-black tracking-tight text-slate-900 dark:text-white block mb-2">
                                TAX INVOICE
                            </span>
                            <div className="text-xs space-y-1 font-mono">
                                <div><span className="text-slate-400">Invoice #:</span> <strong>{invoice.invoice_number}</strong></div>
                                <div><span className="text-slate-400">Invoice Date:</span> {new Date(invoice.invoice_date).toLocaleDateString()}</div>
                                <div><span className="text-slate-400">Due Date:</span> {new Date(invoice.due_date).toLocaleDateString()}</div>
                                {invoice.order && (
                                    <div>
                                        <span className="text-slate-400">Order Ref:</span>{' '}
                                        <Link href={route('orders.show', invoice.order.id)} className="text-indigo-600 hover:underline">
                                            {invoice.order.order_number}
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Customer Billed / Shipped Addresses Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                                Billed To
                            </h3>
                            <div className="font-semibold text-slate-900 dark:text-white">
                                {invoice.customer_name_snapshot}
                            </div>
                            <div className="text-xs text-slate-500 font-mono mt-0.5">
                                Customer Code: {invoice.customer_code_snapshot}
                            </div>
                            <div className="text-xs text-slate-600 dark:text-slate-300 mt-2 space-y-0.5">
                                {invoice.customer_contact_snapshot && <div>Attn: {invoice.customer_contact_snapshot}</div>}
                                <div>{invoice.billing_address_line1_snapshot}</div>
                                {invoice.billing_address_line2_snapshot && <div>{invoice.billing_address_line2_snapshot}</div>}
                                <div>{invoice.billing_city_snapshot}, {invoice.billing_state_snapshot} {invoice.billing_postal_code_snapshot}</div>
                                <div>{invoice.billing_country_snapshot}</div>
                                {invoice.customer_tax_id_snapshot && <div className="mt-1 font-mono">Tax ID: {invoice.customer_tax_id_snapshot}</div>}
                            </div>
                        </div>

                        <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                                Shipped To
                            </h3>
                            <div className="font-semibold text-slate-900 dark:text-white">
                                {invoice.customer_name_snapshot}
                            </div>
                            <div className="text-xs text-slate-600 dark:text-slate-300 mt-2 space-y-0.5">
                                <div>{invoice.shipping_address_line1_snapshot}</div>
                                {invoice.shipping_address_line2_snapshot && <div>{invoice.shipping_address_line2_snapshot}</div>}
                                <div>{invoice.shipping_city_snapshot}, {invoice.shipping_state_snapshot} {invoice.shipping_postal_code_snapshot}</div>
                                <div>{invoice.shipping_country_snapshot}</div>
                                {invoice.customer_phone_snapshot && <div className="mt-1">Phone: {invoice.customer_phone_snapshot}</div>}
                            </div>
                        </div>
                    </div>

                    {/* Line Items Table (RULE-DOC-001: STRICTLY ZERO PRODUCT IMAGES) */}
                    <div className="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th className="px-3 py-2.5 text-center w-10">#</th>
                                    <th className="px-3 py-2.5">SKU</th>
                                    <th className="px-3 py-2.5">Description</th>
                                    <th className="px-3 py-2.5 text-center">Unit</th>
                                    <th className="px-3 py-2.5 text-right">Qty</th>
                                    <th className="px-3 py-2.5 text-right">Unit Price</th>
                                    <th className="px-3 py-2.5 text-right">Tax Rate</th>
                                    <th className="px-3 py-2.5 text-right">Tax</th>
                                    <th className="px-3 py-2.5 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {invoice.items.map((item, index) => (
                                    <tr key={item.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                        <td className="px-3 py-2.5 text-center text-xs text-slate-400 font-mono">{index + 1}</td>
                                        <td className="px-3 py-2.5 font-mono text-xs font-semibold text-slate-900 dark:text-white">{item.sku_snapshot}</td>
                                        <td className="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-200">{item.product_name_snapshot}</td>
                                        <td className="px-3 py-2.5 text-center text-xs text-slate-500">{item.unit_snapshot}</td>
                                        <td className="px-3 py-2.5 text-right font-mono font-semibold text-slate-900 dark:text-white">{item.quantity}</td>
                                        <td className="px-3 py-2.5 text-right font-mono text-xs text-slate-600 dark:text-slate-300">
                                            ${Number(item.unit_price).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono text-xs text-slate-500">
                                            {(Number(item.tax_rate_snapshot) * 100).toFixed(2)}%
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono text-xs text-slate-600 dark:text-slate-300">
                                            ${Number(item.tax_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono font-bold text-slate-900 dark:text-white">
                                            ${Number(item.line_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Summary and Financial Totals */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        {/* Remittance and Payments Summary */}
                        <div className="space-y-4">
                            <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800">
                                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">
                                    Payment Instructions
                                </h4>
                                <p className="text-xs text-slate-600 dark:text-slate-300">
                                    Payment is due upon <strong>{invoice.payment_terms}</strong> terms on or before <strong>{new Date(invoice.due_date).toLocaleDateString()}</strong>.
                                </p>
                                <p className="text-xs text-slate-500 mt-1">
                                    Please reference invoice <strong>{invoice.invoice_number}</strong> on all remittances.
                                </p>
                            </div>

                            {verifiedPayments.length > 0 && (
                                <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800 space-y-2">
                                    <h4 className="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                        Verified Payments Applied
                                    </h4>
                                    <div className="space-y-1.5">
                                        {verifiedPayments.map(p => (
                                            <div key={p.id} className="flex justify-between items-center text-xs font-mono">
                                                <span className="text-slate-700 dark:text-slate-300 font-semibold">{p.payment_number} ({p.payment_method})</span>
                                                <span className="text-emerald-600 font-bold">+${Number(p.amount).toFixed(2)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Totals Breakdown */}
                        <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800">
                            <div className="space-y-2.5 font-mono text-sm">
                                <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                    <span>Subtotal:</span>
                                    <span>${Number(invoice.subtotal).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                    <span>Tax Total:</span>
                                    <span>${Number(invoice.tax_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                </div>
                                {Number(invoice.adjustment_total) !== 0 && (
                                    <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                        <span>Adjustments:</span>
                                        <span>${Number(invoice.adjustment_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                    </div>
                                )}
                                <div className="border-t border-slate-200 dark:border-slate-700 pt-2 flex justify-between text-base font-bold text-slate-900 dark:text-white">
                                    <span>Grand Total ({invoice.currency}):</span>
                                    <span>${Number(invoice.grand_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="flex justify-between text-emerald-600 font-semibold text-xs">
                                    <span>Amount Paid:</span>
                                    <span>${Number(invoice.amount_paid).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="border-t border-slate-200 dark:border-slate-700 pt-2 flex justify-between text-base font-bold text-rose-600 dark:text-rose-400">
                                    <span>Balance Due:</span>
                                    <span>${Number(invoice.amount_due).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Footer note */}
                    {invoice.invoice_footer_note_snapshot && (
                        <div className="border-t border-slate-200 dark:border-slate-800 pt-4 text-center text-xs text-slate-500">
                            {invoice.invoice_footer_note_snapshot}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
