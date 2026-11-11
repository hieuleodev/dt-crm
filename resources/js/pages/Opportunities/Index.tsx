import { useEffect, useState } from "react";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
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
    Row,
    Segmented,
    Select,
    Space,
    Statistic,
    Table,
    Tag,
    Timeline,
    Typography,
} from "antd";
import type { TableColumnsType } from "antd";
import {
    AppstoreOutlined,
    CheckSquareOutlined,
    DeleteOutlined,
    EditOutlined,
    HistoryOutlined,
    PlusOutlined,
    TableOutlined,
    UserAddOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import AppLayout from "@/layouts/AppLayout";
import TaskModal from "@/components/tasks/TaskModal";
import type { PageProps } from "@/types";
import type { TaskAssignee, TaskOptions, TaskReference } from "@/types/task";

interface CustomerOption {
    id: number;
    code: string;
    name: string;
    phone: string | null;
    email: string | null;
}

interface Opportunity {
    id: number;
    owner_id?: number;
    customer_id: number | null;
    title: string;
    contact_name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    stage: string;
    estimated_value: string;
    probability: number | null;
    expected_close_date: string | null;
    lost_reason: string | null;
    notes: string | null;
    tasks_count?: number;
    owner: { id: number; name: string } | null;
    customer: CustomerOption | null;
}

interface OpportunityForm {
    owner_id: number | null;
    customer_id: number | null;
    title: string;
    contact_name: string;
    company: string;
    email: string;
    phone: string;
    stage: string;
    estimated_value: string;
    probability: number;
    expected_close_date: string;
    lost_reason: string;
    notes: string;
}

interface Filters {
    q: string;
    stage: string;
    customer_id: number | null;
}

interface StageMetric {
    count: number;
    value: string;
}

interface StageHistoryEntry {
    id: number;
    from: string | null;
    to: string;
    by: string;
    at: string | null;
    reason: string | null;
}

interface Props extends PageProps {
    opportunities: {
        data: Opportunity[];
        current_page: number;
        per_page: number;
        total: number;
    };
    board: Record<string, Opportunity[]>;
    stageHistory: Record<number, StageHistoryEntry[]>;
    filters: Filters;
    stages: Record<string, string>;
    stageProbabilities: Record<string, number>;
    lostReasons: Record<string, string>;
    customers: CustomerOption[];
    owners: TaskAssignee[];
    taskOptions: TaskOptions;
    summary: {
        total: number;
        all_count: number;
        open_count: number;
        open_value: string;
        weighted_value: string;
        won_value: string;
        by_stage: Record<string, StageMetric>;
    };
}

type ViewMode = "kanban" | "table";

const EMPTY_FORM: OpportunityForm = {
    owner_id: null,
    customer_id: null,
    title: "",
    contact_name: "",
    company: "",
    email: "",
    phone: "",
    stage: "proposal",
    estimated_value: "0",
    probability: 30,
    expected_close_date: "",
    lost_reason: "",
    notes: "",
};

const money = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
});

