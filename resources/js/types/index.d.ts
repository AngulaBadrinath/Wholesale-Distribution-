export interface User {
    id: number;
    name: string;
    email: string;
    role?: string;
    created_at?: string;
}

export interface SharedProps {
    appName: string;
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
