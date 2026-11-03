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
