import { useEffect, useRef, useState } from "react";
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
    DeleteOutlined,
    EditOutlined,
    PictureOutlined,
    PlusOutlined,
    SyncOutlined,
} from "@ant-design/icons";
import LeadProductList from "@/components/common/LeadProductList";
import type { LeadProduct } from "@/types/lead-product";
import AppLayout from "@/layouts/AppLayout";
import type { PageProps } from "@/types";

type Kind = "lead" | "opportunity";
const CUSTOMER_DETAIL_FIELDS = ["contact_name", "company", "email", "phone"] as const;
type CustomerOption = {
    value: number;
    label: string;
    details?: Pick<LeadForm, (typeof CUSTOMER_DETAIL_FIELDS)[number]>;
};

interface Lead {
    id: number;
    title: string;
    products: LeadProduct[];
    contact_name: string;
    company: string | null;
    customer: {
        id: number;
        code: string;
        name: string;
        owner_id: number | null;
        owner: { id: number; name: string } | null;
    } | null;
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
    products: ProductDraft[];
    contact_name: string;
    company: string;
    customer_id?: number | null;
    email: string;
    phone: string;
    stage: string;
    estimated_value: string;
    notes: string;
}

interface SapoProductOption {
    sapo_variant_id: string;
    name: string;
    unit_price: string;
    sku: string | null;
    image_url: string | null;
    label: string;
}

interface ProductDraft {
    id: number;
    key: string;
    name: string;
    sku: string | null;
    imageUrl: string | null;
    quantity: string;
    unit_price: string;
}

function productPayload({ key, name, quantity, unit_price }: ProductDraft) {
    return { key, name, quantity, unit_price };
}

function ProductThumbnail({ imageUrl }: { imageUrl: string | null }) {
    const [imageFailed, setImageFailed] = useState(false);

    return (
        <div className="leads-products__thumbnail" aria-hidden="true">
            {imageUrl && !imageFailed ? (
                <img src={imageUrl} alt="" loading="lazy" decoding="async"
                    referrerPolicy="no-referrer" onError={() => setImageFailed(true)} />
            ) : (
                <PictureOutlined />
            )}
        </div>
    );
}

function validQuantity(quantity: string) {
    return /^[1-9]\d*$/.test(quantity) && Number(quantity) <= 1_000_000;
}

// Tính bằng phần trăm đồng để thành tiền từng dòng khớp với giá lưu trên máy chủ.
function lineCents(product: ProductDraft): bigint {
    if (!validQuantity(product.quantity) || !/^\d+(\.\d{1,2})?$/.test(product.unit_price)) {
        return 0n;
    }

    const [price, priceDecimal = ""] = product.unit_price.split(".");
    return BigInt(product.quantity) * BigInt(price + priceDecimal.padEnd(2, "0"));
}

const productMoney = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 2,
});

function formatCents(cents: bigint) {
    const whole = cents / 100n;
    const fraction = cents % 100n;
    return fraction === 0n
        ? productMoney.format(whole)
        : `${whole.toLocaleString("vi-VN")},${String(fraction).padStart(2, "0")} ₫`;
}


type CatalogSyncStatus = {
    state: "idle" | "queued" | "running" | "succeeded" | "failed";
    message: string | null;
};

interface Filters {
    q: string;
    kind: string;
    stage: string;
    owner_id: number | null;
}

