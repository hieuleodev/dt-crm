import { useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Alert, Button, Input, Select, Table, Tag } from "antd";
import type { TableProps } from "antd";
import { PlusOutlined } from "@ant-design/icons";

import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";

import type { PageProps, Paginated } from "@/types";
import type { CustomerOptions, CustomerRow } from "@/types/customer";

interface Filters {
    q?: string;
    status?: string;
    industry?: string;
    company_type?: string;
    sort?: string;
    direction?: "asc" | "desc";
}

interface Props extends PageProps {
    customers: Paginated<CustomerRow>;
    filters: Filters;
    options: CustomerOptions;
}

export default function Index() {
    const { customers, filters, options } = usePage<Props>().props;

    const [q, setQ] = useState(filters.q ?? "");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        setQ(filters.q ?? "");
    }, [filters.q]);

    function apply(patch: Partial<Filters> & { page?: number } = {}) {
        // Chỉ giữ bộ lọc xuất hiện trên giao diện mới.
        const next = {
            q: filters.q,
            status: filters.status,
            industry: filters.industry,
            company_type: filters.company_type,
            sort: filters.sort === "name" ? "name" : undefined,
            direction: filters.sort === "name" ? filters.direction : undefined,
            page: 1,
            ...patch,
        };

        const query: Record<string, string | number> = {};

        for (const [key, value] of Object.entries(next)) {
            if (value !== undefined && value !== null && value !== "") {
                query[key] = value;
            }
        }

        router.get("/customers", query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                setLoading(true);
                setError("");
            },
            onFinish: () => setLoading(false),
            onError: () => {
                setError("Không tải được danh sách khách hàng.");
            },
        });
    }

    const columns: TableProps<CustomerRow>["columns"] = [
        {
            title: "Tên khách hàng",
            dataIndex: "name",
            key: "name",
            width: 320,
            sorter: true,
            sortOrder:
                filters.sort === "name"
                    ? filters.direction === "asc"
                        ? "ascend"
                        : "descend"
                    : null,
            render: (_, customer) => (
                <div className="crm-accounts__identity">
                    <Link
                        href={`/customers/${customer.id}`}
                        className="crm-accounts__name"
                    >
                        {customer.name}
                    </Link>

                    <span className="crm-accounts__secondary">
                        {customer.email || customer.code}
                    </span>
                </div>
            ),
        },
        {
            title: "Ngành nghề",
            dataIndex: "industry",
            key: "industry",
            width: 190,
            render: (value: string | null) => value || "—",
        },
        {
            title: "Loại khách",
            dataIndex: "company_type",
            key: "company_type",
            width: 190,
            render: (value: string | null) => value || "—",
        },
        {
            title: "Số điện thoại",
            dataIndex: "phone",
            key: "phone",
            width: 160,
            render: (value: string | null) =>
                value ? <a href={`tel:${value}`}>{value}</a> : "—",
        },
        {
            title: "Trạng thái",
            dataIndex: "status",
            key: "status",
            width: 170,
            render: (status: CustomerRow["status"]) => (
                <Tag
                    className={`crm-accounts__status crm-accounts__status--${status.value}`}
                >
                    {status.label}
                </Tag>
            ),
        },
        {
            title: "",
            key: "actions",
            width: 110,
            render: (_, customer) => (
                <Link
                    href={`/customers/${customer.id}`}
                    className="crm-accounts__detail"
                    aria-label={`Xem chi tiết ${customer.name}`}
                >
                    Chi tiết
                </Link>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Khách hàng" />

            <div className="crm-accounts">
                <PageHeader
                    title="Khách hàng"
                    extra={
                        <Button
                            icon={<PlusOutlined />}
                            onClick={() => router.visit("/customers/create")}
                        >
                            Thêm khách hàng
                        </Button>
                    }
                />

                <section
                    className="crm-accounts__toolbar"
                    aria-label="Tìm kiếm khách hàng"
                >
                    <div className="crm-accounts__search">
                        <Select
                            aria-label="Lọc trạng thái"
                            value={filters.status || ""}
                            disabled={loading}
                            options={[
                                { value: "", label: "Tất cả trạng thái" },
                                ...options.statuses,
                            ]}
                            onChange={(value) =>
                                apply({
                                    status: value || undefined,
                                    q: q.trim(),
                                })
                            }
                        />

                        <Input.Search
                            aria-label="Tìm tên, email hoặc số điện thoại"
                            placeholder="Tìm tên, email, số điện thoại..."
                            allowClear
                            maxLength={180}
                            value={q}
                            loading={loading}
                            onChange={(event) => setQ(event.target.value)}
                            onSearch={(value) => apply({ q: value.trim() })}
                        />
                    </div>

                    <div className="crm-accounts__filters">
                        <Select
                            aria-label="Lọc ngành nghề"
                            placeholder="Tất cả ngành nghề"
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            disabled={loading}
                            value={filters.industry}
                            options={options.industries}
                            onChange={(value) =>
                                apply({
                                    industry: value,
                                    q: q.trim(),
                                })
                            }
                        />

                        <Select
                            aria-label="Lọc loại khách"
                            placeholder="Tất cả loại khách"
                            allowClear
                            disabled={loading}
                            value={filters.company_type}
                            options={options.companyTypes}
                            onChange={(value) =>
                                apply({
                                    company_type: value,
                                    q: q.trim(),
                                })
                            }
                        />
                    </div>
                </section>

                {error && (
                    <Alert type="error" showIcon title={error} role="alert" />
                )}

                <div className="crm-accounts__table">
                    <Table<CustomerRow>
                        rowKey="id"
                        size="small"
                        columns={columns}
                        dataSource={customers.data}
                        loading={loading}
                        scroll={{ x: 1140 }}
                        rowClassName="crm-accounts__row"
                        onRow={(customer) => ({
                            onClick: (event) => {
                                const target = event.target;

                                // Link, điện thoại và điều khiển riêng
                                // tự xử lý click, tránh điều hướng hai lần.
                                if (
                                    target instanceof Element &&
                                    target.closest(
                                        "a, button, input, [role='button']",
                                    )
                                ) {
                                    return;
                                }

                                // Không mở trang khi người dùng chọn chữ.
                                if (window.getSelection()?.toString()) {
                                    return;
                                }

                                router.visit(`/customers/${customer.id}`);
                            },
                        })}
                        pagination={{
                            position: ["topRight"],
                            current: customers.current_page,
                            pageSize: customers.per_page,
                            total: customers.total,
                            showSizeChanger: false,
                            showTotal: (total, range) =>
                                total === 0
                                    ? "0 khách hàng"
                                    : `${range[0]}–${range[1]} / ${total}`,
                        }}
                        onChange={(pagination, _filters, sorter, extra) => {
                            const sort = Array.isArray(sorter)
                                ? sorter[0]
                                : sorter;

                            apply({
                                page:
                                    extra.action === "paginate"
                                        ? pagination.current
                                        : 1,
                                sort: sort?.order ? "name" : undefined,
                                direction:
                                    sort?.order === "ascend"
                                        ? "asc"
                                        : sort?.order === "descend"
                                          ? "desc"
                                          : undefined,
                            });
                        }}
                        locale={{
                            emptyText: "Không có khách hàng phù hợp.",
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
