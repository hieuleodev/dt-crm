import { useState } from "react";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    Alert,
    Badge,
    Button,
    Card,
    Checkbox,
    Col,
    Dropdown,
    Empty,
    Form,
    Input,
    InputNumber,
    Modal,
    Row,
    Select,
    Space,
    Statistic,
    Table,
    Tabs,
    Tag,
    Tooltip,
    Typography,
} from "antd";
import type { ColumnsType } from "antd/es/table";
import {
    BellOutlined,
    CalendarOutlined,
    CheckCircleOutlined,
    ClockCircleOutlined,
    ExclamationCircleOutlined,
    HistoryOutlined,
    PhoneOutlined,
    PlusCircleOutlined,
    RetweetOutlined,
    SearchOutlined,
    ShoppingCartOutlined,
    UserOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";

import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import type { PageProps } from "@/types";

const { Text, Paragraph } = Typography;
const { TextArea } = Input;

export type CycleStatus =
    | "due"
    | "overdue"
    | "upcoming"
    | "scheduled"
    | "completed";

export interface ReminderTaskSummary {
    id: number;
    title: string;
    type?: string;
    type_label?: string;
    status?: string;
    status_label?: string;
    due_at?: string | null;
    result?: string | null;
    completed_at?: string | null;
    assignee?: string | null;
}

export interface RepurchaseReminderItem {
    id: number;
    title: string;
    stage: "won" | "proposal";
    stage_label: string;
    company: string;
    customer_id: number | null;
    customer_code: string | null;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    estimated_value: string;
    owner_id: number | null;
    owner_name: string;
    deal_at: string;
    days_elapsed: number;
    window_start_at: string;
    window_end_at: string;
    days_until_window: number;
    cycle_status: CycleStatus;
    is_in_golden_window: boolean;
    open_task: ReminderTaskSummary | null;
    completed_task: ReminderTaskSummary | null;
    notes: string | null;
}

interface Props extends PageProps {
    reminders: RepurchaseReminderItem[];
    summary: {
        total: number;
        due_15_30: number;
        overdue_30: number;
        upcoming_under_15: number;
        scheduled: number;
        completed: number;
        won_count: number;
        proposal_count: number;
    };
    filters: {
        q: string;
        stage: string;
        bucket: string;
    };
    assignees: { id: number; name: string }[];
    taskOptions: {
        types: Record<string, string>;
        priorities: Record<string, string>;
    };
}

const money = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
});

