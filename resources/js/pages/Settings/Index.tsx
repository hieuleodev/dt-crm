import { useState, useMemo } from "react";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import {
    Avatar,
    Badge,
    Button,
    Card,
    Col,
    Descriptions,
    Divider,
    Empty,
    Form,
    Image,
    Input,
    Modal,
    Popconfirm,
    Row,
    Select,
    Space,
    Table,
    Tabs,
    Tag,
    Tooltip,
    Typography,
    Upload,
} from "antd";
import type { ColumnsType } from "antd/es/table";
import {
    BankOutlined,
    BranchesOutlined,
    CheckCircleOutlined,
    ClockCircleOutlined,
    CreditCardOutlined,
    EditOutlined,
    FileTextOutlined,
    GlobalOutlined,
    HomeOutlined,
    IdcardOutlined,
    KeyOutlined,
    LockOutlined,
    MailOutlined,
    PhoneOutlined,
    PictureOutlined,
    PlusOutlined,
    SafetyCertificateOutlined,
    SearchOutlined,
    SettingOutlined,
    StopOutlined,
    TeamOutlined,
    UploadOutlined,
    UserOutlined,
} from "@ant-design/icons";

import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import MasterDataTab, { type MasterDataProps } from "./MasterDataTab";
import type { PageProps, UserRole } from "@/types";

const { Text, Title, Paragraph } = Typography;
const { TextArea } = Input;

interface ProfileData {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: UserRole;
    roleLabel: string;
    is_active: boolean;
    created_at: string | null;
    last_login_at: string;
}

export interface UserItem {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: UserRole;
    roleLabel: string;
    is_active: boolean;
    created_at: string | null;
    last_login_at: string;
}

export interface CompanySettingData {
    id: number;
    name: string;
    tax_id: string | null;
    hotline: string | null;
    email: string | null;
    address: string | null;
    website: string | null;
    representative: string | null;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_holder: string | null;
    logo_url: string | null;
    footer_quote_note: string | null;
}

interface Props {
    profile: ProfileData;
    users?: UserItem[];
    company?: CompanySettingData | null;
    masterData?: MasterDataProps["masterData"];
}

const ROLE_COLORS: Record<UserRole, string> = {
    admin: "red",
    manager: "blue",
    sales: "green",
};

