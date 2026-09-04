export type OrderStatus =
    | 'DRAFT'
    | 'SUBMITTED'
    | 'PENDING_APPROVAL'
    | 'APPROVED'
    | 'PROCESSING'
    | 'COMPLETED'
    | 'CANCELLED'
    | 'REJECTED';

export type FulfillmentStatus =
    | 'UNALLOCATED'
    | 'RESERVED'
    | 'PICKED'
    | 'PACKED'
    | 'DISPATCHED'
    | 'DELIVERED'
    | 'PARTIALLY_DELIVERED'
    | 'RETURNED';

export type PaymentStatus =
    | 'UNPAID'
    | 'PARTIALLY_PAID'
    | 'PAID'
    | 'OVERPAID'
    | 'REFUNDED';

export type DeliveryStatus =
    | 'PENDING_ASSIGNMENT'
    | 'ASSIGNED'
    | 'ACCEPTED'
    | 'PICKED_UP'
    | 'OUT_FOR_DELIVERY'
    | 'DELIVERED'
    | 'FAILED';

export type AdjustmentStatus =
    | 'NONE'
    | 'REQUESTED'
    | 'APPLIED'
    | 'REVERSED';

export interface CustomerSummary {
    id: number;
    code: string;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    credit_limit: number;
    payment_terms: string | null;
    payment_terms_label: string | null;
    status: string;
    status_label: string;
    billing_address: string;
    shipping_address: string;
}

export interface CatalogProduct {
    id: number;
    sku: string;
    name: string;
    description: string | null;
    category_id: number | null;
    category: {
        id: number;
        name: string;
        code: string;
    } | null;
    unit: string;
    status: string;
    status_label: string;
    minimum_allowed_price: number;
    default_selling_price: number;
    mrp: number;
    can_order: boolean;
    tax_profile_id: number | null;
    tax_profile: {
        id: number;
        name: string;
        code: string;
        rate: string;
        formatted_rate: string;
        status: string;
        is_exempt?: boolean;
    } | null;
    primary_image_url: string | null;
}

export interface CartLineItem {
    product: CatalogProduct;
    quantity: number;
    unit_price: string;
    is_custom_price: boolean;
}

export interface OrderItemDetail {
    id: number;
    product_id: number;
    product_name: string;
    sku: string;
    unit: string;
    ordered_quantity: number;
    fulfillable_quantity: number;
    unit_price: string;
    is_price_overridden: boolean;
    tax_profile_code: string | null;
    tax_profile_name: string | null;
    tax_rate: string;
    formatted_tax_rate: string;
    taxable_amount: string;
    tax_amount: string;
    line_total: string;
}

export interface OrderTimelineEvent {
    id: string;
    title: string;
    description: string | null;
    timestamp: string | null;
    actor_name: string | null;
    status: 'completed' | 'current' | 'pending' | 'cancelled';
    badge_label?: string | null;
    badge_variant?: string | null;
    icon: 'created' | 'submitted' | 'approved' | 'processing' | 'completed' | 'cancelled' | 'fulfillment' | 'payment' | 'delivery';
}

export interface OrderDetail {
    id: number;
    order_number: string;
    idempotency_key: string;
    draft_token?: string | null;
    status: OrderStatus;
    status_label: string;
    status_badge_variant: string;
    fulfillment_status: FulfillmentStatus | null;
    fulfillment_status_label: string | null;
    fulfillment_badge_variant: string | null;
    payment_status: PaymentStatus | null;
    payment_status_label: string | null;
    payment_badge_variant: string | null;
    delivery_status: DeliveryStatus | null;
    delivery_status_label: string | null;
    delivery_badge_variant: string | null;
    adjustment_status?: AdjustmentStatus | null;
    adjustment_status_label?: string | null;
    adjustment_badge_variant?: string | null;
    currency: string;
    subtotal: string;
    tax_total: string;
    adjustment_total: string;
    grand_total: string;
    notes: string | null;
    submitted_at: string | null;
    approved_at?: string | null;
    approver?: {
        id: number;
        name: string;
    } | null;
    cancelled_at?: string | null;
    canceller?: {
        id: number;
        name: string;
    } | null;
    cancellation_reason?: string | null;
    completed_at?: string | null;
    created_at: string;
    customer: {
        id: number;
        code: string;
        name: string;
        contact_name: string | null;
        email: string | null;
        phone: string | null;
        billing_address: string;
        shipping_address: string;
        payment_terms: string | null;
    };
    salesman: {
        id: number;
        name: string;
        email: string;
    };
    items: OrderItemDetail[];
    timeline?: OrderTimelineEvent[];
}

export interface OrderHistoryItem {
    id: number;
    order_number: string;
    idempotency_key: string;
    customer: {
        id: number;
        code: string;
        name: string;
        contact_name: string | null;
        phone: string | null;
    };
    status: OrderStatus;
    status_label: string;
    status_badge_variant: string;
    fulfillment_status: FulfillmentStatus | null;
    fulfillment_status_label: string | null;
    fulfillment_badge_variant: string | null;
    payment_status: PaymentStatus | null;
    payment_status_label: string | null;
    payment_badge_variant: string | null;
    delivery_status: DeliveryStatus | null;
    delivery_status_label: string | null;
    delivery_badge_variant: string | null;
    adjustment_status: AdjustmentStatus | null;
    adjustment_status_label: string | null;
    adjustment_badge_variant: string | null;
    currency: string;
    subtotal: string;
    tax_total: string;
    adjustment_total: string;
    grand_total: string;
    item_count: number;
    submitted_at: string | null;
    created_at: string;
}

export interface OrderHistoryFilters {
    search?: string;
    status?: string;
    fulfillment_status?: string;
    payment_status?: string;
    delivery_status?: string;
    date_from?: string;
    date_to?: string;
}

export interface OrderDraftSummary {
    id: number;
    draft_token: string;
    version: number;
    idempotency_key: string;
    customer: {
        id: number;
        code: string;
        name: string;
        contact_name: string | null;
        phone: string | null;
        status: string;
        status_label: string;
    };
    item_count: number;
    subtotal: string;
    tax_total: string;
    grand_total: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface InitialDraftData {
    id: number;
    draft_token: string;
    version: number;
    idempotency_key: string;
    customer_id: number;
    notes: string;
    subtotal: string;
    tax_total: string;
    grand_total: string;
    customer_status: string;
    customer_is_active: boolean;
    items: Array<{
        id: number;
        product_id: number;
        quantity: number;
        unit_price: string;
        is_custom_price: boolean;
        product: CatalogProduct | null;
    }>;
}

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
}

