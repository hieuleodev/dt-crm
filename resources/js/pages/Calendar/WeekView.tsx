import { useEffect, useMemo, useRef } from "react";
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

// Hours to display in the grid: 07:00 to 21:00 (15 hours)
const START_HOUR = 7;
const END_HOUR = 21;
const HOUR_HEIGHT = 60; // 60px per hour => 1px per minute

export default function WeekView({
    currentDate,
    tasks,
    events = [],
    onSelectTask,
    onSelectEvent,
    onCreateOnDate,
}: Props) {
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const today = dayjs().tz(TIMEZONE);

    // Auto-scroll to current hour or 8:00 AM on mount
    useEffect(() => {
        if (scrollContainerRef.current) {
            const currentHour = today.hour();
            const targetHour = Math.max(START_HOUR, Math.min(currentHour - 1, END_HOUR - 4));
            const scrollPos = (targetHour - START_HOUR) * HOUR_HEIGHT;
            scrollContainerRef.current.scrollTop = scrollPos;
        }
    }, []);

    // Get the 7 days of the current week (Monday to Sunday)
    const weekDays = useMemo(() => {
        const startOfWeek = currentDate.startOf("isoWeek");
        return Array.from({ length: 7 }, (_, i) => startOfWeek.add(i, "day"));
    }, [currentDate]);

    // Group tasks by day (YYYY-MM-DD)
    const tasksByDay = useMemo(() => {
        const map = new Map<string, TaskRow[]>();
        for (const task of tasks) {
            const dayKey = dayjs(task.due_at).tz(TIMEZONE).format("YYYY-MM-DD");
            const list = map.get(dayKey) ?? [];
            list.push(task);
            map.set(dayKey, list);
        }
        return map;
    }, [tasks]);

    // Group events by day (YYYY-MM-DD)
    const eventsByDay = useMemo(() => {
        const map = new Map<string, UpcomingEvent[]>();
        for (const event of events) {
            const dayKey = event.event_date;
            const list = map.get(dayKey) ?? [];
            list.push(event);
            map.set(dayKey, list);
        }
        return map;
    }, [events]);

    const hasAnyEvents = useMemo(() => {
        return weekDays.some((d) => (eventsByDay.get(d.format("YYYY-MM-DD"))?.length ?? 0) > 0);
    }, [weekDays, eventsByDay]);

    // Current time position indicator
    const currentNow = dayjs().tz(TIMEZONE);
    const currentMinutesFromStart =
        (currentNow.hour() - START_HOUR) * 60 + currentNow.minute();
    const isCurrentWeek = weekDays.some((d) => d.isSame(today, "day"));

    const hours = Array.from(
        { length: END_HOUR - START_HOUR + 1 },
        (_, i) => START_HOUR + i,
    );

    return (
        <div className="crm-cal-week">
            {/* Header row: 7 day columns */}
            <div className="crm-cal-week__header">
                {/* Time gutter spacer */}
                <div className="crm-cal-week__time-gutter-header">
                    <span className="crm-cal-week__tz-label">GMT+7</span>
                </div>

                {/* 7 Day headers */}
                <div className="crm-cal-week__header-cols">
                    {weekDays.map((date) => {
                        const isToday = date.isSame(today, "day");
                        const isWeekend = date.isoWeekday() >= 6;

                        return (
                            <div
                                key={date.format("YYYY-MM-DD")}
                                className={`crm-cal-week__header-col ${
                                    isToday ? "crm-cal-week__header-col--today" : ""
                                } ${isWeekend ? "crm-cal-week__header-col--weekend" : ""}`}
                            >
                                <span className="crm-cal-week__day-name">
                                    {date.format("dddd")}
                                </span>
                                <div
                                    className={`crm-cal-week__day-num ${
                                        isToday ? "crm-cal-week__day-num--today" : ""
                                    }`}
                                >
                                    {date.date()}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* All-Day / Events row (Google Calendar style) */}
            {hasAnyEvents && (
                <div className="crm-cal-allday-row">
                    <div className="crm-cal-allday-gutter">
                        <span>Sự kiện</span>
                    </div>
                    <div className="crm-cal-allday-cols">
                        {weekDays.map((date) => {
                            const dateKey = date.format("YYYY-MM-DD");
                            const dayEvents = eventsByDay.get(dateKey) ?? [];
                            return (
                                <div key={dateKey} className="crm-cal-allday-col">
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
                                                } ${event.is_today ? "crm-cal-chip--today-event" : ""}`}
                                                onClick={() => onSelectEvent(event)}
                                                title={`${event.category_label}: ${event.title} (${event.subtitle})`}
                                            >
                                                <span style={{ flexShrink: 0, fontSize: 11 }}>
                                                    {event.icon}
                                                </span>
                                                <span
                                                    className="crm-cal-chip__title"
                                                    style={{ fontWeight: 600 }}
                                                >
                                                    {isCompany ? `Kỷ niệm: ${event.title}` : event.title}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Scrollable grid area */}
            <div className="crm-cal-week__body" ref={scrollContainerRef}>
                {/* Left time labels */}
                <div className="crm-cal-week__time-gutter">
                    {hours.map((hour) => (
                        <div
                            key={hour}
                            className="crm-cal-week__time-label"
                            style={{ height: HOUR_HEIGHT }}
                        >
                            <span>{String(hour).padStart(2, "0")}:00</span>
                        </div>
                    ))}
                </div>

                {/* 7 Day grid columns */}
                <div className="crm-cal-week__grid">
                    {/* Background hour horizontal grid lines */}
                    <div className="crm-cal-week__grid-lines">
                        {hours.map((hour) => (
                            <div
                                key={hour}
                                className="crm-cal-week__grid-line"
                                style={{ height: HOUR_HEIGHT }}
                            >
                                <div className="crm-cal-week__half-line" />
                            </div>
                        ))}
                    </div>

                    {/* Current time red horizontal line */}
                    {isCurrentWeek &&
                        currentMinutesFromStart >= 0 &&
                        currentMinutesFromStart <= (END_HOUR - START_HOUR + 1) * 60 && (
                            <div
                                className="crm-cal-week__now-line"
                                style={{ top: currentMinutesFromStart }}
                            />
                        )}

                    {/* Columns for the 7 days */}
                    <div className="crm-cal-week__columns">
                        {weekDays.map((date) => {
                            const dateKey = date.format("YYYY-MM-DD");
                            const dayTasks = tasksByDay.get(dateKey) ?? [];
                            const isToday = date.isSame(today, "day");

                            return (
                                <div
                                    key={dateKey}
                                    className={`crm-cal-week__col ${
                                        isToday ? "crm-cal-week__col--today" : ""
                                    }`}
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
                                            onCreateOnDate(date.hour(clickedHour).minute(0));
                                        }
                                    }}
                                >
                                    {dayTasks.map((task) => {
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
                                                className={`crm-cal-week__event ${
                                                    isCompleted
                                                        ? "crm-cal-week__event--completed"
                                                        : ""
                                                } ${
                                                    task.overdue
                                                        ? "crm-cal-week__event--overdue"
                                                        : ""
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
                                                title={`${due.format("HH:mm")} · ${task.title} · ${task.assignee}`}
                                            >
                                                <div
                                                    className="crm-cal-week__event-time"
                                                    style={{ color: typeStyle.color }}
                                                >
                                                    {due.format("HH:mm")}
                                                </div>
                                                <div className="crm-cal-week__event-title">
                                                    {task.title}
                                                </div>
                                                <div className="crm-cal-week__event-ref">
                                                    Phụ trách: {task.assignee}
                                                </div>
                                                {task.reference && (
                                                    <div className="crm-cal-week__event-ref">
                                                        {task.reference.label}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}
