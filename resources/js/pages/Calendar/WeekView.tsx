import { useEffect, useMemo, useRef } from "react";
import dayjs from "dayjs";
import type { TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG } from "./types";

interface Props {
    currentDate: dayjs.Dayjs;
    tasks: TaskRow[];
    onSelectTask: (task: TaskRow) => void;
    onCreateOnDate?: (date: dayjs.Dayjs) => void;
}

// Hours to display in the grid: 07:00 to 21:00 (15 hours)
const START_HOUR = 7;
const END_HOUR = 21;
const HOUR_HEIGHT = 60; // 60px per hour => 1px per minute

export default function WeekView({
    currentDate,
    tasks,
    onSelectTask,
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

                    {/* Columns container */}
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
                                    onClick={onCreateOnDate ? (e) => {
                                        // Calculate clicked hour based on click position
                                        const rect = e.currentTarget.getBoundingClientRect();
                                        const clickY = e.clientY - rect.top;
                                        const totalMinutes = Math.floor(
                                            (clickY / HOUR_HEIGHT) * 60,
                                        );
                                        const clickedHour =
                                            START_HOUR + Math.floor(totalMinutes / 60);
                                        const roundedMinute =
                                            totalMinutes % 60 < 30 ? 0 : 30;

                                        const targetDate = date
                                            .hour(Math.min(clickedHour, END_HOUR))
                                            .minute(roundedMinute);

                                        onCreateOnDate(targetDate);
                                    } : undefined}
                                >
                                    {/* Red current-time line indicator (if today) */}
                                    {isToday &&
                                        currentMinutesFromStart >= 0 &&
                                        currentMinutesFromStart <=
                                            (END_HOUR - START_HOUR + 1) * 60 && (
                                            <div
                                                className="crm-cal-week__now-line"
                                                style={{
                                                    top: `${currentMinutesFromStart * (HOUR_HEIGHT / 60)}px`,
                                                }}
                                            >
                                                <div className="crm-cal-week__now-dot" />
                                            </div>
                                        )}

                                    {/* Event cards positioned vertically */}
                                    {dayTasks.map((task) => {
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
                                        const cardHeight = Math.max(
                                            42,
                                            HOUR_HEIGHT * 0.75,
                                        );
                                        const isCompleted =
                                            task.status === "completed";

                                        return (
                                            <div
                                                key={task.id}
                                                className={`crm-cal-event-card ${
                                                    isCompleted
                                                        ? "crm-cal-event-card--completed"
                                                        : ""
                                                } ${
                                                    task.overdue
                                                        ? "crm-cal-event-card--overdue"
                                                        : ""
                                                }`}
                                                style={{
                                                    top: `${topPos}px`,
                                                    height: `${cardHeight}px`,
                                                    borderLeftColor: typeStyle.color,
                                                    background: typeStyle.bg,
                                                }}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    onSelectTask(task);
                                                }}
                                                title={`${taskTime.format("HH:mm")} · ${task.title}`}
                                            >
                                                <div className="crm-cal-event-card__time">
                                                    <span>{typeStyle.icon}</span>
                                                    <span>{taskTime.format("HH:mm")}</span>
                                                    {task.priority === "urgent" && (
                                                        <span className="crm-cal-event-card__urgent-dot" />
                                                    )}
                                                </div>

                                                <div className="crm-cal-event-card__title">
                                                    {task.title}
                                                </div>

                                                {task.reference && (
                                                    <div className="crm-cal-event-card__ref">
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
