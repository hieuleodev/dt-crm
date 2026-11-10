import { useEffect, useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import {
    Badge,
    Button,
    Card,
    Drawer,
    Input,
    Segmented,
    Select,
    Space,
    Tag,
    Typography,
} from "antd";
import {
    CalendarOutlined,
    CheckCircleOutlined,
    ClockCircleOutlined,
    FilterOutlined,
    LeftOutlined,
    PlusOutlined,
    RightOutlined,
    SearchOutlined,
    WarningOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import isoWeek from "dayjs/plugin/isoWeek";
import AppLayout from "@/layouts/AppLayout";
import TaskModal from "@/components/tasks/TaskModal";
import TaskStatusModal from "@/components/tasks/TaskStatusModal";
import type { TaskStatus } from "@/components/tasks/TaskStatusModal";
import type { TaskRow } from "@/types/task";
import AgendaView from "./AgendaView";
import DayEventsModal from "./DayEventsModal";
import DayView from "./DayView";
import EventDetailModal from "./EventDetailModal";
import MonthView from "./MonthView";
import WeekView from "./WeekView";
import {
    DEFAULT_TYPE_STYLE,
    TIMEZONE,
    TYPE_CONFIG,
    type CalendarFilters,
    type CalendarPageProps,
    type CalendarViewType,
} from "./types";

dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(isoWeek);

export default function CalendarIndex() {
    const {
        tasks,
        filters,
        summary,
        options,
        assignees,
        auth,
    } = usePage<CalendarPageProps>().props;

    const isManager = auth.user?.role !== "sales";

    // Current anchor date
    const [currentDate, setCurrentDate] = useState<dayjs.Dayjs>(() =>
        dayjs(filters.date || undefined).tz(TIMEZONE),
    );

    // Current view
    const [view, setView] = useState<CalendarViewType>(
        (filters.view as CalendarViewType) || "month",
    );

    // Search query
    const [q, setQ] = useState(filters.q ?? "");
    const [filterDrawerOpen, setFilterDrawerOpen] = useState(false);

    // Modals state
    const [editor, setEditor] = useState<{
        open: boolean;
        task: TaskRow | null;
        initialDueAt?: dayjs.Dayjs | null;
    }>({ open: false, task: null, initialDueAt: null });

    const [statusModal, setStatusModal] = useState<{
        task: TaskRow | null;
        status: TaskStatus;
    }>({ task: null, status: "pending" });

    const [detailTask, setDetailTask] = useState<TaskRow | null>(null);

    const [dayModal, setDayModal] = useState<{
        date: dayjs.Dayjs | null;
        tasks: TaskRow[];
    }>({ date: null, tasks: [] });

    // Sync filters from server
    useEffect(() => {
        if (filters.date) {
            setCurrentDate(dayjs(filters.date).tz(TIMEZONE));
        }
        if (filters.view) {
            setView(filters.view);
        }
        setQ(filters.q ?? "");
    }, [filters.date, filters.view, filters.q]);

    // Apply filters to backend
    function applyNavigation(newDate: dayjs.Dayjs, newView: CalendarViewType, patch: Partial<CalendarFilters> = {}) {
        const nextFilters: Record<string, unknown> = {
            ...filters,
            date: newDate.format("YYYY-MM-DD"),
            view: newView,
            ...patch,
        };

        // Clean null/undefined
        for (const key of Object.keys(nextFilters)) {
            if (nextFilters[key] === null || nextFilters[key] === undefined || nextFilters[key] === "") {
                delete nextFilters[key];
            }
        }

        router.get("/calendar", nextFilters as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    // Navigation buttons
    function handleToday() {
        const today = dayjs().tz(TIMEZONE);
        setCurrentDate(today);
        applyNavigation(today, view);
    }

    function handlePrev() {
        let prevDate = currentDate;
        if (view === "month") prevDate = currentDate.subtract(1, "month");
        else if (view === "week") prevDate = currentDate.subtract(1, "week");
        else if (view === "day") prevDate = currentDate.subtract(1, "day");
        else if (view === "agenda") prevDate = currentDate.subtract(1, "month");

        setCurrentDate(prevDate);
        applyNavigation(prevDate, view);
    }

    function handleNext() {
        let nextDate = currentDate;
        if (view === "month") nextDate = currentDate.add(1, "month");
        else if (view === "week") nextDate = currentDate.add(1, "week");
        else if (view === "day") nextDate = currentDate.add(1, "day");
        else if (view === "agenda") nextDate = currentDate.add(1, "month");

        setCurrentDate(nextDate);
        applyNavigation(nextDate, view);
    }

    function handleViewChange(newView: CalendarViewType) {
        setView(newView);
        applyNavigation(currentDate, newView);
    }

    // Title label for the current period (like Google Calendar)
    function renderPeriodTitle() {
        if (view === "month") {
            return `Tháng ${currentDate.month() + 1} năm ${currentDate.year()}`;
        }
        if (view === "week") {
            const startOfWeek = currentDate.startOf("isoWeek");
            const endOfWeek = currentDate.endOf("isoWeek");
            if (startOfWeek.month() === endOfWeek.month()) {
                return `${startOfWeek.format("DD")} - ${endOfWeek.format("DD")} Tháng ${startOfWeek.month() + 1}, ${startOfWeek.year()}`;
            }
            return `${startOfWeek.format("DD/MM")} - ${endOfWeek.format("DD/MM/YYYY")}`;
        }
        if (view === "day") {
            return currentDate.format("dddd, [ngày] DD/MM/YYYY");
        }
        return `Lịch biểu từ ${currentDate.format("DD/MM/YYYY")}`;
    }

    // Action helpers
    function openCreate(initialDueAt?: dayjs.Dayjs) {
        setEditor({
            open: true,
            task: null,
            initialDueAt: initialDueAt ?? dayjs().tz(TIMEZONE).hour(9).minute(0),
        });
    }

    function openEdit(task: TaskRow) {
        setEditor({
            open: true,
            task,
            initialDueAt: dayjs(task.due_at).tz(TIMEZONE),
        });
    }

    function openStatusChange(task: TaskRow, nextStatus: string) {
        setStatusModal({
            task,
            status: nextStatus as TaskStatus,
        });
    }

    const activeFilterCount = [
        filters.type,
        filters.priority,
        filters.status,
        filters.assignee_id,
        filters.q,
    ].filter(Boolean).length;

    return (
        <AppLayout>
            <Head title="Lịch trình" />

            <div className="crm-cal-page">
                {/* Google Calendar Toolbar */}
                <div className="crm-cal-toolbar">
                    {/* Left: Navigation and Date */}
                    <div className="crm-cal-toolbar__left">
                        <Button onClick={handleToday} className="crm-cal-today-btn">
                            Hôm nay
                        </Button>

                        <Space orientation="horizontal" size={4}>
                            <Button
                                icon={<LeftOutlined />}
                                shape="circle"
                                size="middle"
                                onClick={handlePrev}
                                title="Thời gian trước"
                            />
                            <Button
                                icon={<RightOutlined />}
                                shape="circle"
                                size="middle"
                                onClick={handleNext}
                                title="Thời gian tiếp theo"
                            />
                        </Space>

                        <Typography.Title
                            level={3}
                            className="crm-cal-toolbar__title"
                            style={{ margin: 0 }}
                        >
                            {renderPeriodTitle()}
                        </Typography.Title>
                    </div>

                    {/* Middle: Search input */}
                    <div className="crm-cal-toolbar__center">
                        <Input.Search
                            placeholder="Tìm kiếm lịch trình..."
                            allowClear
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            onSearch={(val) =>
                                applyNavigation(currentDate, view, { q: val.trim() || undefined })
                            }
                            style={{ maxWidth: 320 }}
                        />
                    </div>

                    {/* Right: View Switcher, Filter Toggle, Create Button */}
                    <div className="crm-cal-toolbar__right">
                        <Badge count={activeFilterCount} size="small" offset={[-4, 4]}>
                            <Button
                                icon={<FilterOutlined />}
                                onClick={() => setFilterDrawerOpen(true)}
                            >
                                Bộ lọc
                            </Button>
                        </Badge>

                        <Segmented
                            value={view}
                            onChange={(val) => handleViewChange(val as CalendarViewType)}
                            options={[
                                { label: "Tháng", value: "month" },
                                { label: "Tuần", value: "week" },
                                { label: "Ngày", value: "day" },
                                { label: "Lịch biểu", value: "agenda" },
                            ]}
                        />

                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            onClick={() => openCreate()}
                            className="crm-cal-create-btn"
                        >
                            Tạo lịch trình
                        </Button>
                    </div>
                </div>

                {/* Quick KPI stats strip */}
                <div className="crm-cal-stats">
                    <div className="crm-cal-stat-chip">
                        <CalendarOutlined style={{ color: "#2563eb" }} />
                        <span>Tổng số:</span>
                        <strong>{summary.total}</strong>
                    </div>

                    <div className="crm-cal-stat-chip">
                        <ClockCircleOutlined style={{ color: "#d97706" }} />
                        <span>Chưa xong:</span>
                        <strong style={{ color: "#d97706" }}>{summary.open}</strong>
                    </div>

                    {summary.overdue > 0 && (
                        <div className="crm-cal-stat-chip crm-cal-stat-chip--alert">
                            <WarningOutlined style={{ color: "#dc2626" }} />
                            <span>Quá hạn:</span>
                            <strong style={{ color: "#dc2626" }}>{summary.overdue}</strong>
                        </div>
                    )}

                    <div className="crm-cal-stat-chip">
                        <CheckCircleOutlined style={{ color: "#059669" }} />
                        <span>Đã hoàn thành:</span>
                        <strong style={{ color: "#059669" }}>{summary.completed}</strong>
                    </div>

                    {/* Type color indicators */}
                    <div className="crm-cal-legend">
                        {Object.entries(TYPE_CONFIG).slice(0, 5).map(([key, style]) => (
                            <span key={key} className="crm-cal-legend-item">
                                <span
                                    className="crm-cal-legend-dot"
                                    style={{ background: style.color }}
                                />
                                <span>{style.label}</span>
                            </span>
                        ))}
                    </div>
                </div>

                {/* Main Calendar View Area */}
                <Card className="crm-cal-container" bodyStyle={{ padding: 0 }}>
                    {view === "month" && (
                        <MonthView
                            currentDate={currentDate}
                            tasks={tasks}
                            onSelectTask={(task) => setDetailTask(task)}
                            onCreateOnDate={(date) => openCreate(date)}
                            onOpenDayModal={(date, dayTasks) =>
                                setDayModal({ date, tasks: dayTasks })
                            }
                        />
                    )}

                    {view === "week" && (
                        <WeekView
                            currentDate={currentDate}
                            tasks={tasks}
                            onSelectTask={(task) => setDetailTask(task)}
                            onCreateOnDate={(date) => openCreate(date)}
                        />
                    )}

                    {view === "day" && (
                        <DayView
                            currentDate={currentDate}
                            tasks={tasks}
                            onSelectTask={(task) => setDetailTask(task)}
                            onCreateOnDate={(date) => openCreate(date)}
                        />
                    )}

                    {view === "agenda" && (
                        <AgendaView
                            tasks={tasks}
                            options={options}
                            onSelectTask={(task) => setDetailTask(task)}
                            onCreateOnDate={(date) => openCreate(date)}
                            onEdit={(task) => openEdit(task)}
                            onChangeStatus={(task, nextStatus) =>
                                openStatusChange(task, nextStatus)
                            }
                        />
                    )}
                </Card>
            </div>

            {/* Event Detail Modal (Google Calendar style) */}
            <EventDetailModal
                task={detailTask}
                open={detailTask !== null}
                options={options}
                isManager={isManager}
                onClose={() => setDetailTask(null)}
                onEdit={(task) => openEdit(task)}
                onChangeStatus={(task, nextStatus) => openStatusChange(task, nextStatus)}
            />

            {/* Month View: More Events Popover/Modal */}
            <DayEventsModal
                date={dayModal.date}
                open={dayModal.date !== null}
                tasks={dayModal.tasks}
                options={options}
                onClose={() => setDayModal({ date: null, tasks: [] })}
                onSelectTask={(task) => setDetailTask(task)}
                onCreateOnDate={(date) => openCreate(date)}
            />

            {/* Create/Edit Task Modal */}
            <TaskModal
                open={editor.open}
                task={editor.task}
                options={options}
                assignees={assignees}
                currentUserId={auth.user!.id}
                initialReference={null}
                initialDueAt={editor.initialDueAt}
                onClose={() => setEditor({ open: false, task: null, initialDueAt: null })}
            />

            {/* Task Status Transition Modal */}
            <TaskStatusModal
                task={statusModal.task}
                status={statusModal.status}
                onClose={() => setStatusModal({ task: null, status: "pending" })}
            />

            {/* Filter Drawer */}
            <Drawer
                title="Lọc lịch trình"
                open={filterDrawerOpen}
                onClose={() => setFilterDrawerOpen(false)}
                width={360}
                extra={
                    <Button
                        size="small"
                        onClick={() => {
                            applyNavigation(currentDate, view, {
                                type: undefined,
                                priority: undefined,
                                status: undefined,
                                assignee_id: undefined,
                                q: undefined,
                            });
                            setFilterDrawerOpen(false);
                        }}
                    >
                        Đặt lại
                    </Button>
                }
            >
                <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
                    <div>
                        <Typography.Text strong style={{ display: "block", marginBottom: 6 }}>
                            Loại lịch trình
                        </Typography.Text>
                        <Select
                            allowClear
                            placeholder="Tất cả loại lịch"
                            style={{ width: "100%" }}
                            value={filters.type}
                            options={Object.entries(options.types).map(([val, label]) => {
                                const style = TYPE_CONFIG[val] ?? DEFAULT_TYPE_STYLE;
                                return {
                                    value: val,
                                    label: `${style.icon} ${label}`,
                                };
                            })}
                            onChange={(val) =>
                                applyNavigation(currentDate, view, { type: val || undefined })
                            }
                        />
                    </div>

                    <div>
                        <Typography.Text strong style={{ display: "block", marginBottom: 6 }}>
                            Mức ưu tiên
                        </Typography.Text>
                        <Select
                            allowClear
                            placeholder="Mọi mức ưu tiên"
                            style={{ width: "100%" }}
                            value={filters.priority}
                            options={Object.entries(options.priorities).map(([val, label]) => ({
                                value: val,
                                label,
                            }))}
                            onChange={(val) =>
                                applyNavigation(currentDate, view, { priority: val || undefined })
                            }
                        />
                    </div>

                    <div>
                        <Typography.Text strong style={{ display: "block", marginBottom: 6 }}>
                            Trạng thái
                        </Typography.Text>
                        <Select
                            allowClear
                            placeholder="Tất cả trạng thái"
                            style={{ width: "100%" }}
                            value={filters.status ?? "open"}
                            options={[
                                { value: "open", label: "Chưa hoàn thành (Chờ + Đang làm)" },
                                { value: "pending", label: "Chờ thực hiện" },
                                { value: "in_progress", label: "Đang thực hiện" },
                                { value: "completed", label: "Hoàn thành" },
                                { value: "cancelled", label: "Đã hủy" },
                                { value: "all", label: "Tất cả" },
                            ]}
                            onChange={(val) =>
                                applyNavigation(currentDate, view, { status: val || undefined })
                            }
                        />
                    </div>

                    {isManager && (
                        <div>
                            <Typography.Text strong style={{ display: "block", marginBottom: 6 }}>
                                Người thực hiện
                            </Typography.Text>
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                placeholder="Tất cả nhân viên"
                                style={{ width: "100%" }}
                                value={filters.assignee_id}
                                options={assignees.map((user) => ({
                                    value: user.id,
                                    label: user.name,
                                }))}
                                onChange={(val) =>
                                    applyNavigation(currentDate, view, { assignee_id: val || undefined })
                                }
                            />
                        </div>
                    )}
                </div>
            </Drawer>
        </AppLayout>
    );
}
