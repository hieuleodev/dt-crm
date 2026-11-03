export type UserRole = "admin" | "manager" | "sales";

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    roleLabel: string;
}

export interface PageProps {
    auth: { user: AuthUser | null };
    flash: { success?: string | null; error?: string | null };
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface Option<V extends string = string> {
    value: V;
    label: string;
}
