import { useMemo } from "react";
import { Link } from "@inertiajs/react";
import { Button, Empty, Space, Tag } from "antd";
import {
    CheckCircleOutlined,
    ClockCircleOutlined,
    EditOutlined,
    PlusOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import type { TaskOptions, TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG } from "./types";

interface Props {
    tasks: TaskRow[];
    options: TaskOptions;
    onSelectTask: (task: TaskRow) => void;
    onCreateOnDate: (date: dayjs.Dayjs) => void;
    onEdit: (task: TaskRow) => void;
    onChangeStatus: (task: TaskRow, status: string) => void;
}

export default function AgendaView({
    tasks,
    options,
    onSelectTask,
    onCreateOnDate,
    onEdit,
    onChangeStatus,
}: Props) {
    const today = dayjs().tz(TIMEZONE);

    // Group tasks by date key
    const grouped = useMemo(() => {
        const groups: Array<{ date: dayjs.Dayjs; dateKey: string; tasks: TaskRow[] }> = [];
        const map = new Map<string, TaskRow[]>();

        for (const task of tasks) {
            const dateKey = dayjs(task.due_at).tz(TIMEZONE).format("YYYY-MM-DD");
            const list = map.get(dateKey) ?? [];
            list.push(task);
            map.set(dateKey, list);
        }

        // Sort keys chronologically
        const sortedKeys = Array.from(map.keys()).sort();
        for (const dateKey of sortedKeys) {
            groups.push({
                date: dayjs(dateKey).tz(TIMEZONE),
                dateKey,
                tasks: map.get(dateKey) ?? [],
            });
        }

        return groups;
    }, [tasks]);

    if (grouped.length === 0) {
        return (
            <div style={{ padding: 48, background: "#ffffff", borderRadius: 12 }}>
                <Empty
                    description="Không có lịch trình nào trong khoảng thời gian này."
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                >
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => onCreateOnDate(today)}
                    >
                        Tạo lịch trình mới
                    </Button>
                </Empty>
            </div>
        );
    }

    return (
        <div className="crm-cal-agenda">
            {grouped.map(({ date, dateKey, tasks: dayTasks }) => {
                const isToday = date.isSame(today, "day");
                const isTomorrow = date.isSame(today.add(1, "day"), "day");
                const isPast = date.isBefore(today, "day");

                let relativeLabel = "";
                if (isToday) relativeLabel = "Hôm nay";
                else if (isTomorrow) relativeLabel = "Ngày mai";

                return (
                    <div key={dateKey} className="crm-cal-agenda__group">
                        {/* Day heading */}
                        <div className="crm-cal-agenda__day-header">
                            <div className="crm-cal-agenda__day-title">
                                <span className="crm-cal-agenda__day-name">
                                    {date.format("dddd")}
                                </span>
                                <span className="crm-cal-agenda__day-date">
                                    {date.format("DD/MM/YYYY")}
                                </span>
                                {relativeLabel && (
                                    <Tag color={isToday ? "blue" : "cyan"}>
                                        {relativeLabel}
                                    </Tag>
                                )}
                            </div>

                            <Button
                                size="small"
                                icon={<PlusOutlined />}
                                onClick={() => onCreateOnDate(date.hour(9).minute(0))}
                            >
                                Thêm lịch
                            </Button>
                        </div>

                        {/* Task items list */}
                        <div className="crm-cal-agenda__items">
                            {dayTasks.map((task) => {
                                const typeStyle =
                                    TYPE_CONFIG[task.type] ?? DEFAULT_TYPE_STYLE;
                                const dueTime = dayjs(task.due_at).tz(TIMEZONE);
                                const isCompleted = task.status === "completed";
                                const isOpen = ["pending", "in_progress"].includes(task.status);

                                return (
                                    <div
                                        key={task.id}
                                        className={`crm-cal-agenda__item ${
                                            isCompleted ? "crm-cal-agenda__item--completed" : ""
                                        } ${task.overdue ? "crm-cal-agenda__item--overdue" : ""}`}
                                        onClick={() => onSelectTask(task)}
                                    >
                                        {/* Time badge */}
                                        <div className="crm-cal-agenda__item-time">
                                            <ClockCircleOutlined style={{ color: typeStyle.color }} />
                                            <span>{dueTime.format("HH:mm")}</span>
                                        </div>

                                        {/* Type icon */}
                                        <div className="crm-cal-agenda__item-icon">
                                            <span>{typeStyle.icon}</span>
                                        </div>

                                        {/* Main content */}
                                        <div className="crm-cal-agenda__item-body">
                                            <div className="crm-cal-agenda__item-title">
                                                {task.title}
                                            </div>

                                            <div className="crm-cal-agenda__item-meta">
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

                                                {task.priority !== "normal" && (
                                                    <Tag
                                                        color={
                                                            task.priority === "urgent"
                                                                ? "red"
                                                                : "orange"
                                                        }
                                                        style={{ margin: 0 }}
                                                    >
                                                        {options.priorities[task.priority]}
                                                    </Tag>
                                                )}

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

                                                {task.reference && (
                                                    <span className="crm-cal-agenda__item-ref">
                                                        {task.reference.href ? (
                                                            <Link
                                                                href={task.reference.href}
                                                                onClick={(e) => e.stopPropagation()}
                                                            >
                                                                📌 {task.reference.label}
                                                            </Link>
                                                        ) : (
                                                            <span>📌 {task.reference.label}</span>
                                                        )}
                                                    </span>
                                                )}

                                                <span className="crm-cal-agenda__item-assignee">
                                                    👤 {task.assignee}
                                                </span>
                                            </div>

                                            {task.description && (
                                                <div className="crm-cal-agenda__item-desc">
                                                    {task.description}
                                                </div>
                                            )}
                                        </div>

                                        {/* Quick actions */}
                                        <div
                                            className="crm-cal-agenda__item-actions"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Space>
                                                {isOpen && (
                                                    <Button
                                                        size="small"
                                                        type="text"
                                                        icon={<CheckCircleOutlined style={{ color: "#059669" }} />}
                                                        onClick={() => onChangeStatus(task, "completed")}
                                                        title="Hoàn thành nhiệm vụ"
                                                    />
                                                )}
                                                <Button
                                                    size="small"
                                                    type="text"
                                                    icon={<EditOutlined />}
                                                    onClick={() => onEdit(task)}
                                                    title="Chỉnh sửa"
                                                />
                                            </Space>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
