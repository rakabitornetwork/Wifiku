export type AuthUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
};

export type Toast = { type: 'success' | 'error'; message: string };

export type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            appName: string;
            auth: { user: AuthUser } | null;
        };
        flashDataType: { toast?: Toast };
    }
}