export default function RepurchaseRemindersIndex({
    reminders,
    summary,
    filters,
    assignees,
    taskOptions,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const isManager = auth.user?.role !== "sales";

    const [search, setSearch] = useState(filters.q || "");
    const [schedulingItem, setSchedulingItem] =
        useState<RepurchaseReminderItem | null>(null);
    const [completingItem, setCompletingItem] =
        useState<RepurchaseReminderItem | null>(null);

    const scheduleForm = useForm({
        title: "",
        type: "follow_up",
        priority: "high",
        due_at: dayjs().add(1, "day").format("YYYY-MM-DDTHH:mm"),
        assignee_id: auth.user?.id ?? 0,
        description: "",
    });

    const completeForm = useForm({
        result: "",
        create_reorder: false,
        reorder_value: 0,
        snooze_enabled: false,
        snooze_days: 7,
    });

    function applyFilter(next: {
        q?: string;
        stage?: string;
        bucket?: string;
    }) {
        const query: Record<string, string> = {
            q: next.q !== undefined ? next.q : search,
            stage: next.stage !== undefined ? next.stage : filters.stage,
            bucket: next.bucket !== undefined ? next.bucket : filters.bucket,
        };

        Object.keys(query).forEach((k) => {
            if (!query[k] || query[k] === "all") {
                delete query[k];
            }
        });

        router.get("/repurchase-reminders", query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function openScheduleModal(item: RepurchaseReminderItem) {
        setSchedulingItem(item);
        const suggestedDue =
            item.days_elapsed < 15
                ? dayjs(item.deal_at)
                      .add(15, "day")
                      .hour(9)
                      .minute(0)
                      .format("YYYY-MM-DDTHH:mm")
                : dayjs()
                      .add(1, "day")
                      .hour(9)
                      .minute(0)
                      .format("YYYY-MM-DDTHH:mm");

        scheduleForm.setData({
            title: `Nhắc mua lại (chu kỳ 15-30 ngày): ${item.company}`,
            type: "follow_up",
            priority:
                item.cycle_status === "overdue" || item.cycle_status === "due"
                    ? "high"
                    : "normal",
            due_at: suggestedDue,
            assignee_id: item.owner_id || auth.user?.id || 0,
            description: `Liên hệ khách hàng ${item.company} (${item.contact_name || "Người phụ trách mua hàng"}) để nhắc đặt mua lại từ đợt "${item.title}".`,
        });
        scheduleForm.clearErrors();
    }

    function handleScheduleSubmit() {
        if (!schedulingItem) return;
        scheduleForm.post(
            `/repurchase-reminders/${schedulingItem.id}/schedule`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSchedulingItem(null);
                },
            },
        );
    }

    function openCompleteModal(item: RepurchaseReminderItem) {
        setCompletingItem(item);
        completeForm.setData({
            result: "",
            create_reorder: false,
            reorder_value: Number(item.estimated_value) || 0,
            snooze_enabled: false,
            snooze_days: 7,
        });
        completeForm.clearErrors();
    }

    function handleQuickSnooze(item: RepurchaseReminderItem, days: number) {
        router.post(
            `/repurchase-reminders/${item.id}/snooze`,
            {
                days,
                note: `Khách chưa hết hàng, hẹn gọi lại sau ${days} ngày.`,
            },
            { preserveScroll: true },
        );
    }

    function handleCompleteSubmit() {
        if (!completingItem) return;
        completeForm.post(
            `/repurchase-reminders/${completingItem.id}/complete`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCompletingItem(null);
                },
            },
        );
    }

    const columns: ColumnsType<RepurchaseReminderItem> = [
        {
            title: "Khách hàng & Đợt giao dịch",
            key: "deal",
            render: (_, record) => (
                <div style={{ display: "flex", flexDirection: "column", gap: 4 }}>
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: 8,
                            flexWrap: "wrap",
                        }}
                    >
                        {record.customer_id ? (
                            <Link
                                href={`/customers/${record.customer_id}`}
                                style={{ fontWeight: 600, fontSize: 15 }}
                            >
                                {record.company}
                            </Link>
                        ) : (
                            <Text strong style={{ fontSize: 15 }}>
                                {record.company}
                            </Text>
                        )}
                        <Tag
                            color={record.stage === "won" ? "green" : "cyan"}
                            style={{ margin: 0, fontSize: 12 }}
                        >
                            {record.stage === "won"
                                ? "Mua thành công"
                                : "Đợt báo giá"}
                        </Tag>
                    </div>

                    <Text style={{ color: "#333", fontSize: 13 }}>
                        {record.title}
                    </Text>

                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            flexWrap: "wrap",
                            gap: "4px 12px",
                            fontSize: 12,
                            color: "#777",
                        }}
                    >
                        {record.contact_name && (
                            <span>
                                <UserOutlined /> {record.contact_name}
                            </span>
                        )}
                        {record.phone && (
                            <span>
                                <PhoneOutlined /> {record.phone}
                            </span>
                        )}
                        <span>Phụ trách: {record.owner_name}</span>
                    </div>
                </div>
            ),
        },
        {
            title: "Giá trị đợt",
            dataIndex: "estimated_value",
            key: "estimated_value",
            align: "right",
            width: 155,
            render: (val: string) => (
                <Text
                    strong
                    style={{
                        color: "#1f1f1f",
                        whiteSpace: "nowrap",
                        fontVariantNumeric: "tabular-nums",
                    }}
                >
                    {money.format(Number(val))}
                </Text>
            ),
        },
        {
            title: "Chu kỳ 15–30 ngày",
            key: "elapsed",
            width: 175,
            render: (_, record) => {
                let tagColor = "default";
                if (record.days_elapsed >= 15 && record.days_elapsed <= 30) {
                    tagColor = "gold";
                } else if (record.days_elapsed > 30) {
                    tagColor = "volcano";
                } else {
                    tagColor = "blue";
                }

                return (
                    <Tooltip
                        title={`Khung nhắc mua lại chuẩn (15–30 ngày): ${record.window_start_at} – ${record.window_end_at}`}
                    >
                        <div
                            style={{
                                display: "flex",
                                flexDirection: "column",
                                alignItems: "flex-start",
                                gap: 4,
                            }}
                        >
                            <Tag
                                color={tagColor}
                                style={{
                                    margin: 0,
                                    fontWeight: 600,
                                    whiteSpace: "nowrap",
                                }}
                            >
                                <ClockCircleOutlined /> Đã qua{" "}
                                {record.days_elapsed} ngày
                            </Tag>
                            <span
                                style={{
                                    fontSize: 12,
                                    color: "#666",
                                    whiteSpace: "nowrap",
                                }}
                            >
                                Ngày chốt:{" "}
                                <strong>
                                    {dayjs(record.deal_at).format("DD/MM/YYYY")}
                                </strong>
                            </span>
                        </div>
                    </Tooltip>
                );
            },
        },
        {
            title: "Trạng thái nhắc",
            key: "cycle_status",
            width: 225,
            render: (_, record) => {
                if (
                    record.cycle_status === "completed" &&
                    record.completed_task
                ) {
                    const resultText =
                        record.completed_task.result || "Đã liên hệ khách hàng";
                    return (
                        <Tooltip title={`Kết quả: ${resultText}`}>
                            <div
                                style={{
                                    display: "flex",
                                    flexDirection: "column",
                                    alignItems: "flex-start",
                                    gap: 4,
                                }}
                            >
                                <Tag
                                    color="success"
                                    style={{ margin: 0, fontWeight: 600 }}
                                >
                                    <CheckCircleOutlined /> Đã nhắc & Lưu lịch sử
                                </Tag>
                                <Text
                                    type="secondary"
                                    ellipsis
                                    style={{ maxWidth: 205, fontSize: 12 }}
                                >
                                    {resultText}
                                </Text>
                            </div>
                        </Tooltip>
                    );
                }

                if (record.cycle_status === "scheduled" && record.open_task) {
                    const dueStr = record.open_task.due_at
                        ? dayjs(record.open_task.due_at).format(
                              "DD/MM/YYYY HH:mm",
                          )
                        : "Đang chờ";
                    const tooltipText = record.completed_task?.result
                        ? `Hẹn nhắc: ${dueStr} · Lần gọi trước: ${record.completed_task.result}`
                        : `${record.open_task.title} (Hạn: ${dueStr})`;

                    return (
                        <Tooltip title={tooltipText}>
                            <div
                                style={{
                                    display: "flex",
                                    flexDirection: "column",
                                    alignItems: "flex-start",
                                    gap: 4,
                                }}
                            >
                                <Tag
                                    color="processing"
                                    style={{ margin: 0, fontWeight: 600 }}
                                >
                                    <CalendarOutlined /> Đã lên lịch nhắc
                                </Tag>
                                <span
                                    style={{
                                        fontSize: 12,
                                        color: "#0958d9",
                                        whiteSpace: "nowrap",
                                    }}
                                >
                                    Hẹn: <strong>{dueStr}</strong>
                                </span>
                            </div>
                        </Tooltip>
                    );
                }

                if (record.cycle_status === "due") {
                    return (
                        <div
                            style={{
                                display: "flex",
                                flexDirection: "column",
                                alignItems: "flex-start",
                                gap: 4,
                            }}
                        >
                            <Tag
                                color="warning"
                                style={{ margin: 0, fontWeight: 600 }}
                            >
                                <BellOutlined /> Đến hạn nhắc (15–30 ngày)
                            </Tag>
                            <span style={{ fontSize: 12, color: "#666" }}>
                                Cần liên hệ chào mua lại
                            </span>
                        </div>
                    );
                }

                if (record.cycle_status === "overdue") {
                    return (
                        <div
                            style={{
                                display: "flex",
                                flexDirection: "column",
                                alignItems: "flex-start",
                                gap: 4,
                            }}
                        >
                            <Tag
                                color="error"
                                style={{ margin: 0, fontWeight: 600 }}
                            >
                                <ExclamationCircleOutlined /> Quá 30 ngày chưa nhắc
                            </Tag>
                            <span style={{ fontSize: 12, color: "#cf1322" }}>
                                Ưu tiên kiểm tra ngay
                            </span>
                        </div>
                    );
                }

                return (
                    <div
                        style={{
                            display: "flex",
                            flexDirection: "column",
                            alignItems: "flex-start",
                            gap: 4,
                        }}
                    >
                        <Tag color="default" style={{ margin: 0 }}>
                            Chưa tới kỳ (còn {record.days_until_window} ngày)
                        </Tag>
                        <span style={{ fontSize: 12, color: "#888" }}>
                            Đợi đủ 15 ngày hoặc đặt lịch trước
                        </span>
                    </div>
                );
            },
        },
        {
            title: "Thao tác",
            key: "actions",
            align: "center",
            width: 265,
            render: (_, record) => (
                <div
                    style={{
                        display: "inline-flex",
                        alignItems: "center",
                        justifyContent: "center",
                        gap: 6,
                        whiteSpace: "nowrap",
                    }}
                >
                    <Tooltip title="Đặt lịch hẹn nhắc nhở tùy chỉnh vào danh sách Nhiệm vụ">
                        <Button
                            size="small"
                            icon={<CalendarOutlined />}
                            onClick={() => openScheduleModal(record)}
                        >
                            {record.open_task ? "Đặt lịch" : "Lên lịch"}
                        </Button>
                    </Tooltip>

                    <Dropdown
                        trigger={["click"]}
                        menu={{
                            items: [
                                {
                                    key: "3",
                                    icon: <ClockCircleOutlined />,
                                    label: "Hẹn gọi lại sau +3 ngày",
                                },
                                {
                                    key: "7",
                                    icon: <ClockCircleOutlined />,
                                    label: "Hẹn gọi lại sau +7 ngày (1 tuần)",
                                },
                                {
                                    key: "14",
                                    icon: <ClockCircleOutlined />,
                                    label: "Hẹn gọi lại sau +14 ngày (2 tuần)",
                                },
                                {
                                    key: "30",
                                    icon: <ClockCircleOutlined />,
                                    label: "Hẹn gọi lại sau +30 ngày (1 tháng)",
                                },
                            ],
                            onClick: ({ key }) =>
                                handleQuickSnooze(record, Number(key)),
                        }}
                    >
                        <Tooltip title="Khách chưa hết hàng — Dời nhanh lịch hẹn gọi lại (+3 / +7 / +14 ngày)">
                            <Button
                                size="small"
                                icon={<ClockCircleOutlined />}
                            >
                                Hẹn lại
                            </Button>
                        </Tooltip>
                    </Dropdown>

                    <Tooltip title="Ghi nhận kết quả đã nhắc khách mua lại và lưu vào Lịch sử">
                        <Button
                            type="primary"
                            size="small"
                            icon={<CheckCircleOutlined />}
                            style={{ backgroundColor: "#c8102e" }}
                            onClick={() => openCompleteModal(record)}
                        >
                            Đã nhắc
                        </Button>
                    </Tooltip>
                </div>
            ),
        },
    ];

    const bucketTabs = [
        {
            key: "all",
            label: `Tất cả đợt (${summary.total})`,
        },
        {
            key: "due",
            label: (
                <Space size={6}>
                    <BellOutlined style={{ color: "#faad14" }} />
                    <span>Đến hạn 15–30 ngày ({summary.due_15_30})</span>
                </Space>
            ),
        },
        {
            key: "overdue",
            label: (
                <Space size={6}>
                    <ExclamationCircleOutlined style={{ color: "#ff4d4f" }} />
                    <span>Quá hạn &gt; 30 ngày ({summary.overdue_30})</span>
                </Space>
            ),
        },
        {
            key: "scheduled",
            label: `Đã lên lịch nhắc (${summary.scheduled})`,
        },
        {
            key: "upcoming",
            label: `Chưa tới hạn < 15 ngày (${summary.upcoming_under_15})`,
        },
        {
            key: "completed",
            label: (
                <Space size={6}>
                    <CheckCircleOutlined style={{ color: "#52c41a" }} />
                    <span>Đã nhắc & Lưu lịch sử ({summary.completed})</span>
                </Space>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Nhắc nhở mua lại (15–30 ngày)" />

            <PageHeader
                title="Nhắc nhở khách hàng mua lại"
                subtitle="Theo dõi các đợt báo giá và đơn hàng mua bán thành công để nhắc khách hàng đặt mua lại sau 15–30 ngày, đồng bộ trực tiếp với Nhiệm vụ và Lịch sử chăm sóc."
                extra={
                    <Space>
                        <Button
                            icon={<HistoryOutlined />}
                            onClick={() =>
                                router.visit("/history?type=task")
                            }
                        >
                            Xem Lịch sử nhiệm vụ
                        </Button>
                        <Button
                            icon={<ShoppingCartOutlined />}
                            onClick={() =>
                                router.visit("/opportunities")
                            }
                        >
                            Quản lý Cơ hội
                        </Button>
                    </Space>
                }
            />

            <Space
                direction="vertical"
                size={20}
                style={{ width: "100%", marginTop: 20 }}
            >
                {/* Thống kê tổng quan chu kỳ 15-30 ngày */}
                <Row gutter={[16, 16]}>
                    <Col xs={24} sm={12} lg={6}>
                        <Card className="crm-panel" bordered={false}>
                            <Statistic
                                title="Đến hạn nhắc (15–30 ngày)"
                                value={summary.due_15_30}
                                valueStyle={{ color: "#d48806", fontWeight: 700 }}
                                prefix={<BellOutlined />}
                                suffix="đợt"
                            />
                            <Text type="secondary" style={{ fontSize: 12 }}>
                                Khung thời gian vàng để chào mua lại
                            </Text>
                        </Card>
                    </Col>

                    <Col xs={24} sm={12} lg={6}>
                        <Card className="crm-panel" bordered={false}>
                            <Statistic
                                title="Quá 30 ngày chưa nhắc"
                                value={summary.overdue_30}
                                valueStyle={{ color: "#cf1322", fontWeight: 700 }}
                                prefix={<ExclamationCircleOutlined />}
                                suffix="đợt"
                            />
                            <Text type="secondary" style={{ fontSize: 12 }}>
                                Cần ưu tiên liên hệ tránh mất khách
                            </Text>
                        </Card>
                    </Col>

                    <Col xs={24} sm={12} lg={6}>
                        <Card className="crm-panel" bordered={false}>
                            <Statistic
                                title="Đã lên lịch hẹn nhắc"
                                value={summary.scheduled}
                                valueStyle={{ color: "#1677ff", fontWeight: 700 }}
                                prefix={<CalendarOutlined />}
                                suffix="đợt"
                            />
                            <Text type="secondary" style={{ fontSize: 12 }}>
                                Đang theo dõi trong mục Nhiệm vụ
                            </Text>
                        </Card>
                    </Col>

                    <Col xs={24} sm={12} lg={6}>
                        <Card className="crm-panel" bordered={false}>
                            <Statistic
                                title="Đã nhắc & Lưu lịch sử"
                                value={summary.completed}
                                valueStyle={{ color: "#389e0d", fontWeight: 700 }}
                                prefix={<CheckCircleOutlined />}
                                suffix="đợt"
                            />
                            <Text type="secondary" style={{ fontSize: 12 }}>
                                Đã ghi nhận kết quả vào trang Lịch sử
                            </Text>
                        </Card>
                    </Col>
                </Row>

                {/* Bộ lọc & Bảng danh sách */}
                <Card className="crm-panel" bordered={false}>
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: 12,
                            width: "100%",
                            marginBottom: 16,
                            flexWrap: "nowrap",
                        }}
                    >
                        <Input
                            placeholder="Tìm theo tên đơn hàng, công ty, liên hệ, SĐT..."
                            prefix={
                                <SearchOutlined
                                    style={{ color: "#999" }}
                                />
                            }
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onPressEnter={() =>
                                applyFilter({ q: search })
                            }
                            allowClear
                            style={{ flex: "1 1 260px", minWidth: 200 }}
                            size="large"
                        />

                        <Select
                            value={filters.stage}
                            onChange={(val) =>
                                applyFilter({ stage: val })
                            }
                            style={{ width: 280, flexShrink: 0 }}
                            size="large"
                            options={[
                                {
                                    value: "all",
                                    label: `Tất cả nguồn (${summary.total})`,
                                },
                                {
                                    value: "won",
                                    label: `Mua bán thành công (${summary.won_count})`,
                                },
                                {
                                    value: "proposal",
                                    label: `Đợt báo giá (${summary.proposal_count})`,
                                },
                            ]}
                        />

                        <Button
                            type="primary"
                            size="large"
                            style={{
                                backgroundColor: "#c8102e",
                                flexShrink: 0,
                            }}
                            onClick={() => applyFilter({ q: search })}
                        >
                            Tìm kiếm
                        </Button>

                        <Button
                            size="large"
                            style={{ flexShrink: 0 }}
                            disabled={
                                !search &&
                                !filters.q &&
                                filters.stage === "all" &&
                                filters.bucket === "all"
                            }
                            onClick={() => {
                                setSearch("");
                                applyFilter({
                                    q: "",
                                    stage: "all",
                                    bucket: "all",
                                });
                            }}
                        >
                            Xóa lọc
                        </Button>
                    </div>

                    <Tabs
                        activeKey={filters.bucket}
                        items={bucketTabs}
                        onChange={(key) => applyFilter({ bucket: key })}
                    />

                    <Table<RepurchaseReminderItem>
                        rowKey="id"
                        columns={columns}
                        dataSource={reminders}
                        scroll={{ x: 1080 }}
                        pagination={{
                            pageSize: 15,
                            showSizeChanger: true,
                            showTotal: (total) => `${total} đợt báo giá / mua hàng`,
                        }}
                        locale={{
                            emptyText: (
                                <Empty
                                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                                    description="Chưa có đợt báo giá hoặc mua bán thành công nào khớp với bộ lọc hiện tại."
                                />
                            ),
                        }}
                    />
                </Card>
            </Space>

            {/* Modal 1: Lên lịch nhắc mua lại */}
            <Modal
                title={
                    <Space>
                        <CalendarOutlined style={{ color: "#c8102e" }} />
                        <span>
                            Lên lịch nhắc khách hàng mua lại (Chu kỳ 15–30 ngày)
                        </span>
                    </Space>
                }
                open={!!schedulingItem}
                onCancel={() => setSchedulingItem(null)}
                footer={null}
                destroyOnClose
            >
                <Form
                    layout="vertical"
                    onFinish={handleScheduleSubmit}
                    style={{ marginTop: 12 }}
                >
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message={`Đợt gốc: ${schedulingItem?.title} (${schedulingItem?.stage_label})`}
                        description={`Khách hàng: ${schedulingItem?.company} · Đã qua ${schedulingItem?.days_elapsed ?? 0} ngày (Khung 15–30 ngày: ${schedulingItem?.window_start_at} – ${schedulingItem?.window_end_at}).`}
                    />

                    <Form.Item
                        label="Tiêu đề nhiệm vụ nhắc mua lại"
                        required
                        validateStatus={
                            scheduleForm.errors.title ? "error" : ""
                        }
                        help={scheduleForm.errors.title}
                    >
                        <Input
                            value={scheduleForm.data.title}
                            onChange={(e) =>
                                scheduleForm.setData("title", e.target.value)
                            }
                            size="large"
                        />
                    </Form.Item>

                    <Row gutter={12}>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                label="Hình thức liên hệ"
                                required
                                validateStatus={
                                    scheduleForm.errors.type ? "error" : ""
                                }
                                help={scheduleForm.errors.type}
                            >
                                <Select
                                    value={scheduleForm.data.type}
                                    onChange={(val) =>
                                        scheduleForm.setData("type", val)
                                    }
                                    size="large"
                                    options={Object.entries(
                                        taskOptions.types,
                                    ).map(([value, label]) => ({
                                        value,
                                        label,
                                    }))}
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={12}>
                            <Form.Item
                                label="Mức độ ưu tiên"
                                required
                                validateStatus={
                                    scheduleForm.errors.priority ? "error" : ""
                                }
                                help={scheduleForm.errors.priority}
                            >
                                <Select
                                    value={scheduleForm.data.priority}
                                    onChange={(val) =>
                                        scheduleForm.setData("priority", val)
                                    }
                                    size="large"
                                    options={Object.entries(
                                        taskOptions.priorities,
                                    ).map(([value, label]) => ({
                                        value,
                                        label,
                                    }))}
                                />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Row gutter={12}>
                        <Col xs={24} sm={isManager ? 12 : 24}>
                            <Form.Item
                                label="Thời hạn nhắc (Ngày & Giờ)"
                                required
                                validateStatus={
                                    scheduleForm.errors.due_at ? "error" : ""
                                }
                                help={scheduleForm.errors.due_at}
                            >
                                <Input
                                    type="datetime-local"
                                    value={scheduleForm.data.due_at}
                                    onChange={(e) =>
                                        scheduleForm.setData(
                                            "due_at",
                                            e.target.value,
                                        )
                                    }
                                    size="large"
                                />
                            </Form.Item>
                        </Col>

                        {isManager && (
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    label="Người thực hiện"
                                    validateStatus={
                                        scheduleForm.errors.assignee_id
                                            ? "error"
                                            : ""
                                    }
                                    help={scheduleForm.errors.assignee_id}
                                >
                                    <Select
                                        value={scheduleForm.data.assignee_id}
                                        onChange={(val) =>
                                            scheduleForm.setData(
                                                "assignee_id",
                                                val,
                                            )
                                        }
                                        size="large"
                                        options={assignees.map((u) => ({
                                            value: u.id,
                                            label: u.name,
                                        }))}
                                    />
                                </Form.Item>
                            </Col>
                        )}
                    </Row>

                    <Form.Item
                        label="Ghi chú vật tư / mặt hàng cần chào mua lại"
                        validateStatus={
                            scheduleForm.errors.description ? "error" : ""
                        }
                        help={scheduleForm.errors.description}
                    >
                        <TextArea
                            rows={3}
                            value={scheduleForm.data.description}
                            onChange={(e) =>
                                scheduleForm.setData(
                                    "description",
                                    e.target.value,
                                )
                            }
                        />
                    </Form.Item>

                    <div style={{ textAlign: "right", marginTop: 16 }}>
                        <Space>
                            <Button
                                onClick={() => setSchedulingItem(null)}
                                size="large"
                            >
                                Hủy
                            </Button>
                            <Button
                                type="primary"
                                htmlType="submit"
                                loading={scheduleForm.processing}
                                style={{ backgroundColor: "#c8102e" }}
                                size="large"
                            >
                                Lưu lịch nhắc
                            </Button>
                        </Space>
                    </div>
                </Form>
            </Modal>

            {/* Modal 2: Ghi nhận đã nhắc mua lại & Lưu vào Lịch sử */}
            <Modal
                title={
                    <Space>
                        <RetweetOutlined style={{ color: "#c8102e" }} />
                        <span>
                            Ghi nhận kết quả nhắc mua lại & Lưu vào Lịch sử
                        </span>
                    </Space>
                }
                open={!!completingItem}
                onCancel={() => setCompletingItem(null)}
                footer={null}
                destroyOnClose
            >
                <Form
                    layout="vertical"
                    onFinish={handleCompleteSubmit}
                    style={{ marginTop: 12 }}
                >
                    <Paragraph type="secondary">
                        Kết quả chăm sóc đợt{" "}
                        <strong>{completingItem?.title}</strong> của khách hàng{" "}
                        <strong>{completingItem?.company}</strong> sẽ được lưu
                        trực tiếp vào <strong>Lịch sử nhiệm vụ (/history)</strong>{" "}
                        và <strong>Nhật ký chăm sóc Khách hàng</strong>.
                    </Paragraph>

                    <Form.Item
                        label="Kết quả liên hệ nhắc mua lại"
                        required
                        validateStatus={
                            completeForm.errors.result ? "error" : ""
                        }
                        help={completeForm.errors.result}
                    >
                        <TextArea
                            rows={4}
                            placeholder="VD: Đã gọi cho bộ phận thu mua, khách báo còn tồn kho đủ 1 tuần và yêu cầu gửi lại báo giá găng tay chống cắt + giày bảo hộ..."
                            value={completeForm.data.result}
                            onChange={(e) =>
                                completeForm.setData("result", e.target.value)
                            }
                        />
                    </Form.Item>

                    <Form.Item style={{ marginBottom: 12 }}>
                        <Checkbox
                            checked={completeForm.data.create_reorder}
                            onChange={(e) => {
                                const checked = e.target.checked;
                                completeForm.setData({
                                    ...completeForm.data,
                                    create_reorder: checked,
                                    snooze_enabled: checked
                                        ? false
                                        : completeForm.data.snooze_enabled,
                                });
                            }}
                        >
                            <Space>
                                <PlusCircleOutlined
                                    style={{ color: "#389e0d" }}
                                />
                                <span>
                                    Khách đồng ý đặt tiếp — Tạo luôn{" "}
                                    <strong>Cơ hội báo giá mua lại mới</strong>
                                </span>
                            </Space>
                        </Checkbox>
                    </Form.Item>

                    {completeForm.data.create_reorder && (
                        <Form.Item
                            label="Giá trị dự kiến của đợt mua lại mới (VNĐ)"
                            validateStatus={
                                completeForm.errors.reorder_value
                                    ? "error"
                                    : ""
                            }
                            help={completeForm.errors.reorder_value}
                        >
                            <InputNumber
                                style={{ width: "100%" }}
                                size="large"
                                min={0}
                                step={500000}
                                value={completeForm.data.reorder_value}
                                onChange={(val) =>
                                    completeForm.setData(
                                        "reorder_value",
                                        Number(val || 0),
                                    )
                                }
                            />
                        </Form.Item>
                    )}

                    <Form.Item style={{ marginBottom: 12 }}>
                        <Checkbox
                            checked={completeForm.data.snooze_enabled}
                            onChange={(e) => {
                                const checked = e.target.checked;
                                completeForm.setData({
                                    ...completeForm.data,
                                    snooze_enabled: checked,
                                    create_reorder: checked
                                        ? false
                                        : completeForm.data.create_reorder,
                                });
                            }}
                        >
                            <Space>
                                <ClockCircleOutlined
                                    style={{ color: "#1677ff" }}
                                />
                                <span>
                                    Khách chưa hết hàng —{" "}
                                    <strong>
                                        Tự động hẹn gọi nhắc lại sau (+ngày)
                                    </strong>
                                </span>
                            </Space>
                        </Checkbox>
                    </Form.Item>

                    {completeForm.data.snooze_enabled && (
                        <Form.Item
                            label="Khoảng thời gian hẹn gọi lại tiếp theo"
                            validateStatus={
                                completeForm.errors.snooze_days ? "error" : ""
                            }
                            help={completeForm.errors.snooze_days}
                        >
                            <Select
                                size="large"
                                value={completeForm.data.snooze_days}
                                onChange={(val) =>
                                    completeForm.setData("snooze_days", val)
                                }
                                options={[
                                    {
                                        value: 3,
                                        label: "Sau +3 ngày nữa",
                                    },
                                    {
                                        value: 7,
                                        label: "Sau +7 ngày nữa (1 tuần)",
                                    },
                                    {
                                        value: 14,
                                        label: "Sau +14 ngày nữa (2 tuần)",
                                    },
                                    {
                                        value: 30,
                                        label: "Sau +30 ngày nữa (1 tháng)",
                                    },
                                ]}
                            />
                        </Form.Item>
                    )}

                    <div style={{ textAlign: "right", marginTop: 16 }}>
                        <Space>
                            <Button
                                onClick={() => setCompletingItem(null)}
                                size="large"
                            >
                                Hủy
                            </Button>
                            <Button
                                type="primary"
                                htmlType="submit"
                                loading={completeForm.processing}
                                style={{ backgroundColor: "#c8102e" }}
                                size="large"
                            >
                                Xác nhận & Lưu lịch sử
                            </Button>
                        </Space>
                    </div>
                </Form>
            </Modal>
        </AppLayout>
    );
}