export default function Index({
    opportunities,
    board = {},
    stageHistory = {},
    filters,
    stages,
    stageProbabilities,
    lostReasons,
    customers,
    owners = [],
    taskOptions,
    summary,
}: Props) {
    const { auth } = usePage<Props>().props;
    const isManager = auth.user?.role !== "sales";
    const { message } = App.useApp();

    const [viewMode, setViewMode] = useState<ViewMode>("kanban");
    const [historyOpportunity, setHistoryOpportunity] = useState<Opportunity | null>(null);
    const [search, setSearch] = useState(filters.q);
    const [loading, setLoading] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Opportunity | null>(null);
    const [convertingId, setConvertingId] = useState<number | null>(null);

    const [draggingOpp, setDraggingOpp] = useState<Opportunity | null>(null);
    const [dragOverStage, setDragOverStage] = useState<string | null>(null);
    const [lostModalOpp, setLostModalOpp] = useState<Opportunity | null>(null);
    const [lostReasonInput, setLostReasonInput] = useState<string>("");
    const [stageUpdating, setStageUpdating] = useState(false);
    const [deletingOpp, setDeletingOpp] = useState<Opportunity | null>(null);
    const [deleting, setDeleting] = useState(false);

    const [taskModalOpen, setTaskModalOpen] = useState(false);
    const [taskModalRef, setTaskModalRef] = useState<TaskReference | null>(
        null,
    );

    const form = useForm<OpportunityForm>({ ...EMPTY_FORM });

    useEffect(() => {
        setSearch(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const createCustomerId = Number(params.get("create_for_customer"));

        if (createCustomerId) {
            const target = customers.find((c) => c.id === createCustomerId);
            form.clearErrors();
            form.setData({
                ...EMPTY_FORM,
                owner_id: auth.user?.id ?? null,
                customer_id: target ? target.id : null,
                company: target?.name ?? "",
                phone: target?.phone ?? "",
                email: target?.email ?? "",
                probability: stageProbabilities.proposal ?? 30,
            });
            setEditing(null);
            setModalOpen(true);

            params.delete("create_for_customer");
            const nextQuery = params.toString();
            window.history.replaceState(
                {},
                "",
                `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ""}`,
            );
        }
    }, [customers, stageProbabilities, auth.user?.id]);

    const stageOptions = Object.entries(stages).map(([value, label]) => ({
        value,
        label,
    }));

    const customerOptions = customers.map((c) => ({
        value: c.id,
        label: `${c.code} · ${c.name}`,
    }));

    const ownerOptions = owners.map((o) => ({
        value: o.id,
        label: o.name,
    }));

    const lostReasonOptions = Object.entries(lostReasons).map(
        ([, label]) => ({
            value: label,
            label,
        }),
    );

    function visit(next: Partial<Filters> = {}, page = 1) {
        const values = { ...filters, ...next };
        const query: Record<string, string | number> = { page };

        if (values.q) query.q = values.q;
        if (values.stage) query.stage = values.stage;
        if (values.customer_id) query.customer_id = values.customer_id;

        router.get("/opportunities", query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
            onError: () => {
                message.error(
                    "Không thể tải danh sách Cơ hội. Vui lòng thử lại.",
                );
            },
        });
    }

    function openCreate(defaultStage = "proposal") {
        form.clearErrors();
        const selectedCustomer = filters.customer_id
            ? customers.find((c) => c.id === filters.customer_id)
            : undefined;

        form.setData({
            ...EMPTY_FORM,
            owner_id: auth.user?.id ?? null,
            customer_id: selectedCustomer?.id ?? null,
            company: selectedCustomer?.name ?? "",
            phone: selectedCustomer?.phone ?? "",
            email: selectedCustomer?.email ?? "",
            stage: defaultStage,
            probability: stageProbabilities[defaultStage] ?? 30,
        });
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(opp: Opportunity) {
        form.clearErrors();

        const defaultProb = stageProbabilities[opp.stage] ?? 30;

        form.setData({
            owner_id: opp.owner?.id ?? opp.owner_id ?? auth.user?.id ?? null,
            customer_id: opp.customer_id,
            title: opp.title,
            contact_name: opp.contact_name,
            company: opp.company ?? "",
            email: opp.email ?? "",
            phone: opp.phone ?? "",
            stage: opp.stage,
            estimated_value: opp.estimated_value,
            probability: opp.probability ?? defaultProb,
            expected_close_date: opp.expected_close_date ?? "",
            lost_reason: opp.lost_reason ?? "",
            notes: opp.notes ?? "",
        });

        setEditing(opp);
        setModalOpen(true);
    }

    function openQuickTask(opp: Opportunity) {
        setTaskModalRef({
            value: `lead:${opp.id}`,
            label: `Lead/Cơ hội · ${opp.title}`,
        });
        setTaskModalOpen(true);
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
            form.put(`/opportunities/${editing.id}`, options);
        } else {
            form.post("/opportunities", options);
        }
    }

    function deleteOpportunity(opp: Opportunity) {
        router.delete(`/opportunities/${opp.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onSuccess: () => {
                setDeletingOpp(null);
                if (editing?.id === opp.id) {
                    setModalOpen(false);
                    setEditing(null);
                }
            },
            onError: () => {
                message.error("Không thể xóa Cơ hội. Vui lòng thử lại.");
            },
        });
    }

    function transitionStage(
        opp: Opportunity,
        nextStage: string,
        lostReason?: string,
    ) {
        if (opp.stage === nextStage && nextStage !== "lost") {
            return;
        }

        if (nextStage === "lost" && !lostReason) {
            setLostModalOpp(opp);
            setLostReasonInput(opp.lost_reason ?? "");
            return;
        }

        router.patch(
            `/opportunities/${opp.id}/stage`,
            {
                stage: nextStage,
                lost_reason: nextStage === "lost" ? lostReason : null,
            },
            {
                preserveScroll: true,
                onStart: () => setStageUpdating(true),
                onFinish: () => {
                    setStageUpdating(false);
                    setLostModalOpp(null);
                },
                onError: () => {
                    message.error(
                        "Không thể chuyển giai đoạn Cơ hội. Vui lòng thử lại.",
                    );
                },
            },
        );
    }

    function confirmLostStage() {
        if (!lostModalOpp) return;
        const reason = lostReasonInput.trim();
        if (!reason) {
            message.warning("Vui lòng chọn hoặc nhập lý do thất bại.");
            return;
        }
        transitionStage(lostModalOpp, "lost", reason);
    }

    function convertToCustomer(opp: Opportunity) {
        if (convertingId !== null) return;

        router.post(
            `/opportunities/${opp.id}/convert-customer`,
            {},
            {
                preserveScroll: true,
                onStart: () => setConvertingId(opp.id),
                onFinish: () => setConvertingId(null),
                onError: () => {
                    message.error(
                        "Không thể tạo hồ sơ Khách hàng. Vui lòng thử lại.",
                    );
                },
            },
        );
    }

    function handleCustomerChange(customerId: number | null) {
        const selectedCustomer = customers.find((c) => c.id === customerId);

        form.setData((prev) => ({
            ...prev,
            customer_id: customerId,
            company:
                selectedCustomer && !prev.company
                    ? selectedCustomer.name
                    : prev.company,
            phone:
                selectedCustomer?.phone && !prev.phone
                    ? selectedCustomer.phone
                    : prev.phone,
            email:
                selectedCustomer?.email && !prev.email
                    ? selectedCustomer.email
                    : prev.email,
        }));
    }

    function handleStageChange(nextStage: string) {
        const nextProb = stageProbabilities[nextStage] ?? form.data.probability;

        form.setData((prev) => ({
            ...prev,
            stage: nextStage,
            probability: nextProb,
            lost_reason: nextStage === "lost" ? prev.lost_reason : "",
        }));
    }

    function field(name: keyof OpportunityForm, label: string) {
        return {
            label,
            htmlFor: `opp-${name}`,
            validateStatus: form.errors[name] ? ("error" as const) : undefined,
            help: form.errors[name] ? (
                <span id={`opp-${name}-error`} role="alert">
                    {form.errors[name]}
                </span>
            ) : undefined,
        };
    }

    function inputA11y(name: keyof OpportunityForm) {
        return {
            id: `opp-${name}`,
            "aria-invalid": Boolean(form.errors[name]),
            "aria-describedby": form.errors[name]
                ? `opp-${name}-error`
                : undefined,
        };
    }

    const todayStr = dayjs().format("YYYY-MM-DD");

    function stageTimestamp(opp: Opportunity) {
        const latestEntry = stageHistory[opp.id]?.[0];
        if (!latestEntry?.at || latestEntry.to !== opp.stage) return null;

        return (
            <Typography.Text type="secondary" style={{ display: "block", fontSize: 12, marginTop: 4 }}>
                Chuyển lúc: {dayjs(latestEntry.at).format("DD/MM/YYYY HH:mm:ss")}
            </Typography.Text>
        );
    }

    function historyButton(opp: Opportunity) {
        const latestEntry = stageHistory[opp.id]?.[0];

        return (
            <Button
                size="small"
                icon={<HistoryOutlined />}
                title={latestEntry?.at
                    ? `Chuyển gần nhất: ${dayjs(latestEntry.at).format("DD/MM/YYYY HH:mm:ss")}`
                    : "Xem lịch sử chuyển giai đoạn"}
                onClick={() => setHistoryOpportunity(opp)}
            >
                Lịch sử ({stageHistory[opp.id]?.length ?? 0})
            </Button>
        );
    }

    const columns: TableColumnsType<Opportunity> = [
        {
            title: "Thương vụ / Khách hàng",
            key: "title",
            width: 290,
            render: (_, opp) => (
                <div className="leads-contact">
                    <Typography.Text strong>{opp.title}</Typography.Text>

                    {opp.customer ? (
                        <Link
                            href={`/customers/${opp.customer.id}`}
                            className="crm-link-strong"
                        >
                            {opp.customer.code} · {opp.customer.name}
                        </Link>
                    ) : (
                        opp.company && (
                            <Typography.Text type="secondary">
                                {opp.company}
                            </Typography.Text>
                        )
                    )}

                    <Typography.Text type="secondary">
                        Liên hệ: {opp.contact_name}
                        {opp.phone ? ` · ${opp.phone}` : ""}
                    </Typography.Text>
                </div>
            ),
        },
        {
            title: "Giai đoạn & Xác suất",
            key: "stage",
            width: 210,
            render: (_, opp) => {
                const prob =
                    opp.probability ?? stageProbabilities[opp.stage] ?? 0;

                return (
                    <div className="leads-contact">
                        <Space size={6} wrap>
                            <Tag
                                className={`leads-stage leads-stage--${opp.stage}`}
                            >
                                {stages[opp.stage] ?? opp.stage}
                            </Tag>
                            <Typography.Text type="secondary">
                                {prob}%
                            </Typography.Text>
                        </Space>

                        {stageTimestamp(opp)}

                        {opp.stage === "lost" && opp.lost_reason && (
                            <Typography.Text
                                type="danger"
                                style={{ fontSize: 13 }}
                            >
                                Lý do:{" "}
                                {lostReasons[opp.lost_reason] ??
                                    opp.lost_reason}
                            </Typography.Text>
                        )}
                    </div>
                );
            },
        },
        {
            title: "Giá trị dự kiến",
            key: "value",
            align: "right",
            width: 200,
            render: (_, opp) => {
                const prob =
                    opp.probability ?? stageProbabilities[opp.stage] ?? 0;
                const val = Number(opp.estimated_value);
                const weighted = Math.round(val * (prob / 100));

                return (
                    <div
                        className="leads-contact"
                        style={{ alignItems: "flex-end" }}
                    >
                        <Typography.Text strong>
                            {money.format(val)}
                        </Typography.Text>
                        {(opp.stage === "proposal" ||
                            opp.stage === "negotiation") && (
                            <Typography.Text
                                type="secondary"
                                style={{ fontSize: 12 }}
                            >
                                Kỳ vọng: {money.format(weighted)}
                            </Typography.Text>
                        )}
                    </div>
                );
            },
        },
        {
            title: "Ngày dự kiến chốt",
            key: "expected_close_date",
            width: 155,
            render: (_, opp) => {
                if (!opp.expected_close_date) {
                    return (
                        <Typography.Text type="secondary">
                            Chưa đặt lịch
                        </Typography.Text>
                    );
                }

                const isOpen =
                    opp.stage === "proposal" || opp.stage === "negotiation";
                const isOverdue =
                    isOpen && opp.expected_close_date < todayStr;

                return (
                    <div className="leads-contact">
                        <Typography.Text
                            type={isOverdue ? "danger" : undefined}
                            strong={isOverdue}
                        >
                            {dayjs(opp.expected_close_date).format(
                                "DD/MM/YYYY",
                            )}
                        </Typography.Text>
                        {isOverdue && (
                            <Typography.Text
                                type="danger"
                                style={{ fontSize: 12 }}
                            >
                                Quá hạn dự kiến
                            </Typography.Text>
                        )}
                    </div>
                );
            },
        },
        {
            title: "Phụ trách",
            key: "owner",
            width: 140,
            render: (_, opp) => opp.owner?.name ?? "Không còn hoạt động",
        },
        {
            title: "Thao tác",
            key: "actions",
            width: 330,
            render: (_, opp) => (
                <Space wrap>
                    {historyButton(opp)}
                    <Button
                        icon={<EditOutlined />}
                        onClick={() => openEdit(opp)}
                    >
                        Sửa
                    </Button>
                    <Button
                        className="crm-btn-action--task"
                        icon={<PlusOutlined />}
                        onClick={() => openQuickTask(opp)}
                    >
                        Giao việc
                    </Button>
                    <Button
                        className={
                            opp.tasks_count
                                ? "crm-btn-action--list-active"
                                : undefined
                        }
                        icon={<CheckSquareOutlined />}
                        onClick={() =>
                            router.visit(`/tasks?lead_id=${opp.id}`)
                        }
                    >
                        DS Việc
                        {opp.tasks_count ? ` (${opp.tasks_count})` : ""}
                    </Button>
                    {!opp.customer_id && (
                        <Button
                            className="crm-btn-action--convert"
                            icon={<UserAddOutlined />}
                            loading={convertingId === opp.id}
                            onClick={() => convertToCustomer(opp)}
                        >
                            Tạo KH
                        </Button>
                    )}
                    <Button
                        danger
                        icon={<DeleteOutlined />}
                        onClick={() => setDeletingOpp(opp)}
                    >
                        Xóa
                    </Button>
                </Space>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Quản lý Cơ hội" />

            <div className="leads-page">
                <header className="leads-heading">
                    <div>
                        <Typography.Title level={2}>
                            Cơ hội kinh doanh
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Theo dõi phễu thương thảo Kanban kéo - thả, dự báo
                            doanh số và chốt hợp đồng cung ứng PPE.
                        </Typography.Text>
                    </div>

                    <Space wrap>
                        <Segmented
                            aria-label="Chế độ hiển thị"
                            value={viewMode}
                            onChange={(val) => setViewMode(val as ViewMode)}
                            options={[
                                {
                                    value: "kanban",
                                    icon: <AppstoreOutlined />,
                                    label: "Bảng Kanban",
                                },
                                {
                                    value: "table",
                                    icon: <TableOutlined />,
                                    label: "Danh sách",
                                },
                            ]}
                        />
                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            onClick={() => openCreate("proposal")}
                        >
                            Tạo Cơ hội
                        </Button>
                    </Space>
                </header>

                <Row gutter={[16, 16]}>
                    {[
                        {
                            title: "Cơ hội đang mở",
                            value: `${summary.open_count} thương vụ`,
                        },
                        {
                            title: "Tổng giá trị đang mở",
                            value: money.format(Number(summary.open_value)),
                        },
                        {
                            title: "Doanh thu kỳ vọng (Weighted)",
                            value: money.format(Number(summary.weighted_value)),
                        },
                        {
                            title: "Doanh số chốt thành công",
                            value: money.format(Number(summary.won_value)),
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
                        aria-label="Tìm kiếm và lọc Cơ hội"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit({ q: search.trim() });
                        }}
                    >
                        <Input
                            aria-label="Tìm theo tên cơ hội, khách hàng, liên hệ"
                            placeholder="Tìm tên thương vụ, công ty, người liên hệ..."
                            maxLength={180}
                            allowClear
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />

                        <Select
                            aria-label="Lọc theo khách hàng"
                            placeholder="Tất cả khách hàng"
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            value={filters.customer_id ?? undefined}
                            options={customerOptions}
                            onChange={(value) => {
                                visit({
                                    customer_id: value ?? null,
                                    q: search.trim(),
                                });
                            }}
                        />

                        <Select
                            aria-label="Lọc theo giai đoạn"
                            placeholder="Tất cả giai đoạn"
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
                                visit({
                                    q: "",
                                    stage: "",
                                    customer_id: null,
                                });
                            }}
                        >
                            Xóa bộ lọc
                        </Button>
                    </form>

                    {viewMode === "kanban" ? (
                        <>
                            <p className="leads-hint" role="status">
                                Mẹo: Kéo - thả thẻ Cơ hội giữa các cột để chuyển
                                giai đoạn nhanh, hoặc bấm "+ Việc" để giao nhiệm
                                vụ ngay tại chỗ.
                            </p>

                            <div className="crm-kanban">
                                {Object.entries(stages).map(
                                    ([stageKey, stageLabel]) => {
                                        const metric = summary.by_stage[
                                            stageKey
                                        ] ?? {
                                            count: 0,
                                            value: "0",
                                        };
                                        const items = board[stageKey] ?? [];
                                        const isDragOver =
                                            dragOverStage === stageKey;

                                        return (
                                            <div
                                                key={stageKey}
                                                className={`crm-kanban__column${isDragOver ? " is-drag-over" : ""}`}
                                                onDragOver={(e) => {
                                                    e.preventDefault();
                                                    if (
                                                        dragOverStage !==
                                                        stageKey
                                                    ) {
                                                        setDragOverStage(
                                                            stageKey,
                                                        );
                                                    }
                                                }}
                                                onDragLeave={() => {
                                                    if (
                                                        dragOverStage ===
                                                        stageKey
                                                    ) {
                                                        setDragOverStage(null);
                                                    }
                                                }}
                                                onDrop={(e) => {
                                                    e.preventDefault();
                                                    setDragOverStage(null);
                                                    if (draggingOpp) {
                                                        transitionStage(
                                                            draggingOpp,
                                                            stageKey,
                                                        );
                                                        setDraggingOpp(null);
                                                    }
                                                }}
                                            >
                                                <div className="crm-kanban__header">
                                                    <div className="crm-kanban__title-row">
                                                        <span className="crm-kanban__title">
                                                            {stageLabel} (
                                                            {metric.count})
                                                        </span>
                                                        <Tag>
                                                            {stageProbabilities[
                                                                stageKey
                                                            ] ?? 0}
                                                            %
                                                        </Tag>
                                                    </div>
                                                    <div className="crm-kanban__total">
                                                        {money.format(
                                                            Number(
                                                                metric.value,
                                                            ),
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="crm-kanban__cards">
                                                    {items.length === 0 ? (
                                                        <Empty
                                                            image={
                                                                Empty.PRESENTED_IMAGE_SIMPLE
                                                            }
                                                            description="Trống"
                                                        />
                                                    ) : (
                                                        items.map((opp) => {
                                                            const prob =
                                                                opp.probability ??
                                                                stageProbabilities[
                                                                    opp.stage
                                                                ] ??
                                                                0;
                                                            const isOpen =
                                                                opp.stage ===
                                                                    "proposal" ||
                                                                opp.stage ===
                                                                    "negotiation";
                                                            const isOverdue =
                                                                isOpen &&
                                                                Boolean(
                                                                    opp.expected_close_date &&
                                                                        opp.expected_close_date <
                                                                            todayStr,
                                                                );

                                                            return (
                                                                <div
                                                                    key={opp.id}
                                                                    className="crm-kanban__card"
                                                                >
                                                                    <div
                                                                        className="crm-kanban__card-body"
                                                                        draggable
                                                                        onDragStart={() => {
                                                                            setDraggingOpp(
                                                                                opp,
                                                                            );
                                                                        }}
                                                                        onDragEnd={() => {
                                                                            setDraggingOpp(
                                                                                null,
                                                                            );
                                                                            setDragOverStage(
                                                                                null,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <Typography.Text
                                                                            strong
                                                                        >
                                                                            {
                                                                                opp.title
                                                                            }
                                                                        </Typography.Text>

                                                                        {opp.customer ? (
                                                                            <Link
                                                                                href={`/customers/${opp.customer.id}`}
                                                                                className="crm-link-strong"
                                                                            >
                                                                                {
                                                                                    opp
                                                                                        .customer
                                                                                        .code
                                                                                }{" "}
                                                                                ·{" "}
                                                                                {
                                                                                    opp
                                                                                        .customer
                                                                                        .name
                                                                                }
                                                                            </Link>
                                                                        ) : (
                                                                            opp.company && (
                                                                                <Typography.Text type="secondary">
                                                                                    {
                                                                                        opp.company
                                                                                    }
                                                                                </Typography.Text>
                                                                            )
                                                                        )}

                                                                        <div className="crm-kanban__card-value">
                                                                            {money.format(
                                                                                Number(
                                                                                    opp.estimated_value,
                                                                                ),
                                                                            )}
                                                                        </div>

                                                                        <div className="crm-kanban__card-meta">
                                                                            <span>
                                                                                LH:{" "}
                                                                                {
                                                                                    opp.contact_name
                                                                                }
                                                                            </span>
                                                                            <span>
                                                                                XS:{" "}
                                                                                {
                                                                                    prob
                                                                                }
                                                                                %
                                                                            </span>
                                                                        </div>

                                                                        <div className="crm-kanban__card-meta">
                                                                            <span>
                                                                                PT:{" "}
                                                                                {opp
                                                                                    .owner
                                                                                    ?.name ??
                                                                                    "—"}
                                                                            </span>
                                                                            {opp.expected_close_date && (
                                                                                <Typography.Text
                                                                                    type={
                                                                                        isOverdue
                                                                                            ? "danger"
                                                                                            : "secondary"
                                                                                    }
                                                                                    style={{
                                                                                        fontSize: 12,
                                                                                    }}
                                                                                >
                                                                                    {dayjs(
                                                                                        opp.expected_close_date,
                                                                                    ).format(
                                                                                        "DD/MM/YYYY",
                                                                                    )}
                                                                                    {isOverdue
                                                                                        ? " (Quá hạn)"
                                                                                        : ""}
                                                                                </Typography.Text>
                                                                            )}
                                                                        </div>

                                                                        {opp.stage ===
                                                                            "lost" &&
                                                                            opp.lost_reason && (
                                                                                <Typography.Text
                                                                                    type="danger"
                                                                                    style={{
                                                                                        fontSize: 12,
                                                                                    }}
                                                                                >
                                                                                    Lý
                                                                                    do:{" "}
                                                                                    {lostReasons[
                                                                                        opp
                                                                                            .lost_reason
                                                                                    ] ??
                                                                                        opp.lost_reason}
                                                                                </Typography.Text>
                                                                            )}
                                                                    </div>

                                                                    <div className="crm-kanban__card-interactive">
                                                                        <Select
                                                                            size="small"
                                                                            style={{
                                                                                width: "100%",
                                                                            }}
                                                                            aria-label="Chuyển nhanh giai đoạn"
                                                                            value={
                                                                                opp.stage
                                                                            }
                                                                            options={
                                                                                stageOptions
                                                                            }
                                                                            disabled={
                                                                                stageUpdating
                                                                            }
                                                                            onChange={(
                                                                                nextStage,
                                                                            ) =>
                                                                                transitionStage(
                                                                                    opp,
                                                                                    nextStage,
                                                                                )
                                                                            }
                                                                        />
                                                                        {stageTimestamp(opp)}
                                                                    </div>

                                                                    <div className="crm-kanban__card-actions">
                                                                        <Button
                                                                            size="small"
                                                                            icon={
                                                                                <EditOutlined />
                                                                            }
                                                                            onClick={() =>
                                                                                openEdit(
                                                                                    opp,
                                                                                )
                                                                            }
                                                                        >
                                                                            Sửa
                                                                        </Button>
                                                                        {historyButton(opp)}
                                                                        <Button
                                                                            size="small"
                                                                            className="crm-btn-action--task"
                                                                            icon={
                                                                                <PlusOutlined />
                                                                            }
                                                                            onClick={() =>
                                                                                openQuickTask(
                                                                                    opp,
                                                                                )
                                                                            }
                                                                        >
                                                                            Giao
                                                                            việc
                                                                        </Button>
                                                                        <Button
                                                                            size="small"
                                                                            className={
                                                                                opp.tasks_count
                                                                                    ? "crm-btn-action--list-active"
                                                                                    : undefined
                                                                            }
                                                                            icon={
                                                                                <CheckSquareOutlined />
                                                                            }
                                                                            onClick={() =>
                                                                                router.visit(
                                                                                    `/tasks?lead_id=${opp.id}`,
                                                                                )
                                                                            }
                                                                        >
                                                                            DS (
                                                                            {opp.tasks_count ??
                                                                                0}
                                                                            )
                                                                        </Button>
                                                                        {!opp.customer_id && (
                                                                            <Button
                                                                                size="small"
                                                                                className="crm-btn-action--convert"
                                                                                icon={
                                                                                    <UserAddOutlined />
                                                                                }
                                                                                loading={
                                                                                    convertingId ===
                                                                                    opp.id
                                                                                }
                                                                                onClick={() =>
                                                                                    convertToCustomer(
                                                                                        opp,
                                                                                    )
                                                                                }
                                                                            >
                                                                                Tạo
                                                                                KH
                                                                            </Button>
                                                                        )}
                                                                        <Button
                                                                            size="small"
                                                                            danger
                                                                            icon={
                                                                                <DeleteOutlined />
                                                                            }
                                                                            onClick={() =>
                                                                                setDeletingOpp(
                                                                                    opp,
                                                                                )
                                                                            }
                                                                        >
                                                                            Xóa
                                                                        </Button>
                                                                    </div>
                                                                </div>
                                                            );
                                                        })
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    },
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            <p className="leads-hint" role="status">
                                {summary.total} cơ hội theo bộ lọc hiện tại
                            </p>

                            <Table<Opportunity>
                                rowKey="id"
                                columns={columns}
                                dataSource={opportunities.data}
                                loading={loading}
                                scroll={{ x: 1360 }}
                                locale={{
                                    emptyText: (
                                        <Empty
                                            image={Empty.PRESENTED_IMAGE_SIMPLE}
                                            description="Chưa có Cơ hội kinh doanh nào phù hợp. Hãy tạo Cơ hội mới hoặc chuyển đổi từ Lead đủ điều kiện."
                                        />
                                    ),
                                }}
                                pagination={{
                                    current: opportunities.current_page,
                                    pageSize: opportunities.per_page,
                                    total: opportunities.total,
                                    showSizeChanger: false,
                                    hideOnSinglePage: true,
                                    onChange: (page) => visit({}, page),
                                }}
                            />
                        </>
                    )}
                </Card>
            </div>

            <Modal
                title={`Lịch sử giai đoạn · ${historyOpportunity?.title ?? ""}`}
                open={historyOpportunity !== null}
                onCancel={() => setHistoryOpportunity(null)}
                footer={null}
                width={640}
            >
                <Typography.Paragraph type="secondary">
                    Thời gian hiển thị theo múi giờ thiết bị. Các lần chuyển mới nhất ở trên cùng.
                </Typography.Paragraph>
                {historyOpportunity && stageHistory[historyOpportunity.id]?.length ? (
                    <Timeline
                        items={stageHistory[historyOpportunity.id].map((entry) => ({
                            key: entry.id,
                            color: entry.to === "won" ? "green" : entry.to === "lost" ? "red" : "blue",
                            title: entry.at ? dayjs(entry.at).format("DD/MM/YYYY HH:mm:ss") : "Chưa có thời gian",
                            content: (
                                <div>
                                    <Typography.Text strong>
                                        {entry.from ? (stages[entry.from] ?? (entry.from === "qualified" ? "Lead đủ điều kiện" : entry.from)) : "Tạo cơ hội"}
                                        {" → "}{stages[entry.to] ?? entry.to}
                                    </Typography.Text>
                                    <div>Thực hiện: {entry.by}</div>
                                    {entry.reason && (
                                        <Typography.Text type="secondary">
                                            Lý do: {lostReasons[entry.reason] ?? entry.reason}
                                        </Typography.Text>
                                    )}
                                </div>
                            ),
                        }))}
                    />
                ) : (
                    <Empty description="Chưa có lịch sử chuyển giai đoạn được ghi nhận." />
                )}
            </Modal>

            <Modal
                title="Xác nhận xóa Cơ hội kinh doanh"
                open={deletingOpp !== null}
                zIndex={1100}
                focusable={{ trap: false }}
                mask={{ closable: !deleting }}
                closable={!deleting}
                keyboard={!deleting}
                onCancel={() => {
                    if (!deleting) setDeletingOpp(null);
                }}
                okText="Xóa Cơ hội"
                okButtonProps={{ danger: true }}
                cancelText="Hủy"
                confirmLoading={deleting}
                onOk={() => {
                    if (deletingOpp) deleteOpportunity(deletingOpp);
                }}
            >
                <Typography.Paragraph style={{ marginBottom: 0 }}>
                    Bạn có chắc chắn muốn xóa cơ hội{" "}
                    <Typography.Text strong>
                        {deletingOpp?.title ?? ""}
                    </Typography.Text>
                    ? Thao tác này không thể hoàn tác.
                </Typography.Paragraph>
            </Modal>

            <Modal
                title="Xác nhận lý do thất bại"
                open={lostModalOpp !== null}
                focusable={{ trap: false }}
                mask={{ closable: !stageUpdating }}
                onCancel={() => {
                    if (!stageUpdating) setLostModalOpp(null);
                }}
                okText="Xác nhận chuyển sang Thất bại"
                cancelText="Hủy"
                confirmLoading={stageUpdating}
                onOk={confirmLostStage}
            >
                <Form layout="vertical">
                    <Form.Item
                        label={`Lý do thất bại cho thương vụ "${lostModalOpp?.title ?? ""}"`}
                        required
                    >
                        <Select
                            showSearch
                            allowClear
                            placeholder="Chọn lý do thất bại"
                            value={lostReasonInput || undefined}
                            options={lostReasonOptions}
                            onChange={(val) => setLostReasonInput(val ?? "")}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={
                    editing
                        ? "Chỉnh sửa Cơ hội kinh doanh"
                        : "Tạo Cơ hội kinh doanh"
                }
                open={modalOpen}
                onCancel={closeModal}
                width={760}
                focusable={{ trap: false }}
                mask={{ closable: !form.processing }}
                keyboard={!form.processing}
                closable={!form.processing}
                footer={[
                    ...(editing
                        ? [
                              <Button
                                  key="delete"
                                  danger
                                  icon={<DeleteOutlined />}
                                  disabled={form.processing || deleting}
                                  onClick={() => setDeletingOpp(editing)}
                              >
                                  Xóa
                              </Button>,
                          ]
                        : []),
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
                        {editing ? "Lưu thay đổi" : "Tạo Cơ hội"}
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

                    <Form.Item
                        {...field("title", "Tên cơ hội / Thương vụ")}
                        required
                    >
                        <Input
                            {...inputA11y("title")}
                            placeholder="VD: Cung cấp giày & găng tay bảo hộ Nhà máy Bắc Ninh Q4"
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
                                {...field(
                                    "customer_id",
                                    "Liên kết Khách hàng có sẵn",
                                )}
                            >
                                <Select
                                    {...inputA11y("customer_id")}
                                    allowClear
                                    showSearch
                                    optionFilterProp="label"
                                    placeholder="Chọn khách hàng trong CRM (nếu có)"
                                    value={form.data.customer_id ?? undefined}
                                    options={customerOptions}
                                    onChange={(value) =>
                                        handleCustomerChange(value ?? null)
                                    }
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={12}>
                            <Form.Item
                                {...field("company", "Tên doanh nghiệp")}
                            >
                                <Input
                                    {...inputA11y("company")}
                                    maxLength={180}
                                    placeholder="Tên công ty / nhà máy"
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
                            <Form.Item
                                {...field("contact_name", "Người liên hệ")}
                                required
                            >
                                <Input
                                    {...inputA11y("contact_name")}
                                    maxLength={120}
                                    placeholder="Họ tên người phụ trách mua hàng / EHS"
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
                            <Form.Item
                                {...field(
                                    "expected_close_date",
                                    "Ngày dự kiến chốt",
                                )}
                            >
                                <Input
                                    {...inputA11y("expected_close_date")}
                                    type="date"
                                    value={form.data.expected_close_date}
                                    onChange={(event) =>
                                        form.setData(
                                            "expected_close_date",
                                            event.target.value,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>

                        {isManager && (
                            <Col xs={24} sm={12}>
                                <Form.Item
                                    {...field("owner_id", "Người phụ trách")}
                                >
                                    <Select
                                        {...inputA11y("owner_id")}
                                        showSearch
                                        optionFilterProp="label"
                                        placeholder="Chọn nhân viên phụ trách"
                                        value={form.data.owner_id ?? undefined}
                                        options={ownerOptions}
                                        onChange={(val) =>
                                            form.setData("owner_id", val ?? null)
                                        }
                                    />
                                </Form.Item>
                            </Col>
                        )}

                        <Col xs={24} sm={isManager ? 12 : 8}>
                            <Form.Item
                                {...field("stage", "Giai đoạn")}
                                required
                            >
                                <Select
                                    {...inputA11y("stage")}
                                    value={form.data.stage}
                                    options={stageOptions}
                                    onChange={handleStageChange}
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={isManager ? 12 : 8}>
                            <Form.Item
                                {...field(
                                    "probability",
                                    "Xác suất chốt (%)",
                                )}
                            >
                                <InputNumber<number>
                                    {...inputA11y("probability")}
                                    className="leads-value-input"
                                    min={0}
                                    max={100}
                                    disabled={
                                        form.data.stage === "won" ||
                                        form.data.stage === "lost"
                                    }
                                    value={form.data.probability}
                                    onChange={(value) =>
                                        form.setData(
                                            "probability",
                                            value ?? 0,
                                        )
                                    }
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} sm={isManager ? 12 : 8}>
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
                                    step="1000000"
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

                    {form.data.stage === "lost" && (
                        <Form.Item
                            {...field("lost_reason", "Lý do thất bại")}
                            required
                        >
                            <Select
                                {...inputA11y("lost_reason")}
                                showSearch
                                allowClear
                                placeholder="Chọn lý do thất bại"
                                value={form.data.lost_reason || undefined}
                                options={lostReasonOptions}
                                onChange={(value) =>
                                    form.setData("lost_reason", value ?? "")
                                }
                            />
                        </Form.Item>
                    )}

                    <Form.Item
                        {...field("notes", "Ghi chú thương thảo / Yêu cầu PPE")}
                    >
                        <Input.TextArea
                            {...inputA11y("notes")}
                            rows={4}
                            maxLength={5000}
                            showCount
                            placeholder="Ghi chú quy cách hàng hóa, mẫu thử cần gửi, điều khoản công nợ đang đàm phán..."
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData("notes", event.target.value)
                            }
                        />
                    </Form.Item>
                </Form>
            </Modal>

            {taskOptions && (
                <TaskModal
                    open={taskModalOpen}
                    task={null}
                    options={taskOptions}
                    assignees={owners}
                    currentUserId={auth.user?.id ?? 0}
                    initialReference={taskModalRef}
                    onClose={() => setTaskModalOpen(false)}
                />
            )}
        </AppLayout>
    );
}