export default function SettingsIndex({ profile, users = [], company, masterData }: Props) {
    const { auth } = usePage<PageProps>().props;
    const isManager = auth.user?.role !== "sales";
    const isAdmin = auth.user?.role === "admin";
    const [activeTab, setActiveTab] = useState("company");

    // ==========================================
    // 1. Quản lý Hồ sơ cá nhân & Đổi mật khẩu
    // ==========================================
    const profileForm = useForm({
        name: profile.name || "",
        email: profile.email || "",
        phone: profile.phone || "",
    });

    const passwordForm = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    function handleProfileSubmit() {
        profileForm.put("/settings/profile", {
            preserveScroll: true,
        });
    }

    function handlePasswordSubmit() {
        passwordForm.put("/settings/password", {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
            },
        });
    }

    // ==========================================
    // 2. Quản lý Người dùng & Nhân sự
    // ==========================================
    const [searchQuery, setSearchQuery] = useState("");
    const [roleFilter, setRoleFilter] = useState<string>("all");
    const [statusFilter, setStatusFilter] = useState<string>("all");

    // Modal Create User
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const createForm = useForm({
        name: "",
        email: "",
        phone: "",
        role: "sales" as UserRole,
        password: "",
    });

    function handleCreateUser() {
        createForm.post("/users", {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateModalOpen(false);
                createForm.reset();
            },
        });
    }

    // Modal Edit User
    const [editingUser, setEditingUser] = useState<UserItem | null>(null);
    const editForm = useForm({
        name: "",
        email: "",
        phone: "",
        role: "sales" as UserRole,
    });

    function openEditModal(user: UserItem) {
        setEditingUser(user);
        editForm.setData({
            name: user.name,
            email: user.email,
            phone: user.phone || "",
            role: user.role,
        });
        editForm.clearErrors();
    }

    function handleEditUser() {
        if (!editingUser) return;
        editForm.put(`/users/${editingUser.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingUser(null);
            },
        });
    }

    // Modal Reset Password
    const [resettingUser, setResettingUser] = useState<UserItem | null>(null);
    const resetPasswordForm = useForm({
        password: "",
    });

    function openResetPasswordModal(user: UserItem) {
        setResettingUser(user);
        resetPasswordForm.reset();
        resetPasswordForm.clearErrors();
    }

    function handleResetPassword() {
        if (!resettingUser) return;
        resetPasswordForm.post(`/users/${resettingUser.id}/reset-password`, {
            preserveScroll: true,
            onSuccess: () => {
                setResettingUser(null);
            },
        });
    }

    // Toggle status (Active / Inactive)
    function handleToggleStatus(user: UserItem) {
        router.patch(`/users/${user.id}/status`, {}, { preserveScroll: true });
    }

    // Lọc danh sách nhân viên
    const filteredUsers = useMemo(() => {
        return users.filter((u) => {
            const matchSearch =
                !searchQuery ||
                u.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                u.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (u.phone && u.phone.includes(searchQuery));

            const matchRole = roleFilter === "all" || u.role === roleFilter;
            const matchStatus =
                statusFilter === "all" ||
                (statusFilter === "active" ? u.is_active : !u.is_active);

            return matchSearch && matchRole && matchStatus;
        });
    }, [users, searchQuery, roleFilter, statusFilter]);

    const userColumns: ColumnsType<UserItem> = [
        {
            title: "Nhân sự",
            dataIndex: "name",
            key: "name",
            render: (_, record) => (
                <Space>
                    <Avatar
                        style={{
                            backgroundColor:
                                record.role === "admin"
                                    ? "#c8102e"
                                    : record.role === "manager"
                                      ? "#1677ff"
                                      : "#52c41a",
                        }}
                        icon={<UserOutlined />}
                    >
                        {record.name.charAt(0).toUpperCase()}
                    </Avatar>
                    <div>
                        <Text strong>{record.name}</Text>
                        <div style={{ fontSize: 12, color: "#888" }}>
                            Mã: #{record.id}
                        </div>
                    </div>
                </Space>
            ),
        },
        {
            title: "Email (Tài khoản)",
            dataIndex: "email",
            key: "email",
            render: (email: string) => <Text copyable>{email}</Text>,
        },
        {
            title: "Số điện thoại",
            dataIndex: "phone",
            key: "phone",
            render: (phone: string | null) =>
                phone ? (
                    <Text copyable>{phone}</Text>
                ) : (
                    <Text type="secondary">Chưa cập nhật</Text>
                ),
        },
        {
            title: "Vai trò",
            dataIndex: "role",
            key: "role",
            render: (role: UserRole, record) => (
                <Tag color={ROLE_COLORS[role] || "default"}>
                    {record.roleLabel}
                </Tag>
            ),
        },
        {
            title: "Trạng thái",
            dataIndex: "is_active",
            key: "is_active",
            render: (isActive: boolean) =>
                isActive ? (
                    <Badge status="success" text="Đang hoạt động" />
                ) : (
                    <Badge status="error" text="Đã khóa" />
                ),
        },
        {
            title: "Đăng nhập gần nhất",
            dataIndex: "last_login_at",
            key: "last_login_at",
            render: (date: string) => (
                <span style={{ fontSize: 13, color: "#666" }}>{date}</span>
            ),
        },
        {
            title: "Thao tác",
            key: "actions",
            align: "right",
            render: (_, record) => {
                const isSelf = record.id === auth.user?.id;
                const canModify = isAdmin || record.role !== "admin";

                return (
                    <Space size={8}>
                        {canModify && (
                            <Tooltip title="Chỉnh sửa thông tin">
                                <Button
                                    size="small"
                                    icon={<EditOutlined />}
                                    onClick={() => openEditModal(record)}
                                />
                            </Tooltip>
                        )}

                        {canModify && (
                            <Tooltip title="Đặt lại mật khẩu">
                                <Button
                                    size="small"
                                    icon={<KeyOutlined />}
                                    onClick={() => openResetPasswordModal(record)}
                                />
                            </Tooltip>
                        )}

                        {canModify && !isSelf && (
                            <Popconfirm
                                title={
                                    record.is_active
                                        ? "Khóa tài khoản nhân viên này?"
                                        : "Mở khóa tài khoản nhân viên này?"
                                }
                                description={
                                    record.is_active
                                        ? "Nhân viên sẽ không thể đăng nhập vào hệ thống."
                                        : "Nhân viên sẽ có thể đăng nhập lại bình thường."
                                }
                                onConfirm={() => handleToggleStatus(record)}
                                okText="Đồng ý"
                                cancelText="Hủy"
                                okButtonProps={{
                                    danger: record.is_active,
                                }}
                            >
                                <Tooltip
                                    title={
                                        record.is_active
                                            ? "Khóa tài khoản"
                                            : "Mở khóa tài khoản"
                                    }
                                >
                                    <Button
                                        size="small"
                                        danger={record.is_active}
                                        icon={
                                            record.is_active ? (
                                                <StopOutlined />
                                            ) : (
                                                <CheckCircleOutlined />
                                            )
                                        }
                                    />
                                </Tooltip>
                            </Popconfirm>
                        )}
                    </Space>
                );
            },
        },
    ];

    // ==========================================
    // 3. Quản lý Thông tin Doanh nghiệp
    // ==========================================
    const [logoPreview, setLogoPreview] = useState<string | null>(
        company?.logo_url || null,
    );

    const companyForm = useForm({
        name: company?.name || "",
        tax_id: company?.tax_id || "",
        hotline: company?.hotline || "",
        email: company?.email || "",
        address: company?.address || "",
        website: company?.website || "",
        representative: company?.representative || "",
        bank_name: company?.bank_name || "",
        bank_account_number: company?.bank_account_number || "",
        bank_account_holder: company?.bank_account_holder || "",
        footer_quote_note: company?.footer_quote_note || "",
        logo_url: company?.logo_url || "",
        logo: null as File | null,
    });

    function handleCompanySubmit() {
        companyForm.post("/settings/company", {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    const tabItems = [
        {
            key: "profile",
            label: (
                <span>
                    <UserOutlined /> Thông tin cá nhân & Mật khẩu
                </span>
            ),
            children: (
                <Row gutter={[24, 24]}>
                    {/* Cột trái: Tóm tắt thông tin tài khoản */}
                    <Col xs={24} lg={8}>
                        <Card
                            className="crm-panel"
                            bordered={false}
                            style={{ textAlign: "center" }}
                        >
                            <div style={{ marginBottom: 16 }}>
                                <Avatar
                                    size={88}
                                    icon={<UserOutlined />}
                                    style={{
                                        backgroundColor:
                                            profile.role === "admin"
                                                ? "#c8102e"
                                                : profile.role === "manager"
                                                  ? "#1677ff"
                                                  : "#52c41a",
                                    }}
                                />
                            </div>

                            <Title level={4} style={{ marginBottom: 4 }}>
                                {profile.name}
                            </Title>

                            <Space size={8} style={{ marginBottom: 16 }}>
                                <Tag color={ROLE_COLORS[profile.role] || "default"}>
                                    <SafetyCertificateOutlined /> {profile.roleLabel}
                                </Tag>
                                {profile.is_active && (
                                    <Tag color="success">
                                        <CheckCircleOutlined /> Đang hoạt động
                                    </Tag>
                                )}
                            </Space>

                            <Divider style={{ margin: "16px 0" }} />

                            <Descriptions column={1} size="small" style={{ textAlign: "left" }}>
                                <Descriptions.Item label="Mã nhân sự">
                                    <Text strong>#{profile.id}</Text>
                                </Descriptions.Item>
                                <Descriptions.Item label="Email">
                                    <Text copyable>{profile.email}</Text>
                                </Descriptions.Item>
                                <Descriptions.Item label="Số điện thoại">
                                    {profile.phone ? (
                                        <Text copyable>{profile.phone}</Text>
                                    ) : (
                                        <Text type="secondary">Chưa cập nhật</Text>
                                    )}
                                </Descriptions.Item>
                                <Descriptions.Item label="Ngày tham gia">
                                    {profile.created_at || "N/A"}
                                </Descriptions.Item>
                                <Descriptions.Item label="Đăng nhập gần nhất">
                                    <Space size={4}>
                                        <ClockCircleOutlined />
                                        <span>{profile.last_login_at}</span>
                                    </Space>
                                </Descriptions.Item>
                            </Descriptions>
                        </Card>
                    </Col>

                    {/* Cột phải: Form cập nhật thông tin và Form đổi mật khẩu */}
                    <Col xs={24} lg={16}>
                        <Space direction="vertical" size={24} style={{ width: "100%" }}>
                            {/* Card 1: Chỉnh sửa thông tin cá nhân */}
                            <Card
                                title={
                                    <Space>
                                        <IdcardOutlined style={{ color: "#c8102e" }} />
                                        <span>Cập nhật thông tin cá nhân</span>
                                    </Space>
                                }
                                bordered={false}
                                className="crm-panel"
                            >
                                <Form layout="vertical" onFinish={handleProfileSubmit}>
                                    <Row gutter={16}>
                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="Họ và tên"
                                                required
                                                validateStatus={profileForm.errors.name ? "error" : ""}
                                                help={profileForm.errors.name}
                                            >
                                                <Input
                                                    prefix={<UserOutlined style={{ color: "#999" }} />}
                                                    value={profileForm.data.name}
                                                    onChange={(e) =>
                                                        profileForm.setData("name", e.target.value)
                                                    }
                                                    size="large"
                                                />
                                            </Form.Item>
                                        </Col>

                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="Số điện thoại"
                                                validateStatus={profileForm.errors.phone ? "error" : ""}
                                                help={profileForm.errors.phone}
                                            >
                                                <Input
                                                    prefix={<PhoneOutlined style={{ color: "#999" }} />}
                                                    value={profileForm.data.phone}
                                                    onChange={(e) =>
                                                        profileForm.setData("phone", e.target.value)
                                                    }
                                                    size="large"
                                                />
                                            </Form.Item>
                                        </Col>

                                        <Col xs={24}>
                                            <Form.Item
                                                label="Địa chỉ Email (Tên đăng nhập)"
                                                required
                                                validateStatus={profileForm.errors.email ? "error" : ""}
                                                help={profileForm.errors.email}
                                            >
                                                <Input
                                                    type="email"
                                                    prefix={<MailOutlined style={{ color: "#999" }} />}
                                                    value={profileForm.data.email}
                                                    onChange={(e) =>
                                                        profileForm.setData("email", e.target.value)
                                                    }
                                                    size="large"
                                                />
                                            </Form.Item>
                                        </Col>

                                        <Col xs={24}>
                                            <Form.Item label="Vai trò / Quyền hạn">
                                                <Input
                                                    prefix={<SafetyCertificateOutlined style={{ color: "#999" }} />}
                                                    value={profile.roleLabel}
                                                    disabled
                                                    size="large"
                                                />
                                            </Form.Item>
                                        </Col>
                                    </Row>

                                    <div style={{ textAlign: "right", marginTop: 8 }}>
                                        <Button
                                            type="primary"
                                            htmlType="submit"
                                            loading={profileForm.processing}
                                            style={{ backgroundColor: "#c8102e" }}
                                            size="large"
                                        >
                                            Lưu thay đổi
                                        </Button>
                                    </div>
                                </Form>
                            </Card>

                            {/* Card 2: Đổi mật khẩu */}
                            <Card
                                title={
                                    <Space>
                                        <KeyOutlined style={{ color: "#c8102e" }} />
                                        <span>Đổi mật khẩu đăng nhập</span>
                                    </Space>
                                }
                                bordered={false}
                                className="crm-panel"
                            >
                                <Form layout="vertical" onFinish={handlePasswordSubmit}>
                                    <Row gutter={16}>
                                        <Col xs={24}>
                                            <Form.Item
                                                label="Mật khẩu hiện tại"
                                                required
                                                validateStatus={passwordForm.errors.current_password ? "error" : ""}
                                                help={passwordForm.errors.current_password}
                                            >
                                                <Input.Password
                                                    prefix={<LockOutlined style={{ color: "#999" }} />}
                                                    value={passwordForm.data.current_password}
                                                    onChange={(e) =>
                                                        passwordForm.setData("current_password", e.target.value)
                                                    }
                                                    size="large"
                                                />
                                            </Form.Item>
                                        </Col>

                                        <Col xs={24}>
                                            <Form.Item
                                                label="Mật khẩu mới"
                                                required
                                                validateStatus={passwordForm.errors.password ? "error" : ""}
                                                help={passwordForm.errors.password}
                                            >
                                                <Input.Password
                                                    prefix={<LockOutlined style={{ color: "#999" }} />}
                                                    value={passwordForm.data.password}
                                                    onChange={(e) =>
                                                        passwordForm.setData("password", e.target.value)
                                                    }
                                                    size="large"
                                                />
                                            </Form.Item>
                                        </Col>

                                        <Col xs={24}>
                                            <Form.Item
                                                label="Xác nhận mật khẩu mới"
                                                required
                                                validateStatus={passwordForm.errors.password_confirmation ? "error" : ""}
                                                help={passwordForm.errors.password_confirmation}
                                            >
                                                <Input.Password
                                                    prefix={<LockOutlined style={{ color: "#999" }} />}
                                                    value={passwordForm.data.password_confirmation}
                                                    onChange={(e) =>
                                                        passwordForm.setData("password_confirmation", e.target.value)
                                                    }
                                                    size="large"
                                                />
                                            </Form.Item>
                                        </Col>
                                    </Row>

                                    <div style={{ textAlign: "right", marginTop: 8 }}>
                                        <Button
                                            type="primary"
                                            htmlType="submit"
                                            loading={passwordForm.processing}
                                            style={{ backgroundColor: "#c8102e" }}
                                            size="large"
                                        >
                                            Cập nhật mật khẩu
                                        </Button>
                                    </div>
                                </Form>
                            </Card>
                        </Space>
                    </Col>
                </Row>
            ),
        },
        ...(isManager
            ? [
                  {
                      key: "users",
                      label: (
                          <Space>
                              <TeamOutlined /> Quản lý người dùng ({users.length})
                          </Space>
                      ),
                      children: (
                          <Card className="crm-panel" bordered={false}>
                              {/* Thanh công cụ tìm kiếm và lọc */}
                              <Row
                                  gutter={[16, 16]}
                                  align="middle"
                                  justify="space-between"
                                  style={{ marginBottom: 20 }}
                              >
                                  <Col xs={24} md={16}>
                                      <Space wrap size={12}>
                                          <Input
                                              placeholder="Tìm theo tên, email, SĐT..."
                                              prefix={<SearchOutlined style={{ color: "#999" }} />}
                                              value={searchQuery}
                                              onChange={(e) => setSearchQuery(e.target.value)}
                                              allowClear
                                              style={{ width: 260 }}
                                              size="large"
                                          />

                                          <Select
                                              value={roleFilter}
                                              onChange={setRoleFilter}
                                              style={{ width: 170 }}
                                              size="large"
                                              options={[
                                                  { value: "all", label: "Tất cả vai trò" },
                                                  { value: "admin", label: "Quản trị viên" },
                                                  { value: "manager", label: "Quản lý" },
                                                  { value: "sales", label: "Kinh doanh (Sales)" },
                                              ]}
                                          />

                                          <Select
                                              value={statusFilter}
                                              onChange={setStatusFilter}
                                              style={{ width: 170 }}
                                              size="large"
                                              options={[
                                                  { value: "all", label: "Tất cả trạng thái" },
                                                  { value: "active", label: "Đang hoạt động" },
                                                  { value: "inactive", label: "Đã khóa" },
                                              ]}
                                          />
                                      </Space>
                                  </Col>

                                  <Col xs={24} md={8} style={{ textAlign: "right" }}>
                                      <Button
                                          type="primary"
                                          icon={<PlusOutlined />}
                                          style={{ backgroundColor: "#c8102e" }}
                                          size="large"
                                          onClick={() => {
                                              createForm.reset();
                                              createForm.clearErrors();
                                              setIsCreateModalOpen(true);
                                          }}
                                      >
                                          Thêm nhân viên
                                      </Button>
                                  </Col>
                              </Row>

                              {/* Bảng danh sách người dùng */}
                              <Table
                                  columns={userColumns}
                                  dataSource={filteredUsers}
                                  rowKey="id"
                                  pagination={{ pageSize: 10, showSizeChanger: true }}
                                  locale={{
                                      emptyText: (
                                          <Empty
                                              image={Empty.PRESENTED_IMAGE_SIMPLE}
                                              description="Không tìm thấy nhân sự phù hợp"
                                          />
                                      ),
                                  }}
                              />
                          </Card>
                      ),
                  },
                  {
                      key: "company",
                      label: (
                          <Space>
                              <BankOutlined /> Thông tin doanh nghiệp
                          </Space>
                      ),
                      children: (
                          <Row gutter={[24, 24]}>
                              {/* Cột trái: Thẻ Xem trước Nhận diện & Mẫu Báo giá */}
                              <Col xs={24} xl={9}>
                                  <Space direction="vertical" size={24} style={{ width: "100%" }}>
                                      {/* Thẻ Logo & Nhận diện */}
                                      <Card
                                          className="crm-panel"
                                          bordered={false}
                                          style={{ textAlign: "center" }}
                                      >
                                          <div
                                              style={{
                                                  width: "100%",
                                                  minHeight: 120,
                                                  display: "flex",
                                                  alignItems: "center",
                                                  justifyContent: "center",
                                                  backgroundColor: "#fafafa",
                                                  borderRadius: 8,
                                                  border: "1px dashed #d9d9d9",
                                                  marginBottom: 16,
                                                  overflow: "hidden",
                                                  padding: 12,
                                              }}
                                          >
                                              {logoPreview ? (
                                                  <Image
                                                      src={logoPreview}
                                                      alt="Logo Doanh nghiệp"
                                                      style={{ maxHeight: 100, maxWidth: "100%", objectFit: "contain" }}
                                                  />
                                              ) : (
                                                  <div style={{ color: "#999" }}>
                                                      <PictureOutlined style={{ fontSize: 36, marginBottom: 8, display: "block" }} />
                                                      <Text type="secondary">Chưa tải logo công ty</Text>
                                                  </div>
                                              )}
                                          </div>

                                          <Upload
                                              accept="image/*"
                                              showUploadList={false}
                                              beforeUpload={(file) => {
                                                  companyForm.setData("logo", file);
                                                  const objectUrl = URL.createObjectURL(file);
                                                  setLogoPreview(objectUrl);
                                                  return false;
                                              }}
                                          >
                                              <Button icon={<UploadOutlined />} size="large">
                                                  {logoPreview ? "Thay đổi Logo" : "Tải lên Logo"}
                                              </Button>
                                          </Upload>

                                          <Divider style={{ margin: "16px 0" }} />

                                          <Title level={5} style={{ marginBottom: 4 }}>
                                              {companyForm.data.name || (
                                              <Text type="secondary" italic>
                                                  Chưa thiết lập tên doanh nghiệp
                                              </Text>
                                          )}
                                          </Title>

                                          <Paragraph type="secondary" style={{ fontSize: 13, marginBottom: 0 }}>
                                              MST: {companyForm.data.tax_id || "Chưa thiết lập"}
                                          </Paragraph>
                                      </Card>

                                      {/* Thẻ Mẫu xem trước Tiêu đề Báo giá / Chứng từ */}
                                      <Card
                                          title={
                                              <Space>
                                                  <FileTextOutlined style={{ color: "#c8102e" }} />
                                                  <span>Mẫu thông tin trên Báo giá & Hợp đồng</span>
                                              </Space>
                                          }
                                          bordered={false}
                                          className="crm-panel"
                                      >
                                          <Descriptions
                                              size="small"
                                              column={1}
                                              bordered
                                              styles={{
                                                  label: {
                                                      width: 120,
                                                      minWidth: 110,
                                                      whiteSpace: "nowrap",
                                                      fontWeight: 600,
                                                      backgroundColor: "#fafafa",
                                                  },
                                                  content: {
                                                      wordBreak: "break-word",
                                                  },
                                              }}
                                          >
                                              <Descriptions.Item label="Đơn vị">
                                                  {companyForm.data.name ? (
                                                      <Text strong style={{ color: "#c8102e" }}>
                                                          {companyForm.data.name}
                                                      </Text>
                                                  ) : (
                                                      <Text type="secondary">Chưa thiết lập</Text>
                                                  )}
                                              </Descriptions.Item>
                                              <Descriptions.Item label="Mã số thuế">
                                                  {companyForm.data.tax_id || <Text type="secondary">Chưa thiết lập</Text>}
                                              </Descriptions.Item>
                                              <Descriptions.Item label="Hotline">
                                                  {companyForm.data.hotline ? (
                                                      <Text copyable>{companyForm.data.hotline}</Text>
                                                  ) : (
                                                      <Text type="secondary">Chưa thiết lập</Text>
                                                  )}
                                              </Descriptions.Item>
                                              <Descriptions.Item label="Email">
                                                  {companyForm.data.email ? (
                                                      <Text copyable>{companyForm.data.email}</Text>
                                                  ) : (
                                                      <Text type="secondary">Chưa thiết lập</Text>
                                                  )}
                                              </Descriptions.Item>
                                              <Descriptions.Item label="Địa chỉ">
                                                  {companyForm.data.address || <Text type="secondary">Chưa cập nhật</Text>}
                                              </Descriptions.Item>
                                              <Descriptions.Item label="Tài khoản">
                                                  {companyForm.data.bank_account_number ? (
                                                      <div>
                                                          <div>
                                                              <strong>STK:</strong>{" "}
                                                              <Text copyable>{companyForm.data.bank_account_number}</Text>{" "}
                                                              ({companyForm.data.bank_name || "Ngân hàng"})
                                                          </div>
                                                          <div style={{ marginTop: 4 }}>
                                                              <strong>Chủ TK:</strong> {companyForm.data.bank_account_holder || "—"}
                                                          </div>
                                                      </div>
                                                  ) : (
                                                      <Text type="secondary">Chưa thiết lập</Text>
                                                  )}
                                              </Descriptions.Item>
                                          </Descriptions>
                                      </Card>
                                  </Space>
                              </Col>

                              {/* Cột phải: Form cập nhật thông tin công ty */}
                              <Col xs={24} xl={15}>
                                  <Card
                                      title={
                                          <Space>
                                              <BankOutlined style={{ color: "#c8102e" }} />
                                              <span>Cấu hình thông tin doanh nghiệp</span>
                                          </Space>
                                      }
                                      bordered={false}
                                      className="crm-panel"
                                  >
                                      <Form layout="vertical" onFinish={handleCompanySubmit}>
                                          {/* Phần 1: Pháp lý & Liên hệ */}
                                          <Divider titlePlacement="start" style={{ margin: "0 0 20px 0", fontSize: 14 }}>
                                              <IdcardOutlined /> Thông tin pháp lý & Liên hệ
                                          </Divider>

                                          <Row gutter={16}>
                                              <Col xs={24}>
                                                  <Form.Item
                                                      label="Tên doanh nghiệp đầy đủ"
                                                      validateStatus={companyForm.errors.name ? "error" : ""}
                                                      help={companyForm.errors.name}
                                                  >
                                                      <Input
                                                          prefix={<BankOutlined style={{ color: "#999" }} />}
                                                          placeholder="Nhập tên doanh nghiệp đầy đủ (để trống nếu chưa thiết lập)"
                                                          value={companyForm.data.name}
                                                          onChange={(e) => companyForm.setData("name", e.target.value)}
                                                          size="large"
                                                          allowClear
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24} md={12}>
                                                  <Form.Item
                                                      label="Mã số thuế (MST)"
                                                      validateStatus={companyForm.errors.tax_id ? "error" : ""}
                                                      help={companyForm.errors.tax_id}
                                                  >
                                                      <Input
                                                          prefix={<IdcardOutlined style={{ color: "#999" }} />}
                                                          value={companyForm.data.tax_id}
                                                          onChange={(e) => companyForm.setData("tax_id", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24} md={12}>
                                                  <Form.Item
                                                      label="Hotline / Số điện thoại liên hệ"
                                                      validateStatus={companyForm.errors.hotline ? "error" : ""}
                                                      help={companyForm.errors.hotline}
                                                  >
                                                      <Input
                                                          prefix={<PhoneOutlined style={{ color: "#999" }} />}
                                                          value={companyForm.data.hotline}
                                                          onChange={(e) => companyForm.setData("hotline", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24}>
                                                  <Form.Item
                                                      label="Email liên hệ doanh nghiệp"
                                                      validateStatus={companyForm.errors.email ? "error" : ""}
                                                      help={companyForm.errors.email}
                                                  >
                                                      <Input
                                                          type="email"
                                                          prefix={<MailOutlined style={{ color: "#999" }} />}
                                                          value={companyForm.data.email}
                                                          onChange={(e) => companyForm.setData("email", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24}>
                                                  <Form.Item
                                                      label="Website doanh nghiệp"
                                                      validateStatus={companyForm.errors.website ? "error" : ""}
                                                      help={companyForm.errors.website}
                                                  >
                                                      <Input
                                                          prefix={<GlobalOutlined style={{ color: "#999" }} />}
                                                          value={companyForm.data.website}
                                                          onChange={(e) => companyForm.setData("website", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24}>
                                                  <Form.Item
                                                      label="Địa chỉ trụ sở chính / Nhà kho"
                                                      validateStatus={companyForm.errors.address ? "error" : ""}
                                                      help={companyForm.errors.address}
                                                  >
                                                      <Input
                                                          prefix={<HomeOutlined style={{ color: "#999" }} />}
                                                          value={companyForm.data.address}
                                                          onChange={(e) => companyForm.setData("address", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24}>
                                                  <Form.Item
                                                      label="Người đại diện pháp luật / Giám đốc"
                                                      validateStatus={companyForm.errors.representative ? "error" : ""}
                                                      help={companyForm.errors.representative}
                                                  >
                                                      <Input
                                                          prefix={<UserOutlined style={{ color: "#999" }} />}
                                                          value={companyForm.data.representative}
                                                          onChange={(e) => companyForm.setData("representative", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>
                                          </Row>

                                          {/* Phần 2: Tài khoản thanh toán ngân hàng */}
                                          <Divider titlePlacement="start" style={{ margin: "24px 0 20px 0", fontSize: 14 }}>
                                              <CreditCardOutlined /> Thông tin Tài khoản Ngân hàng
                                          </Divider>

                                          <Row gutter={16}>
                                              <Col xs={24} md={12}>
                                                  <Form.Item
                                                      label="Ngân hàng"
                                                      validateStatus={companyForm.errors.bank_name ? "error" : ""}
                                                      help={companyForm.errors.bank_name}
                                                  >
                                                      <Input
                                                          value={companyForm.data.bank_name}
                                                          onChange={(e) => companyForm.setData("bank_name", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24} md={12}>
                                                  <Form.Item
                                                      label="Số tài khoản"
                                                      validateStatus={companyForm.errors.bank_account_number ? "error" : ""}
                                                      help={companyForm.errors.bank_account_number}
                                                  >
                                                      <Input
                                                          value={companyForm.data.bank_account_number}
                                                          onChange={(e) => companyForm.setData("bank_account_number", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>

                                              <Col xs={24}>
                                                  <Form.Item
                                                      label="Tên chủ tài khoản"
                                                      validateStatus={companyForm.errors.bank_account_holder ? "error" : ""}
                                                      help={companyForm.errors.bank_account_holder}
                                                  >
                                                      <Input
                                                          value={companyForm.data.bank_account_holder}
                                                          onChange={(e) => companyForm.setData("bank_account_holder", e.target.value)}
                                                          size="large"
                                                      />
                                                  </Form.Item>
                                              </Col>
                                          </Row>

                                          {/* Phần 3: Lời nhắn & Điều khoản chân trang Báo giá */}
                                          <Divider titlePlacement="start" style={{ margin: "24px 0 20px 0", fontSize: 14 }}>
                                              <FileTextOutlined /> Ghi chú chân trang Báo giá & Hợp đồng
                                          </Divider>

                                          <Row gutter={16}>
                                              <Col xs={24}>
                                                  <Form.Item
                                                      label="Điều khoản / Cam kết mặc định"
                                                      validateStatus={companyForm.errors.footer_quote_note ? "error" : ""}
                                                      help={companyForm.errors.footer_quote_note}
                                                  >
                                                      <TextArea
                                                          rows={4}
                                                          value={companyForm.data.footer_quote_note}
                                                          onChange={(e) => companyForm.setData("footer_quote_note", e.target.value)}
                                                      />
                                                  </Form.Item>
                                              </Col>
                                          </Row>

                                          <div style={{ textAlign: "right", marginTop: 20 }}>
                                              <Button
                                                  type="primary"
                                                  htmlType="submit"
                                                  loading={companyForm.processing}
                                                  style={{ backgroundColor: "#c8102e" }}
                                                  size="large"
                                              >
                                                  Lưu thông tin doanh nghiệp
                                              </Button>
                                          </div>
                                      </Form>
                                  </Card>
                              </Col>
                          </Row>
                      ),
                  },
                  {
                      key: "master-data",
                      label: (
                          <Space>
                              <BranchesOutlined /> Danh mục & Quy trình CRM
                          </Space>
                      ),
                      children: <MasterDataTab masterData={masterData} />,
                  },
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title="Cài đặt hệ thống & Tài khoản" />

            <PageHeader
                title="Cài đặt & Tài khoản"
                subtitle="Quản lý thông tin tài khoản cá nhân, bảo mật mật khẩu và các thiết lập hệ thống CRM"
            />

            <div style={{ marginTop: 24 }}>
                <Tabs
                    activeKey={activeTab}
                    onChange={setActiveTab}
                    items={tabItems}
                    size="large"
                />
            </div>

            {/* ========================================== */}
            {/* Modal 1: Thêm nhân viên mới                */}
            {/* ========================================== */}
            <Modal
                title={
                    <Space>
                        <UserOutlined style={{ color: "#c8102e" }} />
                        <span>Thêm tài khoản nhân viên mới</span>
                    </Space>
                }
                open={isCreateModalOpen}
                onCancel={() => setIsCreateModalOpen(false)}
                footer={null}
                destroyOnClose
            >
                <Form layout="vertical" onFinish={handleCreateUser} style={{ marginTop: 16 }}>
                    <Form.Item
                        label="Họ và tên"
                        required
                        validateStatus={createForm.errors.name ? "error" : ""}
                        help={createForm.errors.name}
                    >
                        <Input
                            prefix={<UserOutlined style={{ color: "#999" }} />}
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData("name", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <Form.Item
                        label="Email đăng nhập"
                        required
                        validateStatus={createForm.errors.email ? "error" : ""}
                        help={createForm.errors.email}
                    >
                        <Input
                            type="email"
                            prefix={<MailOutlined style={{ color: "#999" }} />}
                            value={createForm.data.email}
                            onChange={(e) => createForm.setData("email", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <Form.Item
                        label="Số điện thoại"
                        validateStatus={createForm.errors.phone ? "error" : ""}
                        help={createForm.errors.phone}
                    >
                        <Input
                            prefix={<PhoneOutlined style={{ color: "#999" }} />}
                            value={createForm.data.phone}
                            onChange={(e) => createForm.setData("phone", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <Form.Item
                        label="Vai trò / Chức vụ"
                        required
                        validateStatus={createForm.errors.role ? "error" : ""}
                        help={createForm.errors.role}
                    >
                        <Select
                            value={createForm.data.role}
                            onChange={(val) => createForm.setData("role", val)}
                            size="large"
                            options={[
                                { value: "sales", label: "Nhân viên kinh doanh (Sales)" },
                                { value: "manager", label: "Quản lý (Manager)" },
                                ...(isAdmin ? [{ value: "admin", label: "Quản trị viên (Admin)" }] : []),
                            ]}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Mật khẩu khởi tạo"
                        required
                        validateStatus={createForm.errors.password ? "error" : ""}
                        help={createForm.errors.password}
                    >
                        <Input.Password
                            prefix={<LockOutlined style={{ color: "#999" }} />}
                            placeholder="Tối thiểu 8 ký tự"
                            value={createForm.data.password}
                            onChange={(e) => createForm.setData("password", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <div style={{ textAlign: "right", marginTop: 24 }}>
                        <Space>
                            <Button onClick={() => setIsCreateModalOpen(false)} size="large">
                                Hủy
                            </Button>
                            <Button
                                type="primary"
                                htmlType="submit"
                                loading={createForm.processing}
                                style={{ backgroundColor: "#c8102e" }}
                                size="large"
                            >
                                Tạo tài khoản
                            </Button>
                        </Space>
                    </div>
                </Form>
            </Modal>

            {/* ========================================== */}
            {/* Modal 2: Chỉnh sửa thông tin nhân viên     */}
            {/* ========================================== */}
            <Modal
                title={
                    <Space>
                        <EditOutlined style={{ color: "#c8102e" }} />
                        <span>Chỉnh sửa thông tin nhân sự</span>
                    </Space>
                }
                open={!!editingUser}
                onCancel={() => setEditingUser(null)}
                footer={null}
                destroyOnClose
            >
                <Form layout="vertical" onFinish={handleEditUser} style={{ marginTop: 16 }}>
                    <Form.Item
                        label="Họ và tên"
                        required
                        validateStatus={editForm.errors.name ? "error" : ""}
                        help={editForm.errors.name}
                    >
                        <Input
                            prefix={<UserOutlined style={{ color: "#999" }} />}
                            value={editForm.data.name}
                            onChange={(e) => editForm.setData("name", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <Form.Item
                        label="Email đăng nhập"
                        required
                        validateStatus={editForm.errors.email ? "error" : ""}
                        help={editForm.errors.email}
                    >
                        <Input
                            type="email"
                            prefix={<MailOutlined style={{ color: "#999" }} />}
                            value={editForm.data.email}
                            onChange={(e) => editForm.setData("email", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <Form.Item
                        label="Số điện thoại"
                        validateStatus={editForm.errors.phone ? "error" : ""}
                        help={editForm.errors.phone}
                    >
                        <Input
                            prefix={<PhoneOutlined style={{ color: "#999" }} />}
                            value={editForm.data.phone}
                            onChange={(e) => editForm.setData("phone", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <Form.Item
                        label="Vai trò / Chức vụ"
                        required
                        validateStatus={editForm.errors.role ? "error" : ""}
                        help={editForm.errors.role}
                    >
                        <Select
                            value={editForm.data.role}
                            onChange={(val) => editForm.setData("role", val)}
                            size="large"
                            options={[
                                { value: "sales", label: "Nhân viên kinh doanh (Sales)" },
                                { value: "manager", label: "Quản lý (Manager)" },
                                ...(isAdmin ? [{ value: "admin", label: "Quản trị viên (Admin)" }] : []),
                            ]}
                        />
                    </Form.Item>

                    <div style={{ textAlign: "right", marginTop: 24 }}>
                        <Space>
                            <Button onClick={() => setEditingUser(null)} size="large">
                                Hủy
                            </Button>
                            <Button
                                type="primary"
                                htmlType="submit"
                                loading={editForm.processing}
                                style={{ backgroundColor: "#c8102e" }}
                                size="large"
                            >
                                Lưu thay đổi
                            </Button>
                        </Space>
                    </div>
                </Form>
            </Modal>

            {/* ========================================== */}
            {/* Modal 3: Đặt lại mật khẩu nhân viên        */}
            {/* ========================================== */}
            <Modal
                title={
                    <Space>
                        <KeyOutlined style={{ color: "#c8102e" }} />
                        <span>Đặt lại mật khẩu cho {resettingUser?.name}</span>
                    </Space>
                }
                open={!!resettingUser}
                onCancel={() => setResettingUser(null)}
                footer={null}
                destroyOnClose
            >
                <Form layout="vertical" onFinish={handleResetPassword} style={{ marginTop: 16 }}>
                    <Paragraph type="secondary">
                        Nhập mật khẩu mới cho tài khoản <strong>{resettingUser?.email}</strong>. Nhân viên sẽ sử dụng mật khẩu này cho lần đăng nhập tiếp theo.
                    </Paragraph>

                    <Form.Item
                        label="Mật khẩu mới"
                        required
                        validateStatus={resetPasswordForm.errors.password ? "error" : ""}
                        help={resetPasswordForm.errors.password}
                    >
                        <Input.Password
                            prefix={<LockOutlined style={{ color: "#999" }} />}
                            placeholder="Tối thiểu 8 ký tự"
                            value={resetPasswordForm.data.password}
                            onChange={(e) => resetPasswordForm.setData("password", e.target.value)}
                            size="large"
                        />
                    </Form.Item>

                    <div style={{ textAlign: "right", marginTop: 24 }}>
                        <Space>
                            <Button onClick={() => setResettingUser(null)} size="large">
                                Hủy
                            </Button>
                            <Button
                                type="primary"
                                htmlType="submit"
                                loading={resetPasswordForm.processing}
                                style={{ backgroundColor: "#c8102e" }}
                                size="large"
                            >
                                Cấp mật khẩu mới
                            </Button>
                        </Space>
                    </div>
                </Form>
            </Modal>
        </AppLayout>
    );
}
