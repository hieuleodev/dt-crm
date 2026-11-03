import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { Alert, Form, Input, Modal, Typography } from "antd";
import type { TaskRow } from "@/types/task";

export type TaskStatus = "pending" | "in_progress" | "completed" | "cancelled";

interface Props {
    task: TaskRow | null;
    status: TaskStatus;
    onClose: () => void;
}

export default function TaskStatusModal({ task, status, onClose }: Props) {
    const [form] = Form.useForm<{ note: string }>();
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        form.resetFields();
        setError("");
    }, [task?.id, status, form]);

    const reopening = Boolean(
        task && ["completed", "cancelled"].includes(task.status),
    );

    const required = reopening || ["completed", "cancelled"].includes(status);

    const title = reopening
        ? "Mở lại nhiệm vụ"
        : status === "completed"
          ? "Hoàn thành nhiệm vụ"
          : status === "cancelled"
            ? "Hủy nhiệm vụ"
            : status === "in_progress"
              ? "Bắt đầu nhiệm vụ"
              : "Chuyển về chờ thực hiện";

    function submit(values: { note: string }) {
        if (!task || saving) return;

        setError("");

        router.patch(
            `/tasks/${task.id}/status`,
            {
                status,
                note: values.note?.trim() || null,
                version: task.version,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: onClose,
                onError: (errors) => {
                    const message =
                        Object.values(errors)[0] ??
                        "Chưa cập nhật được trạng thái.";

                    setError(message);

                    if (errors.note) {
                        form.setFields([
                            { name: "note", errors: [errors.note] },
                        ]);
                    }
                },
            },
        );
    }

    return (
        <Modal
            title={title}
            open={task !== null}
            onCancel={() => !saving && onClose()}
            onOk={() => form.submit()}
            okText="Xác nhận"
            cancelText="Đóng"
            confirmLoading={saving}
            cancelButtonProps={{ disabled: saving }}
            closable={!saving}
            maskClosable={!saving}
            keyboard={!saving}
        >
            <Typography.Paragraph strong>{task?.title}</Typography.Paragraph>

            {error && (
                <Alert
                    type="error"
                    showIcon
                    title={error}
                    role="alert"
                    className="crm-task-form-alert"
                />
            )}

            <Form
                form={form}
                layout="vertical"
                disabled={saving}
                onFinish={submit}
                aria-busy={saving}
            >
                <Form.Item
                    name="note"
                    label={
                        status === "completed"
                            ? "Kết quả thực hiện"
                            : reopening || status === "cancelled"
                              ? "Lý do"
                              : "Ghi chú"
                    }
                    rules={[
                        {
                            required,
                            whitespace: true,
                            message: "Vui lòng nhập kết quả hoặc lý do.",
                        },
                    ]}
                >
                    <Input.TextArea rows={4} maxLength={5000} showCount />
                </Form.Item>
            </Form>
        </Modal>
    );
}
