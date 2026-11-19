import { useState } from "react";
import { Link } from "@inertiajs/react";
import { Badge, Button, Card, Empty, Radio, Space, Tag, Tooltip, Typography } from "antd";
import {
    CalendarOutlined,
    ClockCircleOutlined,
    FireOutlined,
    GiftOutlined,
    NotificationOutlined,
    RightOutlined,
    ShopOutlined,
} from "@ant-design/icons";

export interface UpcomingEvent {
    id: string;
    category: "company_anniversary" | "holiday";
    category_label: string;
    icon: string;
    title: string;
    subtitle: string;
    description: string;
    event_date: string;
    display_date: string;
    days_left: number;
    is_today: boolean;
    badge: string;
    customer: {
        id: number;
        code: string;
        name: string;
        href: string;
        phone: string | null;
        owner_name: string | null;
    } | null;
    suggested_task: {
        title: string;
        reference: string | null;
        due_at: string;
        type: string;
    };
}

export interface UpcomingEventsData {
    events: UpcomingEvent[];
    total: number;
    today_count: number;
    company_count: number;
    holiday_count: number;
    notice_days: number;
}

interface Props {
    data?: UpcomingEventsData;
    onCreateTask: (suggested: {
        title: string;
        reference: string | null;
        due_at: string;
        type: string;
    }) => void;
}

export default function UpcomingEventsCard({ data, onCreateTask }: Props) {
    const [filterCategory, setFilterCategory] = useState<"all" | "company_anniversary" | "holiday">("all");

    if (!data) return null;

    const filteredEvents = data.events.filter((e) => {
        if (filterCategory === "all") return true;
        return e.category === filterCategory;
    });

    return (
        <Card
            className="crm-workbench-events"
            title={
                <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                    <span style={{ fontSize: 20 }}>🎉</span>
                    <Typography.Title level={4} style={{ margin: 0, fontSize: 16 }}>
                        Sự kiện & Ngày lễ sắp tới
                    </Typography.Title>
                    <Badge
                        count={data.total}
                        style={{
                            backgroundColor: data.today_count > 0 ? "#dc2626" : "#2563eb",
                        }}
                    />
                    <span style={{ fontSize: 13, color: "#666", fontWeight: "normal" }}>
                        (Thông báo trước {data.notice_days} ngày)
                    </span>
                </div>
            }
            extra={
                <Radio.Group
                    size="small"
                    value={filterCategory}
                    onChange={(e) => setFilterCategory(e.target.value)}
                    optionType="button"
                    buttonStyle="solid"
                >
                    <Radio.Button value="all">Tất cả ({data.total})</Radio.Button>
                    <Radio.Button value="company_anniversary">
                        🏢 Thành lập ({data.company_count})
                    </Radio.Button>
                    <Radio.Button value="holiday">
                        🎈 Ngày lễ ({data.holiday_count})
                    </Radio.Button>
                </Radio.Group>
            }
        >
            {data.total === 0 ? (
                <Empty
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    description={
                        <div style={{ color: "#666" }}>
                            <span>Không có ngày kỷ niệm thành lập công ty hay ngày lễ nào trong 7 ngày tới.</span>
                            <div style={{ fontSize: 12, marginTop: 4, color: "#888" }}>
                                💡 Nhập <strong>Ngày thành lập</strong> trong hồ sơ khách hàng để hệ thống tự động nhắc nhở trước 1 tuần.
                            </div>
                        </div>
                    }
                />
            ) : filteredEvents.length === 0 ? (
                <Empty
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    description="Không có sự kiện nào thuộc danh mục này."
                />
            ) : (
                <div className="crm-workbench-events__grid">
                    {filteredEvents.map((event) => {
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
                                        <span style={{ fontSize: 22 }}>{event.icon}</span>
                                    </div>

                                    <div className="crm-workbench-event-card__header-info">
                                        <div style={{ display: "flex", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
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
                                                {event.is_today ? <FireOutlined /> : <ClockCircleOutlined />}
                                                {event.badge}
                                            </Tag>

                                            <span style={{ fontSize: 12, color: "#666", marginLeft: "auto" }}>
                                                📅 {event.display_date}
                                            </span>
                                        </div>

                                        <h4 className="crm-workbench-event-card__title">
                                            {event.customer ? (
                                                <Link href={event.customer.href} title="Xem hồ sơ khách hàng">
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
                                                · Phụ trách: <strong>{event.customer.owner_name}</strong>
                                            </span>
                                        )}
                                    </div>

                                    <div className="crm-workbench-event-card__actions">
                                        <Button
                                            type="primary"
                                            size="small"
                                            icon={<GiftOutlined />}
                                            style={{
                                                backgroundColor: isCompany ? "#7c3aed" : "#ea580c",
                                                borderColor: isCompany ? "#7c3aed" : "#ea580c",
                                            }}
                                            onClick={() => onCreateTask(event.suggested_task)}
                                        >
                                            {isCompany ? "Tạo việc chúc mừng" : "Tạo việc chăm sóc"}
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
        </Card>
    );
}
