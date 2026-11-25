import { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Alert,
    Button,
    Card,
    DatePicker,
    Empty,
    Space,
    Statistic,
    Table,
    Tabs,
    Tag,
    Typography,
} from "antd";
import type { TableColumnsType } from "antd";
import type { Dayjs } from "dayjs";
import {
    CheckCircleOutlined,
    ClockCircleOutlined,
    CloseCircleOutlined,
    HistoryOutlined,
    SolutionOutlined,
    TrophyOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";

import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import type { PageProps } from "@/types";

dayjs.extend(utc);
dayjs.extend(timezone);

type HistoryType = "lead" | "opportunity" | "task";
type DateRange = [Dayjs | null, Dayjs | null];

interface HistoryRecord {
    id: number;
    title: string;
    company?: string | null;
    contact_name?: string | null;
    status: string;
    status_label: string;
    owner: string | null;
    value?: string;
    type_label?: string;
    reference?: string | null;
    result?: string | null;
    lost_reason?: string | null;
    closed_at: string | null;
    url: string;
}

interface Props extends PageProps {
    records: {
        data: HistoryRecord[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        type: HistoryType;
        from: string | null;
        to: string | null;
    };
    summary: {
        successful: number;
        failed: number;
        breakdown: Record<
            HistoryType,
            { total: number; successful: number; failed: number }
        >;
    };
}

const HISTORY_TYPES: { key: HistoryType; label: string }[] = [
    { key: "lead", label: "Lead không phù hợp" },
    { key: "opportunity", label: "Cơ hội" },
    { key: "task", label: "Nhiệm vụ" },
];

const dateTime = (value: string | null) =>
    value
        ? dayjs(value).tz("Asia/Ho_Chi_Minh").format("DD/MM/YYYY HH:mm")
        : "—";

const money = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
});

