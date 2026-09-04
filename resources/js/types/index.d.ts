export interface User {
    id: number;
    name: string;
    email: string;
    role?: string;
    status?: string;
    permissions?: string[];
    created_at?: string;
}

export interface ApplicationIdentity {
    name: string;
    company_name: string;
    tagline: string;
    support_email: string;
    support_phone: string;
    logo_path: string;
    favicon_path: string;
    footer_text: string;
}

export interface CompanyInformation {
    id: number;
    legal_name: string;
    dba_name: string | null;
    display_name: string;
    address_line1: string;
    address_line2: string | null;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    formatted_address: string;
    phone: string;
    email: string;
    website: string | null;
    tax_id: string | null;
    state_tax_id: string | null;
    currency: string;
    timezone: string;
    invoice_footer_note: string | null;
    updated_at: string | null;
}

export interface SharedProps {
    appName: string;
    identity: ApplicationIdentity;
    company?: CompanyInformation;
    auth: {
        user: User | null;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    [key: string]: unknown;
}

export interface SessionRecord {
    id: string;
    device_type: 'desktop' | 'mobile' | 'tablet' | 'unknown';
    browser: string;
    platform: string;
    ip_address: string;
    last_active: string;
    last_active_human: string;
    is_current: boolean;
}

export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_label?: string;
    status: string;
    created_at?: string;
}

export interface RoleOption {
    value: string;
    label: string;
    description: string;
    is_privileged: boolean;
}

export type CustomerStatus = 'ACTIVE' | 'ON_HOLD' | 'INACTIVE';
export type PaymentTerms = 'NET_30' | 'NET_15' | 'NET_60' | 'COD' | 'DUE_ON_RECEIPT';

export interface Customer {
    id: number;
    code: string;
    name: string;
    contact_name: string;
    email: string | null;
    phone: string;
    billing_address_line1: string;
    billing_address_line2: string | null;
    billing_city: string;
    billing_state: string;
    billing_postal_code: string;
    billing_country: string;
    formatted_billing_address?: string;
    shipping_address_line1: string | null;
    shipping_address_line2: string | null;
    shipping_city: string | null;
    shipping_state: string | null;
    shipping_postal_code: string | null;
    shipping_country: string;
    formatted_shipping_address?: string;
    tax_id: string | null;
    credit_limit: number;
    payment_terms: string;
    payment_terms_label?: string;
    status: CustomerStatus;
    status_label?: string;
    can_order?: boolean;
    notes: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface CustomerStatusOption {
    value: CustomerStatus;
    label: string;
    badgeVariant?: string;
}

export interface PaymentTermsOption {
    value: PaymentTerms;
    label: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}


export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & SharedProps;
