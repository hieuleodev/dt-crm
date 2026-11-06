import type { ThemeConfig } from "antd";

export const brand = {
    accent: "#c8102e",
    accentStrong: "#ce0e2d",
    accentHover: "#a90c25",
    accentActive: "#87091d",

    text: "#333333",
    muted: "#666666",
    inverse: "#ffffff",

    surface: "#fbfbfb",
    panel: "#ffffff",
    subtle: "#f4f4f4",
    accentSoft: "#fff1f3",

    border: "#ececec",
    controlBorder: "#767676",

    success: "#237545",
    warning: "#8a5600",
    error: "#b42318",
};

export const fontFamily =
    '"Roboto", system-ui, -apple-system, "Segoe UI", sans-serif';

export const theme: ThemeConfig = {
    token: {
        fontFamily,
        fontSize: 16,
        fontSizeSM: 14,
        lineHeight: 1.6,

        colorPrimary: brand.accentStrong,
        colorPrimaryHover: brand.accentHover,
        colorPrimaryActive: brand.accentActive,

        colorLink: brand.accent,
        colorLinkHover: brand.accentHover,
        colorLinkActive: brand.accentActive,

        colorText: brand.text,
        colorTextSecondary: brand.muted,
        colorTextDisabled: brand.muted,

        colorBgLayout: brand.surface,
        colorBgContainer: brand.panel,
        colorBgContainerDisabled: brand.subtle,

        colorBorder: brand.controlBorder,
        colorBorderSecondary: brand.border,

        colorSuccess: brand.success,
        colorWarning: brand.warning,
        colorError: brand.error,
        colorInfo: brand.accent,

        borderRadius: 12,
        borderRadiusSM: 8,
        borderRadiusLG: 16,

        controlHeight: 44,
        controlHeightSM: 36,
        controlHeightLG: 48,

        motionDurationFast: "0.2s",
        motionDurationMid: "0.2s",
        motionDurationSlow: "0.3s",
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
            itemColor: brand.text,
            itemHoverBg: brand.subtle,
            itemHoverColor: brand.text,
            itemSelectedBg: brand.accentSoft,
            itemSelectedColor: brand.accent,
            itemBorderRadius: 12,
            itemHeight: 44,
        },

        Button: {
            borderRadius: 999,
            borderRadiusSM: 999,
            borderRadiusLG: 999,
            primaryShadow: "none",
            defaultShadow: "none",
            fontWeight: 500,
        },

        Input: {
            borderRadius: 24,
            borderRadiusLG: 24,
            borderRadiusSM: 18,
            activeBorderColor: brand.accent,
            hoverBorderColor: brand.accent,
        },

        InputNumber: {
            borderRadius: 24,
            activeBorderColor: brand.accent,
            hoverBorderColor: brand.accent,
        },

        Select: {
            borderRadius: 24,
            optionSelectedBg: brand.accentSoft,
            optionSelectedColor: brand.accent,
        },

        DatePicker: {
            borderRadius: 24,
        },

        Table: {
            fontSize: 14,
            headerBg: brand.subtle,
            headerColor: brand.text,
            rowHoverBg: brand.accentSoft,
            borderColor: brand.border,
            cellPaddingBlock: 12,
            cellPaddingInline: 16,
        },

        Card: {
            borderRadiusLG: 12,
            headerFontSize: 16,
        },

        Tabs: {
            itemColor: brand.muted,
            itemSelectedColor: brand.accent,
            itemHoverColor: brand.accentHover,
            inkBarColor: brand.accent,
        },

        Tag: {
            borderRadiusSM: 999,
        },
    },
};
