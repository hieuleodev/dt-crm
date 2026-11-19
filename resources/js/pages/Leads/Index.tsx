import { useEffect, useState } from "react";
import { Head, router, useForm, usePage } from "@inertiajs/react";
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
    SyncOutlined,
} from "@ant-design/icons";
import AppLayout from "@/layouts/AppLayout";
import type { PageProps } from "@/types";

type Kind = "lead" | "opportunity";

interface Lead {
    id: number;
    title: string;
    requested_product: string | null;
    sapo_product_id: number | null;
    sapo_variant_id: number | null;
    requested_quantity: string | null;
    requested_unit_price: string | null;
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
    sapo_variant_id: string | null;
    contact_name: string;
    company: string;
    email: string;
    phone: string;
    stage: string;
    estimated_value: string;
    notes: string;
}

interface SapoProductOption {
    sapo_product_id: string;
    sapo_variant_id: string;
    product_name: string;
    variant_name: string | null;
    sku: string | null;
    unit_price: string;
    vendor: string | null;
    product_type: string | null;
    description: string | null;
    image_url: string | null;
    product_status: string | null;
    tags: string[];
    label: string;
}

type CatalogSyncStatus = {
    state: "idle" | "queued" | "running" | "succeeded" | "failed";
    message: string | null;
};

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
    canSyncProducts: boolean;
    productsLastSyncedAt: string | null;
}

const EMPTY_FORM: LeadForm = {
    title: "",
    sapo_variant_id: null,
    contact_name: "",
    company: "",
    email: "",
    phone: "",
    stage: "new",
    estimated_value: "",
    notes: "",
};

const money = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
});

