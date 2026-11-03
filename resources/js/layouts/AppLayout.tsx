import { ReactNode, useEffect, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { App, Avatar, Dropdown, Layout, Menu } from "antd";
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

// Các module sau sẽ lần lượt mở trong menu này.
const MENU = [
    { key: "/", icon: <DashboardOutlined />, label: "Tổng quan" },
    {
        key: "/workbench",
        icon: <CalendarOutlined />,
        label: "Ngày làm việc",
        disabled: true,
    },
    { key: "/customers", icon: <TeamOutlined />, label: "Khách hàng" },
    {
        key: "/leads",
        icon: <FunnelPlotOutlined />,
        label: "Lead & Cơ hội",
        disabled: false,
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
        <Layout className="crm-shell">
            <Sider
                className="crm-sidebar"
                breakpoint="lg"
                collapsedWidth={0}
                theme="light"
                width={256}
            >
                <div className="crm-brand">
                    <span className="crm-brand__mark" aria-hidden="true">
                        D&T
                    </span>

                    <div>
                        <div className="crm-brand__name">D&T CRM</div>
                        <div className="crm-brand__caption">
                            Quản lý khách hàng
                        </div>
                    </div>
                </div>

                <nav className="crm-nav" aria-label="Điều hướng chính">
                    <p className="crm-nav__label">Không gian làm việc</p>

                    <Menu
                        className="crm-menu"
                        mode="inline"
                        selectedKeys={selected.length ? selected : ["/"]}
                        items={MENU.filter(
                            (m) => !m.managerOnly || isManager,
                        ).map(({ managerOnly, ...item }) => item)}
                        onClick={({ key }) => router.visit(key)}
                    />
                </nav>
            </Sider>

            <Layout className="crm-main">
                <Header className="crm-header">
                    <Dropdown
                        trigger={["click"]}
                        placement="bottomRight"
                        classNames={{ root: "crm-account-popup" }}
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
                        <button
                            type="button"
                            className="crm-account"
                            aria-label={`Mở menu tài khoản ${
                                auth.user?.name ?? ""
                            }`}
                        >
                            <Avatar
                                className="crm-account__avatar"
                                size={40}
                                icon={<UserOutlined />}
                                aria-hidden="true"
                            />

                            <span className="crm-account__details">
                                <span
                                    className="crm-account__name"
                                    title={auth.user?.name}
                                >
                                    {auth.user?.name}
                                </span>

                                <span className="crm-account__role">
                                    {auth.user?.roleLabel}
                                </span>
                            </span>
                        </button>
                    </Dropdown>
                </Header>

                <Content className="crm-content">
                    <main className="crm-content__inner">{children}</main>
                </Content>
            </Layout>
        </Layout>
    );
}
