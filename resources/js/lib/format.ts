import dayjs from "dayjs";
import type { Option } from "@/types";

export const formatDate = (v?: string | null) =>
    v ? dayjs(v).format("DD/MM/YYYY") : "—";
export const formatDateTime = (v?: string | null) =>
    v ? dayjs(v).format("DD/MM/YYYY HH:mm") : "—";
export const formatNumber = (v?: number | null) =>
    v === null || v === undefined ? "—" : v.toLocaleString("vi-VN");
export const formatVnd = (v?: number | null) =>
    v === null || v === undefined ? "—" : `${v.toLocaleString("vi-VN")} đ`;

export const labelOf = (options: Option[], value?: string | null) =>
    value ? (options.find((o) => o.value === value)?.label ?? value) : "—";

export const stageColor = (stage: string) =>
    ({
        loyal: "green",
        repeat: "green",
        new_customer: "cyan",
        at_risk: "orange",
        dormant: "gold",
        lost: "red",
    })[stage] ?? "default";
