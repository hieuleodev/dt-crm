import { useEffect, useRef, useState } from "react";
import { Link, useForm } from "@inertiajs/react";
import {
    Alert,
    Button,
    DatePicker,
    Empty,
    Form,
    Input,
    Pagination,
    Select,
    Spin,
    Switch,
    Tag,
} from "antd";
import { BellOutlined, PlusOutlined, ReloadOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import type { Paginated } from "@/types";

dayjs.extend(utc);
dayjs.extend(timezone);

const TIMEZONE = "Asia/Ho_Chi_Minh";

interface NoteItem {
    id: number;
    body: string;
    created_at: string;
    author: string;
    task: {
        id: number;
        title: string;
        due_at: string;
        assignee: string;
        status: string;
        status_label: string;
        overdue: boolean;
    } | null;
}

interface NotesResponse {
    notes: Paginated<NoteItem>;
    assignees: {
        id: number;
        name: string;
    }[];
}

interface NoteForm {
    request_id: string;
    body: string;
    create_reminder: boolean;
    reminder_title: string;
    reminder_due_at: string;
    assignee_id: number;
}

interface Props {
    customerId: number;
    currentUserId: number;
    canCreate: boolean;
    canCreateReminder: boolean;
}

const formatTime = (value: string) =>
    dayjs(value).tz(TIMEZONE).format("DD/MM/YYYY HH:mm");

export default function CustomerNotes({
    customerId,
    currentUserId,
    canCreate,
    canCreateReminder,
}: Props) {
    const [page, setPage] = useState(1);
    const [refresh, setRefresh] = useState(0);
    const [response, setResponse] = useState<NotesResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState("");
    const [composerOpen, setComposerOpen] = useState(false);

    const pending = useRef(false);
    const [initialRequestId] = useState(() => crypto.randomUUID());

    const form = useForm<NoteForm>({
        request_id: initialRequestId,
        body: "",
        create_reminder: false,
        reminder_title: "Nhắc chăm sóc khách hàng",
        reminder_due_at: "",
        assignee_id: currentUserId,
    });

    useEffect(() => {
        const controller = new AbortController();

        async function loadNotes() {
            setLoading(true);
            setLoadError("");

            try {
                const result = await fetch(
                    `/customers/${customerId}/notes?page=${page}`,
                    {
                        signal: controller.signal,
                        credentials: "same-origin",
                        headers: { Accept: "application/json" },
                    },
                );

                if (!result.ok) {
                    throw new Error("Failed to load notes");
                }

                const data: NotesResponse = await result.json();

                if (!controller.signal.aborted) {
                    setResponse(data);
                }
            } catch {
                if (!controller.signal.aborted) {
                    setLoadError("Không tải được ghi chú. Vui lòng thử lại.");
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        loadNotes();

        return () => controller.abort();
    }, [customerId, page, refresh]);

    function save() {
        if (pending.current || form.processing) return;

        pending.current = true;
        form.clearErrors();

        form.post(`/customers/${customerId}/notes`, {
            preserveScroll: true,

            onSuccess: () => {
                form.setData({
                    request_id: crypto.randomUUID(),
                    body: "",
                    create_reminder: false,
                    reminder_title: "Nhắc chăm sóc khách hàng",
                    reminder_due_at: "",
                    assignee_id: currentUserId,
                });

                form.clearErrors();
                setComposerOpen(false);
                setPage(1);
                setRefresh((value) => value + 1);
            },

            onFinish: () => {
                pending.current = false;
            },
        });
    }

    const notes = response?.notes.data ?? [];

    const serverErrors = form.errors as Record<string, string | undefined>;

    const generalErrors = [
        serverErrors.request_id,
        serverErrors.reference,
        serverErrors.title,
    ].filter((value): value is string => Boolean(value));

    return (
        <section className="crm-customer-notes">
            <div className="crm-customer-notes__toolbar">
                <div>
                    <h2>Ghi chú khách hàng</h2>
                    <p>
                        {canCreateReminder
                            ? "Lưu lịch sử trao đổi và đặt việc cần theo dõi."
                            : "Lưu lịch sử trao đổi với khách hàng."}
                    </p>
                </div>

                <div className="crm-customer-notes__actions">
                    <Button
                        icon={<ReloadOutlined />}
                        loading={loading}
                        onClick={() => setRefresh((value) => value + 1)}
                    >
                        Cập nhật
                    </Button>

                    {canCreate && (
                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            disabled={form.processing}
                            onClick={() => setComposerOpen((value) => !value)}
                        >
                            {composerOpen ? "Đóng form" : "Thêm ghi chú"}
                        </Button>
                    )}
                </div>
            </div>

            {composerOpen && canCreate && (
                <Form
                    layout="vertical"
                    className="crm-customer-notes__composer"
                    onFinish={save}
                    disabled={form.processing}
                    aria-busy={form.processing}
                >
                    {generalErrors.length > 0 && (
                        <Alert
                            type="error"
                            showIcon
                            title={generalErrors.join(" ")}
                            role="alert"
                        />
                    )}

                    <Form.Item
                        label="Nội dung ghi chú"
                        htmlFor="customer-note-body"
                        required
                        validateStatus={form.errors.body ? "error" : undefined}
                        help={form.errors.body}
                    >
                        <Input.TextArea
                            id="customer-note-body"
                            rows={4}
                            maxLength={5000}
                            showCount
                            placeholder="Ví dụ: Khách yêu cầu gọi lại để xác nhận số lượng..."
                            value={form.data.body}
                            aria-invalid={Boolean(form.errors.body)}
                            onChange={(event) =>
                                form.setData("body", event.target.value)
                            }
                        />
                    </Form.Item>

                    {canCreateReminder && (
                        <Form.Item label="Tạo nhiệm vụ nhắc việc">
                            <Switch
                                checked={form.data.create_reminder}
                                checkedChildren="Có"
                                unCheckedChildren="Không"
                                onChange={(checked) =>
                                    form.setData("create_reminder", checked)
                                }
                            />
                        </Form.Item>
                    )}

                    {canCreateReminder && form.data.create_reminder && (
                        <div className="crm-customer-notes__reminder-form">
                            <Form.Item
                                label="Tên việc cần nhắc"
                                htmlFor="customer-note-reminder-title"
                                required
                                validateStatus={
                                    form.errors.reminder_title
                                        ? "error"
                                        : undefined
                                }
                                help={form.errors.reminder_title}
                            >
                                <Input
                                    id="customer-note-reminder-title"
                                    maxLength={180}
                                    value={form.data.reminder_title}
                                    onChange={(event) =>
                                        form.setData(
                                            "reminder_title",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>

                            <Form.Item
                                label="Thời gian nhắc — giờ Việt Nam"
                                required
                                validateStatus={
                                    form.errors.reminder_due_at
                                        ? "error"
                                        : undefined
                                }
                                help={form.errors.reminder_due_at}
                            >
                                <DatePicker
                                    aria-label="Thời gian nhắc"
                                    className="crm-customer-notes__date"
                                    showTime={{ format: "HH:mm" }}
                                    format="DD/MM/YYYY HH:mm"
                                    value={
                                        form.data.reminder_due_at
                                            ? dayjs(
                                                  form.data.reminder_due_at,
                                              ).tz(TIMEZONE)
                                            : null
                                    }
                                    onChange={(value) =>
                                        form.setData(
                                            "reminder_due_at",
                                            value
                                                ? value.utc().toISOString()
                                                : "",
                                        )
                                    }
                                />
                            </Form.Item>

                            <Form.Item
                                label="Người thực hiện"
                                required
                                validateStatus={
                                    form.errors.assignee_id
                                        ? "error"
                                        : undefined
                                }
                                help={form.errors.assignee_id}
                            >
                                <Select
                                    aria-label="Người thực hiện"
                                    showSearch
                                    optionFilterProp="label"
                                    value={form.data.assignee_id}
                                    options={(response?.assignees ?? []).map(
                                        (user) => ({
                                            value: user.id,
                                            label: user.name,
                                        }),
                                    )}
                                    onChange={(value) =>
                                        form.setData("assignee_id", value)
                                    }
                                />
                            </Form.Item>
                        </div>
                    )}

                    <div className="crm-customer-notes__actions">
                        <Button
                            disabled={form.processing}
                            onClick={() => setComposerOpen(false)}
                        >
                            Đóng
                        </Button>

                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={form.processing}
                        >
                            {form.data.create_reminder
                                ? "Lưu ghi chú & tạo nhắc việc"
                                : "Lưu ghi chú"}
                        </Button>
                    </div>
                </Form>
            )}

            {loadError && (
                <Alert type="error" showIcon title={loadError} role="alert" />
            )}

            <Spin spinning={loading}>
                {!loading && !loadError && notes.length === 0 && (
                    <Empty description="Chưa có ghi chú cho khách hàng này." />
                )}

                <ol className="crm-customer-notes__list">
                    {notes.map((note) => (
                        <li key={note.id}>
                            <article className="crm-customer-notes__item">
                                <header className="crm-customer-notes__meta">
                                    <strong>{note.author}</strong>

                                    <time dateTime={note.created_at}>
                                        {formatTime(note.created_at)}
                                    </time>
                                </header>

                                <div className="crm-customer-notes__body">
                                    {note.body}
                                </div>

                                {note.task && (
                                    <div className="crm-customer-notes__reminder">
                                        <div className="crm-customer-notes__reminder-heading">
                                            <BellOutlined aria-hidden="true" />

                                            <strong>{note.task.title}</strong>

                                            <Tag
                                                color={
                                                    note.task.status ===
                                                    "completed"
                                                        ? "green"
                                                        : note.task.overdue
                                                          ? "red"
                                                          : "default"
                                                }
                                            >
                                                {note.task.status_label}
                                            </Tag>

                                            {note.task.overdue && (
                                                <Tag color="red">Quá hạn</Tag>
                                            )}
                                        </div>

                                        <p>
                                            Nhắc lúc{" "}
                                            {formatTime(note.task.due_at)}
                                            {" · "}
                                            {note.task.assignee}
                                        </p>

                                        <Link
                                            href={`/tasks?customer_id=${customerId}&bucket=all`}
                                        >
                                            Xem nhiệm vụ của khách hàng
                                        </Link>
                                    </div>
                                )}
                            </article>
                        </li>
                    ))}
                </ol>

                {response && (
                    <Pagination
                        current={response.notes.current_page}
                        pageSize={response.notes.per_page}
                        total={response.notes.total}
                        showSizeChanger={false}
                        hideOnSinglePage
                        disabled={loading}
                        onChange={setPage}
                    />
                )}
            </Spin>
        </section>
    );
}
