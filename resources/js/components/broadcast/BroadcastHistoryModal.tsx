import { Empty, Modal, Space, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import {
    MailOutlined,
    MessageOutlined,
    NotificationOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";

const { Text } = Typography;

export interface BroadcastRecipientLog {
    id: number;
    channel: string;
    recipient_name: string | null;
    recipient_address: string | null;
    status: string;
    status_label: string;
    error_message: string | null;
    sent_at: string | null;
}

export interface BroadcastCampaignSummary {
    id: number;
    name: string;
    purpose: string;
    purpose_label: string;
    channels: string[];
    status: string;
    status_label: string;
    creator_name: string;
    articles_count: number;
    first_article_title: string | null;
    total_recipients: number;
    sent_count: number;
    failed_count: number;
    skipped_count: number;
    scheduled_at?: string | null;
    sent_at: string | null;
    recipients: BroadcastRecipientLog[];
}

interface Props {
    open: boolean;
    onClose: () => void;
    campaigns: BroadcastCampaignSummary[];
}

export default function BroadcastHistoryModal({
    open,
    onClose,
    campaigns,
}: Props) {
    const columns: ColumnsType<BroadcastCampaignSummary> = [
        {
            title: "Chiến dịch",
            key: "name",
            render: (_, record) => (
                <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                    <Text strong>{record.name}</Text>
                    <Text type="secondary" style={{ fontSize: 12 }}>
                        {record.purpose_label} · {record.articles_count} bài viết ·
                        Người gửi: {record.creator_name}
                    </Text>
                </div>
            ),
        },
        {
            title: "Kênh",
            key: "channels",
            width: 150,
            render: (_, record) => (
                <Space size={4} wrap>
                    {record.channels.includes("email") && (
                        <Tag icon={<MailOutlined />} color="blue" style={{ margin: 0 }}>
                            Email
                        </Tag>
                    )}
                    {record.channels.includes("zalo") && (
                        <Tag
                            icon={<MessageOutlined />}
                            color="geekblue"
                            style={{ margin: 0 }}
                        >
                            Zalo OA
                        </Tag>
                    )}
                </Space>
            ),
        },
        {
            title: "Kết quả gửi",
            key: "stats",
            width: 240,
            render: (_, record) => (
                <Space size={4} wrap>
                    {record.status === "scheduled" && (
                        <Tag color="blue" style={{ margin: 0 }}>
                            Đã hẹn lịch ({record.total_recipients})
                        </Tag>
                    )}
                    {record.status === "queued" && (
                        <Tag color="processing" style={{ margin: 0 }}>
                            Đang xếp hàng Queue
                        </Tag>
                    )}
                    {record.sent_count > 0 && (
                        <Tag color="success" style={{ margin: 0 }}>
                            Gửi: {record.sent_count}
                        </Tag>
                    )}
                    {record.failed_count > 0 && (
                        <Tag color="error" style={{ margin: 0 }}>
                            Lỗi: {record.failed_count}
                        </Tag>
                    )}
                    {record.skipped_count > 0 && (
                        <Tag color="default" style={{ margin: 0 }}>
                            Bỏ qua: {record.skipped_count}
                        </Tag>
                    )}
                </Space>
            ),
        },
        {
            title: "Thời gian",
            key: "sent_at",
            width: 160,
            render: (_, record) => {
                if (record.status === "scheduled" && record.scheduled_at) {
                    return (
                        <Text type="secondary" style={{ fontSize: 12 }}>
                            Hẹn:{" "}
                            {dayjs(record.scheduled_at).format(
                                "DD/MM/YYYY HH:mm",
                            )}
                        </Text>
                    );
                }

                return record.sent_at
                    ? dayjs(record.sent_at).format("DD/MM/YYYY HH:mm")
                    : "—";
            },
        },
    ];

    const recipientColumns: ColumnsType<BroadcastRecipientLog> = [
        {
            title: "Người nhận",
            dataIndex: "recipient_name",
            key: "recipient_name",
            render: (val) => val || "—",
        },
        {
            title: "Kênh",
            dataIndex: "channel",
            key: "channel",
            width: 100,
            render: (channel: string) =>
                channel === "zalo" ? (
                    <Tag color="geekblue">Zalo OA</Tag>
                ) : (
                    <Tag color="blue">Email</Tag>
                ),
        },
        {
            title: "Địa chỉ nhận",
            dataIndex: "recipient_address",
            key: "recipient_address",
            render: (val) => val || <Text type="secondary">Chưa có</Text>,
        },
        {
            title: "Trạng thái",
            key: "status",
            width: 110,
            render: (_, r) => {
                const color =
                    r.status === "seen"
                        ? "purple"
                        : r.status === "delivered"
                          ? "cyan"
                          : r.status === "sent"
                            ? "success"
                            : r.status === "failed"
                              ? "error"
                              : "default";
                return <Tag color={color}>{r.status_label}</Tag>;
            },
        },
        {
            title: "Ghi chú / Lý do",
            dataIndex: "error_message",
            key: "error_message",
            render: (val) =>
                val ? (
                    <Text type="secondary" style={{ fontSize: 12 }}>
                        {val}
                    </Text>
                ) : (
                    "—"
                ),
        },
    ];

    return (
        <Modal
            title={
                <Space>
                    <NotificationOutlined style={{ color: "#c8102e" }} />
                    <span>Lịch sử chiến dịch Tin nhắn Broadcast gần đây</span>
                </Space>
            }
            open={open}
            onCancel={onClose}
            footer={null}
            width={920}
            destroyOnClose
        >
            <Table<BroadcastCampaignSummary>
                rowKey="id"
                columns={columns}
                dataSource={campaigns}
                size="small"
                pagination={{ pageSize: 8 }}
                expandable={{
                    expandedRowRender: (record) => (
                        <Table<BroadcastRecipientLog>
                            rowKey="id"
                            columns={recipientColumns}
                            dataSource={record.recipients}
                            size="small"
                            pagination={false}
                        />
                    ),
                }}
                locale={{
                    emptyText: (
                        <Empty
                            image={Empty.PRESENTED_IMAGE_SIMPLE}
                            description="Chưa có chiến dịch Broadcast nào được gửi."
                        />
                    ),
                }}
            />
        </Modal>
    );
}