export default function Index({
    leads,
    filters,
    stages,
    summary,
    canSyncProducts,
    productsLastSyncedAt,
}: Props) {
    const { message } = App.useApp();
    const { auth } = usePage<PageProps>().props;
    const canUseTasks = auth.user !== null;

    const [search, setSearch] = useState(filters.q);
    const [loading, setLoading] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Lead | null>(null);
    const [converting, setConverting] = useState<number | null>(null);
    const [conversionLead, setConversionLead] = useState<Lead | null>(null);
    const [productSearch, setProductSearch] = useState("");
    const [productOptions, setProductOptions] = useState<SapoProductOption[]>([]);
    const [catalogLoading, setCatalogLoading] = useState(false);
    const [catalogRevision, setCatalogRevision] = useState(0);
    const [syncingProducts, setSyncingProducts] = useState(false);
    const [syncStatusRevision, setSyncStatusRevision] = useState(0);
    const [syncStatus, setSyncStatus] = useState<CatalogSyncStatus>({
        state: "idle",
        message: null,
    });
    const syncInProgress =
        syncStatus.state === "queued" || syncStatus.state === "running";

    const form = useForm<LeadForm>({ ...EMPTY_FORM });
    const conversionForm = useForm({ quantity: "1", unit_price: "0" });
    const selectedProduct = productOptions.find(
        (product) => product.sapo_variant_id === form.data.sapo_variant_id,
    );

    useEffect(() => {
        setSearch(filters.q);
    }, [filters.q]);

    // Theo dõi trạng thái từ worker. Chỉ hỏi lặp lại khi có lượt đang chờ hoặc đang chạy.
    useEffect(() => {
        if (!canSyncProducts) return;

        const controller = new AbortController();
        let nextCheck: number | undefined;
        let awaitingResult = syncStatusRevision > 0;

        async function checkStatus() {
            try {
                const response = await fetch("/sapo/products/sync/status", {
                    headers: { Accept: "application/json" },
                    signal: controller.signal,
                    cache: "no-store",
                });
                if (!response.ok) throw new Error();
                const status = (await response.json()) as CatalogSyncStatus;
                if (controller.signal.aborted) return;

                setSyncStatus(status);
                if (status.state === "queued" || status.state === "running") {
                    awaitingResult = true;
                    nextCheck = window.setTimeout(checkStatus, 5000);
                } else if (status.state === "succeeded" && awaitingResult) {
                    setCatalogRevision((revision) => revision + 1);
                    router.reload({ only: ["productsLastSyncedAt"] });
                }
            } catch {
                if (!controller.signal.aborted && awaitingResult) {
                    nextCheck = window.setTimeout(checkStatus, 5000);
                }
            }
        }

        void checkStatus();
        return () => {
            controller.abort();
            window.clearTimeout(nextCheck);
        };
    }, [canSyncProducts, syncStatusRevision]);

    // Đợi người dùng ngừng gõ rồi tìm trong danh mục CRM. Hủy truy vấn cũ khi từ khóa đổi.
    useEffect(() => {
        if (!modalOpen) {
            setCatalogLoading(false);
            return;
        }

        const query = productSearch.trim();
        if (query.length < 2) {
            setProductOptions([]);
            setCatalogLoading(false);
            return;
        }

        const controller = new AbortController();
        setCatalogLoading(true);
        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    `/sapo/products?q=${encodeURIComponent(query)}`,
                    {
                        headers: { Accept: "application/json" },
                        signal: controller.signal,
                    },
                );
                if (!response.ok) {
                    throw new Error(
                        `Không thể tìm sản phẩm (HTTP ${response.status}).`,
                    );
                }
                const result = (await response.json()) as {
                    data: SapoProductOption[];
                };
                if (!controller.signal.aborted) setProductOptions(result.data);
            } catch (error) {
                if (controller.signal.aborted) return;
                const errorMessage =
                    error instanceof Error
                        ? error.message
                        : "Không thể tìm sản phẩm trong danh mục Sapo.";
                message.error(errorMessage);
            } finally {
                if (!controller.signal.aborted) setCatalogLoading(false);
            }
        }, 300);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [catalogRevision, message, modalOpen, productSearch]);

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
        setProductOptions([]);
        setProductSearch("");
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(lead: Lead) {
        form.clearErrors();

        form.setData({
            title: lead.title,
            sapo_variant_id: lead.sapo_variant_id
                ? String(lead.sapo_variant_id)
                : null,
            contact_name: lead.contact_name,
            company: lead.company ?? "",
            email: lead.email ?? "",
            phone: lead.phone ?? "",
            stage: lead.stage,
            estimated_value: lead.estimated_value,
            notes: lead.notes ?? "",
        });
        // Hiển thị tên và giá đã lưu trên Lead trong lúc tải kết quả từ danh mục.
        setProductOptions(
            lead.sapo_variant_id && lead.requested_product
                ? [
                      {
                          sapo_product_id: String(lead.sapo_product_id ?? ""),
                          sapo_variant_id: String(lead.sapo_variant_id),
                          product_name: lead.requested_product,
                          variant_name: null,
                          sku: null,
                          unit_price: lead.requested_unit_price ?? "0",
                          vendor: null,
                          product_type: null,
                          description: null,
                          image_url: null,
                          product_status: null,
                          tags: [],
                          label: lead.requested_product,
                      },
                  ]
                : [],
        );
        setProductSearch(
            lead.sapo_variant_id ? lead.requested_product ?? "" : "",
        );

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

    // Dùng số lượng và đơn giá đã lưu làm giá trị ban đầu khi chuyển Lead sang Cơ hội.
    function openConversion(lead: Lead) {
        conversionForm.clearErrors();
        conversionForm.setData({
            quantity: lead.requested_quantity === null
                ? "1"
                : String(Number(lead.requested_quantity)),
            unit_price: lead.requested_unit_price ?? "0",
        });
        setConversionLead(lead);
    }

    function convert() {
        if (!conversionLead || converting !== null) return;

        conversionForm.post(`/leads/${conversionLead.id}/convert`, {
            preserveScroll: true,
            onStart: () => setConverting(conversionLead.id),
            onSuccess: () => setConversionLead(null),
            onFinish: () => setConverting(null),
            onError: (errors) => {
                // Lead lưu trước khi có quy tắc mới có thể cần sửa liên hệ rồi mới chuyển được.
                const contactError = Object.entries(errors).find(([field]) =>
                    ["phone", "email"].includes(field),
                )?.[1];
                const customerError = Object.entries(errors).find(
                    ([field]) => field === "customer_id",
                )?.[1];
                if (typeof contactError === "string") {
                    message.error(`${contactError} Hãy sửa thông tin Lead rồi thử lại.`);
                    return;
                }
                if (typeof customerError === "string") {
                    message.error(customerError);
                    return;
                }
                message.error("Không thể chuyển đổi Lead. Vui lòng kiểm tra số lượng và đơn giá.");
            },
        });
    }

    // POST chỉ xếp lịch. Danh mục được tải lại sau khi worker báo hoàn tất.
    function syncProducts() {
        router.post("/sapo/products/sync", {}, {
            preserveScroll: true,
            onStart: () => setSyncingProducts(true),
            onSuccess: () => {
                setSyncStatus({ state: "queued", message: "Đang chờ đồng bộ Sapo" });
                setSyncStatusRevision((revision) => revision + 1);
            },
            onFinish: () => setSyncingProducts(false),
            onError: (errors) => {
                message.error(
                    typeof errors.sapo === "string"
                        ? errors.sapo
                        : "Không đồng bộ được danh mục sản phẩm Sapo.",
                );
            },
        });
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
                    {lead.requested_product && (
                        <Typography.Text type="secondary">
                            Sản phẩm: {lead.requested_product}
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
                    {canUseTasks && (
                        <Button
                            onClick={() =>
                                router.visit(`/tasks?lead_id=${lead.id}`)
                            }
                        >
                            Nhiệm vụ
                        </Button>
                    )}
                    {lead.kind === "lead" && lead.stage === "qualified" && (
                        <Popconfirm
                            title="Chuyển thành Cơ hội?"
                            description="Thông tin và người phụ trách được giữ nguyên."
                            okText="Chuyển đổi"
                            cancelText="Hủy"
                            onConfirm={() => openConversion(lead)}
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
                    <Space wrap>
                        {canSyncProducts && (
                            <Button
                                icon={<SyncOutlined />}
                                loading={syncingProducts || syncInProgress}
                                disabled={syncingProducts || syncInProgress}
                                onClick={syncProducts}
                            >
                                {syncInProgress ? syncStatus.message : "Đồng bộ dữ liệu"}
                            </Button>
                        )}
                        {canSyncProducts && !syncInProgress && syncStatus.message && (
                            <Typography.Text
                                type={syncStatus.state === "failed" ? "danger" : "secondary"}
                                role="status"
                            >
                                {syncStatus.message}
                            </Typography.Text>
                        )}
                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            onClick={openCreate}
                        >
                            Tạo Lead
                        </Button>
                    </Space>
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
                        <Col xs={24}>
                            <Form.Item
                                {...field("sapo_variant_id", "Sản phẩm")}
                            >
                                <Select
                                    {...inputA11y("sapo_variant_id")}
                                    showSearch={{
                                        filterOption: false,
                                        onSearch: setProductSearch,
                                    }}
                                    allowClear
                                    value={
                                        form.data.sapo_variant_id ?? undefined
                                    }
                                    loading={catalogLoading}
                                    placeholder="Nhập tên sản phẩm hoặc SKU đã đồng bộ từ Sapo"
                                    notFoundContent={
                                        catalogLoading
                                            ? "Đang tìm..."
                                            : productSearch.trim().length < 2
                                              ? "Nhập ít nhất 2 ký tự để tìm theo tên hoặc SKU."
                                              : productsLastSyncedAt
                                                ? "Không có sản phẩm khớp từ khóa."
                                                : "Danh mục Sapo chưa có dữ liệu đồng bộ."
                                    }
                                    options={productOptions.map((product) => ({
                                        value: product.sapo_variant_id,
                                        label: product.label,
                                    }))}
                                    // Lưu mã biến thể vào một trường riêng. Giá trị dự kiến do người dùng nhập.
                                    onChange={(variantId) =>
                                        form.setData(
                                            "sapo_variant_id",
                                            variantId ?? null,
                                        )
                                    }
                                />
                            </Form.Item>
                            {productsLastSyncedAt && (
                                <Typography.Text type="secondary">
                                    Danh mục đồng bộ lần cuối: {productsLastSyncedAt}
                                </Typography.Text>
                            )}
                            {selectedProduct && (
                                <Card
                                    size="small"
                                    className="sapo-product-details"
                                    style={{ marginTop: 12 }}
                                >
                                    <Row gutter={16} wrap={false}>
                                        {selectedProduct.image_url && (
                                            <Col flex="96px">
                                                <img
                                                    src={selectedProduct.image_url}
                                                    alt=""
                                                    loading="lazy"
                                                    style={{
                                                        width: 80,
                                                        height: 80,
                                                        objectFit: "contain",
                                                    }}
                                                />
                                            </Col>
                                        )}
                                        <Col flex="auto">
                                            <Space wrap>
                                                <Typography.Text strong>
                                                    {selectedProduct.product_name}
                                                </Typography.Text>
                                                {selectedProduct.product_type && (
                                                    <Tag>
                                                        {selectedProduct.product_type}
                                                    </Tag>
                                                )}
                                                {selectedProduct.product_status && (
                                                    <Tag color="blue">
                                                        {selectedProduct.product_status}
                                                    </Tag>
                                                )}
                                            </Space>
                                            <div>
                                                {[selectedProduct.vendor, selectedProduct.sku]
                                                    .filter(Boolean)
                                                    .join(" · ")}
                                            </div>
                                            <Typography.Text>
                                                {money.format(
                                                    Number(selectedProduct.unit_price),
                                                )}
                                            </Typography.Text>
                                            {selectedProduct.tags.length > 0 && (
                                                <div>
                                                    <Space size={[0, 4]} wrap>
                                                        {selectedProduct.tags.map((tag) => (
                                                            <Tag key={tag}>{tag}</Tag>
                                                        ))}
                                                    </Space>
                                                </div>
                                            )}
                                            {selectedProduct.description && (
                                                <Typography.Paragraph
                                                    type="secondary"
                                                    ellipsis={{ rows: 3 }}
                                                    style={{ marginTop: 8, marginBottom: 0 }}
                                                >
                                                    {selectedProduct.description}
                                                </Typography.Paragraph>
                                            )}
                                        </Col>
                                    </Row>
                                </Card>
                            )}
                        </Col>
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
                            <Form.Item {...field("email", "Email")} required>
                                <Input
                                    {...inputA11y("email")}
                                    aria-required="true"
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
                                            value ?? "",
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

            <Modal
                title="Thông tin sản phẩm khi chuyển thành cơ hội"
                open={conversionLead !== null}
                onCancel={() => {
                    if (!conversionForm.processing) setConversionLead(null);
                }}
                okText="Tính giá trị và chuyển đổi"
                cancelText="Hủy"
                confirmLoading={conversionForm.processing}
                onOk={convert}
                maskClosable={!conversionForm.processing}
            >
                <Typography.Paragraph type="secondary">
                    CRM sẽ lưu hồ sơ Khách hàng khi chuyển Lead thành Cơ hội.
                </Typography.Paragraph>
                {conversionLead?.requested_product ? (
                    <Typography.Paragraph>
                        Sản phẩm yêu cầu: <strong>{conversionLead.requested_product}</strong>
                    </Typography.Paragraph>
                ) : (
                    <Typography.Paragraph type="secondary">
                        Lead chưa có sản phẩm yêu cầu. Bạn có thể chuyển đổi và
                        bổ sung sản phẩm sau.
                    </Typography.Paragraph>
                )}
                <Form layout="vertical">
                    <Form.Item
                        label="Số lượng"
                        required={Boolean(conversionLead?.requested_product)}
                        validateStatus={conversionForm.errors.quantity ? "error" : ""}
                        help={conversionForm.errors.quantity}
                    >
                        {/* Giữ giá trị nhập để máy chủ báo lỗi nếu số lượng có phần thập phân. */}
                        <Input
                            type="number"
                            inputMode="numeric"
                            style={{ width: "100%" }}
                            min="1"
                            max="1000000"
                            step="1"
                            value={conversionForm.data.quantity}
                            onChange={(event) =>
                                conversionForm.setData("quantity", event.target.value)
                            }
                        />
                    </Form.Item>
                    <Form.Item
                        label="Đơn giá (VND)"
                        required={Boolean(conversionLead?.requested_product)}
                        validateStatus={conversionForm.errors.unit_price ? "error" : ""}
                        help={conversionForm.errors.unit_price}
                    >
                        <InputNumber<string>
                            style={{ width: "100%" }}
                            stringMode
                            min="0"
                            max="9999999999999.99"
                            step="1000"
                            value={conversionForm.data.unit_price}
                            onChange={(value) =>
                                conversionForm.setData("unit_price", value ?? "")
                            }
                        />
                    </Form.Item>
                    {/* Hiển thị giá trị tạm tính. Máy chủ làm tròn và lưu kết quả khi chuyển đổi. */}
                    <Typography.Text>
                        Giá trị cơ hội dự kiến:{" "}
                        <strong>
                            {/^[1-9]\d*$/.test(conversionForm.data.quantity) &&
                            Number(conversionForm.data.quantity) <= 1000000
                                ? money.format(
                                      Number(conversionForm.data.quantity) *
                                          Number(conversionForm.data.unit_price || 0),
                                  )
                                : "—"}
                        </strong>
                    </Typography.Text>
                </Form>
            </Modal>
        </AppLayout>
    );
}
