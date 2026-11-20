import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import {
    Alert,
    App,
    Button,
    Card,
    Col,
    Form,
    Input,
    Modal,
    Row,
    Select,
    Switch,
    Table,
    Tag,
    Typography,
} from "antd";
import type { TableColumnsType } from "antd";
import { EditOutlined, PlusOutlined } from "@ant-design/icons";
import AppLayout from "@/layouts/AppLayout";
import type { Paginated } from "@/types";

interface StaffUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    role_label: string;
    is_active: boolean;
}

interface Filters {
    q: string;
    role: string;
    status: string;
}

interface AccountForm {
    name: string;
    email: string;
    phone: string;
    role: string;
    is_active: boolean;
    password: string;
    password_confirmation: string;
    status_reason: string;
}

interface Props {
    users: Paginated<StaffUser>;
    filters: Filters;
    roles: { value: string; label: string }[];
}

const EMPTY_FORM: AccountForm = {
    name: "",
    email: "",
    phone: "",
    role: "sales",
    is_active: true,
    password: "",
    password_confirmation: "",
    status_reason: "",
};

export default function Index({ users, filters, roles }: Props) {
    const { message } = App.useApp();
    const [editorOpen, setEditorOpen] = useState(false);
    const [editing, setEditing] = useState<StaffUser | null>(null);
    const [search, setSearch] = useState(filters.q);
    const form = useForm<AccountForm>({ ...EMPTY_FORM });

    // Giữ các bộ lọc hiện tại khi đổi trang và quay về trang đầu khi đổi bộ lọc.
    function visit(next: Partial<Filters> = {}, page = 1) {
        const values = { ...filters, ...next };
        const query: Record<string, string | number> = { page };

        for (const [key, value] of Object.entries(values)) {
            if (value) query[key] = value;
        }

        router.get("/users", query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onError: () => message.error("Không thể tải danh sách tài khoản."),
        });
    }

    function openCreate() {
        setEditing(null);
        form.clearErrors();
        form.setData({
            ...EMPTY_FORM,
        });
        setEditorOpen(true);
    }

    // Để trống mật khẩu khi sửa hồ sơ. Chỉ nhập mật khẩu mới khi cần đổi.
    function openEdit(user: StaffUser) {
        setEditing(user);
        form.clearErrors();
        form.setData({
            name: user.name,
            email: user.email,
            phone: user.phone ?? "",
            role: user.role,
            is_active: user.is_active,
            password: "",
            password_confirmation: "",
            status_reason: "",
        });
        setEditorOpen(true);
    }

    function closeEditor() {
        if (!form.processing) setEditorOpen(false);
    }

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: () => setEditorOpen(false),
            onError: () => message.error("Vui lòng kiểm tra lại thông tin."),
        };

        if (editing) {
            form.put(`/users/${editing.id}`, options);
        } else {
            form.post("/users", options);
        }
    }

    const columns: TableColumnsType<StaffUser> = [
        { title: "ID", dataIndex: "id", width: 72 },
        {
            title: "Tài khoản & họ tên",
            key: "account",
            width: 230,
            render: (_, user) => (
                <div>
                    <Typography.Text strong>{user.email}</Typography.Text>
                    <br />
                    <Typography.Text type="secondary">
                        {user.name}
                    </Typography.Text>
                </div>
            ),
        },
        {
            title: "Liên hệ",
            key: "contact",
            width: 190,
            render: (_, user) => (
                <div>
                    <div>{user.phone || "Chưa có số điện thoại"}</div>
                    <Typography.Text type="secondary">
                        {user.email}
                    </Typography.Text>
                </div>
            ),
        },
        { title: "Vai trò", dataIndex: "role_label", width: 170 },
        {
            title: "Trạng thái",
            dataIndex: "is_active",
            width: 120,
            render: (active: boolean) => (
                <Tag color={active ? "green" : "default"}>
                    {active ? "Đang hoạt động" : "Đã khóa"}
                </Tag>
            ),
        },
        {
            title: "Thao tác",
            key: "actions",
            fixed: "right",
            width: 120,
            render: (_, user) => (
                <Button
                    icon={<EditOutlined />}
                    onClick={() => openEdit(user)}
                    aria-label={`Chỉnh sửa tài khoản ${user.email}`}
                >
                    Sửa
                </Button>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Quản lý tài khoản" />

            <div className="crm-page">
                <header className="crm-page__header">
                    <div>
                        <Typography.Title level={2}>
                            Quản lý tài khoản
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Quản lý thông tin cơ bản, vai trò và trạng thái tài khoản.
                        </Typography.Text>
                    </div>
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={openCreate}
                    >
                        Tạo tài khoản
                    </Button>
                </header>

                <Card>
                    <form
                        className="crm-list-filters"
                        role="search"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit({ q: search.trim() });
                        }}
                    >
                        <Input
                            aria-label="Tìm tài khoản"
                            placeholder="Tìm tên, email hoặc số điện thoại"
                            value={search}
                            maxLength={180}
                            allowClear
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Select
                            aria-label="Lọc theo vai trò"
                            placeholder="Tất cả vai trò"
                            allowClear
                            value={filters.role || undefined}
                            options={roles}
                            onChange={(role) => visit({ role: role ?? "" })}
                        />
                        <Select
                            aria-label="Lọc theo trạng thái"
                            placeholder="Mọi trạng thái"
                            allowClear
                            value={filters.status || undefined}
                            options={[
                                { value: "active", label: "Đang hoạt động" },
                                { value: "inactive", label: "Đã khóa" },
                            ]}
                            onChange={(status) =>
                                visit({ status: status ?? "" })
                            }
                        />
                        <Button htmlType="submit">Tìm kiếm</Button>
                    </form>

                    <Table<StaffUser>
                        rowKey="id"
                        columns={columns}
                        dataSource={users.data}
                        scroll={{ x: 900 }}
                        pagination={{
                            current: users.current_page,
                            pageSize: users.per_page,
                            total: users.total,
                            showSizeChanger: false,
                            hideOnSinglePage: true,
                            onChange: (page) => visit({}, page),
                        }}
                    />
                </Card>
            </div>

            <Modal
                title={editing ? "Chỉnh sửa tài khoản" : "Tạo tài khoản"}
                open={editorOpen}
                onCancel={closeEditor}
                width={760}
                maskClosable={!form.processing}
                footer={[
                    <Button
                        key="cancel"
                        onClick={closeEditor}
                        disabled={form.processing}
                    >
                        Hủy
                    </Button>,
                    <Button
                        key="save"
                        type="primary"
                        loading={form.processing}
                        onClick={submit}
                    >
                        Lưu tài khoản
                    </Button>,
                ]}
            >
                <Form
                    layout="vertical"
                    onFinish={submit}
                    disabled={form.processing}
                >
                    {Object.keys(form.errors).length > 0 && (
                        <Alert
                            type="error"
                            showIcon
                            title="Chưa lưu được thông tin"
                            description="Vui lòng kiểm tra các trường được đánh dấu."
                            role="alert"
                        />
                    )}

                    <Row gutter={16}>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                label="Họ và tên"
                                validateStatus={form.errors.name ? "error" : ""}
                                help={form.errors.name}
                                required
                            >
                                <Input
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData("name", event.target.value)
                                    }
                                    maxLength={255}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                label="Email đăng nhập"
                                validateStatus={form.errors.email ? "error" : ""}
                                help={form.errors.email}
                                required
                            >
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData("email", event.target.value)
                                    }
                                    maxLength={255}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item label="Số điện thoại">
                                <Input
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData("phone", event.target.value)
                                    }
                                    maxLength={20}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                label="Vai trò"
                                validateStatus={form.errors.role ? "error" : ""}
                                help={form.errors.role}
                                required
                            >
                                <Select
                                    value={form.data.role}
                                    options={roles}
                                    onChange={(role) =>
                                        form.setData("role", role)
                                    }
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                label={editing ? "Mật khẩu mới (nếu đổi)" : "Mật khẩu"}
                                validateStatus={
                                    form.errors.password ? "error" : ""
                                }
                                help={form.errors.password}
                                required={!editing}
                            >
                                <Input.Password
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            "password",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                label="Xác nhận mật khẩu"
                                validateStatus={
                                    form.errors.password_confirmation
                                        ? "error"
                                        : ""
                                }
                                help={form.errors.password_confirmation}
                                required={!editing}
                            >
                                <Input.Password
                                    autoComplete="new-password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) =>
                                        form.setData(
                                            "password_confirmation",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item label="Trạng thái tài khoản">
                        <Switch
                            checked={form.data.is_active}
                            checkedChildren="Hoạt động"
                            unCheckedChildren="Đã khóa"
                            onChange={(isActive) =>
                                form.setData("is_active", isActive)
                            }
                        />
                        {form.errors.is_active && (
                            <Typography.Text type="danger">
                                {form.errors.is_active}
                            </Typography.Text>
                        )}
                    </Form.Item>

                    {editing && (
                        <Form.Item
                            label="Lý do thay đổi trạng thái"
                            validateStatus={
                                form.errors.status_reason ? "error" : ""
                            }
                            help={form.errors.status_reason}
                        >
                            <Input
                                value={form.data.status_reason}
                                onChange={(event) =>
                                    form.setData(
                                        "status_reason",
                                        event.target.value,
                                    )
                                }
                                maxLength={255}
                                placeholder="Bắt buộc khi khóa hoặc mở lại tài khoản"
                            />
                        </Form.Item>
                    )}
                </Form>
            </Modal>
        </AppLayout>
    );
}
