/**
 * Financial & Tax Preview Utilities (Client-Side Preview Only)
 *
 * IMPORTANT:
 * The browser is NEVER the financial authority. All calculations in this module
 * exist solely for immediate visual feedback, client-side draft previews, and UI responsiveness.
 * The server (OrderService & TaxCalculationService) remains the sole authoritative source of truth
 * for price boundaries, line-item taxes, subtotal, tax total, grand total, and immutable historical snapshots.
 */

import { CartLineItem, CatalogProduct } from '@/types/order';

export interface LineFinancialPreview {
    product: CatalogProduct;
    quantity: number;
    unitPrice: string;
    isCustomPrice: boolean;
    taxableAmount: string;
    taxProfileCode: string;
    taxProfileName: string;
    taxRate: string;
    formattedTaxRate: string;
    isExempt: boolean;
    taxAmount: string;
    lineTotal: string;
}

export interface OrderFinancialPreview {
    lines: LineFinancialPreview[];
    itemCount: number;
    totalUnits: number;
    subtotal: string;
    taxTotal: string;
    adjustmentTotal: string;
    grandTotal: string;
    numericSubtotal: number;
    numericTaxTotal: number;
    numericGrandTotal: number;
}


/**
 * Format a numeric or string monetary value to standard USD currency string ($X,XXX.XX).
 */
export function formatCurrency(value: number | string): string {
    const num = typeof value === 'string' ? parseFloat(value) || 0 : value;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

/**
 * Format a numeric decimal string as standard 2-decimal display ($XX.XX without currency symbol).
 */
export function formatDecimal(value: number | string, decimals: number = 2): string {
    const num = typeof value === 'string' ? parseFloat(value) || 0 : value;
    return num.toFixed(decimals);
}

/**
 * Format a tax rate percentage cleanly and consistently.
 * Preserves meaningful decimal precision (e.g. 8.25%, 8.2%, 8.125%, 8%) without unnecessary trailing zeros.
 *
 * Examples:
 * - "8.2500" -> "8.25%"
 * - "8.2000" -> "8.2%"
 * - "8.0000" -> "8%"
 * - "0.0000" -> "0%"
 * - "8.1250" -> "8.125%"
 * - "100.0000" -> "100%"
 */
export function formatTaxPercentage(value: number | string | null | undefined, includePercent: boolean = true): string {
    if (value === null || value === undefined || value === '') {
        return includePercent ? '0%' : '0';
    }
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) {
        return includePercent ? '0%' : '0';
    }

    const formatted = parseFloat(num.toFixed(4)).toString();
    return includePercent ? `${formatted}%` : formatted;
}

/**
 * Client-side ROUND_HALF_UP rounding for monetary preview calculations.
 * Uses integer cent conversion with epsilon correction to prevent IEEE-754 floating-point inaccuracies.
 */
export function roundHalfUpPreview(value: number, decimals: number = 2): number {
    const factor = Math.pow(10, decimals);
    const sign = value < 0 ? -1 : 1;
    const absVal = Math.abs(value);
    return (sign * Math.floor(absVal * factor + 0.5 + Number.EPSILON)) / factor;
}

/**
 * Calculate client-side line-level tax and financial preview for an individual cart item.
 */
export function calculateLinePreview(item: CartLineItem): LineFinancialPreview {
    const price = Math.max(0, parseFloat(item.unit_price) || 0);
    const quantity = Math.max(1, Math.min(999999, Math.floor(item.quantity) || 1));
    const taxProfile = item.product.tax_profile;

    const rate = taxProfile ? parseFloat(taxProfile.rate) || 0 : 0;
    const isExempt = taxProfile ? (taxProfile.is_exempt || rate === 0) : true;

    // Taxable amount = unit_price * quantity
    const taxableNum = roundHalfUpPreview(price * quantity, 2);

    // Line tax = ROUND_HALF_UP(taxable_amount * (tax_rate / 100), 2)
    const rawTax = taxableNum * (rate / 100);
    const taxNum = isExempt ? 0 : roundHalfUpPreview(rawTax, 2);

    // Line total = taxable_amount + line_tax
    const lineTotalNum = roundHalfUpPreview(taxableNum + taxNum, 2);

    const taxProfileCode = taxProfile?.code || 'NO-TAX';
    const taxProfileName = taxProfile?.name || 'No Tax Profile';
    const formattedTaxRate = taxProfile?.formatted_rate || `${rate.toFixed(2)}%`;

    return {
        product: item.product,
        quantity,
        unitPrice: price.toFixed(2),
        isCustomPrice: item.is_custom_price,
        taxableAmount: taxableNum.toFixed(2),
        taxProfileCode,
        taxProfileName,
        taxRate: rate.toFixed(4),
        formattedTaxRate,
        isExempt,
        taxAmount: taxNum.toFixed(2),
        lineTotal: lineTotalNum.toFixed(2),
    };
}

/**
 * Calculate client-side aggregated order financial totals strictly as the sum of rounded line taxes.
 * Ensures order tax total preview mirrors the backend sum-of-lines rule (RULE-ORD-005).
 */
export function calculateOrderPreview(cart: CartLineItem[]): OrderFinancialPreview {
    let subtotalCents = 0;
    let taxTotalCents = 0;
    let totalUnits = 0;

    const lines = cart.map((item) => {
        const line = calculateLinePreview(item);
        const taxableCents = Math.round(parseFloat(line.taxableAmount) * 100);
        const taxCents = Math.round(parseFloat(line.taxAmount) * 100);

        subtotalCents += taxableCents;
        taxTotalCents += taxCents;
        totalUnits += line.quantity;

        return line;
    });

    const grandTotalCents = subtotalCents + taxTotalCents;

    return {
        lines,
        itemCount: cart.length,
        totalUnits,
        subtotal: (subtotalCents / 100).toFixed(2),
        taxTotal: (taxTotalCents / 100).toFixed(2),
        adjustmentTotal: '0.00',
        grandTotal: (grandTotalCents / 100).toFixed(2),
        numericSubtotal: subtotalCents / 100,
        numericTaxTotal: taxTotalCents / 100,
        numericGrandTotal: grandTotalCents / 100,
    };
}
