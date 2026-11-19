import { Button, List, Modal, Tag, Typography } from "antd";
import { GiftOutlined, PlusOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import type { TaskOptions, TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG, type UpcomingEvent } from "./types";

interface Props {
    date: dayjs.Dayjs | null;
    open: boolean;
    tasks: TaskRow[];
    events?: UpcomingEvent[];
    options: TaskOptions;
    onClose: () => void;
    onSelectTask: (task: TaskRow) => void;
    onSelectEvent?: (event: UpcomingEvent) => void;
    onCreateOnDate?: (date: dayjs.Dayjs) => void;
}

export default function DayEventsModal({
    date,
    open,
    tasks,
    events = [],
    options,
    onClose,
    onSelectTask,
    onSelectEvent,
    onCreateOnDate,
}: Props) {
    if (!date) return null;

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={620}
            title={
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", paddingRight: 24 }}>
                    <div>
                        <Typography.Title level={4} style={{ margin: 0, fontSize: 16 }}>
                            {date.format("dddd, [ngày] DD/MM/YYYY")}
                        </Typography.Title>
                        <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                            {events.length > 0 && `${events.length} sự kiện · `}
                            {tasks.length} nhiệm vụ
                        </Typography.Text>
                    </div>
                    {onCreateOnDate && (
                        <Button
                            type="primary"
                            size="small"
                            icon={<PlusOutlined />}
                            onClick={() => {
                                onClose();
                                onCreateOnDate(date);
                            }}
                        >
                            Thêm lịch
                        </Button>
                    )}
                </div>
            }
            footer={<Button onClick={onClose}>Đóng</Button>}
        >
            <div style={{ display: "flex", flexDirection: "column", gap: 16, marginTop: 12 }}>
                {/* Events list */}
                {events.length > 0 && (
                    <div>
                        <Typography.Text strong style={{ color: "#7c3aed", fontSize: 13, marginBottom: 8, display: "block" }}>
                            🎉 Sự kiện & Ngày lễ ({events.length})
                        </Typography.Text>
                        <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                            {events.map((event) => {
                                const isCompany = event.category === "company_anniversary";
                                return (
                                    <div
                                        key={event.id}
                                        style={{
                                            display: "flex",
                                            alignItems: "center",
                                            justifyContent: "space-between",
                                            padding: "8px 12px",
                                            borderRadius: 8,
                                            background: isCompany ? "#faf5ff" : "#fff7ed",
                                            border: `1px solid ${isCompany ? "#e9d5ff" : "#fed7aa"}`,
                                            cursor: "pointer",
                                        }}
                                        onClick={() => {
                                            if (onSelectEvent) {
                                                onClose();
                                                onSelectEvent(event);
                                            }
                                        }}
                                    >
                                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                                            <span style={{ fontSize: 20 }}>{event.icon}</span>
                                            <div>
                                                <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                                    <Tag color={isCompany ? "purple" : "volcano"} style={{ margin: 0 }}>
                                                        {event.category_label}
                                                    </Tag>
                                                    <strong>
                                                        {isCompany ? `Kỷ niệm: ${event.title}` : event.title}
                                                    </strong>
                                                </div>
                                                <div style={{ fontSize: 12, color: "#666", marginTop: 2 }}>
                                                    {event.subtitle}
                                                </div>
                                            </div>
                                        </div>

                                        <Button
                                            size="small"
                                            icon={<GiftOutlined />}
                                            type="primary"
                                            style={{
                                                backgroundColor: isCompany ? "#7c3aed" : "#ea580c",
                                                borderColor: isCompany ? "#7c3aed" : "#ea580c",
                                            }}
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                if (onSelectEvent) {
                                                    onClose();
                                                    onSelectEvent(event);
                                                }
                                            }}
                                        >
                                            Chi tiết
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Tasks list */}
                <div>
                    <Typography.Text strong style={{ color: "#334155", fontSize: 13, marginBottom: 8, display: "block" }}>
                        📋 Nhiệm vụ ({tasks.length})
                    </Typography.Text>
                    {tasks.length === 0 ? (
                        <div style={{ color: "#888", fontSize: 13, fontStyle: "italic" }}>
                            Không có nhiệm vụ nào trong ngày này.
                        </div>
                    ) : (
                        <List
                            size="small"
                            dataSource={tasks}
                            renderItem={(task) => {
                                const typeStyle = TYPE_CONFIG[task.type] ?? DEFAULT_TYPE_STYLE;
                                const dueTime = dayjs(task.due_at).tz(TIMEZONE);
                                const isCompleted = task.status === "completed";

                                return (
                                    <List.Item
                                        key={task.id}
                                        style={{ cursor: "pointer" }}
                                        onClick={() => {
                                            onClose();
                                            onSelectTask(task);
                                        }}
                                    >
                                        <div style={{ display: "flex", alignItems: "center", gap: 8, width: "100%" }}>
                                            <span style={{ fontWeight: 600, color: typeStyle.color, width: 44 }}>
                                                {dueTime.format("HH:mm")}
                                            </span>
                                            <Tag style={{ margin: 0, color: typeStyle.color, background: typeStyle.bg }}>
                                                {options.types[task.type] ?? task.type}
                                            </Tag>
                                            <span
                                                style={{
                                                    flex: 1,
                                                    textDecoration: isCompleted ? "line-through" : "none",
                                                    opacity: isCompleted ? 0.6 : 1,
                                                }}
                                            >
                                                {task.title}
                                            </span>
                                            <Tag color={isCompleted ? "green" : "blue"} style={{ margin: 0 }}>
                                                {options.statuses[task.status]}
                                            </Tag>
                                        </div>
                                    </List.Item>
                                );
                            }}
                        />
                    )}
                </div>
            </div>
        </Modal>
    );
}
