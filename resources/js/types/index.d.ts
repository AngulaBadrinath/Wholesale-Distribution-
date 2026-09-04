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

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & SharedProps;
