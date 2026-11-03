import { ReactNode, useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import {
    Alert,
    Button,
    Card,
    Descriptions,
    Empty,
    Popconfirm,
    Table,
    Tabs,
    Tag,
    Timeline,
    Typography,
} from "antd";
import type { DescriptionsProps, TableProps } from "antd";
import {
    DeleteOutlined,
    EditOutlined,
    PlusOutlined,
    SwapOutlined,
} from "@ant-design/icons";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import CompletenessBadge from "@/components/customer/CompletenessBadge";
import ContactModal from "@/components/customer/ContactModal";
import PlantModal from "@/components/customer/PlantModal";
import ReassignModal from "@/components/customer/ReassignModal";
import {
    formatDate,
    formatDateTime,
    formatNumber,
    formatVnd,
    labelOf,
    stageColor,
} from "@/lib/format";
import type { PageProps } from "@/types";
import type {
    AuditItem,
    ContactItem,
    CustomerDetail,
    CustomerOptions,
    OwnerHistoryItem,
    OwnerOption,
    PlantItem,
} from "@/types/customer";

interface Props extends PageProps {
    customer: CustomerDetail;
    contacts: ContactItem[];
    plants: PlantItem[];
    ownerHistory: OwnerHistoryItem[];
    auditLogs: AuditItem[];
    options: CustomerOptions;
    owners: OwnerOption[];
    can: { update: boolean; delete: boolean; reassign: boolean };
}

const FIELD_LABELS: Record<string, string> = {
    code: "Mã KH",
    name: "Tên",
    short_name: "Tên viết tắt",
    tax_code: "MST",
    company_type: "Loại hình",
    phone: "Điện thoại",
    email: "Email",
    address: "Địa chỉ",
    province: "Tỉnh",
    industrial_zone: "KCN",
    industry: "Ngành",
    employee_count: "Số công nhân",
    ppe_budget_estimate: "Tiềm năng PPE",
    source_channel: "Kênh",
    tier: "Hạng",
    lifecycle_stage: "Giai đoạn",
    status: "Trạng thái",
    owner_id: "Người phụ trách",
    full_name: "Họ tên",
    title: "Chức vụ",
    buying_role: "Vai trò mua hàng",
    left_at: "Ngày nghỉ việc",
    is_primary: "Liên hệ chính",
    credit_limit: "Hạn mức công nợ",
    payment_terms: "Thanh toán",
    notes: "Ghi chú",
    marketing_consent: "Đồng ý nhận tin",
};
const SUBJECTS: Record<string, string> = {
    Customer: "Khách hàng",
    Contact: "Người liên hệ",
    Plant: "Nhà máy",
};
const EVENTS: Record<string, { label: string; color: string }> = {
    created: { label: "Tạo mới", color: "green" },
    updated: { label: "Cập nhật", color: "blue" },
    deleted: { label: "Xóa", color: "red" },
};

const plain = (v: unknown) =>
    v === null || v === undefined || v === ""
        ? "∅"
        : typeof v === "boolean"
          ? v
              ? "Có"
              : "Không"
          : String(v);

export default function Show() {
    const {
        customer: c,
        contacts,
        plants,
        ownerHistory,
        auditLogs,
        options,
        owners,
        can,
    } = usePage<Props>().props;

    const ENUM_FIELDS: Record<string, CustomerOptions[keyof CustomerOptions]> =
        {
            company_type: options.companyTypes,
            industry: options.industries,
            tier: options.tiers,
            status: options.statuses,
            lifecycle_stage: options.lifecycleStages,
            source_channel: options.sourceChannels,
            procurement_method: options.procurementMethods,
            buying_role: options.buyingRoles,
            influence: options.influences,
            attitude: options.attitudes,
            preferred_channel: options.contactChannels,
        };
    const show = (key: string, v: unknown) =>
        ENUM_FIELDS[key] && typeof v === "string"
            ? labelOf(ENUM_FIELDS[key], v)
            : plain(v);
    const [contactModal, setContactModal] = useState<{
        open: boolean;
        item: ContactItem | null;
    }>({ open: false, item: null });
    const [plantModal, setPlantModal] = useState<{
        open: boolean;
        item: PlantItem | null;
    }>({ open: false, item: null });
    const [reassignOpen, setReassignOpen] = useState(false);
    const activeContacts = contacts.filter((x) => !x.left_at).length;

    const field = (label: string, value: ReactNode, full = false) => ({
        key: label,
        label,
        children: value ?? "—",
        span: full ? ("filled" as const) : 1,
    });

    const general: DescriptionsProps["items"] = [
        field("Mã số thuế", c.tax_code),
        field("Loại hình", labelOf(options.companyTypes, c.company_type)),
        field(
            "Tập đoàn mẹ",
            [c.parent_group, c.parent_country].filter(Boolean).join(" – ") ||
                "—",
        ),
        field("Điện thoại", c.phone),
        field("Email", c.email),
        field("Website", c.website),
        field("Địa chỉ", c.address, true),
        field(
            "KCN / Tỉnh",
            [c.industrial_zone, c.province].filter(Boolean).join(", ") || "—",
        ),
    ];
    const potential: DescriptionsProps["items"] = [
        field("Ngành", labelOf(options.industries, c.industry)),
        field("Sản phẩm chính", c.industry_detail),
        field("Số công nhân", formatNumber(c.employee_count)),
        field("Tiềm năng PPE / năm", formatVnd(c.ppe_budget_estimate)),
        field("Nhà cung cấp hiện tại", c.current_suppliers, true),
    ];
    const commercial: DescriptionsProps["items"] = [
        field(
            "Kênh khách tìm đến",
            labelOf(options.sourceChannels, c.source_channel),
        ),
        field("Người giới thiệu", c.referred_by),
        field(
            "Cách mua hàng",
            labelOf(options.procurementMethods, c.procurement_method),
        ),
        field("Điều khoản thanh toán", c.payment_terms),
        field("Hạn mức công nợ", formatVnd(c.credit_limit)),
        field(
            "Đăng ký nhà cung cấp",
            c.vendor_registered
                ? `Đã duyệt ${formatDate(c.vendor_registered_at)}`
                : "Chưa",
        ),
        field(
            "Chứng từ yêu cầu",
            c.required_certificates.length
                ? c.required_certificates.map((x) => <Tag key={x}>{x}</Tag>)
                : "—",
            true,
        ),
        field(
            "Đồng ý nhận tin",
            c.marketing_consent
                ? `Có (${c.consent_channels.join(", ") || "chưa chọn kênh"}) – từ ${formatDate(c.consent_at)}`
                : "Chưa",
            true,
        ),
        field(
            "Ghi chú",
            c.notes ? (
                <span style={{ whiteSpace: "pre-line" }}>{c.notes}</span>
            ) : (
                "—"
            ),
            true,
        ),
    ];

    const contactColumns: TableProps<ContactItem>["columns"] = [
        {
            title: "Họ tên",
            render: (_, x) => (
                <div>
                    <Typography.Text strong delete={!!x.left_at}>
                        {x.full_name}
                    </Typography.Text>{" "}
                    {x.is_primary && <Tag color="volcano">Liên hệ chính</Tag>}
                    {x.left_at && <Tag>Nghỉ từ {formatDate(x.left_at)}</Tag>}
                    <div className="crm-muted">
                        {[x.title, x.department, x.plant]
                            .filter(Boolean)
                            .join(" · ") || "—"}
                    </div>
                </div>
            ),
        },
        {
            title: "Vai trò",
            render: (_, x) => (
                <div>
                    <div>{labelOf(options.buyingRoles, x.buying_role)}</div>
                    <div className="crm-muted">
                        {labelOf(options.influences, x.influence)}
                    </div>
                </div>
            ),
        },
        {
            title: "Liên lạc",
            render: (_, x) => (
                <div>
                    {x.phone && (
                        <div>
                            <a href={`tel:${x.phone}`}>{x.phone}</a>
                        </div>
                    )}
                    {x.email && (
                        <div>
                            <a href={`mailto:${x.email}`}>{x.email}</a>
                        </div>
                    )}
                    {x.zalo && <div className="crm-muted">Zalo: {x.zalo}</div>}
                    {!x.phone && !x.email && !x.zalo && "—"}
                </div>
            ),
        },
        {
            title: "Thái độ",
            dataIndex: "attitude",
            render: (v) => labelOf(options.attitudes, v),
            responsive: ["lg"],
        },
        {
            title: "",
            width: 96,
            render: (_, x) =>
                can.update && (
                    <div className="crm-row-actions">
                        <Button
                            size="small"
                            type="text"
                            icon={<EditOutlined />}
                            aria-label="Sửa"
                            onClick={() =>
                                setContactModal({ open: true, item: x })
                            }
                        />
                        <Popconfirm
                            title="Xóa người liên hệ này?"
                            okText="Xóa"
                            cancelText="Hủy"
                            onConfirm={() =>
                                router.delete(
                                    `/customers/${c.id}/contacts/${x.id}`,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Button
                                size="small"
                                type="text"
                                danger
                                icon={<DeleteOutlined />}
                                aria-label="Xóa"
                            />
                        </Popconfirm>
                    </div>
                ),
        },
    ];

    const plantColumns: TableProps<PlantItem>["columns"] = [
        {
            title: "Nhà máy",
            render: (_, p) => (
                <div>
                    <Typography.Text strong>{p.name}</Typography.Text>
                    <div className="crm-muted">{p.address ?? "—"}</div>
                </div>
            ),
        },
        {
            title: "KCN / Tỉnh",
            render: (_, p) =>
                [p.industrial_zone, p.province].filter(Boolean).join(", ") ||
                "—",
        },
        {
            title: "Công nhân",
            dataIndex: "employee_count",
            render: (v) => formatNumber(v),
            align: "right",
        },
        {
            title: "Khu vực rủi ro",
            dataIndex: "hazard_areas",
            render: (v: string[] | null) =>
                v?.length ? v.map((h) => <Tag key={h}>{h}</Tag>) : "—",
            responsive: ["md"],
        },
        {
            title: "Giao hàng",
            render: (_, p) =>
                [p.receiving_hours, p.delivery_notes]
                    .filter(Boolean)
                    .join(" · ") || "—",
            responsive: ["lg"],
        },
        {
            title: "",
            width: 96,
            render: (_, p) =>
                can.update && (
                    <div className="crm-row-actions">
                        <Button
                            size="small"
                            type="text"
                            icon={<EditOutlined />}
                            aria-label="Sửa"
                            onClick={() =>
                                setPlantModal({ open: true, item: p })
                            }
                        />
                        <Popconfirm
                            title="Xóa nhà máy này?"
                            okText="Xóa"
                            cancelText="Hủy"
                            onConfirm={() =>
                                router.delete(
                                    `/customers/${c.id}/plants/${p.id}`,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Button
                                size="small"
                                type="text"
                                danger
                                icon={<DeleteOutlined />}
                                aria-label="Xóa"
                            />
                        </Popconfirm>
                    </div>
                ),
        },
    ];

    const auditColumns: TableProps<AuditItem>["columns"] = [
        {
            title: "Thời gian",
            dataIndex: "at",
            width: 150,
            render: formatDateTime,
        },
        { title: "Người thực hiện", dataIndex: "user", width: 170 },
        {
            title: "Thao tác",
            width: 190,
            render: (_, l) => (
                <>
                    <Tag color={EVENTS[l.event]?.color}>
                        {EVENTS[l.event]?.label ?? l.event}
                    </Tag>
                    {SUBJECTS[l.subject] ?? l.subject}
                </>
            ),
        },
        {
            title: "Chi tiết",
            render: (_, l) => {
                if (l.event !== "updated")
                    return (
                        <span className="crm-muted">
                            {plain(
                                l.new?.name ??
                                    l.new?.full_name ??
                                    l.old?.name ??
                                    l.old?.full_name ??
                                    "",
                            )}
                        </span>
                    );
                return Object.keys(l.new ?? {}).map((k) => (
                    <div key={k}>
                        <span className="crm-muted">
                            {FIELD_LABELS[k] ?? k}:
                        </span>{" "}
                        {show(k, l.old?.[k])} →{" "}
                        <strong>{show(k, l.new?.[k])}</strong>
                    </div>
                ));
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={c.name} />
            <PageHeader
                back={{ href: "/customers", label: "Danh sách khách hàng" }}
                title={c.name}
                subtitle={
                    <div className="crm-badges">
                        <span>{c.code}</span>
                        <Tag color={stageColor(c.lifecycle_stage)}>
                            {labelOf(
                                options.lifecycleStages,
                                c.lifecycle_stage,
                            )}
                        </Tag>
                        {c.tier && (
                            <Tag
                                color={
                                    c.tier === "A"
                                        ? "volcano"
                                        : c.tier === "B"
                                          ? "gold"
                                          : "default"
                                }
                            >
                                Hạng {c.tier}
                            </Tag>
                        )}
                        {c.status !== "active" && (
                            <Tag color="red">
                                {labelOf(options.statuses, c.status)}
                            </Tag>
                        )}
                        {c.tags.map((t) => (
                            <Tag key={t}>{t}</Tag>
                        ))}
                        <span className="crm-muted">
                            · Phụ trách:{" "}
                            <strong>{c.owner?.name ?? "Chưa giao"}</strong>
                        </span>
                    </div>
                }
                extra={
                    <>
                        {can.reassign && (
                            <Button
                                icon={<SwapOutlined />}
                                onClick={() => setReassignOpen(true)}
                            >
                                Đổi phụ trách
                            </Button>
                        )}
                        {can.update && (
                            <Button
                                type="primary"
                                icon={<EditOutlined />}
                                onClick={() =>
                                    router.visit(`/customers/${c.id}/edit`)
                                }
                            >
                                Sửa thông tin
                            </Button>
                        )}
                        {can.delete && (
                            <Popconfirm
                                title="Xóa khách hàng này?"
                                description="Dữ liệu được ẩn đi và vẫn có thể khôi phục."
                                okText="Xóa"
                                cancelText="Hủy"
                                onConfirm={() =>
                                    router.delete(`/customers/${c.id}`)
                                }
                            >
                                <Button danger icon={<DeleteOutlined />}>
                                    Xóa
                                </Button>
                            </Popconfirm>
                        )}
                    </>
                }
            />

            {c.data_completeness < 100 && (
                <Alert
                    className="crm-section"
                    type={c.data_completeness < 60 ? "warning" : "info"}
                    showIcon
                    title={
                        <div className="crm-completeness">
                            <span>Hồ sơ đầy đủ {c.data_completeness}%</span>
                            <CompletenessBadge value={c.data_completeness} />
                        </div>
                    }
                    description={`Còn thiếu: ${c.missing.join(" · ")}`}
                />
            )}

            <Card className="crm-section">
                <Tabs
                    items={[
                        {
                            key: "overview",
                            label: "Tổng quan",
                            children: (
                                <div className="crm-stack">
                                    <Descriptions
                                        title="Thông tin doanh nghiệp"
                                        bordered
                                        size="small"
                                        column={{ xs: 1, md: 2 }}
                                        items={general}
                                    />
                                    <Descriptions
                                        title="Quy mô & tiềm năng"
                                        bordered
                                        size="small"
                                        column={{ xs: 1, md: 2 }}
                                        items={potential}
                                    />
                                    <Descriptions
                                        title="Thương mại & chăm sóc"
                                        bordered
                                        size="small"
                                        column={{ xs: 1, md: 2 }}
                                        items={commercial}
                                    />
                                    <Typography.Text type="secondary">
                                        Tạo ngày {formatDate(c.created_at)}
                                        {c.creator && ` bởi ${c.creator}`} · Vào
                                        giai đoạn hiện tại:{" "}
                                        {formatDate(c.lifecycle_stage_at)}
                                    </Typography.Text>
                                </div>
                            ),
                        },
                        {
                            key: "contacts",
                            label: `Người liên hệ (${activeContacts})`,
                            children: (
                                <>
                                    {can.update && (
                                        <div className="crm-tab-toolbar">
                                            <Button
                                                icon={<PlusOutlined />}
                                                onClick={() =>
                                                    setContactModal({
                                                        open: true,
                                                        item: null,
                                                    })
                                                }
                                            >
                                                Thêm người liên hệ
                                            </Button>
                                        </div>
                                    )}
                                    <Table<ContactItem>
                                        rowKey="id"
                                        columns={contactColumns}
                                        dataSource={contacts}
                                        pagination={false}
                                        scroll={{ x: 720 }}
                                        locale={{
                                            emptyText: (
                                                <Empty description="Chưa có người liên hệ – nên có ít nhất Purchasing và EHS/QA" />
                                            ),
                                        }}
                                    />
                                </>
                            ),
                        },
                        {
                            key: "plants",
                            label: `Nhà máy (${plants.length})`,
                            children: (
                                <>
                                    {can.update && (
                                        <div className="crm-tab-toolbar">
                                            <Button
                                                icon={<PlusOutlined />}
                                                onClick={() =>
                                                    setPlantModal({
                                                        open: true,
                                                        item: null,
                                                    })
                                                }
                                            >
                                                Thêm nhà máy
                                            </Button>
                                        </div>
                                    )}
                                    <Table<PlantItem>
                                        rowKey="id"
                                        columns={plantColumns}
                                        dataSource={plants}
                                        pagination={false}
                                        scroll={{ x: 720 }}
                                        locale={{
                                            emptyText: (
                                                <Empty description="Chưa có nhà máy / địa điểm" />
                                            ),
                                        }}
                                    />
                                </>
                            ),
                        },
                        {
                            key: "owners",
                            label: "Lịch sử phụ trách",
                            children: ownerHistory.length ? (
                                <Timeline
                                    items={ownerHistory.map((h) => ({
                                        key: h.id,
                                        title: formatDateTime(h.at),
                                        content: (
                                            <div>
                                                <strong>
                                                    {h.from ?? "Chưa giao"}
                                                </strong>{" "}
                                                →{" "}
                                                <strong>
                                                    {h.to ?? "Chưa giao"}
                                                </strong>
                                                <div className="crm-muted">
                                                    {[
                                                        h.by &&
                                                            `Thực hiện: ${h.by}`,
                                                        h.reason,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(" · ")}
                                                </div>
                                            </div>
                                        ),
                                    }))}
                                />
                            ) : (
                                <Empty description="Chưa có lịch sử" />
                            ),
                        },
                        {
                            key: "audit",
                            label: "Nhật ký thay đổi",
                            children: (
                                <Table<AuditItem>
                                    rowKey="id"
                                    size="small"
                                    columns={auditColumns}
                                    dataSource={auditLogs}
                                    pagination={{ pageSize: 10 }}
                                    scroll={{ x: 760 }}
                                />
                            ),
                        },
                    ]}
                />
            </Card>

            <ContactModal
                open={contactModal.open}
                customerId={c.id}
                contact={contactModal.item}
                plants={plants}
                options={options}
                onClose={() => setContactModal({ open: false, item: null })}
            />
            <PlantModal
                open={plantModal.open}
                customerId={c.id}
                plant={plantModal.item}
                options={options}
                onClose={() => setPlantModal({ open: false, item: null })}
            />
            {can.reassign && (
                <ReassignModal
                    open={reassignOpen}
                    customerId={c.id}
                    currentOwnerId={c.owner?.id ?? null}
                    owners={owners}
                    onClose={() => setReassignOpen(false)}
                />
            )}
        </AppLayout>
    );
}
