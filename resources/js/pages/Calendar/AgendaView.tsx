import { useMemo } from "react";
import { Link } from "@inertiajs/react";
import { Button, Empty, Space, Tag } from "antd";
import {
    CheckCircleOutlined,
    ClockCircleOutlined,
    EditOutlined,
    GiftOutlined,
    PlusOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import type { TaskOptions, TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG, type UpcomingEvent } from "./types";

interface Props {
    tasks: TaskRow[];
    events?: UpcomingEvent[];
    options: TaskOptions;
    onSelectTask: (task: TaskRow) => void;
    onSelectEvent: (event: UpcomingEvent) => void;
    onCreateOnDate?: (date: dayjs.Dayjs) => void;
    onEdit?: (task: TaskRow) => void;
    onChangeStatus: (task: TaskRow, status: string) => void;
}

export default function AgendaView({
    tasks,
    events = [],
    options,
    onSelectTask,
    onSelectEvent,
    onCreateOnDate,
    onEdit,
    onChangeStatus,
}: Props) {
    const today = dayjs().tz(TIMEZONE);

    // Group tasks and events by date key
    const grouped = useMemo(() => {
        const dateKeysSet = new Set<string>();
        const taskMap = new Map<string, TaskRow[]>();
        const eventMap = new Map<string, UpcomingEvent[]>();

        for (const task of tasks) {
            const dateKey = dayjs(task.due_at).tz(TIMEZONE).format("YYYY-MM-DD");
            dateKeysSet.add(dateKey);
            const list = taskMap.get(dateKey) ?? [];
            list.push(task);
            taskMap.set(dateKey, list);
        }

        for (const event of events) {
            const dateKey = event.event_date;
            dateKeysSet.add(dateKey);
            const list = eventMap.get(dateKey) ?? [];
            list.push(event);
            eventMap.set(dateKey, list);
        }

        const sortedKeys = Array.from(dateKeysSet).sort();
        return sortedKeys.map((dateKey) => ({
            date: dayjs(dateKey).tz(TIMEZONE),
            dateKey,
            tasks: taskMap.get(dateKey) ?? [],
            events: eventMap.get(dateKey) ?? [],
        }));
    }, [tasks, events]);

    if (grouped.length === 0) {
        return (
            <div style={{ padding: 48, background: "#ffffff", borderRadius: 12 }}>
                <Empty
                    description="Không có lịch trình hay sự kiện nào trong khoảng thời gian này."
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                >
                    {onCreateOnDate && (
                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            onClick={() => onCreateOnDate(today)}
                        >
                            Tạo lịch trình mới
                        </Button>
                    )}
                </Empty>
            </div>
        );
    }

    return (
        <div className="crm-cal-agenda">
            {grouped.map(({ date, dateKey, tasks: dayTasks, events: dayEvents }) => {
                const isToday = date.isSame(today, "day");
                const isTomorrow = date.isSame(today.add(1, "day"), "day");

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

                            {onCreateOnDate && (
                                <Button
                                    size="small"
                                    icon={<PlusOutlined />}
                                    onClick={() => onCreateOnDate(date.hour(9).minute(0))}
                                >
                                    Thêm lịch
                                </Button>
                            )}
                        </div>

                        {/* Events list for this day */}
                        {dayEvents.length > 0 && (
                            <div style={{ display: "flex", flexDirection: "column", gap: 8, padding: "8px 16px" }}>
                                {dayEvents.map((event) => {
                                    const isCompany = event.category === "company_anniversary";
                                    return (
                                        <div
                                            key={event.id}
                                            style={{
                                                display: "flex",
                                                alignItems: "center",
                                                justifyContent: "space-between",
                                                gap: 12,
                                                padding: "10px 14px",
                                                borderRadius: 8,
                                                background: isCompany ? "#faf5ff" : "#fff7ed",
                                                border: `1px solid ${isCompany ? "#e9d5ff" : "#fed7aa"}`,
                                                cursor: "pointer",
                                            }}
                                            onClick={() => onSelectEvent(event)}
                                        >
                                            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                                                <span style={{ fontSize: 20 }}>{event.icon}</span>
                                                <div>
                                                    <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                                        <Tag color={isCompany ? "purple" : "volcano"} style={{ margin: 0 }}>
                                                            {event.category_label}
                                                        </Tag>
                                                        <strong style={{ fontSize: 14 }}>
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
                                                type="primary"
                                                icon={<GiftOutlined />}
                                                style={{
                                                    backgroundColor: isCompany ? "#7c3aed" : "#ea580c",
                                                    borderColor: isCompany ? "#7c3aed" : "#ea580c",
                                                }}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    onSelectEvent(event);
                                                }}
                                            >
                                                Chi tiết
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* Task items list */}
                        {dayTasks.length > 0 && (
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
                                                                    ? "default"
                                                                    : "gold"
                                                        }
                                                        style={{ margin: 0 }}
                                                    >
                                                        {options.statuses[task.status]}
                                                    </Tag>

                                                    {task.reference && (
                                                        <span className="crm-cal-agenda__reference">
                                                            📎 {task.reference.label}
                                                        </span>
                                                    )}

                                                    {task.assignee && (
                                                        <span className="crm-cal-agenda__assignee">
                                                            👤 {task.assignee}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Action buttons */}
                                            <div
                                                className="crm-cal-agenda__item-actions"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                {isOpen && (
                                                    <Button
                                                        size="small"
                                                        type="text"
                                                        icon={<CheckCircleOutlined />}
                                                        style={{ color: "#059669" }}
                                                        title="Đánh dấu hoàn thành"
                                                        onClick={() =>
                                                            onChangeStatus(task, "completed")
                                                        }
                                                    >
                                                        Xong
                                                    </Button>
                                                )}

                                                {onEdit && (
                                                    <Button
                                                        size="small"
                                                        type="text"
                                                        icon={<EditOutlined />}
                                                        title="Chỉnh sửa"
                                                        onClick={() => onEdit(task)}
                                                    />
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
