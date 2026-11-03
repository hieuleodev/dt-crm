import { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Button,
    Card,
    Col,
    Input,
    Row,
    Select,
    Statistic,
    Table,
    Tag,
    Typography,
} from "antd";
import type { TableProps } from "antd";
import { PlusOutlined } from "@ant-design/icons";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import CompletenessBadge from "@/components/customer/CompletenessBadge";
import { stageColor } from "@/lib/format";
import type { PageProps, Paginated } from "@/types";
import type {
    CustomerOptions,
    CustomerRow,
    OwnerOption,
} from "@/types/customer";

interface Filters {
    q?: string;
    status?: string;
    tier?: string;
    industry?: string;
    company_type?: string;
    lifecycle_stage?: string;
    owner_id?: string;
    completeness?: string;
    sort?: string;
    direction?: "asc" | "desc";
}

interface Props extends PageProps {
    customers: Paginated<CustomerRow>;
    filters: Filters;
    stats: {
        total: number;
        lowCompleteness: number;
        unassigned: number | null;
        newThisMonth: number;
    };
    options: CustomerOptions;
    owners: OwnerOption[];
}

export default function Index() {
    const { customers, filters, stats, options, owners, auth } =
        usePage<Props>().props;
    const [q, setQ] = useState(filters.q ?? "");
    const isManager = auth.user?.role !== "sales";

    const apply = (patch: Partial<Filters> & { page?: number }) => {
        const next = { ...filters, ...patch } as Record<string, unknown>;
        if (!("page" in patch)) delete next.page;
        Object.keys(next).forEach(
            (k) =>
                (next[k] === undefined || next[k] === "" || next[k] === null) &&
                delete next[k],
        );
        router.get("/customers", next as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const sortOrder = (field: string) =>
        filters.sort === field
            ? filters.direction === "asc"
                ? "ascend"
                : "descend"
            : null;

    const columns: TableProps<CustomerRow>["columns"] = [
        {
            title: "Khách hàng",
            dataIndex: "name",
            sorter: true,
            sortOrder: sortOrder("name"),
            render: (_, c) => (
                <div>
                    <Link
                        href={`/customers/${c.id}`}
                        className="crm-link-strong"
                    >
                        {c.name}
                    </Link>
                    {c.tags.map((t) => (
                        <Tag key={t} className="crm-tag-inline">
                            {t}
                        </Tag>
                    ))}
                    <div className="crm-muted">
                        {[c.code, c.tax_code && `MST ${c.tax_code}`, c.location]
                            .filter(Boolean)
                            .join(" · ")}
                    </div>
                </div>
            ),
        },
        {
            title: "Ngành",
            width: 160,
            render: (_, c) => (
                <div>
                    <div>{c.industry ?? "—"}</div>
                    <div className="crm-muted">{c.company_type ?? ""}</div>
                </div>
            ),
            responsive: ["md"],
        },
        {
            title: "Hạng",
            dataIndex: "tier",
            width: 70,
            align: "center",
            render: (v) =>
                v ? (
                    <Tag
                        color={
                            v === "A"
                                ? "volcano"
                                : v === "B"
                                  ? "gold"
                                  : "default"
                        }
                    >
                        {v}
                    </Tag>
                ) : (
                    "—"
                ),
        },
        {
            title: "Giai đoạn",
            dataIndex: "lifecycle_stage",
            width: 150,
            render: (s: CustomerRow["lifecycle_stage"]) => (
                <Tag color={stageColor(s.value)}>{s.label}</Tag>
            ),
        },
        {
            title: "Phụ trách",
            dataIndex: "owner",
            width: 150,
            ellipsis: true,
            render: (v) =>
                v ?? (
                    <Typography.Text type="warning">Chưa giao</Typography.Text>
                ),
            responsive: ["md"],
        },
        {
            title: "Liên hệ",
            dataIndex: "contacts_count",
            width: 76,
            align: "center",
            responsive: ["lg"],
        },
        {
            title: "Đầy đủ",
            dataIndex: "data_completeness",
            width: 140,
            sorter: true,
            sortOrder: sortOrder("data_completeness"),
            render: (v: number) => <CompletenessBadge value={v} />,
        },
    ];

    return (
        <AppLayout>
            <Head title="Khách hàng" />
            <PageHeader
                title="Khách hàng"
                subtitle="Toàn bộ khách hàng tập trung một nơi – tìm theo tên, mã KH, MST, số điện thoại hoặc tên người liên hệ."
                extra={
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => router.visit("/customers/create")}
                    >
                        Thêm khách hàng
                    </Button>
                }
            />

            <Row gutter={[16, 16]} className="crm-section">
                <Col xs={12} lg={6}>
                    <Card className="crm-stat">
                        <Statistic
                            title="Tổng khách hàng"
                            value={stats.total}
                        />
                    </Card>
                </Col>
                <Col xs={12} lg={6}>
                    <Card className="crm-stat">
                        <Statistic
                            title="Mới trong tháng"
                            value={stats.newThisMonth}
                        />
                    </Card>
                </Col>
                <Col xs={12} lg={6}>
                    <Card
                        className="crm-stat crm-stat--clickable"
                        onClick={() =>
                            apply({
                                completeness:
                                    filters.completeness === "low"
                                        ? undefined
                                        : "low",
                            })
                        }
                    >
                        <Statistic
                            title="Thiếu thông tin (< 60%)"
                            value={stats.lowCompleteness}
                        />
                    </Card>
                </Col>
                {stats.unassigned !== null && (
                    <Col xs={12} lg={6}>
                        <Card
                            className="crm-stat crm-stat--clickable"
                            onClick={() =>
                                apply({
                                    owner_id:
                                        filters.owner_id === "none"
                                            ? undefined
                                            : "none",
                                })
                            }
                        >
                            <Statistic
                                title="Chưa có người phụ trách"
                                value={stats.unassigned}
                            />
                        </Card>
                    </Col>
                )}
            </Row>

            <Card className="crm-section">
                <div className="crm-filters">
                    <Input.Search
                        allowClear
                        placeholder="Tìm khách hàng…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onSearch={(v) => apply({ q: v })}
                        style={{ maxWidth: 360 }}
                    />
                    <Select
                        allowClear
                        placeholder="Giai đoạn"
                        value={filters.lifecycle_stage}
                        onChange={(v) => apply({ lifecycle_stage: v })}
                        options={options.lifecycleStages}
                        style={{ minWidth: 180 }}
                    />
                    <Select
                        allowClear
                        placeholder="Hạng"
                        value={filters.tier}
                        onChange={(v) => apply({ tier: v })}
                        options={options.tiers}
                        style={{ minWidth: 110 }}
                    />
                    <Select
                        allowClear
                        placeholder="Ngành"
                        value={filters.industry}
                        onChange={(v) => apply({ industry: v })}
                        options={options.industries}
                        style={{ minWidth: 150 }}
                    />
                    <Select
                        allowClear
                        placeholder="Loại hình"
                        value={filters.company_type}
                        onChange={(v) => apply({ company_type: v })}
                        options={options.companyTypes}
                        style={{ minWidth: 170 }}
                    />
                    {isManager && (
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Người phụ trách"
                            value={filters.owner_id}
                            onChange={(v) => apply({ owner_id: v })}
                            options={[
                                { value: "none", label: "— Chưa giao —" },
                                ...owners.map((o) => ({
                                    value: String(o.id),
                                    label: o.name,
                                })),
                            ]}
                            style={{ minWidth: 180 }}
                        />
                    )}
                    <Select
                        allowClear
                        placeholder="Trạng thái"
                        value={filters.status}
                        onChange={(v) => apply({ status: v })}
                        options={options.statuses}
                        style={{ minWidth: 150 }}
                    />
                </div>

                <Table<CustomerRow>
                    rowKey="id"
                    columns={columns}
                    dataSource={customers.data}
                    scroll={{ x: 1000 }}
                    onRow={(c) => ({
                        onDoubleClick: () => router.visit(`/customers/${c.id}`),
                    })}
                    pagination={{
                        current: customers.current_page,
                        pageSize: customers.per_page,
                        total: customers.total,
                        showSizeChanger: false,
                        showTotal: (t) => `${t} khách hàng`,
                    }}
                    onChange={(p, _f, sorter) => {
                        const s = Array.isArray(sorter) ? sorter[0] : sorter;
                        apply({
                            page: p.current,
                            sort: s?.order ? String(s.field) : undefined,
                            direction:
                                s?.order === "ascend"
                                    ? "asc"
                                    : s?.order === "descend"
                                      ? "desc"
                                      : undefined,
                        });
                    }}
                    locale={{ emptyText: "Không có khách hàng phù hợp" }}
                />
            </Card>
        </AppLayout>
    );
}
