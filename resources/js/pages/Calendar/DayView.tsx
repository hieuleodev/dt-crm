import { useEffect, useMemo, useRef } from "react";
import { Tag } from "antd";
import dayjs from "dayjs";
import type { TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG, type UpcomingEvent } from "./types";

interface Props {
    currentDate: dayjs.Dayjs;
    tasks: TaskRow[];
    events?: UpcomingEvent[];
    onSelectTask: (task: TaskRow) => void;
    onSelectEvent: (event: UpcomingEvent) => void;
    onCreateOnDate?: (date: dayjs.Dayjs) => void;
}

const START_HOUR = 6;
const END_HOUR = 22;
const HOUR_HEIGHT = 70; // 70px per hour for Day view so there's plenty of space

export default function DayView({
    currentDate,
    tasks,
    events = [],
    onSelectTask,
    onSelectEvent,
    onCreateOnDate,
}: Props) {
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const today = dayjs().tz(TIMEZONE);
    const isToday = currentDate.isSame(today, "day");

    const dateKey = currentDate.format("YYYY-MM-DD");
    const dayEvents = useMemo(() => {
        return events.filter((e) => e.event_date === dateKey);
    }, [events, dateKey]);

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
                        {tasks.length} nhiệm vụ
                        {dayEvents.length > 0 && ` · ${dayEvents.length} sự kiện & ngày lễ`}
                    </div>
                </div>
            </div>

            {/* Day Events Banner (Google Calendar style) */}
            {dayEvents.length > 0 && (
                <div
                    style={{
                        padding: "10px 16px",
                        background: "#faf5ff",
                        borderBottom: "1px solid var(--crm-border)",
                    }}
                >
                    <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                        <span style={{ fontSize: 13, fontWeight: 600, color: "#475569" }}>
                            🎉 Sự kiện trong ngày:
                        </span>
                        {dayEvents.map((event) => {
                            const isCompany = event.category === "company_anniversary";
                            return (
                                <button
                                    key={event.id}
                                    type="button"
                                    className={`crm-cal-chip ${
                                        isCompany
                                            ? "crm-cal-chip--event-company"
                                            : "crm-cal-chip--event-holiday"
                                    }`}
                                    style={{
                                        width: "auto",
                                        padding: "4px 12px",
                                        fontSize: 13,
                                        display: "inline-flex",
                                        alignItems: "center",
                                        gap: 6,
                                    }}
                                    onClick={() => onSelectEvent(event)}
                                    title={`${event.category_label}: ${event.title}`}
                                >
                                    <span style={{ fontSize: 15 }}>{event.icon}</span>
                                    <span style={{ fontWeight: 600 }}>
                                        {isCompany ? `Kỷ niệm: ${event.title}` : event.title}
                                    </span>
                                    <span style={{ opacity: 0.85, fontSize: 12 }}>
                                        ({event.subtitle})
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

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

                    {/* Current time red horizontal line */}
                    {isToday &&
                        currentMinutesFromStart >= 0 &&
                        currentMinutesFromStart <= (END_HOUR - START_HOUR + 1) * 60 && (
                            <div
                                className="crm-cal-day__now-line"
                                style={{ top: currentMinutesFromStart }}
                            />
                        )}

                    {/* Task cards positioned absolutely in the grid */}
                    <div
                        className="crm-cal-day__events-container"
                        onClick={(e) => {
                            if (onCreateOnDate && e.target === e.currentTarget) {
                                const rect = e.currentTarget.getBoundingClientRect();
                                const clickY = e.clientY - rect.top;
                                const clickedMinutes = Math.floor(clickY);
                                const clickedHour = Math.min(
                                    END_HOUR,
                                    Math.max(
                                        START_HOUR,
                                        START_HOUR + Math.floor(clickedMinutes / 60),
                                    ),
                                );
                                onCreateOnDate(currentDate.hour(clickedHour).minute(0));
                            }
                        }}
                    >
                        {tasks.map((task) => {
                            const typeStyle =
                                TYPE_CONFIG[task.type] ?? DEFAULT_TYPE_STYLE;
                            const due = dayjs(task.due_at).tz(TIMEZONE);
                            const startMin =
                                (due.hour() - START_HOUR) * 60 + due.minute();
                            const clampedTop = Math.max(0, startMin);
                            const isCompleted = task.status === "completed";

                            return (
                                <div
                                    key={task.id}
                                    className={`crm-cal-day__event ${
                                        isCompleted
                                            ? "crm-cal-day__event--completed"
                                            : ""
                                    } ${
                                        task.overdue ? "crm-cal-day__event--overdue" : ""
                                    }`}
                                    style={{
                                        top: clampedTop,
                                        borderLeftColor: typeStyle.color,
                                        background: typeStyle.bg,
                                    }}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onSelectTask(task);
                                    }}
                                >
                                    <div className="crm-cal-day__event-header">
                                        <div
                                            className="crm-cal-day__event-time"
                                            style={{ color: typeStyle.color }}
                                        >
                                            {due.format("HH:mm")}
                                        </div>
                                        <Tag
                                            color={typeStyle.badge}
                                            style={{ margin: 0, fontSize: 11 }}
                                        >
                                            {typeStyle.label}
                                        </Tag>
                                        {task.overdue && (
                                            <Tag color="error" style={{ margin: 0 }}>
                                                Quá hạn
                                            </Tag>
                                        )}
                                        {isCompleted && (
                                            <Tag color="success" style={{ margin: 0 }}>
                                                Đã xong
                                            </Tag>
                                        )}
                                    </div>

                                    <div className="crm-cal-day__event-title">
                                        {task.title}
                                    </div>

                                    {task.reference && (
                                        <div className="crm-cal-day__event-ref">
                                            {task.reference.label}
                                        </div>
                                    )}

                                    {task.assignee && (
                                        <div className="crm-cal-day__event-assignee">
                                            Phụ trách: {task.assignee}
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