interface Props {
    leads: {
        data: Lead[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: Filters;
    productImages: Record<string, string | undefined>;
    selectedOwnerName: string | null;
    stages: Record<Kind, Record<string, string>>;
    summary: {
        total: number;
        leads: number;
        opportunities: number;
        open_value: string;
    };
    canLinkCustomers: boolean;
    canSyncProducts: boolean;
    productsLastSyncedAt: string | null;
}

const EMPTY_FORM: LeadForm = {
    title: "",
    products: [],
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
    productImages,
    selectedOwnerName,
    stages,
    summary,
    canLinkCustomers,
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
    const [customerSearch, setCustomerSearch] = useState("");
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>([]);
    const [selectedCustomer, setSelectedCustomer] = useState<CustomerOption>();
    const [customerLoading, setCustomerLoading] = useState(false);
    const [customerLookupFailed, setCustomerLookupFailed] = useState(false);
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
    const conversionForm = useForm({ products: [] as ProductDraft[] });
    const nextProductId = useRef(0);

    function productDraft(product: LeadProduct): ProductDraft {
        return {
            ...product,
            id: nextProductId.current++,
            imageUrl: productImages[product.key] ?? null,
            quantity: product.quantity === null ? "1" : String(Number(product.quantity)),
            unit_price: product.unit_price ?? "0",
        };
    }

    useEffect(() => {
        setSearch(filters.q);
    }, [filters.q]);

    const showCustomerField = canLinkCustomers && editing?.kind !== "opportunity";

    useEffect(() => {
        if (!modalOpen || !showCustomerField) return;

        const controller = new AbortController();
        setCustomerLoading(true);
        setCustomerLookupFailed(false);

        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(`/leads/customer-options?q=${encodeURIComponent(customerSearch.trim())}`, {
                    signal: controller.signal,
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw new Error("Customer lookup failed");

                const { options }: { options: CustomerOption[] } = await response.json();
                setCustomerOptions(options);
            } catch {
                if (!controller.signal.aborted) {
                    setCustomerOptions([]);
                    setCustomerLookupFailed(true);
                }
            } finally {
                if (!controller.signal.aborted) setCustomerLoading(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [modalOpen, showCustomerField, customerSearch]);

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
        setCustomerSearch("");
        setCustomerOptions([]);
        setSelectedCustomer(undefined);
        setProductOptions([]);
        setProductSearch("");
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(lead: Lead) {
        form.clearErrors();
        setCustomerSearch("");
        setCustomerOptions([]);
        setSelectedCustomer(lead.customer ? {
            value: lead.customer.id,
            label: `${lead.customer.code} · ${lead.customer.name}`,
        } : undefined);

        form.setData({
            title: lead.title,
            products: lead.products.map(productDraft),
            contact_name: lead.contact_name,
            company: lead.company ?? "",
            ...(canLinkCustomers && lead.kind === "lead" && lead.customer
                ? { customer_id: lead.customer.id }
                : {}),
            email: lead.email ?? "",
            phone: lead.phone ?? "",
            stage: lead.stage,
            estimated_value: lead.estimated_value,
            notes: lead.notes ?? "",
        });
        setProductOptions([]);
        setProductSearch("");

        setEditing(lead);
        setModalOpen(true);
    }

    function closeModal() {
        if (!form.processing) setModalOpen(false);
    }

    function handleCustomerChange(customer: CustomerOption | undefined) {
        form.setData((previous) => {
            const next = { ...previous, customer_id: customer?.value ?? null };

            // Khi đổi hoặc bỏ chọn khách hàng, giữ lại phần thông tin đã nhập hoặc sửa tay.
            for (const field of CUSTOMER_DETAIL_FIELDS) {
                if (!previous[field].trim() || previous[field] === selectedCustomer?.details?.[field]) {
                    next[field] = customer?.details?.[field] ?? "";
                }
            }

            return next;
        });
        setSelectedCustomer(customer);
    }

    function submit() {
        if (editingKind === "lead" && form.data.products.some((product) => !validQuantity(product.quantity))) {
            message.error("Số lượng phải là số nguyên dương từ 1 đến 1.000.000.");
            return;
        }

        form.transform(({ products, ...data }) => ({
            ...data,
            ...(editingKind === "lead" ? { products: products.map(productPayload) } : {}),
        }));
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
        conversionForm.setData({ products: lead.products.map(productDraft) });
        setConversionLead(lead);
    }

    function convert() {
        if (!conversionLead || converting !== null) return;
        if (conversionForm.data.products.some((product) => !validQuantity(product.quantity))) {
            message.error("Số lượng phải là số nguyên dương từ 1 đến 1.000.000.");
            return;
        }

        conversionForm.transform(({ products }) => ({ products: products.map(productPayload) }));
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

    function renderProducts(quoting: boolean) {
        const productForm = quoting ? conversionForm : form;
        const rows = productForm.data.products;
        const errors = productForm.errors as Record<string, string | undefined>;
        const total = rows.reduce((sum, row) => sum + lineCents(row), 0n);

        function changeRow(row: ProductDraft, changes: Partial<ProductDraft>) {
            const updated = rows.map((current) =>
                current.id === row.id ? { ...current, ...changes } : current,
            );
            if (quoting) {
                conversionForm.setData("products", updated);
            } else {
                form.setData("products", updated);
            }
        }

        return (
            <section className="leads-products" aria-label={quoting ? "Sản phẩm báo giá" : "Sản phẩm"}>
                {!quoting && (
                    <div className="leads-products__header">
                        <Typography.Text strong>Sản phẩm</Typography.Text>
                        <Button
                            htmlType="button"
                            icon={<PlusOutlined />}
                            disabled={rows.length >= 50}
                            onClick={() => form.setData("products", [...rows, {
                                id: nextProductId.current++, key: "", name: "", sku: null,
                                imageUrl: null, quantity: "1", unit_price: "",
                            }])}
                        >
                            Thêm sản phẩm
                        </Button>
                    </div>
                )}
                {errors.products && <Alert type="error" title={errors.products} showIcon />}
                {!rows.length && <Typography.Text type="secondary">Chưa có sản phẩm.</Typography.Text>}
                {rows.map((row, index) => {
                    const error = (field: "key" | "name" | "quantity" | "unit_price") => errors[`products.${index}.${field}`];
                    const quantityError = error("quantity") ?? (row.quantity && !validQuantity(row.quantity)
                        ? "Nhập số nguyên dương từ 1 đến 1.000.000."
                        : undefined);
                    const available = productOptions.filter((product) => product.sapo_variant_id !== row.key);
                    const rowOptions = [
                        ...(row.key ? [{ value: row.key, label: row.name }] : []),
                        ...available.map((product) => ({
                            value: product.sapo_variant_id,
                            label: product.label,
                            disabled: rows.some((other) => other.key === product.sapo_variant_id),
                        })),
                    ];

                    return (
                        <div className="leads-products__row" key={row.id}>
                            <div className="leads-products__picker">
                                <ProductThumbnail key={row.imageUrl} imageUrl={row.imageUrl} />
                                {quoting ? (
                                    <Typography.Text className="leads-products__name" strong>{row.name}</Typography.Text>
                                ) : (
                                    <>
                                        <Form.Item label="Sản phẩm / SKU" htmlFor={`lead-product-${row.id}`}
                                            validateStatus={error("key") ? "error" : undefined} help={error("key")}>
                                            <Select
                                                id={`lead-product-${row.id}`}
                                                showSearch={{ filterOption: false, onSearch: setProductSearch }}
                                                value={row.key || undefined}
                                                loading={catalogLoading}
                                                placeholder="Tìm tên hoặc SKU từ Sapo"
                                                notFoundContent={productSearch.trim().length < 2
                                                    ? "Nhập ít nhất 2 ký tự để tìm sản phẩm."
                                                    : productsLastSyncedAt
                                                        ? "Không có sản phẩm khớp từ khóa."
                                                        : "Chưa có danh mục Sapo. Nhờ quản lý kết nối và đồng bộ sản phẩm trước."}
                                                options={rowOptions}
                                                onOpenChange={(open) => { if (!open) setProductSearch(""); }}
                                                onChange={(key) => {
                                                    if (key === row.key) return;
                                                    const product = productOptions.find((option) => option.sapo_variant_id === key);
                                                    if (product) changeRow(row, {
                                                        key, name: product.name, sku: product.sku, unit_price: product.unit_price,
                                                        imageUrl: product.image_url,
                                                    });
                                                    setProductSearch("");
                                                }}
                                            />
                                        </Form.Item>
                                        <Button htmlType="button" type="text" danger icon={<DeleteOutlined />}
                                            aria-label={`Xóa dòng ${index + 1}`} title="Xóa sản phẩm" onClick={() => {
                                                form.setData((previous) => ({
                                                    ...previous,
                                                    products: rows.filter((current) => current.id !== row.id),
                                                    estimated_value: rows.length === 1 ? "0" : previous.estimated_value,
                                                }));
                                            }} />
                                    </>
                                )}
                            </div>
                            {row.key && (
                                <Form.Item className="leads-products__editable-name" label="Tên sản phẩm trên hồ sơ"
                                    htmlFor={`lead-product-name-${row.id}`}
                                    validateStatus={error("name") ? "error" : undefined} help={error("name")}>
                                    <Input id={`lead-product-name-${row.id}`} value={row.name} maxLength={180}
                                        placeholder="Có thể chỉnh tên sản phẩm cho hồ sơ này"
                                        onChange={(event) => changeRow(row, { name: event.target.value })} />
                                </Form.Item>
                            )}
                            <Form.Item label="Số lượng" htmlFor={`lead-quantity-${row.id}`}
                                validateStatus={quantityError ? "error" : undefined} help={quantityError}>
                                <Input id={`lead-quantity-${row.id}`} type="number" inputMode="numeric" min={1} max={1000000}
                                    step={1} value={row.quantity} onChange={(event) => changeRow(row, { quantity: event.target.value })} />
                            </Form.Item>
                            <Form.Item label="Đơn giá (₫)" htmlFor={`lead-price-${row.id}`}
                                validateStatus={error("unit_price") ? "error" : undefined} help={error("unit_price")}>
                                <InputNumber<string> id={`lead-price-${row.id}`} stringMode min="0" max="9999999999999.99"
                                    step="0.01" value={row.unit_price || null} onChange={(value) => changeRow(row, { unit_price: value ?? "" })} />
                            </Form.Item>
                            <Form.Item label="Thành tiền" htmlFor={`lead-amount-${row.id}`}>
                                <output className="leads-products__amount" id={`lead-amount-${row.id}`}>
                                    {formatCents(lineCents(row))}
                                </output>
                            </Form.Item>
                        </div>
                    );
                })}
                {rows.length > 0 && (
                    <div className="leads-products__total">
                        <Typography.Text strong>Tổng giá báo</Typography.Text>
                        <output aria-live="polite">{formatCents(total)}</output>
                    </div>
                )}
                {!quoting && productsLastSyncedAt && (
                    <Typography.Text type="secondary">Danh mục đồng bộ lần cuối: {productsLastSyncedAt}</Typography.Text>
                )}
            </section>
        );
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
                    {lead.kind === "lead" && lead.customer && (
                        <Typography.Text type="secondary">
                            Khách hàng: {lead.customer.code} · {lead.customer.name}
                        </Typography.Text>
                    )}
                    <LeadProductList products={lead.products} />
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
            render: (_, lead) => {
                const owner = lead.kind === "lead" && lead.customer?.owner_id != null
                    ? lead.customer.owner
                    : lead.owner;
                return owner?.name ?? "Không còn hoạt động";
            },
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
                            description={lead.customer
                                ? "Cơ hội sẽ theo người phụ trách hồ sơ khách hàng nếu đã được phân công."
                                : "Thông tin và người phụ trách được giữ nguyên."}
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
                                visit({ q: "", kind: "", stage: "", owner_id: null });
                            }}
                        >
                            Xóa bộ lọc
                        </Button>
                    </form>

                    <p className="leads-hint" role="status">
                        {summary.total} bản ghi theo bộ lọc hiện tại
                        {filters.owner_id !== null && ` · Người phụ trách: ${selectedOwnerName ?? "Không còn hoạt động"}`}
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
                className="leads-editor-modal"
                classNames={{ body: "leads-editor-modal__body" }}
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
                    className="leads-editor-form"
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

                    {showCustomerField && (
                        <Form.Item
                            {...field("customer_id", "Khách hàng liên quan")}
                            extra="Chọn hồ sơ để điền thông tin liên hệ có sẵn. Có thể bỏ trống cho khách hàng mới."
                        >
                            <Select<number | undefined, CustomerOption>
                                {...inputA11y("customer_id")}
                                allowClear
                                placeholder="Tìm theo tên hoặc mã khách hàng"
                                showSearch={{ filterOption: false, onSearch: setCustomerSearch }}
                                loading={customerLoading}
                                notFoundContent={customerLookupFailed
                                    ? "Không tải được danh sách. Hãy thử lại."
                                    : "Không tìm thấy khách hàng."}
                                value={form.data.customer_id ?? undefined}
                                options={selectedCustomer
                                    ? [selectedCustomer, ...customerOptions.filter((option) => option.value !== selectedCustomer.value)]
                                    : customerOptions}
                                onChange={(value, option) => {
                                    handleCustomerChange(value !== undefined && !Array.isArray(option) ? option : undefined);
                                }}
                            />
                        </Form.Item>
                    )}

                    <Row gutter={16}>
                        <Col xs={24}>
                            {editingKind === "lead"
                                ? renderProducts(false)
                                : <LeadProductList products={editing?.products ?? []} />}
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

                        <Col xs={24} sm={form.data.products.length ? 24 : 12}>
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

                        {!form.data.products.length && <Col xs={24} sm={12}>
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
                        </Col>}
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
                width={720}
                className="leads-editor-modal"
                classNames={{ body: "leads-editor-modal__body" }}
                onOk={convert}
                maskClosable={!conversionForm.processing}
            >
                <Typography.Paragraph type="secondary">
                    CRM sẽ lưu hồ sơ Khách hàng khi chuyển Lead thành Cơ hội.
                </Typography.Paragraph>
                {conversionForm.data.products.length ? (
                    <Form layout="vertical" disabled={conversionForm.processing}>
                        {renderProducts(true)}
                    </Form>
                ) : (
                    <Typography.Text type="secondary">
                        Giá trị dự kiến: {money.format(Number(conversionLead?.estimated_value ?? 0))}
                    </Typography.Text>
                )}
            </Modal>
        </AppLayout>
    );
}
