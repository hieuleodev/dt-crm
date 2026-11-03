import { ReactNode, useEffect } from "react";
import { router, usePage } from "@inertiajs/react";
import { App, Avatar, Dropdown, Layout, Menu, Space, Typography } from "antd";
import {
    BarChartOutlined,
    CalendarOutlined,
    CustomerServiceOutlined,
    DashboardOutlined,
    FunnelPlotOutlined,
    LogoutOutlined,
    SettingOutlined,
    TeamOutlined,
    UploadOutlined,
    UserOutlined,
} from "@ant-design/icons";
import type { PageProps } from "@/types";

const { Header, Sider, Content } = Layout;

const MENU = [
    { key: "/", icon: <DashboardOutlined />, label: "Tổng quan" },
    {
        key: "/workbench",
        icon: <CalendarOutlined />,
        label: "Ngày làm việc",
        disabled: true,
    },
    {
        key: "/customers",
        icon: <TeamOutlined />,
        label: "Khách hàng",
        disabled: true,
    },
    {
        key: "/leads",
        icon: <FunnelPlotOutlined />,
        label: "Lead & Cơ hội",
        disabled: true,
    },
    {
        key: "/tickets",
        icon: <CustomerServiceOutlined />,
        label: "Khiếu nại",
        disabled: true,
    },
    {
        key: "/reports",
        icon: <BarChartOutlined />,
        label: "Báo cáo",
        disabled: true,
    },
    {
        key: "/imports",
        icon: <UploadOutlined />,
        label: "Nhập dữ liệu",
        disabled: true,
        managerOnly: true,
    },
    {
        key: "/settings",
        icon: <SettingOutlined />,
        label: "Cài đặt",
        disabled: true,
        managerOnly: true,
    },
];

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage<PageProps>().props;
    const url = usePage().url;
    const { message } = App.useApp();
    const isManager = auth.user?.role !== "sales";

    useEffect(() => {
        if (flash.success) message.success(flash.success);
        if (flash.error) message.error(flash.error);
    }, [flash, message]);

    const selected = MENU.filter(
        (m) => m.key !== "/" && url.startsWith(m.key),
    ).map((m) => m.key);

    return (
        <Layout
            style={{
                minHeight: "100vh",
                background: "#f5f7fa",
            }}
        >
            <Sider
                breakpoint="lg"
                collapsedWidth={0}
                theme="light"
                width={248}
                style={{
                    borderRight: "1px solid #e8edf3",
                    background: "#fff",
                }}
            >
                <div
                    style={{
                        height: 72,
                        display: "flex",
                        alignItems: "center",
                        gap: 12,
                        paddingInline: 24,
                        borderBottom: "1px solid #e8edf3",
                    }}
                >
                    <div
                        style={{
                            width: 36,
                            height: 36,
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                            flexShrink: 0,
                            borderRadius: 10,
                            background: "#1d4ed8",
                            color: "#fff",
                            fontSize: 13,
                            fontWeight: 700,
                        }}
                    >
                        D&T
                    </div>

                    <Typography.Text
                        strong
                        style={{
                            color: "#172b4d",
                            fontSize: 18,
                            letterSpacing: "-0.4px",
                        }}
                    >
                        D&T CRM
                    </Typography.Text>
                </div>

                <div style={{ padding: "20px 12px" }}>
                    <Menu
                        mode="inline"
                        selectedKeys={selected.length ? selected : ["/"]}
                        items={MENU.filter(
                            (m) => !m.managerOnly || isManager,
                        ).map(({ managerOnly, ...item }) => item)}
                        onClick={({ key }) => router.visit(key)}
                        style={{
                            borderInlineEnd: 0,
                            background: "transparent",
                            fontSize: 14,
                            fontWeight: 500,
                        }}
                    />
                </div>
            </Sider>

            <Layout style={{ minWidth: 0, background: "transparent" }}>
                <Header
                    style={{
                        height: 72,
                        lineHeight: "normal",
                        background: "#fff",
                        borderBottom: "1px solid #e8edf3",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "flex-end",
                        paddingInline: "clamp(16px, 3vw, 32px)",
                    }}
                >
                    <Dropdown
                        trigger={["click"]}
                        placement="bottomRight"
                        menu={{
                            items: [
                                {
                                    key: "logout",
                                    icon: <LogoutOutlined />,
                                    label: "Đăng xuất",
                                },
                            ],
                            onClick: ({ key }) =>
                                key === "logout" && router.post("/logout"),
                        }}
                    >
                        <Space
                            size={12}
                            style={{
                                cursor: "pointer",
                                padding: "8px 12px",
                                border: "1px solid #e8edf3",
                                borderRadius: 12,
                                background: "#fff",
                            }}
                        >
                            <Avatar
                                size={36}
                                icon={<UserOutlined />}
                                style={{
                                    background: "#eff6ff",
                                    color: "#1d4ed8",
                                    border: "1px solid #dbeafe",
                                }}
                            />

                            <div
                                style={{
                                    display: "flex",
                                    flexDirection: "column",
                                    gap: 3,
                                    maxWidth: 200,
                                    minWidth: 0,
                                }}
                            >
                                <Typography.Text
                                    strong
                                    ellipsis
                                    style={{
                                        color: "#172b4d",
                                        fontSize: 14,
                                        lineHeight: "20px",
                                    }}
                                >
                                    {auth.user?.name}
                                </Typography.Text>

                                <Typography.Text
                                    type="secondary"
                                    ellipsis
                                    style={{
                                        fontSize: 12,
                                        lineHeight: "16px",
                                    }}
                                >
                                    {auth.user?.roleLabel}
                                </Typography.Text>
                            </div>
                        </Space>
                    </Dropdown>
                </Header>

                <Content
                    style={{
                        minWidth: 0,
                        padding: "clamp(16px, 3vw, 32px)",
                    }}
                >
                    <div
                        style={{
                            width: "100%",
                            maxWidth: 1440,
                            marginInline: "auto",
                        }}
                    >
                        {children}
                    </div>
                </Content>
            </Layout>
        </Layout>
    );
}
