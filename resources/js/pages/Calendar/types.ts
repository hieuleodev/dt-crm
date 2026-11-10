import type { TaskRow, TaskOptions, TaskAssignee, TaskReference } from "@/types/task";
import type { PageProps } from "@/types";

export type CalendarViewType = "month" | "week" | "day" | "agenda";

export interface CalendarFilters {
    date: string;
    view: CalendarViewType;
    type?: string;
    priority?: string;
    status?: string;
    assignee_id?: number;
    q?: string;
    start?: string;
    end?: string;
}

export interface CalendarSummary {
    total: number;
    open: number;
    completed: number;
    overdue: number;
}

export interface CalendarPageProps extends PageProps {
    tasks: TaskRow[];
    filters: CalendarFilters;
    summary: CalendarSummary;
    options: TaskOptions;
    assignees: TaskAssignee[];
}

export interface TypeStyle {
    label: string;
    color: string;
    bg: string;
    border: string;
    badge: string;
    icon: string;
}

export const TYPE_CONFIG: Record<string, TypeStyle> = {
    call: {
        label: "Gọi điện",
        color: "#059669",
        bg: "#ecfdf5",
        border: "#a7f3d0",
        badge: "green",
        icon: "📞",
    },
    email: {
        label: "Gửi email",
        color: "#0891b2",
        bg: "#ecfeff",
        border: "#a5f3fc",
        badge: "cyan",
        icon: "✉️",
    },
    zalo: {
        label: "Liên hệ Zalo",
        color: "#2563eb",
        bg: "#eff6ff",
        border: "#bfdbfe",
        badge: "blue",
        icon: "💬",
    },
    meeting: {
        label: "Gặp / khảo sát",
        color: "#7c3aed",
        bg: "#f5f3ff",
        border: "#ddd6fe",
        badge: "purple",
        icon: "🤝",
    },
    quotation: {
        label: "Theo dõi báo giá",
        color: "#d97706",
        bg: "#fffbeb",
        border: "#fde68a",
        badge: "gold",
        icon: "📑",
    },
    sample: {
        label: "Theo dõi mẫu thử",
        color: "#ea580c",
        bg: "#fff7ed",
        border: "#fed7aa",
        badge: "orange",
        icon: "📦",
    },
    vendor_registration: {
        label: "Đăng ký NCC",
        color: "#4f46e5",
        bg: "#eef2ff",
        border: "#c7d2fe",
        badge: "geekblue",
        icon: "🏢",
    },
    follow_up: {
        label: "Chăm sóc",
        color: "#0d9488",
        bg: "#f0fdfa",
        border: "#99f6e4",
        badge: "teal",
        icon: "🔄",
    },
    internal: {
        label: "Nội bộ",
        color: "#475569",
        bg: "#f8fafc",
        border: "#cbd5e1",
        badge: "default",
        icon: "📌",
    },
};

export const DEFAULT_TYPE_STYLE: TypeStyle = {
    label: "Công việc",
    color: "#6b7280",
    bg: "#f3f4f6",
    border: "#e5e7eb",
    badge: "default",
    icon: "📋",
};

export const TIMEZONE = "Asia/Ho_Chi_Minh";
