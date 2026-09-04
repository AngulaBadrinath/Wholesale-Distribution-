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

export interface OrderDetail {
    id: number;
    order_number: string;
    idempotency_key: string;
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
    currency: string;
    subtotal: string;
    tax_total: string;
    adjustment_total: string;
    grand_total: string;
    notes: string | null;
    submitted_at: string | null;
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
}
