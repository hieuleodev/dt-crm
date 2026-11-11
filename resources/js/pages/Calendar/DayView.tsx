import { useEffect, useRef } from "react";
import { Tag } from "antd";
import dayjs from "dayjs";
import type { TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG } from "./types";

interface Props {
    currentDate: dayjs.Dayjs;
    tasks: TaskRow[];
    onSelectTask: (task: TaskRow) => void;
    onCreateOnDate?: (date: dayjs.Dayjs) => void;
}

const START_HOUR = 6;
const END_HOUR = 22;
const HOUR_HEIGHT = 70; // 70px per hour for Day view so there's plenty of space

export default function DayView({
    currentDate,
    tasks,
    onSelectTask,
    onCreateOnDate,
}: Props) {
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const today = dayjs().tz(TIMEZONE);
    const isToday = currentDate.isSame(today, "day");

    useEffect(() => {
        if (scrollContainerRef.current) {
            const currentHour = isToday ? today.hour() : 8;
            const targetHour = Math.max(START_HOUR, Math.min(currentHour - 1, END_HOUR - 4));
            const scrollPos = (targetHour - START_HOUR) * HOUR_HEIGHT;
            scrollContainerRef.current.scrollTop = scrollPos;
        }
    }, [currentDate, isToday]);

    // Current time position indicator
    const currentNow = dayjs().tz(TIMEZONE);
    const currentMinutesFromStart =
        (currentNow.hour() - START_HOUR) * 60 + currentNow.minute();

    const hours = Array.from(
        { length: END_HOUR - START_HOUR + 1 },
        (_, i) => START_HOUR + i,
    );

    return (
        <div className="crm-cal-day">
            {/* Header */}
            <div className="crm-cal-day__header">
                <div className="crm-cal-day__header-gutter">GMT+7</div>
                <div className="crm-cal-day__header-content">
                    <div className="crm-cal-day__header-title">
                        <span className="crm-cal-day__day-name">
                            {currentDate.format("dddd")}
                        </span>
                        <div
                            className={`crm-cal-day__day-num ${
                                isToday ? "crm-cal-day__day-num--today" : ""
                            }`}
                        >
                            {currentDate.format("DD/MM/YYYY")}
                        </div>
                    </div>
                    <div className="crm-cal-day__header-count">
                        {tasks.length} nhiệm vụ / lịch trình trong ngày
                    </div>
                </div>
            </div>

            {/* Scrollable grid area */}
            <div className="crm-cal-day__body" ref={scrollContainerRef}>
                {/* Left time labels */}
                <div className="crm-cal-day__time-gutter">
                    {hours.map((hour) => (
                        <div
                            key={hour}
                            className="crm-cal-day__time-label"
                            style={{ height: HOUR_HEIGHT }}
                        >
                            <span>{String(hour).padStart(2, "0")}:00</span>
                        </div>
                    ))}
                </div>

                {/* Day column */}
                <div className="crm-cal-day__grid">
                    {/* Background hour horizontal grid lines */}
                    <div className="crm-cal-day__grid-lines">
                        {hours.map((hour) => (
                            <div
                                key={hour}
                                className="crm-cal-day__grid-line"
                                style={{ height: HOUR_HEIGHT }}
                            >
                                <div className="crm-cal-day__half-line" />
                            </div>
                        ))}
                    </div>

                    {/* Content area */}
                    <div
                        className="crm-cal-day__content"
                        onClick={onCreateOnDate ? (e) => {
                            const rect = e.currentTarget.getBoundingClientRect();
                            const clickY = e.clientY - rect.top;
                            const totalMinutes = Math.floor(
                                (clickY / HOUR_HEIGHT) * 60,
                            );
                            const clickedHour =
                                START_HOUR + Math.floor(totalMinutes / 60);
                            const roundedMinute =
                                totalMinutes % 60 < 30 ? 0 : 30;

                            const targetDate = currentDate
                                .hour(Math.min(clickedHour, END_HOUR))
                                .minute(roundedMinute);

                            onCreateOnDate(targetDate);
                        } : undefined}
                    >
                        {/* Red current time line */}
                        {isToday &&
                            currentMinutesFromStart >= 0 &&
                            currentMinutesFromStart <=
                                (END_HOUR - START_HOUR + 1) * 60 && (
                                <div
                                    className="crm-cal-day__now-line"
                                    style={{
                                        top: `${currentMinutesFromStart * (HOUR_HEIGHT / 60)}px`,
                                    }}
                                >
                                    <div className="crm-cal-day__now-dot" />
                                </div>
                            )}

                        {/* Events placed vertically */}
                        {tasks.map((task) => {
                            const typeStyle =
                                TYPE_CONFIG[task.type] ?? DEFAULT_TYPE_STYLE;
                            const taskTime = dayjs(task.due_at).tz(TIMEZONE);
                            const taskHour = taskTime.hour();
                            const taskMinute = taskTime.minute();

                            const minutesFromGridStart = Math.max(
                                0,
                                (taskHour - START_HOUR) * 60 + taskMinute,
                            );

                            const topPos =
                                minutesFromGridStart * (HOUR_HEIGHT / 60);
                            const isCompleted = task.status === "completed";

                            return (
                                <div
                                    key={task.id}
                                    className={`crm-cal-day-event ${
                                        isCompleted
                                            ? "crm-cal-day-event--completed"
                                            : ""
                                    } ${
                                        task.overdue
                                            ? "crm-cal-day-event--overdue"
                                            : ""
                                    }`}
                                    style={{
                                        top: `${topPos}px`,
                                        borderLeftColor: typeStyle.color,
                                        background: typeStyle.bg,
                                    }}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onSelectTask(task);
                                    }}
                                >
                                    <div className="crm-cal-day-event__header">
                                        <div className="crm-cal-day-event__meta">
                                            <span style={{ fontSize: 16 }}>
                                                {typeStyle.icon}
                                            </span>
                                            <span className="crm-cal-day-event__time">
                                                {taskTime.format("HH:mm")}
                                            </span>
                                            <Tag
                                                style={{
                                                    color: typeStyle.color,
                                                    background: "#ffffff",
                                                    borderColor: typeStyle.border,
                                                    margin: 0,
                                                }}
                                            >
                                                {typeStyle.label}
                                            </Tag>
                                            <Tag
                                                color={
                                                    task.priority === "urgent"
                                                        ? "red"
                                                        : task.priority === "high"
                                                          ? "orange"
                                                          : "default"
                                                }
                                                style={{ margin: 0 }}
                                            >
                                                {task.priority === "urgent"
                                                    ? "Khẩn cấp"
                                                    : task.priority === "high"
                                                      ? "Ưu tiên cao"
                                                      : "Bình thường"}
                                            </Tag>
                                        </div>

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
                                        >
                                            {task.status === "completed"
                                                ? "Hoàn thành"
                                                : task.status === "in_progress"
                                                  ? "Đang thực hiện"
                                                  : task.status === "cancelled"
                                                    ? "Đã hủy"
                                                    : "Chờ xử lý"}
                                        </Tag>
                                    </div>

                                    <div className="crm-cal-day-event__title">
                                        {task.title}
                                    </div>

                                    {task.reference && (
                                        <div className="crm-cal-day-event__ref">
                                            📌 {task.reference.label}
                                        </div>
                                    )}

                                    {task.description && (
                                        <div className="crm-cal-day-event__desc">
                                            {task.description}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}
