import { useEffect, useState } from "react";
import { useForm, usePage } from "@inertiajs/react";
import {
    Alert,
    Button,
    Card,
    Checkbox,
    Col,
    Divider,
    Form,
    Input,
    Modal,
    Radio,
    Row,
    Select,
    Space,
    Tag,
    Typography,
} from "antd";
import {
    ClockCircleOutlined,
    DeleteOutlined,
    LinkOutlined,
    MailOutlined,
    MessageOutlined,
    NotificationOutlined,
    PictureOutlined,
    PlusOutlined,
    SafetyCertificateOutlined,
    SendOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import type { PageProps } from "@/types";

const { Text, Title } = Typography;
const { TextArea } = Input;

export interface BroadcastArticleDraft {
    title: string;
    summary: string;
    cover_image_url: string;
    cta_label: string;
    cta_url: string;
}

export interface BroadcastTargetOpportunity {
    id: number;
    title: string;
    company: string;
    contact_name: string | null;
    email: string | null;
    zalo_user_id: string | null;
    can_email_broadcast: boolean;
    can_zalo_broadcast: boolean;
}

export interface BroadcastConfig {
    purposes: Record<string, string>;
    channels: Record<string, string>;
    zalo_configured: boolean;
    zalo_sandbox: boolean;
    zalo_oauth_connected?: boolean;
    zalo_oa_name?: string | null;
    zalo_token_expires_at?: string | null;
    zalo_oauth_install_url?: string;
    zalo_webhook_url?: string;
}

interface Props {
    open: boolean;
    onClose: () => void;
    targets: BroadcastTargetOpportunity[];
    config: BroadcastConfig;
}

const emptyArticle = (): BroadcastArticleDraft => ({
    title: "",
    summary: "",
    cover_image_url: "",
    cta_label: "Xem chi tiết",
    cta_url: "",
});

export default function BroadcastComposerModal({
    open,
    onClose,
    targets,
    config,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const isManager = auth.user?.role !== "sales";
    const [sendMode, setSendMode] = useState<"now" | "schedule">("now");

    const form = useForm<{
        name: string;
        purpose: string;
        channels: string[];
        opportunity_ids: number[];
        scheduled_at: string;
        articles: BroadcastArticleDraft[];
    }>({
        name: "",
        purpose: "repurchase_reminder",
        channels: ["email", "zalo"],
        opportunity_ids: [],
        scheduled_at: "",
        articles: [
            {
                title: "Nhắc lịch bổ sung vật tư định kỳ — Ưu đãi cho khách hàng thân thiết",
                summary:
                    "Kính chào Quý doanh nghiệp, đã đến chu kỳ 15–30 ngày kể từ đợt giao hàng gần nhất. Vui lòng kiểm tra tồn kho và liên hệ để được ưu tiên giao nhanh trong tuần này.",
                cover_image_url: "",
                cta_label: "Yêu cầu báo giá lại",
                cta_url: "",
            },
        ],
    });

    useEffect(() => {
        if (!open) return;

        setSendMode("now");
        const ids = targets.map((t) => t.id);
        const defaultName =
            targets.length === 1
                ? `Nhắc mua lại: ${targets[0].company}`
                : `Chiến dịch nhắc mua lại (${targets.length} khách hàng)`;

        form.setData({
            name: defaultName,
            purpose: "repurchase_reminder",
            channels: ["email", "zalo"],
            opportunity_ids: ids,
            scheduled_at: "",
            articles: [
                {
                    title: "Nhắc lịch bổ sung vật tư định kỳ — Ưu đãi cho khách hàng thân thiết",
                    summary:
                        "Kính chào Quý doanh nghiệp, đã đến chu kỳ 15–30 ngày kể từ đợt báo giá / giao hàng gần nhất. Vui lòng kiểm tra tồn kho và phản hồi để chúng tôi giữ giá ưu đãi.",
                    cover_image_url: "",
                    cta_label: "Yêu cầu báo giá lại",
                    cta_url: "",
                },
            ],
        });
        form.clearErrors();
    }, [open, targets]);

    const emailReadyCount = targets.filter((t) => t.can_email_broadcast).length;
    const zaloReadyCount = targets.filter((t) => t.can_zalo_broadcast).length;

    function updateArticle(
        index: number,
        field: keyof BroadcastArticleDraft,
        value: string,
    ) {
        const next = form.data.articles.map((item, idx) =>
            idx === index ? { ...item, [field]: value } : item,
        );
        form.setData("articles", next);
    }

    function addArticle() {
        if (form.data.articles.length >= 5) return;
        form.setData("articles", [...form.data.articles, emptyArticle()]);
    }

    function removeArticle(index: number) {
        if (form.data.articles.length <= 1) return;
        form.setData(
            "articles",
            form.data.articles.filter((_, idx) => idx !== index),
        );
    }

    function handleSubmit() {
        form.post("/repurchase-reminders/broadcast", {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
            },
        });
    }

    const heroArticle = form.data.articles[0];
    const secondaryArticles = form.data.articles.slice(1);
    const zaloSelected = form.data.channels.includes("zalo");

    return (
        <Modal
            title={
                <Space>
                    <NotificationOutlined style={{ color: "#c8102e" }} />
                    <span>
                        Soạn &amp; Gửi Tin nhắn Broadcast đa bài viết (
                        {targets.length} đợt đã chọn)
                    </span>
                </Space>
            }
            open={open}
            onCancel={onClose}
            footer={null}
            width={1060}
            destroyOnClose
        >
            <Row gutter={20} style={{ marginTop: 12 }}>
                {/* Cột trái: Form cấu hình & đa bài viết */}
                <Col xs={24} lg={14}>
                    <Form layout="vertical" onFinish={handleSubmit}>
                        <Alert
                            type="info"
                            showIcon
                            style={{ marginBottom: 14 }}
                            message={`Đối tượng đã chọn: ${targets.length} đợt giao dịch`}
                            description={
                                <Space wrap size={8} style={{ marginTop: 4 }}>
                                    <Tag icon={<MailOutlined />} color="blue">
                                        Email hợp lệ: {emailReadyCount}/
                                        {targets.length}
                                    </Tag>
                                    <Tag
                                        icon={<MessageOutlined />}
                                        color={
                                            zaloReadyCount > 0
                                                ? "geekblue"
                                                : "default"
                                        }
                                    >
                                        Zalo OA User ID hợp lệ: {zaloReadyCount}
                                        /{targets.length}
                                    </Tag>
                                </Space>
                            }
                        />

                        {zaloSelected &&
                            !config.zalo_configured &&
                            !config.zalo_sandbox && (
                                <Alert
                                    type="warning"
                                    showIcon
                                    style={{ marginBottom: 14 }}
                                    message="Chưa kết nối Zalo OA OAuth hoặc ZALO_OA_ACCESS_TOKEN"
                                    description={
                                        <Space direction="vertical" size={6}>
                                            <span>
                                                Kênh Email vẫn gửi bình thường. Để
                                                gửi thật qua Zalo OA, hãy kết nối
                                                OAuth hoặc bật{" "}
                                                <code>ZALO_OA_SANDBOX=true</code>{" "}
                                                trong <code>.env</code> để chạy
                                                thử.
                                            </span>
                                            {isManager &&
                                                config.zalo_oauth_install_url && (
                                                    <Button
                                                        size="small"
                                                        type="primary"
                                                        icon={
                                                            <SafetyCertificateOutlined />
                                                        }
                                                        href={
                                                            config.zalo_oauth_install_url
                                                        }
                                                    >
                                                        Kết nối Zalo OA (OAuth v4)
                                                    </Button>
                                                )}
                                        </Space>
                                    }
                                />
                            )}

                        {zaloSelected &&
                            config.zalo_oauth_connected &&
                            !config.zalo_sandbox && (
                                <Alert
                                    type="success"
                                    showIcon
                                    style={{ marginBottom: 14 }}
                                    message={`Đã kết nối Zalo OA: ${config.zalo_oa_name || "Official Account"}`}
                                    description={
                                        config.zalo_token_expires_at
                                            ? `Token tự động làm mới định kỳ (hạn hiện tại: ${dayjs(config.zalo_token_expires_at).format("HH:mm DD/MM/YYYY")}).`
                                            : "Access/Refresh token đang được lưu mã hóa trên máy chủ."
                                    }
                                />
                            )}

                        {zaloSelected && config.zalo_sandbox && (
                            <Alert
                                type="success"
                                showIcon
                                style={{ marginBottom: 14 }}
                                message="Zalo OA đang chạy ở chế độ Sandbox (Mô phỏng)"
                                description="Lượt gửi Zalo OA sẽ được ghi nhận thành công mô phỏng mà không gọi API thật."
                            />
                        )}

                        <Row gutter={12}>
                            <Col xs={24} sm={14}>
                                <Form.Item
                                    label="Tên chiến dịch Broadcast"
                                    required
                                    validateStatus={
                                        form.errors.name ? "error" : ""
                                    }
                                    help={form.errors.name}
                                >
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData("name", e.target.value)
                                        }
                                        placeholder="VD: Nhắc mua lại vật tư BHLĐ tháng 11"
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} sm={10}>
                                <Form.Item
                                    label="Mục đích gửi"
                                    required
                                    validateStatus={
                                        form.errors.purpose ? "error" : ""
                                    }
                                    help={form.errors.purpose}
                                >
                                    <Select
                                        value={form.data.purpose}
                                        onChange={(val) =>
                                            form.setData("purpose", val)
                                        }
                                        options={Object.entries(
                                            config.purposes,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        }))}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>

                        <Form.Item
                            label="Kênh gửi tin nhắn doanh nghiệp"
                            required
                            validateStatus={form.errors.channels ? "error" : ""}
                            help={form.errors.channels}
                        >
                            <Checkbox.Group
                                value={form.data.channels}
                                onChange={(vals) =>
                                    form.setData("channels", vals as string[])
                                }
                                options={Object.entries(config.channels).map(
                                    ([value, label]) => ({
                                        value,
                                        label,
                                    }),
                                )}
                            />
                        </Form.Item>

                        <Row gutter={12}>
                            <Col xs={24} sm={12}>
                                <Form.Item label="Thời điểm gửi">
                                    <Radio.Group
                                        value={sendMode}
                                        onChange={(e) => {
                                            const mode = e.target.value as
                                                | "now"
                                                | "schedule";
                                            setSendMode(mode);
                                            if (mode === "now") {
                                                form.setData(
                                                    "scheduled_at",
                                                    "",
                                                );
                                            } else if (
                                                !form.data.scheduled_at
                                            ) {
                                                form.setData(
                                                    "scheduled_at",
                                                    dayjs()
                                                        .add(1, "hour")
                                                        .format(
                                                            "YYYY-MM-DDTHH:mm",
                                                        ),
                                                );
                                            }
                                        }}
                                    >
                                        <Radio value="now">
                                            Gửi ngay (Queue)
                                        </Radio>
                                        <Radio value="schedule">
                                            Hẹn lịch gửi
                                        </Radio>
                                    </Radio.Group>
                                </Form.Item>
                            </Col>

                            {sendMode === "schedule" && (
                                <Col xs={24} sm={12}>
                                    <Form.Item
                                        label="Ngày & Giờ hẹn gửi"
                                        required
                                        validateStatus={
                                            form.errors.scheduled_at
                                                ? "error"
                                                : ""
                                        }
                                        help={form.errors.scheduled_at}
                                    >
                                        <Input
                                            type="datetime-local"
                                            value={form.data.scheduled_at}
                                            onChange={(e) =>
                                                form.setData(
                                                    "scheduled_at",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Form.Item>
                                </Col>
                            )}
                        </Row>

                        <Divider style={{ margin: "12px 0" }}>
                            Nội dung đa bài viết ({form.data.articles.length}/5)
                        </Divider>

                        <Space
                            direction="vertical"
                            size={12}
                            style={{ width: "100%" }}
                        >
                            {form.data.articles.map((article, idx) => (
                                <Card
                                    key={idx}
                                    size="small"
                                    title={
                                        idx === 0
                                            ? "Bài viết chính (Hero Banner)"
                                            : `Bài viết phụ #${idx + 1}`
                                    }
                                    extra={
                                        idx > 0 && (
                                            <Button
                                                type="text"
                                                danger
                                                size="small"
                                                icon={<DeleteOutlined />}
                                                onClick={() =>
                                                    removeArticle(idx)
                                                }
                                            >
                                                Xóa
                                            </Button>
                                        )
                                    }
                                >
                                    <Form.Item
                                        label="Tiêu đề bài viết"
                                        required
                                        style={{ marginBottom: 10 }}
                                        validateStatus={
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string
                                                >
                                            )[`articles.${idx}.title`]
                                                ? "error"
                                                : ""
                                        }
                                        help={
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string
                                                >
                                            )[`articles.${idx}.title`]
                                        }
                                    >
                                        <Input
                                            value={article.title}
                                            onChange={(e) =>
                                                updateArticle(
                                                    idx,
                                                    "title",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Nhập tiêu đề thu hút..."
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        label="Tóm tắt nội dung"
                                        style={{ marginBottom: 10 }}
                                    >
                                        <TextArea
                                            rows={2}
                                            value={article.summary}
                                            onChange={(e) =>
                                                updateArticle(
                                                    idx,
                                                    "summary",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Mô tả ngắn ưu đãi, nhắc lịch bổ sung hàng hoặc sản phẩm mới..."
                                        />
                                    </Form.Item>

                                    <Row gutter={10}>
                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="URL ảnh bìa (tùy chọn)"
                                                style={{ marginBottom: 10 }}
                                                validateStatus={
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `articles.${idx}.cover_image_url`
                                                    ]
                                                        ? "error"
                                                        : ""
                                                }
                                                help={
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `articles.${idx}.cover_image_url`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    prefix={<PictureOutlined />}
                                                    value={
                                                        article.cover_image_url
                                                    }
                                                    onChange={(e) =>
                                                        updateArticle(
                                                            idx,
                                                            "cover_image_url",
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="https://..."
                                                />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} sm={12}>
                                            <Form.Item
                                                label="Đường dẫn đích CTA (tùy chọn)"
                                                style={{ marginBottom: 10 }}
                                                validateStatus={
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[`articles.${idx}.cta_url`]
                                                        ? "error"
                                                        : ""
                                                }
                                                help={
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[`articles.${idx}.cta_url`]
                                                }
                                            >
                                                <Input
                                                    prefix={<LinkOutlined />}
                                                    value={article.cta_url}
                                                    onChange={(e) =>
                                                        updateArticle(
                                                            idx,
                                                            "cta_url",
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="https://..."
                                                />
                                            </Form.Item>
                                        </Col>
                                    </Row>

                                    <Form.Item
                                        label="Nhãn nút hành động (CTA)"
                                        style={{ marginBottom: 0 }}
                                    >
                                        <Input
                                            value={article.cta_label}
                                            onChange={(e) =>
                                                updateArticle(
                                                    idx,
                                                    "cta_label",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="VD: Xem báo giá, Đặt hàng ngay"
                                        />
                                    </Form.Item>
                                </Card>
                            ))}

                            {form.data.articles.length < 5 && (
                                <Button
                                    type="dashed"
                                    block
                                    icon={<PlusOutlined />}
                                    onClick={addArticle}
                                >
                                    Thêm bài viết phụ (tối đa 5 bài)
                                </Button>
                            )}
                        </Space>

                        <div style={{ textAlign: "right", marginTop: 18 }}>
                            <Space>
                                <Button onClick={onClose} size="large">
                                    Hủy
                                </Button>
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    size="large"
                                    icon={
                                        sendMode === "schedule" ? (
                                            <ClockCircleOutlined />
                                        ) : (
                                            <SendOutlined />
                                        )
                                    }
                                    loading={form.processing}
                                    disabled={
                                        targets.length === 0 ||
                                        form.data.channels.length === 0
                                    }
                                    style={{ backgroundColor: "#c8102e" }}
                                >
                                    {sendMode === "schedule"
                                        ? "Lưu lịch gửi Broadcast"
                                        : "Gửi Broadcast ngay"}
                                </Button>
                            </Space>
                        </div>
                    </Form>
                </Col>

                {/* Cột phải: Xem trước (Rich Media Mobile Preview) */}
                <Col xs={24} lg={10}>
                    <div
                        style={{
                            position: "sticky",
                            top: 0,
                            backgroundColor: "#f5f7fa",
                            border: "1px solid #e5e7eb",
                            borderRadius: 12,
                            padding: 16,
                        }}
                    >
                        <Text
                            type="secondary"
                            strong
                            style={{
                                display: "block",
                                marginBottom: 10,
                                fontSize: 12,
                                textTransform: "uppercase",
                            }}
                        >
                            Xem trước Tin nhắn đa bài viết
                        </Text>

                        <div
                            style={{
                                backgroundColor: "#ffffff",
                                borderRadius: 10,
                                overflow: "hidden",
                                border: "1px solid #e5e7eb",
                                boxShadow: "0 2px 8px rgba(0,0,0,0.05)",
                            }}
                        >
                            {/* Banner bài chính */}
                            <div
                                style={{
                                    backgroundColor: "#c8102e",
                                    color: "#fff",
                                    padding: "10px 14px",
                                    fontSize: 12,
                                    fontWeight: 600,
                                }}
                            >
                                Tài khoản Doanh nghiệp ·{" "}
                                {config.purposes[form.data.purpose] ??
                                    "Broadcast"}
                            </div>

                            {heroArticle?.cover_image_url ? (
                                <img
                                    src={heroArticle.cover_image_url}
                                    alt={heroArticle.title}
                                    style={{
                                        width: "100%",
                                        height: 150,
                                        objectFit: "cover",
                                        display: "block",
                                    }}
                                />
                            ) : (
                                <div
                                    style={{
                                        height: 110,
                                        background:
                                            "linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%)",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        color: "#c8102e",
                                        fontSize: 13,
                                        fontWeight: 500,
                                    }}
                                >
                                    <PictureOutlined
                                        style={{ marginRight: 6 }}
                                    />
                                    Ảnh bìa bài viết chính
                                </div>
                            )}

                            <div style={{ padding: 14 }}>
                                <Title
                                    level={5}
                                    style={{ margin: 0, fontSize: 15 }}
                                >
                                    {heroArticle?.title ||
                                        "Tiêu đề bài viết chính..."}
                                </Title>
                                {heroArticle?.summary && (
                                    <Text
                                        type="secondary"
                                        style={{
                                            display: "block",
                                            marginTop: 6,
                                            fontSize: 13,
                                            whiteSpace: "pre-line",
                                        }}
                                    >
                                        {heroArticle.summary}
                                    </Text>
                                )}
                                {heroArticle?.cta_label && (
                                    <div style={{ marginTop: 10 }}>
                                        <span
                                            style={{
                                                display: "inline-block",
                                                backgroundColor: "#c8102e",
                                                color: "#fff",
                                                padding: "5px 12px",
                                                borderRadius: 6,
                                                fontSize: 12,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {heroArticle.cta_label}
                                        </span>
                                    </div>
                                )}
                            </div>

                            {secondaryArticles.length > 0 && (
                                <div
                                    style={{
                                        borderTop: "1px solid #f0f0f0",
                                        padding: "10px 14px",
                                    }}
                                >
                                    {secondaryArticles.map((item, idx) => (
                                        <div
                                            key={idx}
                                            style={{
                                                display: "flex",
                                                alignItems: "center",
                                                justifyContent: "space-between",
                                                gap: 10,
                                                padding: "8px 0",
                                                borderTop:
                                                    idx > 0
                                                        ? "1px solid #f5f5f5"
                                                        : undefined,
                                            }}
                                        >
                                            <div style={{ flex: 1 }}>
                                                <Text
                                                    strong
                                                    style={{
                                                        fontSize: 13,
                                                        display: "block",
                                                    }}
                                                >
                                                    {item.title ||
                                                        `Bài viết phụ #${idx + 2}`}
                                                </Text>
                                                {item.summary && (
                                                    <Text
                                                        type="secondary"
                                                        style={{ fontSize: 12 }}
                                                    >
                                                        {item.summary}
                                                    </Text>
                                                )}
                                            </div>
                                            {item.cover_image_url && (
                                                <img
                                                    src={item.cover_image_url}
                                                    alt={item.title}
                                                    style={{
                                                        width: 48,
                                                        height: 48,
                                                        borderRadius: 6,
                                                        objectFit: "cover",
                                                    }}
                                                />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </Col>
            </Row>
        </Modal>
    );
}
