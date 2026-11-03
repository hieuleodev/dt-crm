import { useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Alert,
    Button,
    Card,
    Form,
    Input,
    Modal,
    Select,
    Space,
    Table,
    Tag,
    Typography,
} from "antd";
import type { TableColumnsType } from "antd";
import { PlusOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import TaskModal from "@/components/tasks/TaskModal";
import type { PageProps, Paginated } from "@/types";
import type {
    TaskAssignee,
    TaskOptions,
    TaskReference,
    TaskRow,
} from "@/types/task";

dayjs.extend(utc);
dayjs.extend(timezone);

interface Filters {
    q?: string;
    bucket?: string;
    priority?: string;
    assignee_id?: number;
    customer_id?: number;
    lead_id?: number;
    page?: number;
}

interface Props extends PageProps {
    tasks: Paginated<TaskRow>;
    filters: Filters;
    summary: {
        open: number;
        today: number;
        overdue: number;
    };
    options: TaskOptions;
    assignees: TaskAssignee[];
    initialReference: TaskReference | null;
}

const BUCKETS = [
    { value: "open", label: "Chưa hoàn thành" },
    { value: "today", label: "Đến hạn hôm nay" },
    { value: "overdue", label: "Quá hạn" },
    { value: "completed", label: "Hoàn thành" },
    { value: "cancelled", label: "Đã hủy" },
    { value: "all", label: "Tất cả" },
];

const dateTime = (value: string) =>
    dayjs(value).tz("Asia/Ho_Chi_Minh").format("DD/MM/YYYY HH:mm");

export default function Index() {
    const {
        tasks,
        filters,
        summary,
        options,
        assignees,
        initialReference,
        auth,
    } = usePage<Props>().props;

    const isManager = auth.user?.role !== "sales";

    const [q, setQ] = useState(filters.q ?? "");
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState("");

    const [editor, setEditor] = useState<{
        open: boolean;
        task: TaskRow | null;
    }>({ open: false, task: null });

    const [transition, setTransition] = useState<{
        task: TaskRow;
        status: string;
    } | null>(null);

    const [statusForm] = Form.useForm<{ note: string }>();
    const [savingStatus, setSavingStatus] = useState(false);
    const [statusError, setStatusError] = useState("");

    useEffect(() => {
        setQ(filters.q ?? "");
    }, [filters.q]);

    function apply(patch: Partial<Filters>) {
        const next: Filters = {
            ...filters,
            page: 1,
            ...patch,
        };

        router.get("/tasks", next as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                setLoading(true);
                setLoadError("");
            },
            onFinish: () => setLoading(false),
            onError: () => setLoadError("Không tải được danh sách nhiệm vụ."),
        });
    }

    function openTransition(task: TaskRow, status: string) {
        statusForm.resetFields();
        setStatusError("");
        setTransition({ task, status });
    }

    function submitStatus(values: { note: string }) {
        if (!transition) return;

        router.patch(
            `/tasks/${transition.task.id}/status`,
            {
                status: transition.status,
                note: values.note || null,
                version: transition.task.version,
            },
            {
                preserveScroll: true,
                onStart: () => setSavingStatus(true),
                onFinish: () => setSavingStatus(false),
                onSuccess: () => setTransition(null),
                onError: (errors) => {
                    setStatusError(
                        Object.values(errors)[0] ??
                            "Chưa cập nhật được trạng thái.",
                    );
                },
            },
        );
    }

    const columns: TableColumnsType<TaskRow> = [
        {
            title: "Nhiệm vụ",
            key: "title",
            width: 320,
            render: (_, task) => (
                <div className="crm-task-stack">
                    <Typography.Text strong>{task.title}</Typography.Text>

                    <Typography.Text type="secondary">
                        {options.types[task.type]}
                    </Typography.Text>

                    {task.description && (
                        <Typography.Paragraph
                            className="crm-task-description"
                            ellipsis={{
                                rows: 2,
                                expandable: true,
                                symbol: "Xem thêm",
                            }}
                        >
                            {task.description}
                        </Typography.Paragraph>
                    )}

                    {task.result && (
                        <details className="crm-task-result">
                            <summary>
                                {task.status === "cancelled"
                                    ? "Lý do hủy"
                                    : "Kết quả"}
                            </summary>
                            <div>{task.result}</div>
                        </details>
                    )}
                </div>
            ),
        },
        {
            title: "Liên quan",
            key: "reference",
            width: 230,
            render: (_, task) =>
                task.reference?.href ? (
                    <Link href={task.reference.href}>
                        {task.reference.label}
                    </Link>
                ) : (
                    (task.reference?.label ?? "Công việc cá nhân / nội bộ")
                ),
        },
        {
            title: "Hạn xử lý",
            key: "due",
            width: 180,
            render: (_, task) => (
                <div className="crm-task-stack">
                    <span>{dateTime(task.due_at)}</span>
                    {task.overdue && <Tag color="red">Quá hạn</Tag>}
                </div>
            ),
        },
        {
            title: "Ưu tiên",
            key: "priority",
            width: 130,
            render: (_, task) => (
                <Tag
                    color={
                        task.priority === "urgent"
                            ? "red"
                            : task.priority === "high"
                              ? "orange"
                              : "default"
                    }
                >
                    {options.priorities[task.priority]}
                </Tag>
            ),
        },
        {
            title: "Người thực hiện",
            dataIndex: "assignee",
            width: 180,
        },
        {
            title: "Trạng thái",
            key: "status",
            width: 170,
            render: (_, task) => (
                <Tag
                    color={
                        task.status === "completed"
                            ? "green"
                            : task.status === "in_progress"
                              ? "blue"
                              : "default"
                    }
                >
                    {options.statuses[task.status]}
                </Tag>
            ),
        },
        {
            title: "Thao tác",
            key: "actions",
            width: 300,
            render: (_, task) => {
                const open = ["pending", "in_progress"].includes(task.status);

                return (
                    <Space wrap>
                        {open && (
                            <>
                                <Button
                                    onClick={() =>
                                        setEditor({ open: true, task })
                                    }
                                >
                                    Chỉnh sửa
                                </Button>

                                <Button
                                    onClick={() =>
                                        openTransition(
                                            task,
                                            task.status === "pending"
                                                ? "in_progress"
                                                : "pending",
                                        )
                                    }
                                >
                                    {task.status === "pending"
                                        ? "Bắt đầu"
                                        : "Chuyển về chờ"}
                                </Button>

                                <Button
                                    onClick={() =>
                                        openTransition(task, "completed")
                                    }
                                >
                                    Hoàn thành
                                </Button>

                                <Button
                                    danger
                                    onClick={() =>
                                        openTransition(task, "cancelled")
                                    }
                                >
                                    Hủy nhiệm vụ
                                </Button>
                            </>
                        )}

                        {!open && isManager && (
                            <Button
                                onClick={() => openTransition(task, "pending")}
                            >
                                Mở lại
                            </Button>
                        )}
                    </Space>
                );
            },
        },
    ];

    const transitionTitle =
        transition?.status === "completed"
            ? "Hoàn thành nhiệm vụ"
            : transition?.status === "cancelled"
              ? "Hủy nhiệm vụ"
              : transition?.task.status === "completed" ||
                  transition?.task.status === "cancelled"
                ? "Mở lại nhiệm vụ"
                : "Cập nhật trạng thái";

    const noteRequired = Boolean(
        transition &&
        (["completed", "cancelled"].includes(transition.status) ||
            ["completed", "cancelled"].includes(transition.task.status)),
    );

    return (
        <AppLayout>
            <Head title="Nhiệm vụ" />

            <div className="crm-tasks-page">
                <PageHeader
                    title="Nhiệm vụ"
                    subtitle="Theo dõi việc cần làm, hạn xử lý và kết quả thực hiện."
                    extra={
                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            onClick={() =>
                                setEditor({ open: true, task: null })
                            }
                        >
                            Tạo nhiệm vụ
                        </Button>
                    }
                />

                <div className="crm-task-summary">
                    {[
                        {
                            key: "open",
                            label: "Chưa hoàn thành",
                            value: summary.open,
                        },
                        {
                            key: "today",
                            label: "Đến hạn hôm nay",
                            value: summary.today,
                        },
                        {
                            key: "overdue",
                            label: "Quá hạn",
                            value: summary.overdue,
                        },
                    ].map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            className="crm-task-summary__item"
                            aria-pressed={filters.bucket === item.key}
                            onClick={() => apply({ bucket: item.key })}
                        >
                            <span>{item.label}</span>
                            <strong>
                                {item.value.toLocaleString("vi-VN")}
                            </strong>
                        </button>
                    ))}
                </div>

                {initialReference && (
                    <Alert
                        className="crm-task-context"
                        type="info"
                        showIcon
                        title={`Đang xem nhiệm vụ: ${initialReference.label}`}
                        action={
                            <Button
                                onClick={() =>
                                    apply({
                                        customer_id: undefined,
                                        lead_id: undefined,
                                    })
                                }
                            >
                                Xem tất cả
                            </Button>
                        }
                    />
                )}

                <Card>
                    <div className="crm-task-filters">
                        <Input.Search
                            aria-label="Tìm nhiệm vụ"
                            placeholder="Tìm tên hoặc nội dung nhiệm vụ"
                            allowClear
                            value={q}
                            maxLength={180}
                            onChange={(event) => setQ(event.target.value)}
                            onSearch={(value) => apply({ q: value.trim() })}
                        />

                        <Select
                            aria-label="Lọc thời hạn và trạng thái"
                            value={filters.bucket ?? "open"}
                            options={BUCKETS}
                            onChange={(value) => apply({ bucket: value })}
                        />

                        <Select
                            aria-label="Lọc mức ưu tiên"
                            placeholder="Mọi mức ưu tiên"
                            allowClear
                            value={filters.priority}
                            options={Object.entries(options.priorities).map(
                                ([value, label]) => ({ value, label }),
                            )}
                            onChange={(value) => apply({ priority: value })}
                        />

                        {isManager && (
                            <Select
                                aria-label="Lọc người thực hiện"
                                placeholder="Mọi người thực hiện"
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                value={filters.assignee_id}
                                options={assignees.map((user) => ({
                                    value: user.id,
                                    label: user.name,
                                }))}
                                onChange={(value) =>
                                    apply({ assignee_id: value })
                                }
                            />
                        )}
                    </div>

                    {loadError && (
                        <Alert type="error" title={loadError} role="alert" />
                    )}

                    <Table<TaskRow>
                        rowKey="id"
                        columns={columns}
                        dataSource={tasks.data}
                        loading={loading}
                        scroll={{ x: 1510 }}
                        locale={{ emptyText: "Không có nhiệm vụ phù hợp." }}
                        pagination={{
                            current: tasks.current_page,
                            pageSize: tasks.per_page,
                            total: tasks.total,
                            showSizeChanger: false,
                            showTotal: (total) => `${total} nhiệm vụ`,
                            onChange: (page) => apply({ page }),
                        }}
                    />
                </Card>
            </div>

            <TaskModal
                open={editor.open}
                task={editor.task}
                options={options}
                assignees={assignees}
                currentUserId={auth.user!.id}
                initialReference={initialReference}
                onClose={() => setEditor({ open: false, task: null })}
            />

            <Modal
                title={transitionTitle}
                open={transition !== null}
                confirmLoading={savingStatus}
                okText="Xác nhận"
                cancelText="Đóng"
                closable={!savingStatus}
                maskClosable={!savingStatus}
                keyboard={!savingStatus}
                cancelButtonProps={{ disabled: savingStatus }}
                onCancel={() => !savingStatus && setTransition(null)}
                onOk={() => statusForm.submit()}
            >
                <Typography.Paragraph strong>
                    {transition?.task.title}
                </Typography.Paragraph>

                {statusError && (
                    <Alert
                        className="crm-task-form-alert"
                        type="error"
                        title={statusError}
                        role="alert"
                    />
                )}

                <Form
                    form={statusForm}
                    layout="vertical"
                    disabled={savingStatus}
                    onFinish={submitStatus}
                >
                    <Form.Item
                        name="note"
                        label={
                            transition?.status === "completed"
                                ? "Kết quả thực hiện"
                                : "Ghi chú / lý do"
                        }
                        rules={[
                            {
                                required: noteRequired,
                                whitespace: true,
                                message: "Vui lòng ghi kết quả hoặc lý do.",
                            },
                        ]}
                    >
                        <Input.TextArea rows={4} maxLength={5000} showCount />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
