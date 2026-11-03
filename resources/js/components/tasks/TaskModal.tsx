import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import {
    Alert,
    Button,
    Col,
    DatePicker,
    Form,
    Input,
    Modal,
    Row,
    Select,
} from "antd";
import dayjs from "dayjs";
import type { Dayjs } from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import type {
    TaskAssignee,
    TaskOptions,
    TaskReference,
    TaskRow,
} from "@/types/task";

dayjs.extend(utc);
dayjs.extend(timezone);

const TIMEZONE = "Asia/Ho_Chi_Minh";

interface Values {
    title: string;
    description?: string;
    type: string;
    priority: string;
    assignee_id: number;
    reference?: string;
    due_at: Dayjs;
}

interface Props {
    open: boolean;
    task: TaskRow | null;
    options: TaskOptions;
    assignees: TaskAssignee[];
    currentUserId: number;
    initialReference: TaskReference | null;
    onClose: () => void;
}

const toOptions = (values: Record<string, string>) =>
    Object.entries(values).map(([value, label]) => ({ value, label }));

export default function TaskModal({
    open,
    task,
    options,
    assignees,
    currentUserId,
    initialReference,
    onClose,
}: Props) {
    const [form] = Form.useForm<Values>();
    const [saving, setSaving] = useState(false);
    const [search, setSearch] = useState("");
    const [references, setReferences] = useState<TaskReference[]>([]);
    const [referenceLoading, setReferenceLoading] = useState(false);
    const [referenceError, setReferenceError] = useState(false);
    const [saveError, setSaveError] = useState("");

    useEffect(() => {
        if (!open) return;

        form.resetFields();
        setSaveError("");
        setSearch("");

        form.setFieldsValue({
            title: task?.title ?? "",
            description: task?.description ?? "",
            type: task?.type ?? "follow_up",
            priority: task?.priority ?? "normal",
            assignee_id: task?.assignee_id ?? currentUserId,
            reference: task?.reference?.value ?? initialReference?.value,
            due_at: task
                ? dayjs(task.due_at).tz(TIMEZONE)
                : dayjs().tz(TIMEZONE).add(1, "day").startOf("hour"),
        });
    }, [open, task, initialReference, currentUserId, form]);

    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();

        setReferenceLoading(true);

        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    `/tasks/references?q=${encodeURIComponent(search)}`,
                    {
                        signal: controller.signal,
                        headers: { Accept: "application/json" },
                        credentials: "same-origin",
                    },
                );

                if (!response.ok) throw new Error("Reference lookup failed");

                const data = await response.json();

                setReferences(data.options);
                setReferenceError(false);
            } catch {
                if (!controller.signal.aborted) {
                    setReferenceError(true);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setReferenceLoading(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, search]);

    const selectedReference = task?.reference ?? initialReference;

    const referenceOptions = Array.from(
        new Map(
            [
                ...(selectedReference ? [selectedReference] : []),
                ...references,
            ].map((item) => [item.value, item]),
        ).values(),
    );

    function submit(values: Values) {
        setSaveError("");

        const data = {
            ...values,
            description: values.description || null,
            reference: values.reference || null,
            due_at: values.due_at.utc().toISOString(),
            version: task?.version ?? 0,
        };

        const visitOptions = {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: onClose,
            onError: (errors: Record<string, string>) => {
                const fieldNames: Array<keyof Values> = [
                    "title",
                    "description",
                    "type",
                    "priority",
                    "assignee_id",
                    "reference",
                    "due_at",
                ];

                form.setFields(
                    fieldNames
                        .filter((name) => Boolean(errors[name]))
                        .map((name) => ({
                            name,
                            errors: [errors[name]],
                        })),
                );

                setSaveError(
                    Object.values(errors)[0] ?? "Chưa lưu được nhiệm vụ.",
                );
            },
        };

        if (task) {
            router.put(`/tasks/${task.id}`, data, visitOptions);
        } else {
            router.post("/tasks", data, visitOptions);
        }
    }

    return (
        <Modal
            title={task ? "Chỉnh sửa nhiệm vụ" : "Tạo nhiệm vụ"}
            open={open}
            width={720}
            onCancel={() => !saving && onClose()}
            closable={!saving}
            maskClosable={!saving}
            keyboard={!saving}
            footer={[
                <Button key="cancel" disabled={saving} onClick={onClose}>
                    Hủy
                </Button>,
                <Button
                    key="save"
                    type="primary"
                    loading={saving}
                    onClick={() => form.submit()}
                >
                    {task ? "Lưu thay đổi" : "Tạo nhiệm vụ"}
                </Button>,
            ]}
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={submit}
                disabled={saving}
                aria-busy={saving}
            >
                {saveError && (
                    <Alert
                        className="crm-task-form-alert"
                        type="error"
                        showIcon
                        title={saveError}
                        role="alert"
                    />
                )}

                <Form.Item
                    name="title"
                    label="Tên nhiệm vụ"
                    rules={[
                        {
                            required: true,
                            whitespace: true,
                            message: "Nhập tên nhiệm vụ.",
                        },
                    ]}
                >
                    <Input
                        maxLength={180}
                        placeholder="Ví dụ: Gọi xác nhận nhu cầu mẫu găng tay"
                    />
                </Form.Item>

                <Row gutter={16}>
                    <Col xs={24} sm={12}>
                        <Form.Item
                            name="type"
                            label="Loại nhiệm vụ"
                            rules={[{ required: true }]}
                        >
                            <Select options={toOptions(options.types)} />
                        </Form.Item>
                    </Col>

                    <Col xs={24} sm={12}>
                        <Form.Item
                            name="priority"
                            label="Mức ưu tiên"
                            rules={[{ required: true }]}
                        >
                            <Select options={toOptions(options.priorities)} />
                        </Form.Item>
                    </Col>

                    <Col xs={24} sm={12}>
                        <Form.Item
                            name="assignee_id"
                            label="Người thực hiện"
                            rules={[{ required: true }]}
                        >
                            <Select
                                showSearch
                                optionFilterProp="label"
                                options={assignees.map((user) => ({
                                    value: user.id,
                                    label: user.name,
                                }))}
                            />
                        </Form.Item>
                    </Col>

                    <Col xs={24} sm={12}>
                        <Form.Item
                            name="due_at"
                            label="Hạn xử lý — giờ Việt Nam"
                            rules={[
                                { required: true, message: "Chọn hạn xử lý." },
                            ]}
                        >
                            <DatePicker
                                className="crm-task-date"
                                showTime={{ format: "HH:mm" }}
                                format="DD/MM/YYYY HH:mm"
                            />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item
                    name="reference"
                    label="Khách hàng / Lead / Cơ hội liên quan"
                    extra={
                        referenceError
                            ? "Chưa tải được hồ sơ. Hãy nhập lại từ khóa để thử lại."
                            : "Để trống nếu đây là công việc cá nhân hoặc nội bộ."
                    }
                >
                    <Select
                        showSearch
                        allowClear
                        filterOption={false}
                        loading={referenceLoading}
                        onSearch={setSearch}
                        options={referenceOptions}
                        placeholder="Nhập tên hoặc mã khách hàng để tìm"
                        notFoundContent={
                            referenceLoading
                                ? "Đang tìm..."
                                : "Không có hồ sơ phù hợp"
                        }
                    />
                </Form.Item>

                <Form.Item name="description" label="Nội dung cần thực hiện">
                    <Input.TextArea
                        rows={4}
                        maxLength={5000}
                        showCount
                        placeholder="Mục tiêu, thông tin cần chuẩn bị và bước tiếp theo..."
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}
