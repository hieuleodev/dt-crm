import { useMemo } from "react";
import { PlusOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import type { TaskRow } from "@/types/task";
import { DEFAULT_TYPE_STYLE, TIMEZONE, TYPE_CONFIG } from "./types";

interface Props {
    currentDate: dayjs.Dayjs;
    tasks: TaskRow[];
    onSelectTask: (task: TaskRow) => void;
    onCreateOnDate: (date: dayjs.Dayjs) => void;
    onOpenDayModal: (date: dayjs.Dayjs, dayTasks: TaskRow[]) => void;
}

const WEEKDAYS = ["Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7", "Chủ Nhật"];

export default function MonthView({
    currentDate,
    tasks,
    onSelectTask,
    onCreateOnDate,
    onOpenDayModal,
}: Props) {
    const today = dayjs().tz(TIMEZONE);

    // Group tasks by date string (YYYY-MM-DD) in Vietnam timezone
    const tasksByDate = useMemo(() => {
        const map = new Map<string, TaskRow[]>();
        for (const task of tasks) {
            const dateKey = dayjs(task.due_at).tz(TIMEZONE).format("YYYY-MM-DD");
            const existing = map.get(dateKey) ?? [];
            existing.push(task);
            map.set(dateKey, existing);
        }
        return map;
    }, [tasks]);

    // Calculate the 5 or 6 weeks for the calendar grid
    const weeks = useMemo(() => {
        const startOfMonth = currentDate.startOf("month");
        const endOfMonth = currentDate.endOf("month");

        // Start from Monday of the week containing the 1st
        let current = startOfMonth.startOf("isoWeek");
        // End at Sunday of the week containing the last day
        const end = endOfMonth.endOf("isoWeek");

        const result: dayjs.Dayjs[][] = [];
        let currentWeek: dayjs.Dayjs[] = [];

        while (current.isBefore(end) || current.isSame(end, "day")) {
            currentWeek.push(current);
            if (currentWeek.length === 7) {
                result.push(currentWeek);
                currentWeek = [];
            }
            current = current.add(1, "day");
        }

        return result;
    }, [currentDate]);

    return (
        <div className="crm-cal-month">
            {/* Weekdays header */}
            <div className="crm-cal-month__header">
                {WEEKDAYS.map((name, index) => (
                    <div
                        key={name}
                        className={`crm-cal-month__header-col ${
                            index >= 5 ? "crm-cal-month__header-col--weekend" : ""
                        }`}
                    >
                        {name}
                    </div>
                ))}
            </div>

            {/* Days grid */}
            <div className="crm-cal-month__grid">
                {weeks.map((week, weekIndex) => (
                    <div key={weekIndex} className="crm-cal-month__week">
                        {week.map((date) => {
                            const dateKey = date.format("YYYY-MM-DD");
                            const dayTasks = tasksByDate.get(dateKey) ?? [];
                            const isToday = date.isSame(today, "day");
                            const isCurrentMonth = date.isSame(currentDate, "month");
                            const isWeekend = date.isoWeekday() >= 6;

                            const visibleTasks = dayTasks.slice(0, 3);
                            const moreCount = dayTasks.length - 3;

                            return (
                                <div
                                    key={dateKey}
                                    className={`crm-cal-month__cell ${
                                        !isCurrentMonth ? "crm-cal-month__cell--other-month" : ""
                                    } ${isToday ? "crm-cal-month__cell--today" : ""} ${
                                        isWeekend ? "crm-cal-month__cell--weekend" : ""
                                    }`}
                                    onClick={(e) => {
                                        // Only trigger create if clicking directly on cell background
                                        if (e.target === e.currentTarget) {
                                            onCreateOnDate(date.hour(9).minute(0));
                                        }
                                    }}
                                >
                                    {/* Cell header: Date number and quick add button */}
                                    <div className="crm-cal-month__cell-header">
                                        <button
                                            type="button"
                                            className={`crm-cal-month__date-num ${
                                                isToday ? "crm-cal-month__date-num--today" : ""
                                            }`}
                                            onClick={() => onOpenDayModal(date, dayTasks)}
                                            title="Xem danh sách nhiệm vụ ngày này"
                                        >
                                            {date.date() === 1
                                                ? `${date.date()} Th${date.month() + 1}`
                                                : date.date()}
                                        </button>

                                        <button
                                            type="button"
                                            className="crm-cal-month__add-btn"
                                            title="Tạo nhiệm vụ ngày này"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onCreateOnDate(date.hour(9).minute(0));
                                            }}
                                        >
                                            <PlusOutlined />
                                        </button>
                                    </div>

                                    {/* Event chips */}
                                    <div className="crm-cal-month__events">
                                        {visibleTasks.map((task) => {
                                            const typeStyle =
                                                TYPE_CONFIG[task.type] ?? DEFAULT_TYPE_STYLE;
                                            const dueTime = dayjs(task.due_at).tz(TIMEZONE);
                                            const isCompleted = task.status === "completed";

                                            return (
                                                <button
                                                    key={task.id}
                                                    type="button"
                                                    className={`crm-cal-chip ${
                                                        isCompleted ? "crm-cal-chip--completed" : ""
                                                    } ${task.overdue ? "crm-cal-chip--overdue" : ""}`}
                                                    style={{
                                                        borderLeftColor: typeStyle.color,
                                                        background: typeStyle.bg,
                                                    }}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        onSelectTask(task);
                                                    }}
                                                    title={`${dueTime.format("HH:mm")} · ${
                                                        task.title
                                                    }${task.reference ? ` (${task.reference.label})` : ""}`}
                                                >
                                                    <span
                                                        className="crm-cal-chip__time"
                                                        style={{ color: typeStyle.color }}
                                                    >
                                                        {dueTime.format("HH:mm")}
                                                    </span>
                                                    <span className="crm-cal-chip__title">
                                                        {task.title}
                                                    </span>
                                                </button>
                                            );
                                        })}

                                        {moreCount > 0 && (
                                            <button
                                                type="button"
                                                className="crm-cal-month__more"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    onOpenDayModal(date, dayTasks);
                                                }}
                                            >
                                                +{moreCount} nhiệm vụ khác
                                            </button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
        </div>
    );
}
