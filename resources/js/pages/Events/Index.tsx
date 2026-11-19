import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import {
    Badge,
    Button,
    Card,
    Empty,
    Input,
    Radio,
    Select,
    Space,
    Tag,
    Typography,
} from "antd";
import {
    ClockCircleOutlined,
    FireOutlined,
    GiftOutlined,
    RightOutlined,
    SearchOutlined,
    ShopOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import TaskModal from "@/components/tasks/TaskModal";
import type { PageProps } from "@/types";
import type { TaskAssignee, TaskOptions, TaskReference } from "@/types/task";
import type { UpcomingEvent, UpcomingEventsData } from "@/components/workbench/UpcomingEventsCard";

dayjs.extend(utc);
dayjs.extend(timezone);

interface Filters {
    range: string;
    category: string;
    q: string;
}

interface Props extends PageProps {
    eventsData: UpcomingEventsData;
    filters: Filters;
    options: TaskOptions;
    assignees: TaskAssignee[];
}

export default function EventsIndex({
    auth,
    eventsData,
    filters,
    options,
    assignees,
}: Props) {
    const [search, setSearch] = useState(filters.q ?? "");
    const [editor, setEditor] = useState<{
        open: boolean;
        initialTitle: string | null;
        initialReference: TaskReference | null;
        initialDueAt: dayjs.Dayjs | null;
        initialType: string | null;
    }>({
        open: false,
        initialTitle: null,
        initialReference: null,
        initialDueAt: null,
        initialType: null,
    });

    const isManager = auth.user?.role !== "sales";

    function applyFilter(patch: Partial<Filters>) {
        const next = {
            range: patch.range ?? filters.range,
            category: patch.category ?? filters.category,
            q: patch.q !== undefined ? patch.q : search,
        };

        router.get("/events", next, {
            preserveState: true,
            replace: true,
        });
    }

    function handleCreateTask(suggested: UpcomingEvent["suggested_task"]) {
        setEditor({
            open: true,
            initialTitle: suggested.title,
            initialReference: suggested.reference
                ? { value: suggested.reference, label: suggested.title }
                : null,
            initialDueAt: dayjs(suggested.due_at).tz("Asia/Ho_Chi_Minh"),
            initialType: suggested.type,
        });
    }

    return (
        <AppLayout>
            <Head title="Sự kiện & Ngày lễ" />

            <div style={{ display: "flex", flexDirection: "column", gap: 24 }}>
                <PageHeader
                    title="Sự kiện & Ngày lễ"
                    subtitle="Theo dõi ngày kỷ niệm thành lập công ty khách hàng và các ngày lễ để kịp thời chúc mừng, tri ân & chăm sóc khách hàng."
                />

                {/* Metrics Summary */}
                <div
                    style={{
                        display: "grid",
                        gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                        gap: 16,
                    }}
                >
                    <Card size="small" style={{ borderRadius: 12 }}>
                        <div style={{ color: "#666", fontSize: 13, marginBottom: 4 }}>
                            Tổng số sự kiện
                        </div>
                        <div style={{ fontSize: 28, fontWeight: 700, color: "#1e293b" }}>
                            {eventsData.total}
                        </div>
                        <div style={{ fontSize: 12, color: "#888", marginTop: 4 }}>
                            Trong vòng {eventsData.notice_days} ngày tới
                        </div>
                    </Card>

                    <Card
                        size="small"
                        style={{
                            borderRadius: 12,
                            borderColor: eventsData.today_count > 0 ? "#fca5a5" : undefined,
                            background: eventsData.today_count > 0 ? "#fff5f5" : undefined,
                        }}
                    >
                        <div style={{ color: "#666", fontSize: 13, marginBottom: 4 }}>
                            🔥 Sự kiện hôm nay
                        </div>
                        <div
                            style={{
                                fontSize: 28,
                                fontWeight: 700,
                                color: eventsData.today_count > 0 ? "#dc2626" : "#1e293b",
                            }}
                        >
                            {eventsData.today_count}
                        </div>
                        <div style={{ fontSize: 12, color: eventsData.today_count > 0 ? "#dc2626" : "#888", marginTop: 4 }}>
                            {eventsData.today_count > 0 ? "Cần liên hệ chúc mừng ngay!" : "Không có sự kiện hôm nay"}
                        </div>
                    </Card>

                    <Card size="small" style={{ borderRadius: 12 }}>
                        <div style={{ color: "#666", fontSize: 13, marginBottom: 4 }}>
                            🏢 Kỷ niệm thành lập
                        </div>
                        <div style={{ fontSize: 28, fontWeight: 700, color: "#7c3aed" }}>
                            {eventsData.company_count}
                        </div>
                        <div style={{ fontSize: 12, color: "#888", marginTop: 4 }}>
                            Ngày thành lập công ty khách hàng
                        </div>
                    </Card>

                    <Card size="small" style={{ borderRadius: 12 }}>
                        <div style={{ color: "#666", fontSize: 13, marginBottom: 4 }}>
                            🎈 Ngày lễ & Sự kiện
                        </div>
                        <div style={{ fontSize: 28, fontWeight: 700, color: "#ea580c" }}>
                            {eventsData.holiday_count}
                        </div>
                        <div style={{ fontSize: 12, color: "#888", marginTop: 4 }}>
                            Ngày lễ quốc gia & sự kiện chung
                        </div>
                    </Card>
                </div>

                {/* Filters Card */}
                <Card size="small" style={{ borderRadius: 12 }}>
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            gap: 16,
                            flexWrap: "wrap",
                        }}
                    >
                        <Space wrap size="middle">
                            <span style={{ fontWeight: 600, fontSize: 14 }}>Khoảng thời gian:</span>
                            <Select
                                value={filters.range}
                                style={{ width: 190 }}
                                onChange={(value) => applyFilter({ range: value })}
                                options={[
                                    { value: "7", label: "7 ngày tới (1 tuần tới)" },
                                    { value: "14", label: "14 ngày tới (2 tuần)" },
                                    { value: "30", label: "30 ngày tới (1 tháng)" },
                                    { value: "90", label: "90 ngày tới (Quý này)" },
                                    { value: "365", label: "365 ngày (Cả năm)" },
                                ]}
                            />

                            <Radio.Group
                                value={filters.category}
                                onChange={(e) => applyFilter({ category: e.target.value })}
                                optionType="button"
                                buttonStyle="solid"
                            >
                                <Radio.Button value="all">Tất cả ({eventsData.total})</Radio.Button>
                                <Radio.Button value="company_anniversary">
                                    🏢 Thành lập ({eventsData.company_count})
                                </Radio.Button>
                                <Radio.Button value="holiday">
                                    🎈 Ngày lễ ({eventsData.holiday_count})
                                </Radio.Button>
                            </Radio.Group>
                        </Space>

                        <div style={{ minWidth: 260 }}>
                            <Input.Search
                                placeholder="Tìm khách hàng hoặc ngày lễ..."
                                allowClear
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onSearch={(val) => applyFilter({ q: val })}
                            />
                        </div>
                    </div>
                </Card>

                {/* Events List / Grid */}
                {eventsData.events.length === 0 ? (
                    <Card style={{ borderRadius: 12, textAlign: "center", padding: "40px 20px" }}>
                        <Empty
                            image={Empty.PRESENTED_IMAGE_SIMPLE}
                            description={
                                <div style={{ color: "#666" }}>
                                    <p style={{ fontSize: 15, margin: 0 }}>
                                        Không tìm thấy sự kiện nào trong khoảng thời gian này.
                                    </p>
                                    <p style={{ fontSize: 13, color: "#888", marginTop: 8 }}>
                                        💡 Hãy cập nhật <strong>Ngày thành lập</strong> trong hồ sơ khách hàng để hệ thống tự động thông báo trước.
                                    </p>
                                </div>
                            }
                        />
                    </Card>
                ) : (
                    <div className="crm-workbench-events__grid">
                        {eventsData.events.map((event) => {
                            const isCompany = event.category === "company_anniversary";

                            return (
                                <div
                                    key={event.id}
                                    className={`crm-workbench-event-card ${
                                        isCompany
                                            ? "crm-workbench-event-card--company"
                                            : "crm-workbench-event-card--holiday"
                                    } ${event.is_today ? "crm-workbench-event-card--today" : ""}`}
                                >
                                    <div className="crm-workbench-event-card__top">
                                        <div className="crm-workbench-event-card__icon-box">
                                            <span style={{ fontSize: 24 }}>{event.icon}</span>
                                        </div>

                                        <div className="crm-workbench-event-card__header-info">
                                            <div
                                                style={{
                                                    display: "flex",
                                                    alignItems: "center",
                                                    gap: 6,
                                                    flexWrap: "wrap",
                                                }}
                                            >
                                                <Tag
                                                    color={isCompany ? "purple" : "volcano"}
                                                    style={{ margin: 0, fontSize: 11 }}
                                                >
                                                    {event.category_label}
                                                </Tag>

                                                <Tag
                                                    color={
                                                        event.is_today
                                                            ? "red"
                                                            : event.days_left === 1
                                                              ? "orange"
                                                              : "blue"
                                                    }
                                                    style={{
                                                        margin: 0,
                                                        fontWeight: 600,
                                                        display: "inline-flex",
                                                        alignItems: "center",
                                                        gap: 4,
                                                    }}
                                                >
                                                    {event.is_today ? (
                                                        <FireOutlined />
                                                    ) : (
                                                        <ClockCircleOutlined />
                                                    )}
                                                    {event.badge}
                                                </Tag>

                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        color: "#666",
                                                        marginLeft: "auto",
                                                    }}
                                                >
                                                    📅 {event.display_date}
                                                </span>
                                            </div>

                                            <h4 className="crm-workbench-event-card__title">
                                                {event.customer ? (
                                                    <Link
                                                        href={event.customer.href}
                                                        title="Xem hồ sơ khách hàng"
                                                    >
                                                        {event.title}
                                                    </Link>
                                                ) : (
                                                    <span>{event.title}</span>
                                                )}
                                            </h4>

                                            <div className="crm-workbench-event-card__subtitle">
                                                {event.subtitle}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="crm-workbench-event-card__bottom">
                                        <div className="crm-workbench-event-card__desc">
                                            {event.description}
                                            {event.customer?.owner_name && (
                                                <span style={{ marginLeft: 8, color: "#666" }}>
                                                    · Phụ trách:{" "}
                                                    <strong>
                                                        {event.customer.owner_name}
                                                    </strong>
                                                </span>
                                            )}
                                        </div>

                                        <div className="crm-workbench-event-card__actions">
                                            <Button
                                                type="primary"
                                                size="small"
                                                icon={<GiftOutlined />}
                                                style={{
                                                    backgroundColor: isCompany
                                                        ? "#7c3aed"
                                                        : "#ea580c",
                                                    borderColor: isCompany
                                                        ? "#7c3aed"
                                                        : "#ea580c",
                                                }}
                                                onClick={() =>
                                                    handleCreateTask(event.suggested_task)
                                                }
                                            >
                                                {isCompany
                                                    ? "Tạo việc chúc mừng"
                                                    : "Tạo việc chăm sóc"}
                                            </Button>

                                            {event.customer && (
                                                <Button
                                                    size="small"
                                                    type="text"
                                                    icon={<RightOutlined />}
                                                    href={event.customer.href}
                                                >
                                                    Xem hồ sơ
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Quick Task Creation Modal */}
            <TaskModal
                open={editor.open}
                task={null}
                options={options}
                assignees={assignees}
                currentUserId={auth.user!.id}
                initialReference={editor.initialReference}
                initialDueAt={editor.initialDueAt}
                initialTitle={editor.initialTitle}
                initialType={editor.initialType}
                onClose={() =>
                    setEditor({
                        open: false,
                        initialTitle: null,
                        initialReference: null,
                        initialDueAt: null,
                        initialType: null,
                    })
                }
            />
        </AppLayout>
    );
}
