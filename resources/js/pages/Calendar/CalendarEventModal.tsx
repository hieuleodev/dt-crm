import { Button, Descriptions, Modal, Space, Tag, Typography } from "antd";
import {
    CalendarOutlined,
    ClockCircleOutlined,
    FireOutlined,
    GiftOutlined,
    RightOutlined,
    ShopOutlined,
} from "@ant-design/icons";
import type { UpcomingEvent } from "./types";

interface Props {
    event: UpcomingEvent | null;
    onClose: () => void;
    onCreateTask: (suggested: UpcomingEvent["suggested_task"]) => void;
}

export default function CalendarEventModal({
    event,
    onClose,
    onCreateTask,
}: Props) {
    if (!event) return null;

    const isCompany = event.category === "company_anniversary";

    return (
        <Modal
            open={Boolean(event)}
            onCancel={onClose}
            title={
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <span style={{ fontSize: 24 }}>{event.icon}</span>
                    <div>
                        <Typography.Title level={4} style={{ margin: 0, fontSize: 16 }}>
                            {event.title}
                        </Typography.Title>
                        <div style={{ fontSize: 13, color: "#666", fontWeight: "normal" }}>
                            {event.subtitle}
                        </div>
                    </div>
                </div>
            }
            footer={
                <Space>
                    <Button onClick={onClose}>Đóng</Button>
                    {event.customer && (
                        <Button
                            icon={<RightOutlined />}
                            href={event.customer.href}
                            target="_blank"
                        >
                            Xem hồ sơ KH
                        </Button>
                    )}
                    <Button
                        type="primary"
                        icon={<GiftOutlined />}
                        style={{
                            backgroundColor: isCompany ? "#7c3aed" : "#ea580c",
                            borderColor: isCompany ? "#7c3aed" : "#ea580c",
                        }}
                        onClick={() => {
                            onCreateTask(event.suggested_task);
                            onClose();
                        }}
                    >
                        {isCompany ? "Tạo việc chúc mừng" : "Tạo việc chăm sóc"}
                    </Button>
                </Space>
            }
        >
            <div style={{ display: "flex", flexDirection: "column", gap: 16, marginTop: 12 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                    <Tag color={isCompany ? "purple" : "volcano"}>
                        {event.category_label}
                    </Tag>
                    <Tag
                        color={
                            event.is_today
                                ? "red"
                                : event.days_left === 1
                                  ? "orange"
                                  : event.days_left < 0
                                    ? "default"
                                    : "blue"
                        }
                        style={{
                            fontWeight: 600,
                            display: "inline-flex",
                            alignItems: "center",
                            gap: 4,
                        }}
                    >
                        {event.is_today ? <FireOutlined /> : <ClockCircleOutlined />}
                        {event.badge}
                    </Tag>
                    <Tag icon={<CalendarOutlined />}>
                        Ngày diễn ra: {event.display_date}
                    </Tag>
                </div>

                <div
                    style={{
                        padding: "12px 14px",
                        background: isCompany ? "#faf5ff" : "#fff7ed",
                        borderRadius: 8,
                        border: `1px solid ${isCompany ? "#e9d5ff" : "#fed7aa"}`,
                        fontSize: 14,
                        color: "#334155",
                    }}
                >
                    {event.description}
                </div>

                {event.customer && (
                    <Descriptions
                        title="Thông tin khách hàng"
                        size="small"
                        bordered
                        column={1}
                        items={[
                            {
                                key: "name",
                                label: "Tên khách hàng",
                                children: (
                                    <a href={event.customer.href} target="_blank" rel="noreferrer">
                                        {event.customer.name} ({event.customer.code})
                                    </a>
                                ),
                            },
                            {
                                key: "owner",
                                label: "Nhân viên phụ trách",
                                children: event.customer.owner_name ?? "Chưa phân công",
                            },
                            {
                                key: "phone",
                                label: "Số điện thoại",
                                children: event.customer.phone ?? "Chưa cập nhật",
                            },
                        ]}
                    />
                )}
            </div>
        </Modal>
    );
}
