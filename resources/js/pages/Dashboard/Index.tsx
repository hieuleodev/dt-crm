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
        <Layout style={{ minHeight: "100vh" }}>
            <Sider
                breakpoint="lg"
                collapsedWidth={0}
                theme="light"
                width={232}
                style={{ borderRight: "1px solid #f0f0f0" }}
            >
                <div style={{ padding: "18px 20px" }}>
                    <Typography.Text strong style={{ fontSize: 16 }}>
                        D&T CRM
                    </Typography.Text>
                    <div>
                        <Typography.Text
                            type="secondary"
                            style={{ fontSize: 12 }}
                        >
                            Chăm sóc & giữ chân khách hàng
                        </Typography.Text>
                    </div>
                </div>
                <Menu
                    mode="inline"
                    selectedKeys={selected.length ? selected : ["/"]}
                    items={MENU.filter((m) => !m.managerOnly || isManager).map(
                        ({ managerOnly, ...item }) => item,
                    )}
                    onClick={({ key }) => router.visit(key)}
                    style={{ borderInlineEnd: 0 }}
                />
            </Sider>
            <Layout>
                <Header
                    style={{
                        background: "#fff",
                        borderBottom: "1px solid #f0f0f0",
                        display: "flex",
                        justifyContent: "flex-end",
                        paddingInline: 24,
                    }}
                >
                    <Dropdown
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
                        <Space style={{ cursor: "pointer" }}>
                            <Avatar icon={<UserOutlined />} />
                            <span>
                                {auth.user?.name}{" "}
                                <Typography.Text type="secondary">
                                    · {auth.user?.roleLabel}
                                </Typography.Text>
                            </span>
                        </Space>
                    </Dropdown>
                </Header>
                <Content style={{ padding: 24 }}>{children}</Content>
            </Layout>
        </Layout>
    );
}
