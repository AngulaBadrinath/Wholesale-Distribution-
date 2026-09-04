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

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & SharedProps;
