import { useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Alert,
    Button,
    Descriptions,
    Dropdown,
    Empty,
    Input,
    Modal,
    Pagination,
    Select,
    Spin,
    Tag,
    Typography,
} from "antd";
import type { MenuProps } from "antd";
import { MoreOutlined, PlusOutlined, ReloadOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezonePlugin from "dayjs/plugin/timezone";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import TaskModal from "@/components/tasks/TaskModal";
import TaskStatusModal from "@/components/tasks/TaskStatusModal";
import type { TaskStatus } from "@/components/tasks/TaskStatusModal";
import type { PageProps, Paginated } from "@/types";
import type { TaskAssignee, TaskOptions, TaskRow } from "@/types/task";

dayjs.extend(utc);
dayjs.extend(timezonePlugin);

type Bucket = "overdue" | "today" | "upcoming" | "completed";

interface Filters {
    scope: "mine" | "all";
    assignee_id: number | null;
    bucket: Bucket;
    q: string;
    priority: string | null;
}

interface Summary {
    overdue: number;
    today: number;
    in_progress: number;
    completed: number;
    upcoming: number;
}

interface Props extends PageProps {
    tasks: Paginated<TaskRow>;
    filters: Filters;
    summary: Summary;
    options: TaskOptions;
    assignees: TaskAssignee[];
    generatedAt: string;
    timezone: string;
}

const BUCKET_LABELS: Record<Bucket, string> = {
    overdue: "Quá hạn",
    today: "Hôm nay",
    upcoming: "Sắp tới",
    completed: "Hoàn thành hôm nay",
};

export default function Index() {
    const {
        auth,
        tasks,
        filters,
        summary,
        options,
        assignees,
        generatedAt,
        timezone,
    } = usePage<Props>().props;

    const isManager = Boolean(auth.user && auth.user.role !== "sales");

    const [q, setQ] = useState(filters.q);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState("");

    const [editor, setEditor] = useState<{
        open: boolean;
        task: TaskRow | null;
    }>({ open: false, task: null });

    const [transition, setTransition] = useState<{
        task: TaskRow;
        status: TaskStatus;
    } | null>(null);

    const [detail, setDetail] = useState<TaskRow | null>(null);

    const modalOpen = editor.open || transition !== null || detail !== null;

    const formatTime = (value: string) =>
        dayjs(value).tz(timezone).format("DD/MM/YYYY HH:mm");

    useEffect(() => {
        setQ(filters.q);
    }, [filters.q]);

    useEffect(() => {
        if (modalOpen || loading) return;

        const timer = window.setInterval(() => {
            if (document.visibilityState !== "visible") return;

            router.reload({
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
                onError: () => {
                    setLoadError("Chưa làm mới được dữ liệu.");
                },
                onSuccess: () => setLoadError(""),
            });
        }, 60_000);

        return () => window.clearInterval(timer);
    }, [modalOpen, loading]);

    function apply(patch: Partial<Filters> & { page?: number } = {}) {
        if (loading) return;

        const next = {
            ...filters,
            page: 1,
            ...patch,
        };

        const query: Record<string, string | number> = {};

        for (const [key, value] of Object.entries(next)) {
            if (value !== null && value !== undefined && value !== "") {
                query[key] = value;
            }
        }

        router.get("/workbench", query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                setLoading(true);
                setLoadError("");
            },
            onFinish: () => setLoading(false),
            onError: () => {
                setLoadError("Không tải được công việc. Vui lòng thử lại.");
            },
        });
    }

    function changeStatus(task: TaskRow, status: TaskStatus) {
        setTransition({ task, status });
    }

    function moreActions(task: TaskRow): MenuProps {
        const open = ["pending", "in_progress"].includes(task.status);

        const items: MenuProps["items"] = open
            ? [
                  { key: "edit", label: "Chỉnh sửa" },
                  ...(task.status === "in_progress"
                      ? [{ key: "pending", label: "Chuyển về chờ" }]
                      : []),
                  {
                      key: "cancelled",
                      label: "Hủy nhiệm vụ",
                      danger: true,
                  },
              ]
            : isManager
              ? [{ key: "pending", label: "Mở lại nhiệm vụ" }]
              : [];

        return {
            items,
            onClick: ({ key }) => {
                if (key === "edit") {
                    setEditor({ open: true, task });
                } else if (key === "pending" || key === "cancelled") {
                    changeStatus(task, key);
                }
            },
        };
    }

    const metrics = [
        {
            key: "overdue",
            label: "Quá hạn",
            value: summary.overdue,
            bucket: "overdue" as Bucket,
        },
        {
            key: "today",
            label: "Còn phải làm hôm nay",
            value: summary.today,
            bucket: "today" as Bucket,
        },
        {
            key: "in_progress",
            label: "Đang thực hiện",
            value: summary.in_progress,
            bucket: null,
        },
        {
            key: "completed",
            label: "Hoàn thành hôm nay",
            value: summary.completed,
            bucket: "completed" as Bucket,
        },
    ];

    const buckets: Bucket[] = ["overdue", "today", "upcoming", "completed"];

    const bucketCount = (bucket: Bucket) => summary[bucket];

    return (
        <AppLayout>
            <Head title="Công việc hôm nay" />

            <div className="crm-workbench">
                <PageHeader
                    title="Công việc hôm nay"
                    subtitle={
                        <>
                            {dayjs(generatedAt)
                                .tz(timezone)
                                .format("DD/MM/YYYY")}
                            {" · Giờ Việt Nam"}
                        </>
                    }
                    extra={
                        <>
                            <Button
                                icon={<ReloadOutlined />}
                                loading={loading}
                                onClick={() =>
                                    apply({ page: tasks.current_page })
                                }
                            >
                                Làm mới
                            </Button>

                            <Button
                                type="primary"
                                icon={<PlusOutlined />}
                                onClick={() =>
                                    setEditor({ open: true, task: null })
                                }
                            >
                                Tạo nhiệm vụ
                            </Button>
                        </>
                    }
                />

                <section
                    className="crm-workbench__scope"
                    aria-label="Phạm vi công việc"
                >
                    <div className="crm-workbench__scope-controls">
                        {isManager ? (
                            <>
                                <Select
                                    aria-label="Phạm vi công việc"
                                    value={filters.scope}
                                    disabled={loading}
                                    options={[
                                        { value: "mine", label: "Của tôi" },
                                        { value: "all", label: "Toàn đội" },
                                    ]}
                                    onChange={(value: "mine" | "all") =>
                                        apply({
                                            scope: value,
                                            assignee_id: null,
                                        })
                                    }
                                />

                                {filters.scope === "all" && (
                                    <Select
                                        aria-label="Người thực hiện"
                                        placeholder="Tất cả nhân viên"
                                        allowClear
                                        showSearch
                                        optionFilterProp="label"
                                        disabled={loading}
                                        value={filters.assignee_id ?? undefined}
                                        options={assignees.map((user) => ({
                                            value: user.id,
                                            label: user.name,
                                        }))}
                                        onChange={(value) =>
                                            apply({
                                                assignee_id: value ?? null,
                                            })
                                        }
                                    />
                                )}
                            </>
                        ) : (
                            <Typography.Text strong>
                                Công việc của tôi
                            </Typography.Text>
                        )}
                    </div>

                    <Typography.Text type="secondary">
                        Cập nhật lúc{" "}
                        {dayjs(generatedAt).tz(timezone).format("HH:mm")}
                    </Typography.Text>
                </section>

                <section
                    className="crm-workbench__metrics"
                    aria-label="Tổng hợp công việc"
                >
                    {metrics.map((metric) => {
                        const content = (
                            <>
                                <span>{metric.label}</span>
                                <strong>
                                    {metric.value.toLocaleString("vi-VN")}
                                </strong>
                            </>
                        );

                        return metric.bucket ? (
                            <button
                                key={metric.key}
                                type="button"
                                className="crm-workbench__metric"
                                aria-pressed={filters.bucket === metric.bucket}
                                disabled={loading}
                                onClick={() =>
                                    apply({ bucket: metric.bucket! })
                                }
                            >
                                {content}
                            </button>
                        ) : (
                            <div
                                key={metric.key}
                                className="crm-workbench__metric"
                            >
                                {content}
                                <small>Trong toàn bộ việc chưa kết thúc</small>
                            </div>
                        );
                    })}
                </section>

                {loadError && (
                    <Alert
                        type="error"
                        showIcon
                        title={loadError}
                        role="alert"
                    />
                )}

                <section
                    className="crm-workbench__panel"
                    aria-label="Danh sách công việc"
                    aria-busy={loading}
                >
                    <div
                        className="crm-workbench__tabs"
                        role="group"
                        aria-label="Nhóm công việc"
                    >
                        {buckets.map((bucket) => (
                            <button
                                key={bucket}
                                type="button"
                                disabled={loading}
                                aria-pressed={filters.bucket === bucket}
                                onClick={() => apply({ bucket })}
                            >
                                {BUCKET_LABELS[bucket]}
                                <span>{bucketCount(bucket)}</span>
                            </button>
                        ))}
                    </div>

                    <div className="crm-workbench__filters">
                        <Input.Search
                            aria-label="Tìm công việc"
                            placeholder="Tìm tên hoặc nội dung nhiệm vụ"
                            allowClear
                            maxLength={180}
                            value={q}
                            disabled={loading}
                            onChange={(event) => setQ(event.target.value)}
                            onSearch={(value) => apply({ q: value.trim() })}
                        />

                        <Select
                            aria-label="Mức ưu tiên"
                            placeholder="Mọi mức ưu tiên"
                            allowClear
                            disabled={loading}
                            value={filters.priority ?? undefined}
                            options={Object.entries(options.priorities).map(
                                ([value, label]) => ({
                                    value,
                                    label,
                                }),
                            )}
                            onChange={(value) =>
                                apply({ priority: value ?? null })
                            }
                        />

                        <Button
                            disabled={loading}
                            onClick={() => {
                                setQ("");
                                apply({ q: "", priority: null });
                            }}
                        >
                            Xóa bộ lọc
                        </Button>
                    </div>

                    <Spin spinning={loading}>
                        <div className="crm-workbench__results" role="status">
                            {tasks.total} công việc
                        </div>

                        {tasks.data.length === 0 ? (
                            <Empty
                                className="crm-workbench__empty"
                                description={
                                    filters.q || filters.priority
                                        ? "Không có công việc phù hợp với bộ lọc."
                                        : filters.bucket === "completed"
                                          ? "Chưa có công việc hoàn thành hôm nay."
                                          : filters.bucket === "today"
                                            ? "Không còn công việc đến hạn trong hôm nay. Hãy kiểm tra thêm nhóm Quá hạn."
                                            : filters.bucket === "overdue"
                                              ? "Không có công việc quá hạn."
                                              : "Chưa có công việc trong 7 ngày tiếp theo."
                                }
                            />
                        ) : (
                            <ul className="crm-workbench__list">
                                {tasks.data.map((task) => {
                                    const open = [
                                        "pending",
                                        "in_progress",
                                    ].includes(task.status);

                                    const menu = moreActions(task);

                                    return (
                                        <li key={task.id}>
                                            <article className="crm-workbench__task">
                                                <div className="crm-workbench__task-main">
                                                    <div className="crm-workbench__tags">
                                                        <Tag
                                                            color={
                                                                task.priority ===
                                                                "urgent"
                                                                    ? "red"
                                                                    : task.priority ===
                                                                        "high"
                                                                      ? "orange"
                                                                      : "default"
                                                            }
                                                        >
                                                            {
                                                                options
                                                                    .priorities[
                                                                    task
                                                                        .priority
                                                                ]
                                                            }
                                                        </Tag>

                                                        <Tag>
                                                            {
                                                                options
                                                                    .statuses[
                                                                    task.status
                                                                ]
                                                            }
                                                        </Tag>

                                                        {task.overdue && (
                                                            <Tag color="red">
                                                                Quá hạn
                                                            </Tag>
                                                        )}
                                                    </div>

                                                    <h2 className="crm-workbench__task-title">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setDetail(task)
                                                            }
                                                        >
                                                            {task.title}
                                                        </button>
                                                    </h2>

                                                    <div className="crm-workbench__reference">
                                                        {task.reference
                                                            ?.href ? (
                                                            <Link
                                                                href={
                                                                    task
                                                                        .reference
                                                                        .href
                                                                }
                                                            >
                                                                {
                                                                    task
                                                                        .reference
                                                                        .label
                                                                }
                                                            </Link>
                                                        ) : (
                                                            (task.reference
                                                                ?.label ??
                                                            "Công việc cá nhân / nội bộ")
                                                        )}
                                                    </div>

                                                    <div className="crm-workbench__meta">
                                                        <span>
                                                            {
                                                                options.types[
                                                                    task.type
                                                                ]
                                                            }
                                                        </span>
                                                        <span>
                                                            Phụ trách:{" "}
                                                            {task.assignee}
                                                        </span>
                                                        <span>
                                                            Hạn:{" "}
                                                            {formatTime(
                                                                task.due_at,
                                                            )}
                                                        </span>

                                                        {task.completed_at && (
                                                            <span>
                                                                Hoàn thành:{" "}
                                                                {formatTime(
                                                                    task.completed_at,
                                                                )}
                                                            </span>
                                                        )}
                                                    </div>

                                                    {task.result && (
                                                        <p className="crm-workbench__result">
                                                            <strong>
                                                                Kết quả:{" "}
                                                            </strong>
                                                            {task.result}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="crm-workbench__actions">
                                                    <Button
                                                        onClick={() =>
                                                            setDetail(task)
                                                        }
                                                    >
                                                        Chi tiết
                                                    </Button>

                                                    {task.status ===
                                                        "pending" && (
                                                        <Button
                                                            onClick={() =>
                                                                changeStatus(
                                                                    task,
                                                                    "in_progress",
                                                                )
                                                            }
                                                        >
                                                            Bắt đầu
                                                        </Button>
                                                    )}

                                                    {open && (
                                                        <Button
                                                            type="primary"
                                                            onClick={() =>
                                                                changeStatus(
                                                                    task,
                                                                    "completed",
                                                                )
                                                            }
                                                        >
                                                            Hoàn thành
                                                        </Button>
                                                    )}

                                                    {Boolean(
                                                        menu.items?.length,
                                                    ) && (
                                                        <Dropdown
                                                            trigger={["click"]}
                                                            menu={menu}
                                                            placement="bottomRight"
                                                        >
                                                            <Button
                                                                icon={
                                                                    <MoreOutlined />
                                                                }
                                                                aria-label={`Thao tác khác: ${task.title}`}
                                                            />
                                                        </Dropdown>
                                                    )}
                                                </div>
                                            </article>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        <Pagination
                            className="crm-workbench__pagination"
                            current={tasks.current_page}
                            pageSize={tasks.per_page}
                            total={tasks.total}
                            showSizeChanger={false}
                            hideOnSinglePage
                            disabled={loading}
                            onChange={(page) => apply({ page })}
                        />
                    </Spin>
                </section>
            </div>

            <TaskModal
                open={editor.open}
                task={editor.task}
                options={options}
                assignees={assignees}
                currentUserId={auth.user!.id}
                initialReference={null}
                onClose={() => setEditor({ open: false, task: null })}
            />

            <TaskStatusModal
                task={transition?.task ?? null}
                status={transition?.status ?? "pending"}
                onClose={() => setTransition(null)}
            />

            <Modal
                title="Chi tiết nhiệm vụ"
                open={detail !== null}
                onCancel={() => setDetail(null)}
                footer={<Button onClick={() => setDetail(null)}>Đóng</Button>}
            >
                {detail && (
                    <>
                        <Typography.Title level={4}>
                            {detail.title}
                        </Typography.Title>

                        <Descriptions
                            column={1}
                            items={[
                                {
                                    key: "type",
                                    label: "Loại việc",
                                    children: options.types[detail.type],
                                },
                                {
                                    key: "status",
                                    label: "Trạng thái",
                                    children: options.statuses[detail.status],
                                },
                                {
                                    key: "assignee",
                                    label: "Người thực hiện",
                                    children: detail.assignee,
                                },
                                {
                                    key: "due",
                                    label: "Hạn xử lý",
                                    children: formatTime(detail.due_at),
                                },
                                {
                                    key: "reference",
                                    label: "Liên quan",
                                    children:
                                        detail.reference?.label ??
                                        "Cá nhân / nội bộ",
                                },
                                {
                                    key: "description",
                                    label: "Nội dung",
                                    children: (
                                        <div className="crm-workbench__prewrap">
                                            {detail.description ||
                                                "Chưa có mô tả."}
                                        </div>
                                    ),
                                },
                                {
                                    key: "result",
                                    label: "Kết quả",
                                    children: (
                                        <div className="crm-workbench__prewrap">
                                            {detail.result ||
                                                "Chưa có kết quả."}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </>
                )}
            </Modal>
        </AppLayout>
    );
}
