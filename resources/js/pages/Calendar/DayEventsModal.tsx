import { Button, List, Modal, Tag, Typography } from "antd";
import { PlusOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import type { TaskOptions, TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG } from "./types";

interface Props {
    date: dayjs.Dayjs | null;
    open: boolean;
    tasks: TaskRow[];
    options: TaskOptions;
    onClose: () => void;
    onSelectTask: (task: TaskRow) => void;
    onCreateOnDate: (date: dayjs.Dayjs) => void;
}

export default function DayEventsModal({
    date,
    open,
    tasks,
    options,
    onClose,
    onSelectTask,
    onCreateOnDate,
}: Props) {
    if (!date) return null;

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={580}
            title={
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", paddingRight: 24 }}>
                    <div>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            {date.format("dddd, [ngày] DD/MM/YYYY")}
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            {tasks.length} nhiệm vụ / lịch trình
                        </Typography.Text>
                    </div>
                    <Button
                        type="primary"
                        size="small"
                        icon={<PlusOutlined />}
                        onClick={() => {
                            onClose();
                            onCreateOnDate(date);
                        }}
                    >
                        Tạo lịch
                    </Button>
                </div>
            }
            footer={[
                <Button key="close" onClick={onClose}>
                    Đóng
                </Button>,
            ]}
        >
            <List
                itemLayout="horizontal"
                dataSource={tasks}
                style={{ maxHeight: "60vh", overflowY: "auto" }}
                renderItem={(task) => {
                    const typeStyle = TYPE_CONFIG[task.type] ?? DEFAULT_TYPE_STYLE;
                    const dueTime = dayjs(task.due_at).tz(TIMEZONE);
                    const isCompleted = task.status === "completed";

                    return (
                        <List.Item
                            style={{
                                cursor: "pointer",
                                padding: "10px 12px",
                                borderRadius: 8,
                                transition: "background 0.2s",
                                borderBottom: "1px solid #f0f0f0",
                            }}
                            className="crm-calendar-day-item"
                            onClick={() => {
                                onClose();
                                onSelectTask(task);
                            }}
                        >
                            <div style={{ display: "flex", alignItems: "center", gap: 12, width: "100%" }}>
                                <span style={{ fontSize: 18 }}>{typeStyle.icon}</span>

                                <div style={{ minWidth: 64, color: "#666", fontSize: 13, fontWeight: 500 }}>
                                    {dueTime.format("HH:mm")}
                                </div>

                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div
                                        style={{
                                            fontWeight: 600,
                                            fontSize: 14,
                                            textDecoration: isCompleted ? "line-through" : "none",
                                            color: isCompleted ? "#888" : "#222",
                                        }}
                                    >
                                        {task.title}
                                    </div>
                                    {task.reference && (
                                        <div style={{ fontSize: 12, color: "#666", marginTop: 2 }}>
                                            {task.reference.label}
                                        </div>
                                    )}
                                </div>

                                <div style={{ display: "flex", gap: 6, alignItems: "center" }}>
                                    <Tag
                                        style={{
                                            color: typeStyle.color,
                                            background: typeStyle.bg,
                                            borderColor: typeStyle.border,
                                            margin: 0,
                                        }}
                                    >
                                        {options.types[task.type] ?? task.type}
                                    </Tag>

                                    <Tag
                                        color={
                                            task.status === "completed"
                                                ? "green"
                                                : task.status === "in_progress"
                                                  ? "blue"
                                                  : task.status === "cancelled"
                                                    ? "red"
                                                    : "default"
                                        }
                                        style={{ margin: 0 }}
                                    >
                                        {options.statuses[task.status] ?? task.status}
                                    </Tag>
                                </div>
                            </div>
                        </List.Item>
                    );
                }}
            />
        </Modal>
    );
}
