import { Link } from "@inertiajs/react";
import { Alert, Button, Descriptions, Modal, Space, Tag, Typography } from "antd";
import {
    CalendarOutlined,
    CheckCircleOutlined,
    ClockCircleOutlined,
    EditOutlined,
    LinkOutlined,
    StopOutlined,
    SyncOutlined,
    UserOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import type { TaskOptions, TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG } from "./types";

interface Props {
    task: TaskRow | null;
    open: boolean;
    options: TaskOptions;
    isManager: boolean;
    onClose: () => void;
    onEdit: (task: TaskRow) => void;
    onChangeStatus: (task: TaskRow, nextStatus: string) => void;
}

export default function EventDetailModal({
    task,
    open,
    options,
    isManager,
    onClose,
    onEdit,
    onChangeStatus,
}: Props) {
    if (!task) return null;

    const typeStyle = TYPE_CONFIG[task.type] ?? DEFAULT_TYPE_STYLE;
    const dueTime = dayjs(task.due_at).tz(TIMEZONE);
    const isOpen = ["pending", "in_progress"].includes(task.status);

    const priorityColor =
        task.priority === "urgent"
            ? "red"
            : task.priority === "high"
              ? "orange"
              : "default";

    const statusColor =
        task.status === "completed"
            ? "green"
            : task.status === "in_progress"
              ? "blue"
              : task.status === "cancelled"
                ? "red"
                : "default";

    return (
        <Modal
            open={open}
            onCancel={onClose}
            width={640}
            title={
                <div style={{ display: "flex", alignItems: "center", gap: 8, paddingRight: 24 }}>
                    <span style={{ fontSize: 20 }}>{typeStyle.icon}</span>
                    <Typography.Title level={4} style={{ margin: 0, lineHeight: 1.3 }}>
                        {task.title}
                    </Typography.Title>
                </div>
            }
            footer={
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <Button onClick={onClose}>Đóng</Button>
                    <Space wrap>
                        {isOpen && (
                            <>
                                <Button
                                    icon={<EditOutlined />}
                                    onClick={() => {
                                        onClose();
                                        onEdit(task);
                                    }}
                                >
                                    Chỉnh sửa
                                </Button>

                                <Button
                                    icon={<SyncOutlined />}
                                    onClick={() => {
                                        onClose();
                                        onChangeStatus(
                                            task,
                                            task.status === "pending" ? "in_progress" : "pending",
                                        );
                                    }}
                                >
                                    {task.status === "pending" ? "Bắt đầu" : "Về chờ"}
                                </Button>

                                <Button
                                    type="primary"
                                    icon={<CheckCircleOutlined />}
                                    style={{ background: "#059669", borderColor: "#059669" }}
                                    onClick={() => {
                                        onClose();
                                        onChangeStatus(task, "completed");
                                    }}
                                >
                                    Hoàn thành
                                </Button>

                                <Button
                                    danger
                                    icon={<StopOutlined />}
                                    onClick={() => {
                                        onClose();
                                        onChangeStatus(task, "cancelled");
                                    }}
                                >
                                    Hủy
                                </Button>
                            </>
                        )}

                        {!isOpen && isManager && (
                            <>
                                <Button
                                    icon={<EditOutlined />}
                                    onClick={() => {
                                        onClose();
                                        onEdit(task);
                                    }}
                                >
                                    Chỉnh sửa
                                </Button>
                                <Button
                                    onClick={() => {
                                        onClose();
                                        onChangeStatus(task, "pending");
                                    }}
                                >
                                    Mở lại nhiệm vụ
                                </Button>
                            </>
                        )}
                    </Space>
                </div>
            }
        >
            <div style={{ display: "flex", flexDirection: "column", gap: 16, marginTop: 16 }}>
                {task.overdue && (
                    <Alert
                        type="warning"
                        showIcon
                        message="Nhiệm vụ này đã quá hạn xử lý!"
                    />
                )}

                <div style={{ display: "flex", flexWrap: "wrap", gap: 8 }}>
                    <Tag
                        style={{
                            color: typeStyle.color,
                            backgroundColor: typeStyle.bg,
                            borderColor: typeStyle.border,
                            fontSize: 13,
                            padding: "2px 8px",
                            display: "inline-flex",
                            alignItems: "center",
                            gap: 4,
                        }}
                    >
                        <span>{typeStyle.icon}</span>
                        <span>{options.types[task.type] ?? task.type}</span>
                    </Tag>

                    <Tag color={priorityColor} style={{ fontSize: 13, padding: "2px 8px" }}>
                        Mức ưu tiên: {options.priorities[task.priority] ?? task.priority}
                    </Tag>

                    <Tag color={statusColor} style={{ fontSize: 13, padding: "2px 8px" }}>
                        {options.statuses[task.status] ?? task.status}
                    </Tag>
                </div>

                <Descriptions bordered size="small" column={1}>
                    <Descriptions.Item
                        label={
                            <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
                                <ClockCircleOutlined /> Thời gian
                            </span>
                        }
                    >
                        <Typography.Text strong style={{ fontSize: 15 }}>
                            {dueTime.format("HH:mm")} — {dueTime.format("dddd, [ngày] DD/MM/YYYY")}
                        </Typography.Text>
                    </Descriptions.Item>

                    <Descriptions.Item
                        label={
                            <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
                                <UserOutlined /> Người thực hiện
                            </span>
                        }
                    >
                        {task.assignee}
                    </Descriptions.Item>

                    <Descriptions.Item
                        label={
                            <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
                                <LinkOutlined /> Liên quan
                            </span>
                        }
                    >
                        {task.reference?.href ? (
                            <Link
                                href={task.reference.href}
                                style={{ color: "var(--crm-accent)", fontWeight: 500 }}
                            >
                                {task.reference.label}
                            </Link>
                        ) : (
                            task.reference?.label ?? "Công việc cá nhân / nội bộ"
                        )}
                    </Descriptions.Item>

                    {task.description && (
                        <Descriptions.Item label="Nội dung">
                            <Typography.Paragraph
                                style={{ whiteSpace: "pre-wrap", marginBottom: 0 }}
                            >
                                {task.description}
                            </Typography.Paragraph>
                        </Descriptions.Item>
                    )}

                    {task.result && (
                        <Descriptions.Item
                            label={task.status === "cancelled" ? "Lý do hủy" : "Kết quả"}
                        >
                            <Typography.Paragraph
                                style={{
                                    whiteSpace: "pre-wrap",
                                    marginBottom: 0,
                                    color: task.status === "cancelled" ? "#b91c1c" : "#047857",
                                }}
                            >
                                {task.result}
                            </Typography.Paragraph>
                        </Descriptions.Item>
                    )}

                    {task.completed_at && (
                        <Descriptions.Item label="Thời điểm hoàn tất">
                            {dayjs(task.completed_at)
                                .tz(TIMEZONE)
                                .format("DD/MM/YYYY HH:mm")}
                        </Descriptions.Item>
                    )}
                </Descriptions>
            </div>
        </Modal>
    );
}
