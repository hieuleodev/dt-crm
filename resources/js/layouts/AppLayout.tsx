import { ReactNode, useEffect } from "react";
import { router, usePage } from "@inertiajs/react";
import {
    App,
    Avatar,
    Badge,
    Button,
    Dropdown,
    Empty,
    Layout,
    Menu,
    Popover,
    Space,
    Tag,
    Typography,
} from "antd";
import {
    BellOutlined,
    CalendarOutlined,
    CheckSquareOutlined,
    ClockCircleOutlined,
    DashboardOutlined,
    ExclamationCircleOutlined,
    FunnelPlotOutlined,
    HistoryOutlined,
    LogoutOutlined,
    RetweetOutlined,
    ScheduleOutlined,
    SettingOutlined,
    TeamOutlined,
    UploadOutlined,
    UserOutlined,
    GiftOutlined,
} from "@ant-design/icons";
import type { PageProps } from "@/types";

const { Header, Sider, Content } = Layout;

const MENU = [
    { key: "/", icon: <DashboardOutlined />, label: "Tổng quan" },
    {
        key: "/workbench",
        icon: <CheckSquareOutlined />,
        label: "Công việc hôm nay",
        disabled: false,
    },
    { key: "/customers", icon: <TeamOutlined />, label: "Khách hàng" },
    {
        key: "/leads",
        icon: <FunnelPlotOutlined />,
        label: "Lead",
        disabled: false,
    },
    {
        key: "/opportunities",
        icon: <FunnelPlotOutlined />,
        label: "Cơ hội",
        disabled: false,
    },
    {
        key: "/repurchase-reminders",
        icon: <RetweetOutlined />,
        label: "Nhắc nhở mua lại",
        disabled: false,
    },
    {
        key: "/tasks",
        icon: <ScheduleOutlined />,
        label: "Nhiệm vụ",
        disabled: false,
    },
    {
        key: "/history",
        icon: <HistoryOutlined />,
        label: "Lịch sử",
        disabled: false,
    },
    {
        key: "/calendar",
        icon: <CalendarOutlined />,
        label: "Lịch trình",
        disabled: false,
    },
    {
        key: "/events",
        icon: <GiftOutlined />,
        label: "Sự kiện",
        disabled: false,
    },
    {
        key: "/imports",
        icon: <UploadOutlined />,
        label: "Nhập dữ liệu",
        disabled: false,
        managerOnly: true,
    },
    {
        key: "/settings",
        icon: <SettingOutlined />,
        label: "Cài đặt",
        disabled: false,
    },
];

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, flash, repurchaseNotifications } =
        usePage<PageProps>().props;
    const url = usePage().url;
    const { message } = App.useApp();
    const isManager = auth.user?.role !== "sales";

    const notifyCount = repurchaseNotifications?.total_count ?? 0;
    const notifyItems = repurchaseNotifications?.items ?? [];

    useEffect(() => {
        if (flash.success) message.success(flash.success);
        if (flash.error) message.error(flash.error);
    }, [flash, message]);

    const selected = MENU.filter(
        (m) => m.key !== "/" && url.startsWith(m.key),
    ).map((m) => m.key);

    const notificationPopoverContent = (
        <div style={{ width: 360, maxWidth: "85vw" }}>
            <div
                style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    paddingBottom: 10,
                    borderBottom: "1px solid #f0f0f0",
                    marginBottom: 10,
                }}
            >
                <Typography.Text strong style={{ fontSize: 14 }}>
                    <RetweetOutlined
                        style={{ color: "#c8102e", marginRight: 6 }}
                    />
                    Nhắc nhở mua lại (15–30 ngày)
                </Typography.Text>
                <Tag color={notifyCount > 0 ? "red" : "default"}>
                    {notifyCount} cần nhắc
                </Tag>
            </div>

            {notifyCount > 0 && (
                <Space wrap size={6} style={{ marginBottom: 10 }}>
                    {(repurchaseNotifications?.due_count ?? 0) > 0 && (
                        <Tag color="gold" style={{ margin: 0 }}>
                            Đến hạn 15–30 ngày:{" "}
                            {repurchaseNotifications?.due_count}
                        </Tag>
                    )}
                    {(repurchaseNotifications?.overdue_count ?? 0) > 0 && (
                        <Tag color="volcano" style={{ margin: 0 }}>
                            Quá 30 ngày:{" "}
                            {repurchaseNotifications?.overdue_count}
                        </Tag>
                    )}
                    {(repurchaseNotifications?.scheduled_due_count ?? 0) >
                        0 && (
                        <Tag color="blue" style={{ margin: 0 }}>
                            Đến hẹn gọi lại:{" "}
                            {repurchaseNotifications?.scheduled_due_count}
                        </Tag>
                    )}
                </Space>
            )}

            {notifyItems.length === 0 ? (
                <Empty
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    description="Hiện không có khách hàng nào đến hạn nhắc mua lại."
                    style={{ margin: "16px 0" }}
                />
            ) : (
                <div
                    style={{
                        display: "flex",
                        flexDirection: "column",
                        gap: 8,
                        maxHeight: 290,
                        overflowY: "auto",
                    }}
                >
                    {notifyItems.map((item) => (
                        <div
                            key={item.id}
                            onClick={() =>
                                router.visit(
                                    `/repurchase-reminders?q=${encodeURIComponent(item.title)}`,
                                )
                            }
                            style={{
                                padding: "8px 10px",
                                borderRadius: 8,
                                border: "1px solid #f0f0f0",
                                backgroundColor:
                                    item.status === "overdue"
                                        ? "#fff2f0"
                                        : "#fffbe6",
                                cursor: "pointer",
                            }}
                        >
                            <div
                                style={{
                                    display: "flex",
                                    justifyContent: "space-between",
                                    alignItems: "center",
                                    gap: 8,
                                }}
                            >
                                <Typography.Text
                                    strong
                                    ellipsis
                                    style={{ fontSize: 13 }}
                                >
                                    {item.company}
                                </Typography.Text>
                                <Tag
                                    color={
                                        item.status === "overdue"
                                            ? "error"
                                            : "warning"
                                    }
                                    style={{ margin: 0, fontSize: 11 }}
                                >
                                    {item.status === "overdue" ? (
                                        <ExclamationCircleOutlined />
                                    ) : (
                                        <ClockCircleOutlined />
                                    )}{" "}
                                    {item.days_elapsed} ngày
                                </Tag>
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: "#555",
                                    marginTop: 2,
                                }}
                            >
                                {item.stage_label}: {item.title}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div
                style={{
                    marginTop: 12,
                    paddingTop: 10,
                    borderTop: "1px solid #f0f0f0",
                    textAlign: "center",
                }}
            >
                <Button
                    type="primary"
                    block
                    style={{ backgroundColor: "#c8102e" }}
                    onClick={() => router.visit("/repurchase-reminders")}
                >
                    Mở trang Nhắc nhở mua lại
                </Button>
            </div>
        </div>
    );

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
                        ).map(({ managerOnly, ...item }) => {
                            if (
                                item.key === "/repurchase-reminders" &&
                                notifyCount > 0
                            ) {
                                return {
                                    ...item,
                                    label: (
                                        <span
                                            style={{
                                                display: "flex",
                                                alignItems: "center",
                                                justifyContent:
                                                    "space-between",
                                            }}
                                        >
                                            <span>{item.label}</span>
                                            <Badge
                                                count={notifyCount}
                                                size="small"
                                                color="#c8102e"
                                            />
                                        </span>
                                    ),
                                };
                            }

                            return item;
                        })}
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
                                    key: "settings",
                                    icon: <SettingOutlined />,
                                    label: "Cài đặt & Tài khoản",
                                },
                                {
                                    type: "divider",
                                },
                                {
                                    key: "logout",
                                    icon: <LogoutOutlined />,
                                    label: "Đăng xuất",
                                    danger: true,
                                },
                            ],
                            onClick: ({ key }) => {
                                if (key === "logout") {
                                    router.post("/logout");
                                } else if (key === "settings") {
                                    router.visit("/settings");
                                }
                            },
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

                    <Popover
                        trigger="click"
                        placement="bottomRight"
                        content={notificationPopoverContent}
                    >
                        <button
                            type="button"
                            aria-label={`Thông báo nhắc nhở mua lại (${notifyCount})`}
                            style={{
                                display: "inline-flex",
                                alignItems: "center",
                                justifyContent: "center",
                                width: 42,
                                height: 42,
                                borderRadius: "50%",
                                border: "1px solid #ececec",
                                backgroundColor:
                                    notifyCount > 0 ? "#fff1f3" : "#ffffff",
                                cursor: "pointer",
                                flexShrink: 0,
                            }}
                        >
                            <Badge
                                count={notifyCount}
                                overflowCount={99}
                                size="small"
                                color="#c8102e"
                                showZero
                            >
                                <BellOutlined
                                    style={{
                                        fontSize: 19,
                                        color:
                                            notifyCount > 0
                                                ? "#c8102e"
                                                : "#555555",
                                    }}
                                />
                            </Badge>
                        </button>
                    </Popover>
                </Header>

                <Content className="crm-content">
                    <main className="crm-content__inner">{children}</main>
                </Content>
            </Layout>
        </Layout>
    );
}