export default function Index() {
    const { records, filters, summary } = usePage<Props>().props;
    const [range, setRange] = useState<DateRange>([
        filters.from ? dayjs(filters.from) : null,
        filters.to ? dayjs(filters.to) : null,
    ]);
    const [loading, setLoading] = useState(false);

    function visit(params: {
        type?: HistoryType;
        page?: number;
        from?: string | null;
        to?: string | null;
    }) {
        const nextType = params.type ?? filters.type;
        const from = params.from === undefined ? filters.from : params.from;
        const to = params.to === undefined ? filters.to : params.to;
        const query: Record<string, string | number> = {
            type: nextType,
            page: params.page ?? 1,
        };

        if (from) {
            query.from = from;
        }

        if (to) {
            query.to = to;
        }

        router.get("/history", query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    function applyDateRange() {
        const [from, to] = range;

        if ((from && !to) || (!from && to)) {
            return;
        }

        visit({
            from: from?.format("YYYY-MM-DD") ?? null,
            to: to?.format("YYYY-MM-DD") ?? null,
        });
    }

    const columns: TableColumnsType<HistoryRecord> = [
        {
            title:
                filters.type === "task"
                    ? "Nhiệm vụ"
                    : filters.type === "opportunity"
                      ? "Cơ hội"
                      : "Lead",
            key: "title",
            width: 320,
            render: (_, record) => (
                <div className="crm-history__record">
                    <Link href={record.url}>{record.title}</Link>
                    <Typography.Text type="secondary">
                        {filters.type === "task"
                            ? [record.type_label, record.reference]
                                  .filter(Boolean)
                                  .join(" · ") || "Công việc nội bộ"
                            : [record.company, record.contact_name]
                                  .filter(Boolean)
                                  .join(" · ") || "—"}
                    </Typography.Text>
                </div>
            ),
        },
        {
            title: "Trạng thái",
            dataIndex: "status_label",
            key: "status",
            width: 170,
            render: (label: string, record) => (
                <Tag
                    color={
                        record.status === "won" ||
                        record.status === "completed"
                            ? "green"
                            : "red"
                    }
                >
                    {label}
                </Tag>
            ),
        },
        ...(filters.type !== "task"
            ? [
                  {
                      title: "Giá trị",
                      dataIndex: "value",
                      key: "value",
                      width: 170,
                      render: (value: string) => money.format(Number(value)),
                  },
              ]
            : [
                  {
                      title: "Kết quả / lý do",
                      dataIndex: "result",
                      key: "result",
                      width: 240,
                      render: (value: string | null) => value || "—",
                  },
              ]),
        {
            title: filters.type === "task" ? "Người thực hiện" : "Phụ trách",
            dataIndex: "owner",
            key: "owner",
            width: 180,
            render: (owner: string | null) => owner || "—",
        },
        {
            title: "Ngày kết thúc",
            dataIndex: "closed_at",
            key: "closed_at",
            width: 180,
            render: (value: string | null) => dateTime(value),
        },
    ];

    const tabItems = HISTORY_TYPES.map(({ key, label }) => {
        const count = summary.breakdown[key].total;

        return {
            key,
            label: `${label} (${count.toLocaleString("vi-VN")})`,
            children:
                filters.type === key ? (
                    <Table<HistoryRecord>
                        className={
                            filters.type === "task"
                                ? "crm-history__task-table"
                                : undefined
                        }
                        rowKey="id"
                        columns={columns}
                        dataSource={records.data}
                        loading={loading}
                        scroll={{ x: 820 }}
                        locale={{
                            emptyText: (
                                <Empty description="Chưa có dữ liệu trong khoảng thời gian này." />
                            ),
                        }}
                        pagination={{
                            current: records.current_page,
                            pageSize: records.per_page,
                            total: records.total,
                            showSizeChanger: false,
                            showTotal: (total) => {
                                if (filters.type !== "task") {
                                    return `${total.toLocaleString("vi-VN")} bản ghi`;
                                }

                                return (
                                    <span className="crm-history__pagination-summary">
                                        <SolutionOutlined />
                                        {`Nhiệm vụ hoàn thành: ${summary.breakdown.task.successful.toLocaleString("vi-VN")}`}
                                        {" · "}
                                        {`Đã hủy: ${summary.breakdown.task.failed.toLocaleString("vi-VN")}`}
                                    </span>
                                );
                            },
                        }}
                        onChange={(pagination) =>
                            visit({ page: pagination.current ?? 1 })
                        }
                    />
                ) : (
                    <div className="crm-history__tab-placeholder" />
                ),
        };
    });

    return (
        <AppLayout>
            <Head title="Lịch sử" />
            <div className="crm-history">
                <PageHeader
                    title="Lịch sử"
                    subtitle="Theo dõi Lead không phù hợp, Cơ hội đã kết thúc và Nhiệm vụ đã hoàn thành hoặc hủy."
                />

                <div className="crm-history__metrics">
                    <Card className="crm-history__metric crm-history__metric--neutral">
                        <Statistic
                            title="Tổng bản ghi"
                            value={
                                Object.values(summary.breakdown).reduce(
                                    (total, item) => total + item.total,
                                    0,
                                )
                            }
                            prefix={<HistoryOutlined />}
                        />
                    </Card>
                    <Card className="crm-history__metric crm-history__metric--success">
                        <Statistic
                            title="Thành công"
                            value={summary.successful}
                            prefix={<CheckCircleOutlined />}
                        />
                    </Card>
                    <Card className="crm-history__metric crm-history__metric--failure">
                        <Statistic
                            title="Thất bại"
                            value={summary.failed}
                            prefix={<CloseCircleOutlined />}
                        />
                    </Card>
                </div>

                <Card className="crm-history__filter-card">
                    <div className="crm-history__filters">
                        <div>
                            <strong>Khoảng thời gian</strong>
                            <span>
                                {filters.from && filters.to
                                    ? `Từ ${dayjs(filters.from).format("DD/MM/YYYY")} đến ${dayjs(filters.to).format("DD/MM/YYYY")}`
                                    : "Toàn bộ lịch sử"}
                            </span>
                        </div>
                        <Space wrap>
                            <DatePicker.RangePicker
                                aria-label="Khoảng thời gian lịch sử"
                                format="DD/MM/YYYY"
                                value={range}
                                onChange={(dates) =>
                                    setRange(
                                        (dates as DateRange | null) ?? [
                                            null,
                                            null,
                                        ],
                                    )
                                }
                            />
                            <Button
                                type="primary"
                                icon={<ClockCircleOutlined />}
                                disabled={Boolean(
                                    (range[0] && !range[1]) ||
                                        (!range[0] && range[1]),
                                )}
                                loading={loading}
                                onClick={applyDateRange}
                            >
                                Lọc
                            </Button>
                            {(filters.from || filters.to) && (
                                <Button
                                    onClick={() => {
                                        setRange([null, null]);
                                        visit({ from: null, to: null });
                                    }}
                                >
                                    Xóa bộ lọc
                                </Button>
                            )}
                        </Space>
                    </div>
                </Card>

                <Card className="crm-history__records">
                    <div className="crm-history__section-heading">
                        <div>
                            <Typography.Title level={2}>
                                Phân loại lịch sử
                            </Typography.Title>
                            <Typography.Text type="secondary">
                                Chọn từng nhóm để xem các bản ghi đã kết thúc.
                            </Typography.Text>
                        </div>
                        <TrophyOutlined aria-hidden="true" />
                    </div>
                    <Tabs
                        activeKey={filters.type}
                        items={tabItems}
                        onChange={(key) =>
                            visit({ type: key as HistoryType, page: 1 })
                        }
                    />
                    {filters.type === "lead" &&
                        summary.breakdown.lead.total > 0 && (
                            <Alert
                                showIcon
                                type="info"
                                title={`${summary.breakdown.lead.failed.toLocaleString("vi-VN")} Lead không phù hợp.`}
                            />
                        )}
                </Card>
            </div>
        </AppLayout>
    );
}
