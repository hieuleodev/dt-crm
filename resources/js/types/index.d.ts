export type UserRole = "admin" | "manager" | "sales";

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role: UserRole;
    roleLabel: string;
}

export interface RepurchaseNotificationItem {
    id: number;
    title: string;
    company: string;
    stage: "won" | "proposal";
    stage_label: string;
    days_elapsed: number;
    status: "due" | "overdue" | "scheduled_due";
}

export interface RepurchaseNotificationsSummary {
    due_count: number;
    overdue_count: number;
    scheduled_due_count: number;
    total_count: number;
    items: RepurchaseNotificationItem[];
}

export interface PageProps {
    auth: { user: AuthUser | null };
    flash: {
        success?: string | null;
        error?: string | null;
        importResult?: {
            type: "customer" | "lead";
            created: number;
            duplicates: number;
            total: number;
        } | null;
    };
    repurchaseNotifications?: RepurchaseNotificationsSummary | null;
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
