import { useEffect, useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import {
    Alert,
    App,
    Button,
    Card,
    Col,
    Empty,
    Form,
    Input,
    InputNumber,
    Modal,
    Popconfirm,
    Row,
    Select,
    Space,
    Statistic,
    Table,
    Tag,
    Typography,
} from "antd";
import type { TableColumnsType } from "antd";
import {
    ArrowRightOutlined,
    EditOutlined,
    PlusOutlined,
} from "@ant-design/icons";
import AppLayout from "@/layouts/AppLayout";
import "../../../css/leads.css";

type Kind = "lead" | "opportunity";

interface Lead {
    id: number;
    title: string;
    contact_name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    kind: Kind;
    stage: string;
    estimated_value: string;
    notes: string | null;
    owner: { id: number; name: string } | null;
}

interface LeadForm {
    title: string;
    contact_name: string;
    company: string;
    email: string;
    phone: string;
    stage: string;
    estimated_value: string;
    notes: string;
}

interface Filters {
    q: string;
    kind: string;
    stage: string;
}

interface Props {
    leads: {
        data: Lead[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: Filters;
    stages: Record<Kind, Record<string, string>>;
    summary: {
        total: number;
        leads: number;
        opportunities: number;
        open_value: string;
    };
}

const EMPTY_FORM: LeadForm = {
    title: "",
    contact_name: "",
    company: "",
    email: "",
    phone: "",
    stage: "new",
    estimated_value: "0",
    notes: "",
};

const money = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
});

export default function Index({ leads, filters, stages, summary }: Props) {
    const { message } = App.useApp();

    const [search, setSearch] = useState(filters.q);
    const [loading, setLoading] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Lead | null>(null);
    const [converting, setConverting] = useState<number | null>(null);

    const form = useForm<LeadForm>({ ...EMPTY_FORM });

    useEffect(() => {
        setSearch(filters.q);
    }, [filters.q]);

    const editingKind: Kind = editing?.kind ?? "lead";

    const stageOptions = Object.entries(
        filters.kind
            ? stages[filters.kind as Kind]
            : { ...stages.lead, ...stages.opportunity },
    ).map(([value, label]) => ({ value, label }));

    function visit(next: Partial<Filters> = {}, page = 1) {
        const values = { ...filters, ...next };
        const query: Record<string, string | number> = { page };

        for (const [key, value] of Object.entries(values)) {
            if (value) query[key] = value;
        }

        router.get("/leads", query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
            onError: () => {
                message.error("Không thể tải danh sách. Vui lòng thử lại.");
            },
        });
    }

    function openCreate() {
        form.clearErrors();
        form.setData({ ...EMPTY_FORM });
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(lead: Lead) {
        form.clearErrors();

        form.setData({
            title: lead.title,
            contact_name: lead.contact_name,
            company: lead.company ?? "",
            email: lead.email ?? "",
            phone: lead.phone ?? "",
            stage: lead.stage,
            estimated_value: lead.estimated_value,
            notes: lead.notes ?? "",
        });

        setEditing(lead);
        setModalOpen(true);
    }

    function closeModal() {
        if (!form.processing) setModalOpen(false);
    }

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
        };

        if (editing) {
            form.put(`/leads/${editing.id}`, options);
        } else {
            form.post("/leads", options);
        }
    }

    function convert(lead: Lead) {
        if (converting !== null) return;

        router.post(
            `/leads/${lead.id}/convert`,
            {},
            {
                preserveScroll: true,
                onStart: () => setConverting(lead.id),
                onFinish: () => setConverting(null),
                onError: () => {
                    message.error(
                        "Không thể chuyển đổi Lead. Vui lòng thử lại.",
                    );
                },
            },
        );
    }

    function field(name: keyof LeadForm, label: string) {
        return {
            label,
            htmlFor: `lead-${name}`,
            validateStatus: form.errors[name] ? ("error" as const) : undefined,
            help: form.errors[name] ? (
                <span id={`lead-${name}-error`} role="alert">
                    {form.errors[name]}
                </span>
            ) : undefined,
        };
    }

    function inputA11y(name: keyof LeadForm) {
        return {
            id: `lead-${name}`,
            "aria-invalid": Boolean(form.errors[name]),
            "aria-describedby": form.errors[name]
                ? `lead-${name}-error`
                : undefined,
        };
    }

    const columns: TableColumnsType<Lead> = [
        {
            title: "Nhu cầu / Khách hàng",
            key: "contact",
            width: 280,
            render: (_, lead) => (
                <div className="leads-contact">
                    <Typography.Text strong>{lead.title}</Typography.Text>

                    <Typography.Text>{lead.contact_name}</Typography.Text>

                    {lead.company && (
                        <Typography.Text type="secondary">
                            {lead.company}
                        </Typography.Text>
                    )}
                </div>
            ),
        },
        {
            title: "Loại / Trạng thái",
            key: "stage",
            width: 190,
            render: (_, lead) => (
                <div className="leads-contact">
                    <Typography.Text type="secondary">
                        {lead.kind === "lead" ? "Lead" : "Cơ hội"}
                    </Typography.Text>

                    <Tag className={`leads-stage leads-stage--${lead.stage}`}>
                        {stages[lead.kind][lead.stage] ?? lead.stage}
                    </Tag>
                </div>
            ),
        },
        {
            title: "Liên hệ",
            key: "details",
            width: 240,
            render: (_, lead) => (
                <div className="leads-contact">
                    <span>{lead.email || "Chưa có email"}</span>
                    <Typography.Text type="secondary">
                        {lead.phone || "Chưa có số điện thoại"}
                    </Typography.Text>
                </div>
            ),
        },
        {
            title: "Giá trị dự kiến",
            dataIndex: "estimated_value",
            key: "value",
            align: "right",
            width: 180,
            render: (value: string) => money.format(Number(value)),
        },
        {
            title: "Phụ trách",
            key: "owner",
            width: 160,
            render: (_, lead) => lead.owner?.name ?? "Không còn hoạt động",
        },
        {
            title: "Thao tác",
            key: "actions",
            width: 240,
            render: (_, lead) => (
                <Space wrap>
                    <Button
                        icon={<EditOutlined />}
                        onClick={() => openEdit(lead)}
                    >
                        Chỉnh sửa
                    </Button>

                    {lead.kind === "lead" && lead.stage === "qualified" && (
                        <Popconfirm
                            title="Chuyển thành Cơ hội?"
                            description="Thông tin và người phụ trách được giữ nguyên."
                            okText="Chuyển đổi"
                            cancelText="Hủy"
                            onConfirm={() => convert(lead)}
                            disabled={converting !== null}
                        >
                            <Button
                                icon={<ArrowRightOutlined />}
                                loading={converting === lead.id}
                                disabled={converting !== null}
                            >
                                Chuyển đổi
                            </Button>
                        </Popconfirm>
                    )}
                </Space>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Lead & Cơ hội" />

            <div className="leads-page">
                <header className="leads-heading">
                    <div>
                        <Typography.Title level={2}>
                            Lead & Cơ hội
                        </Typography.Title>
                    </div>

                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={openCreate}
                    >
                        Tạo Lead
                    </Button>
                </header>

                <Row gutter={[16, 16]}>
                    {[
                        { title: "Tổng bản ghi", value: summary.total },
                        { title: "Lead", value: summary.leads },
                        { title: "Cơ hội", value: summary.opportunities },
                        {
                            title: "Giá trị cơ hội đang mở",
                            value: money.format(Number(summary.open_value)),
                        },
                    ].map((item) => (
                        <Col xs={24} sm={12} xl={6} key={item.title}>
                            <Card className="leads-stat">
                                <Statistic
                                    title={item.title}
                                    value={item.value}
                                />
                            </Card>
                        </Col>
                    ))}
                </Row>

                <Card className="leads-list">
                    <form
                        className="leads-filters"
                        role="search"
                        aria-label="Tìm kiếm và lọc Lead"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit({ q: search.trim() });
                        }}
                    >
                        <Input
                            aria-label="Tìm theo nhu cầu, tên, email hoặc điện thoại"
                            placeholder="Tìm nhu cầu, khách hàng, email..."
                            maxLength={180}
                            allowClear
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />

                        <Select
                            aria-label="Lọc theo loại"
                            placeholder="Tất cả loại"
                            allowClear
                            value={filters.kind || undefined}
                            options={[
                                { value: "lead", label: "Lead" },
                                { value: "opportunity", label: "Cơ hội" },
                            ]}
                            onChange={(value) => {
                                visit({
                                    kind: value ?? "",
                                    stage: "",
                                    q: search.trim(),
                                });
                            }}
                        />

                        <Select
                            aria-label="Lọc theo trạng thái"
                            placeholder="Tất cả trạng thái"
                            allowClear
                            value={filters.stage || undefined}
                            options={stageOptions}
                            onChange={(value) => {
                                visit({
                                    stage: value ?? "",
                                    q: search.trim(),
                                });
                            }}
                        />

                        <Button htmlType="submit" loading={loading}>
                            Tìm kiếm
                        </Button>

                        <Button
                            disabled={loading}
                            onClick={() => {
                                setSearch("");
                                visit({ q: "", kind: "", stage: "" });
                            }}
                        >
                            Xóa bộ lọc
                        </Button>
                    </form>

                    <p className="leads-hint" role="status">
                        {summary.total} bản ghi theo bộ lọc hiện tại
                    </p>

                    <Table<Lead>
                        rowKey="id"
                        columns={columns}
                        dataSource={leads.data}
                        loading={loading}
                        scroll={{ x: 1290 }}
                        locale={{
                            emptyText: (
                                <Empty
                                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                                    description="Chưa có dữ liệu phù hợp. Hãy tạo Lead hoặc đổi bộ lọc."
                                />
                            ),
                        }}
                        pagination={{
                            current: leads.current_page,
                            pageSize: leads.per_page,
                            total: leads.total,
                            showSizeChanger: false,
                            hideOnSinglePage: true,
                            onChange: (page) => visit({}, page),
                        }}
                    />
                </Card>
            </div>

            <Modal
                title={
                    editing
                        ? `Chỉnh sửa ${editingKind === "lead" ? "Lead" : "Cơ hội"}`
                        : "Tạo Lead"
                }
                open={modalOpen}
                onCancel={closeModal}
                width={720}
                maskClosable={!form.processing}
                keyboard={!form.processing}
                closable={!form.processing}
                footer={[
                    <Button
                        key="cancel"
                        disabled={form.processing}
                        onClick={closeModal}
                    >
                        Hủy
                    </Button>,
                    <Button
                        key="save"
                        type="primary"
                        loading={form.processing}
                        onClick={submit}
                    >
                        {editing ? "Lưu thay đổi" : "Tạo Lead"}
                    </Button>,
                ]}
            >
                <Form
                    layout="vertical"
                    onFinish={submit}
                    disabled={form.processing}
                    aria-busy={form.processing}
                >
                    {Object.keys(form.errors).length > 0 && (
                        <Alert
                            className="leads-form-alert"
                            type="error"
                            showIcon
                            title="Chưa lưu được thông tin"
                            description="Vui lòng kiểm tra các trường được đánh dấu."
                            role="alert"
                        />
                    )}

                    <Form.Item {...field("title", "Tên nhu cầu")} required>
                        <Input
                            {...inputA11y("title")}
                            maxLength={180}
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData("title", event.target.value)
                            }
                        />
                    </Form.Item>

                    <Row gutter={16}>
                        <Col xs={24} sm={12}>
                            <Form.Item
                                {...field("contact_name", "Người liên hệ")}
                                required
                            >
                                <Input
                                    {...inputA11y("contact_name")}
                                    maxLength={120}
                                    value={form.data.contact_name}
                                    onChange={(event) =>
                                        form.setData(
                                            "contact_name",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={12}>
                            <Form.Item {...field("company", "Doanh nghiệp")}>
                                <Input
                                    {...inputA11y("company")}
                                    maxLength={180}
                                    value={form.data.company}
                                    onChange={(event) =>
                                        form.setData(
                                            "company",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={12}>
                            <Form.Item {...field("email", "Email")}>
                                <Input
                                    {...inputA11y("email")}
                                    type="email"
                                    maxLength={255}
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            "email",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={12}>
                            <Form.Item {...field("phone", "Số điện thoại")}>
                                <Input
                                    {...inputA11y("phone")}
                                    type="tel"
                                    maxLength={30}
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            "phone",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={12}>
                            <Form.Item
                                {...field("stage", "Trạng thái")}
                                required
                            >
                                <Select
                                    {...inputA11y("stage")}
                                    value={form.data.stage}
                                    options={Object.entries(
                                        stages[editingKind],
                                    ).map(([value, label]) => ({
                                        value,
                                        label,
                                    }))}
                                    onChange={(value) =>
                                        form.setData("stage", value)
                                    }
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={12}>
                            <Form.Item
                                {...field(
                                    "estimated_value",
                                    "Giá trị dự kiến (VND)",
                                )}
                                required
                            >
                                <InputNumber<string>
                                    {...inputA11y("estimated_value")}
                                    className="leads-value-input"
                                    stringMode
                                    min="0"
                                    max="9999999999999.99"
                                    step="1000"
                                    value={form.data.estimated_value}
                                    onChange={(value) =>
                                        form.setData(
                                            "estimated_value",
                                            value ?? "0",
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item {...field("notes", "Ghi chú")}>
                        <Input.TextArea
                            {...inputA11y("notes")}
                            rows={4}
                            maxLength={5000}
                            showCount
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData("notes", event.target.value)
                            }
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
