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
    CustomerOpportunityItem,
    CustomerOptions,
    OwnerHistoryItem,
    OwnerOption,
    PlantItem,
} from "@/types/customer";
import CustomerNotes from "@/components/customer/CustomerNotes";

interface Props extends PageProps {
    customer: CustomerDetail;
    contacts: ContactItem[];
    plants: PlantItem[];
    opportunities: CustomerOpportunityItem[];
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
    body: "Nội dung ghi chú",
    task_id: "Nhiệm vụ nhắc việc",
    stage: "Giai đoạn cơ hội",
    estimated_value: "Giá trị dự kiến",
    probability: "Xác suất (%)",
    expected_close_date: "Ngày dự kiến chốt",
    lost_reason: "Lý do thất bại",
    contact_name: "Người liên hệ",
};
const SUBJECTS: Record<string, string> = {
    Customer: "Khách hàng",
    Contact: "Người liên hệ",
    Plant: "Nhà máy",
    CustomerNote: "Ghi chú khách hàng",
    Lead: "Cơ hội kinh doanh",
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
        opportunities = [],
        ownerHistory,
        auditLogs,
        options,
        owners,
        can,
        auth,
    } = usePage<Props>().props;
    const customerDetails: DescriptionsProps["items"] = [
        {
            key: "name",
            label: "Tên khách hàng",
            children: c.name,
        },
        {
            key: "company_type",
            label: "Loại khách",
            children: labelOf(options.companyTypes, c.company_type),
        },
        {
            key: "email",
            label: "Email",
            children: c.email ? (
                <a href={`mailto:${c.email}`}>{c.email}</a>
            ) : (
                "—"
            ),
        },
        {
            key: "phone",
            label: "Số điện thoại",
            children: c.phone ? <a href={`tel:${c.phone}`}>{c.phone}</a> : "—",
        },
        {
            key: "industry",
            label: "Ngành nghề",
            children: labelOf(options.industries, c.industry),
        },
        {
            key: "status",
            label: "Trạng thái",
            children: labelOf(options.statuses, c.status),
        },
        {
            key: "address",
            label: "Địa chỉ",
            span: "filled",
            children: c.address || "—",
        },
        {
            key: "notes",
            label: "Mô tả",
            span: "filled",
            children: (
                <div className="crm-customer-description">
                    {c.notes || "Chưa có mô tả."}
                </div>
            ),
        },
        {
            key: "created_at",
            label: "Ngày giờ tạo",
            children: formatDateTime(c.created_at),
        },
        {
            key: "creator",
            label: "Người tạo",
            children: c.creator || "Không có thông tin",
        },
    ];
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
        field("Tên khách hàng", c.name),
        field("Email", c.email || "—"),
        field("Số điện thoại", c.phone || "—"),
        field("Loại khách", labelOf(options.companyTypes, c.company_type)),
        field("Ngành nghề", labelOf(options.industries, c.industry)),
        field("Trạng thái", labelOf(options.statuses, c.status)),
        field("Địa chỉ", c.address || "—", true),
        field(
            "Mô tả",
            c.notes ? (
                <div className="crm-customer-description">{c.notes}</div>
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

    const openOppValue = opportunities
        .filter((o) => o.stage === "proposal" || o.stage === "negotiation")
        .reduce((sum, o) => sum + Number(o.estimated_value), 0);
    const wonOppValue = opportunities
        .filter((o) => o.stage === "won")
        .reduce((sum, o) => sum + Number(o.estimated_value), 0);

    const opportunityColumns: TableProps<CustomerOpportunityItem>["columns"] = [
        {
            title: "Thương vụ / Liên hệ",
            key: "title",
            render: (_, opp) => (
                <div>
                    <div>
                        <strong>{opp.title}</strong>
                    </div>
                    <div className="crm-muted">
                        {opp.contact_name}
                        {opp.phone ? ` · ${opp.phone}` : ""}
                    </div>
                </div>
            ),
        },
        {
            title: "Giai đoạn & Xác suất",
            key: "stage",
            render: (_, opp) => (
                <div>
                    <Tag className={`leads-stage leads-stage--${opp.stage}`}>
                        {opp.stage_label}
                    </Tag>{" "}
                    <span className="crm-muted">{opp.probability}%</span>
                    {opp.stage === "lost" && opp.lost_reason && (
                        <div className="crm-muted">
                            Lý do: {opp.lost_reason}
                        </div>
                    )}
                </div>
            ),
        },
        {
            title: "Giá trị dự kiến",
            key: "estimated_value",
            align: "right",
            render: (_, opp) => <strong>{formatVnd(Number(opp.estimated_value))}</strong>,
        },
        {
            title: "Ngày dự kiến chốt",
            key: "expected_close_date",
            render: (_, opp) =>
                opp.expected_close_date
                    ? formatDate(opp.expected_close_date)
                    : "Chưa đặt lịch",
        },
        {
            title: "Phụ trách",
            dataIndex: "owner",
            key: "owner",
            render: (v) => v || "—",
        },
        {
            title: "Thao tác",
            key: "actions",
            width: 150,
            render: (_, opp) => (
                <Button
                    size="small"
                    onClick={() => router.visit(`/tasks?lead_id=${opp.id}`)}
                >
                    Nhiệm vụ{opp.tasks_count ? ` (${opp.tasks_count})` : ""}
                </Button>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={c.name} />
            <PageHeader
                back={{ href: "/customers", label: "Danh sách khách hàng" }}
                title={c.name}
                extra={
                    <>
                        <Button
                            onClick={() =>
                                router.visit(
                                    `/opportunities?customer_id=${c.id}`,
                                )
                            }
                        >
                            Cơ hội ({opportunities.length})
                        </Button>
                        <Button
                            onClick={() =>
                                router.visit(`/tasks?customer_id=${c.id}`)
                            }
                        >
                            Nhiệm vụ
                        </Button>

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
            <Card className="crm-section">
                <Tabs
                    defaultActiveKey="details"
                    items={[
                        {
                            key: "details",
                            label: "Chi tiết khách hàng",
                            children: (
                                <Descriptions
                                    bordered
                                    column={{ xs: 1, md: 2 }}
                                    items={customerDetails}
                                />
                            ),
                        },
                        {
                            key: "notes",
                            label: "Ghi chú",
                            children: (
                                <CustomerNotes
                                    key={c.id}
                                    customerId={c.id}
                                    currentUserId={auth.user!.id}
                                    canCreate={can.update}
                                    canCreateReminder={auth.user?.role !== "sales"}
                                />
                            ),
                        },
                    ]}
                />

                <Tabs
                    items={[
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
                            key: "opportunities",
                            label: `Cơ hội kinh doanh (${opportunities.length})`,
                            children: (
                                <>
                                    <div className="crm-tab-toolbar">
                                        <div className="crm-muted">
                                            Đang thương thảo:{" "}
                                            <strong>
                                                {formatVnd(openOppValue)}
                                            </strong>{" "}
                                            · Đã chốt thành công:{" "}
                                            <strong>
                                                {formatVnd(wonOppValue)}
                                            </strong>
                                        </div>
                                        <Button
                                            type="primary"
                                            icon={<PlusOutlined />}
                                            onClick={() =>
                                                router.visit(
                                                    `/opportunities?create_for_customer=${c.id}`,
                                                )
                                            }
                                        >
                                            Tạo Cơ hội cho khách hàng này
                                        </Button>
                                    </div>
                                    <Table<CustomerOpportunityItem>
                                        rowKey="id"
                                        columns={opportunityColumns}
                                        dataSource={opportunities}
                                        pagination={false}
                                        scroll={{ x: 780 }}
                                        locale={{
                                            emptyText: (
                                                <Empty description="Chưa có cơ hội kinh doanh nào gắn với khách hàng này." />
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
