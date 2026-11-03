import type { ThemeConfig } from "antd";

export const brand = {
    accent: "#ff4800",
    accentStrong: "#b83200",
    accentHover: "#942900",
    text: "#1f1f1f",
    muted: "#595959",
    surface: "#fcfcfa",
    panel: "#ffffff",
    subtle: "#f8f5ee",
    border: "#deded8",
};

export const fontFamily =
    '"Be Vietnam Pro", system-ui, -apple-system, "Segoe UI", sans-serif';

export const theme: ThemeConfig = {
    token: {
        colorPrimary: brand.accentStrong,
        colorLink: brand.accentStrong,
        colorLinkHover: brand.accentHover,
        colorText: brand.text,
        colorTextSecondary: brand.muted,
        colorBorder: brand.border,
        colorBorderSecondary: brand.border,
        colorBgLayout: brand.surface,
        colorBgContainer: brand.panel,
        fontFamily,
        fontSize: 14,
        borderRadius: 8,
        controlHeight: 40,
    },
    components: {
        Layout: {
            bodyBg: brand.surface,
            headerBg: brand.panel,
            siderBg: brand.panel,
            headerHeight: 64,
            headerPadding: "0 32px",
        },
        Menu: {
            itemBg: "transparent",
            itemSelectedBg: brand.subtle,
            itemSelectedColor: brand.accentStrong,
            itemHoverBg: brand.subtle,
            itemBorderRadius: 8,
            itemHeight: 44,
        },
        Table: { headerBg: brand.subtle, rowHoverBg: "#fbf8f2" },
        Button: { primaryShadow: "none" },
    },
};
