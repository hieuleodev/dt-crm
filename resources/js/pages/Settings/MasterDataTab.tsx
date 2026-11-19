import React, { useState } from "react";
import {
    Alert,
    Badge,
    Card,
    Col,
    Divider,
    Progress,
    Row,
    Space,
    Table,
    Tabs,
    Tag,
    Tooltip,
    Typography,
} from "antd";
import type { ColumnsType } from "antd/es/table";
import {
    ApartmentOutlined,
    AuditOutlined,
    BranchesOutlined,
    CheckCircleOutlined,
    ClockCircleOutlined,
    CloseCircleOutlined,
    CrownOutlined,
    DisconnectOutlined,
    ExclamationCircleOutlined,
    FallOutlined,
    FileTextOutlined,
    FundProjectionScreenOutlined,
    GlobalOutlined,
    HeartOutlined,
    InfoCircleOutlined,
    PhoneOutlined,
    RiseOutlined,
    RocketOutlined,
    SafetyCertificateOutlined,
    ShopOutlined,
    SmileOutlined,
    SolutionOutlined,
    StopOutlined,
    SyncOutlined,
    TagOutlined,
    TeamOutlined,
    ThunderboltOutlined,
    TrophyOutlined,
    UserSwitchOutlined,
} from "@ant-design/icons";

const { Title, Text, Paragraph } = Typography;

export interface MasterDataProps {
    masterData?: {
        leadStages: { key: string; label: string }[];
        opportunityStages: { key: string; label: string; probability: number }[];
        lostReasons: { key: string; label: string }[];
        customerTiers: { value: string; label: string }[];
        lifecycleStages: { value: string; label: string }[];
        productCategories?: { value: string; label: string }[];
        industries: { value: string; label: string }[];
        sourceChannels: { value: string; label: string }[];
        companyTypes: { value: string; label: string }[];
        procurementMethods: { value: string; label: string }[];
    } | null;
}

export default function MasterDataTab({ masterData }: MasterDataProps) {
    const [subTab, setSubTab] = useState<string>("pipeline");

    // ==========================================
    // 1. Phễu Đầu mối (Lead Stages)
    // ==========================================
    const leadStagesList = masterData?.leadStages || [
        { key: "new", label: "Mới" },
        { key: "contacted", label: "Đang liên hệ" },
        { key: "qualified", label: "Đủ điều kiện" },
        { key: "disqualified", label: "Không phù hợp" },
    ];

    const leadDescriptions: Record<string, { desc: string; action: string; color: string; icon: React.ReactNode }> = {
        new: {
            desc: "Đầu mối vừa tiếp nhận từ Marketing, Website, Hotline hoặc Sale tự nhập.",
            action: "Phân bổ phụ trách và liên hệ xác minh trong vòng 24 giờ.",
            color: "blue",
            icon: <ThunderboltOutlined />,
        },
        contacted: {
            desc: "Đã liên hệ, gọi điện, gửi email chào hàng hoặc thư giới thiệu năng lực PPE.",
            action: "Khai thác quy mô nhân sự, nhu cầu trang thiết bị và người quyết định.",
            color: "orange",
            icon: <PhoneOutlined />,
        },
        qualified: {
            desc: "Khách đúng chân dung mục tiêu (ngành công nghiệp, có ngân sách, có nhu cầu thực tế).",
            action: "Chuyển đổi thành Cơ hội Bán hàng (Opportunity) và tạo hồ sơ Khách hàng.",
            color: "green",
            icon: <CheckCircleOutlined />,
        },
        disqualified: {
            desc: "Sai thông tin, cá nhân mua lẻ không thuộc phân khúc B2B hoặc không có nhu cầu.",
            action: "Đóng đầu mối và lưu lý do loại trừ để tối ưu kênh Marketing.",
            color: "default",
            icon: <StopOutlined />,
        },
    };

    // ==========================================
    // 2. Quy trình Cơ hội Bán hàng (Opportunity Stages)
    // ==========================================
    const oppStagesList = masterData?.opportunityStages || [
        { key: "proposal", label: "Báo giá", probability: 30 },
        { key: "negotiation", label: "Đàm phán", probability: 70 },
        { key: "won", label: "Thành công", probability: 100 },
        { key: "lost", label: "Thất bại", probability: 0 },
    ];

    const oppMeta: Record<string, { desc: string; focus: string; color: string; status: "success" | "normal" | "exception" | "active" }> = {
        proposal: {
            desc: "Đã lên bảng chào giá vật tư PPE, bảng mẫu thử hoặc hồ sơ dự thầu chi tiết.",
            focus: "Theo dõi phản hồi đơn giá, gửi mẫu sản phẩm kiểm tra thực tế.",
            color: "#13c2c2",
            status: "normal",
        },
        negotiation: {
            desc: "Khách hàng đồng ý chủng loại, đang thương thảo giá chiết khấu, điều khoản thanh toán & công nợ.",
            focus: "Chốt điều khoản hợp đồng nguyên tắc, lịch giao hàng và hồ sơ năng lực.",
            color: "#faad14",
            status: "active",
        },
        won: {
            desc: "Đạt thỏa thuận, ký hợp đồng kinh tế hoặc nhận Đơn đặt hàng (PO) chính thức.",
            focus: "Chuyển giao bộ phận cung ứng/giao hàng và chăm sóc đơn hàng đầu tiên.",
            color: "#52c41a",
            status: "success",
        },
        lost: {
            desc: "Khách hàng hủy kế hoạch, không duyệt giá hoặc đã lựa chọn nhà cung cấp khác.",
            focus: "Ghi nhận bắt buộc lý do thất bại để thống kê và cải tiến chiến lược.",
            color: "#f5222d",
            status: "exception",
        },
    };

    // ==========================================
    // 3. Vòng đời Khách hàng B2B (Lifecycle Stages)
    // ==========================================
    const lifecycleList = masterData?.lifecycleStages || [
        { value: "prospect", label: "Tiềm năng" },
        { value: "contacted", label: "Đã liên hệ" },
        { value: "qualified", label: "Đã khảo sát nhu cầu" },
        { value: "vendor_registration", label: "Đăng ký nhà cung cấp" },
        { value: "sampling", label: "Gửi mẫu thử" },
        { value: "quoting", label: "Báo giá / đấu thầu" },
        { value: "negotiating", label: "Đàm phán" },
        { value: "new_customer", label: "Khách mới" },
        { value: "repeat", label: "Mua lặp lại" },
        { value: "loyal", label: "Trung thành" },
        { value: "at_risk", label: "Có nguy cơ" },
        { value: "dormant", label: "Ngủ đông" },
        { value: "lost", label: "Đã mất" },
    ];

    const lifecycleGroups = [
        {
            title: "Giai đoạn Tiếp cận & Mở rộng (Sales Pipeline)",
            color: "#1677ff",
            badge: "blue",
            items: lifecycleList.filter((item) =>
                ["prospect", "contacted", "qualified", "vendor_registration", "sampling", "quoting", "negotiating"].includes(
                    item.value,
                ),
            ),
        },
        {
            title: "Giai đoạn Khách hàng Thân thiết (Account Retention & Growth)",
            color: "#52c41a",
            badge: "green",
            items: lifecycleList.filter((item) => ["new_customer", "repeat", "loyal"].includes(item.value)),
        },
        {
            title: "Giai đoạn Cảnh báo & Khôi phục (Churn Prevention)",
            color: "#f5222d",
            badge: "red",
            items: lifecycleList.filter((item) => ["at_risk", "dormant", "lost"].includes(item.value)),
        },
    ];

    // ==========================================
    // 4. Danh mục Lý do Thất bại
    // ==========================================
    const lostReasonsList = masterData?.lostReasons || [
        { key: "price", label: "Giá cao hơn đối thủ" },
        { key: "competitor", label: "Khách chọn nhà cung cấp khác" },
        { key: "spec_mismatch", label: "Chưa đáp ứng tiêu chuẩn kỹ thuật / mẫu thử" },
        { key: "budget_cancelled", label: "Khách hủy / hoãn kế hoạch mua sắm" },
        { key: "payment_terms", label: "Không thống nhất được điều khoản thanh toán / công nợ" },
        { key: "other", label: "Lý do khác" },
    ];

    const lostReasonDescriptions: Record<string, string> = {
        price: "Đơn giá chưa tối ưu so với mặt bằng thị trường hoặc đối thủ có chính sách chiết khấu sâu hơn.",
        competitor: "Khách hàng có quan hệ truyền thống hoặc ưu tiên nhà phân phối độc quyền nhãn hàng khác.",
        spec_mismatch: "Mẫu gửi thử nghiệm chưa đạt tiêu chuẩn phòng sạch, chống tĩnh điện hoặc chống hóa chất đặc thù.",
        budget_cancelled: "Nhà máy cắt giảm ngân sách bảo hộ lao động hoặc hoãn mở rộng dây chuyền sản xuất.",
        payment_terms: "Bất đồng về thời hạn công nợ (VD: Khách yêu cầu trả sau 90 ngày, công ty chỉ chấp nhận 30-45 ngày).",
        other: "Các nguyên nhân phát sinh ngoài dự kiến (thay đổi lãnh đạo nhà máy, thay đổi địa điểm sản xuất...).",
    };

    // ==========================================
    // 5. Phân khúc Khách hàng (Tiers)
    // ==========================================
    const tierMeta: Record<string, { label: string; tag: string; scope: string; strategy: string }> = {
        A: {
            label: "Khách hàng Hạng A (Chiến lược / VIP)",
            tag: "magenta",
            scope: "Nhà máy FDI, tập đoàn lớn (>500 lao động), ngân sách PPE ước tính trên 300 triệu/năm.",
            strategy: "Chăm sóc định kỳ hàng tuần, ưu tiên công nợ tốt, cung cấp mẫu thử nghiệm độc quyền và hỗ trợ kỹ thuật tận nơi.",
        },
        B: {
            label: "Khách hàng Hạng B (Tiềm năng cao)",
            tag: "gold",
            scope: "Doanh nghiệp vừa và lớn (100 - 500 lao động), nhu cầu bảo hộ ổn định theo quý hoặc tháng.",
            strategy: "Theo dõi chu kỳ nhập hàng, đề xuất hợp đồng nguyên tắc 6 tháng - 1 năm để tối ưu chiết khấu.",
        },
        C: {
            label: "Khách hàng Hạng C (Tiêu chuẩn / Phổ thông)",
            tag: "blue",
            scope: "Xưởng sản xuất nhỏ (<100 lao động) hoặc mua hàng theo từng vụ việc đột xuất.",
            strategy: "Bán hàng theo chính sách thanh toán tiền mặt/chuyển khoản ngay, duy trì kênh liên hệ số (Zalo OA/Hotline).",
        },
    };

    return (
        <Space orientation="vertical" size={24} style={{ width: "100%" }}>
            {/* Banner giới thiệu */}
            <Alert
                message="Quy chuẩn Quy trình Bán hàng & Bộ Danh mục CRM Chuẩn"
                description={
                    <span>
                        Hệ thống hóa toàn bộ các bước chuyển đổi từ <strong>Đầu mối (Lead)</strong> ➔{" "}
                        <strong>Cơ hội (Opportunity)</strong> ➔ <strong>Vòng đời Khách hàng (Lifecycle)</strong> và danh
                        mục chuẩn hóa để đảm bảo toàn thể đội ngũ kinh doanh D&T vận hành đồng bộ và chuyên nghiệp.
                    </span>
                }
                type="info"
                showIcon
                icon={<FundProjectionScreenOutlined style={{ color: "#c8102e" }} />}
                style={{ backgroundColor: "#fff2f0", borderColor: "#ffccc7" }}
            />

            {/* Menu phụ chuyển đổi */}
            <Tabs
                activeKey={subTab}
                onChange={setSubTab}
                type="card"
                items={[
                    {
                        key: "pipeline",
                        label: (
                            <Space>
                                <BranchesOutlined /> Quy trình Bán hàng & Phễu Chuyển đổi
                            </Space>
                        ),
                    },
                    {
                        key: "categories",
                        label: (
                            <Space>
                                <TagOutlined /> Danh mục Phân loại & Tham số B2B
                            </Space>
                        ),
                    },
                ]}
            />

            {/* PHẦN 1: QUY TRÌNH & PHỄU BÁN HÀNG */}
            {subTab === "pipeline" && (
                <Space orientation="vertical" size={24} style={{ width: "100%" }}>
                    {/* 1.1 Phễu Đầu mối (Lead Pipeline) */}
                    <Card
                        title={
                            <Space>
                                <ThunderboltOutlined style={{ color: "#1677ff" }} />
                                <span>1. Phễu Xử lý Đầu mối Tiềm năng (Lead Pipeline)</span>
                            </Space>
                        }
                        bordered={false}
                        className="crm-panel"
                    >
                        <Paragraph type="secondary">
                            Đầu mối là các tổ chức/cá nhân mới bày tỏ sự quan tâm đến sản phẩm bảo hộ lao động. Mục tiêu
                            chính của giai đoạn này là xác minh nhu cầu thực tế và chuyển đổi sang Cơ hội Bán hàng.
                        </Paragraph>

                        <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                            {leadStagesList.map((st, index) => {
                                const meta = leadDescriptions[st.key] || {
                                    desc: st.label,
                                    action: "Xử lý theo nghiệp vụ",
                                    color: "blue",
                                    icon: <InfoCircleOutlined />,
                                };
                                return (
                                    <Col xs={24} sm={12} lg={6} key={st.key}>
                                        <Card
                                            size="small"
                                            bordered
                                            style={{
                                                height: "100%",
                                                borderColor: st.key === "qualified" ? "#b7eb8f" : undefined,
                                                backgroundColor: st.key === "qualified" ? "#f6ffed" : "#fafafa",
                                            }}
                                        >
                                            <Space direction="vertical" size={8} style={{ width: "100%" }}>
                                                <Space>
                                                    <Tag color={meta.color} style={{ fontSize: 13, padding: "2px 8px" }}>
                                                        {meta.icon} Bước {index + 1}: {st.label}
                                                    </Tag>
                                                </Space>

                                                <div style={{ minHeight: 48, fontSize: 13, color: "#555" }}>
                                                    {meta.desc}
                                                </div>

                                                <Divider style={{ margin: "8px 0" }} />

                                                <div style={{ fontSize: 12 }}>
                                                    <Text type="secondary">Hành động chuẩn:</Text>
                                                    <div style={{ fontWeight: 500, color: "#1f1f1f", marginTop: 2 }}>
                                                        {meta.action}
                                                    </div>
                                                </div>
                                            </Space>
                                        </Card>
                                    </Col>
                                );
                            })}
                        </Row>
                    </Card>

                    {/* 1.2 Quy trình Cơ hội & Xác suất thắng Deal */}
                    <Card
                        title={
                            <Space>
                                <RiseOutlined style={{ color: "#52c41a" }} />
                                <span>2. Quy trình Cơ hội Bán hàng & Tỷ lệ Xác suất Chốt Deal (Opportunity Pipeline)</span>
                            </Space>
                        }
                        bordered={false}
                        className="crm-panel"
                    >
                        <Paragraph type="secondary">
                            Khi một Lead đã xác định rõ nhu cầu và có ngân sách, một Cơ hội Bán hàng được mở ra. Mỗi giai
                            đoạn gắn liền với tỷ lệ xác suất chốt deal mặc định phục vụ dự báo doanh thu (Forecast).
                        </Paragraph>

                        <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                            {oppStagesList.map((stage) => {
                                const meta = oppMeta[stage.key] || {
                                    desc: stage.label,
                                    focus: "Theo dõi cơ hội",
                                    color: "#1677ff",
                                    status: "normal",
                                };
                                return (
                                    <Col xs={24} md={12} lg={6} key={stage.key}>
                                        <Card
                                            size="small"
                                            bordered
                                            style={{
                                                height: "100%",
                                                borderTop: `3px solid ${meta.color}`,
                                            }}
                                        >
                                            <Space direction="vertical" size={12} style={{ width: "100%" }}>
                                                <div
                                                    style={{
                                                        display: "flex",
                                                        justifyContent: "space-between",
                                                        alignItems: "center",
                                                    }}
                                                >
                                                    <Title level={5} style={{ margin: 0 }}>
                                                        {stage.label}
                                                    </Title>
                                                    <Tag color={meta.color} style={{ fontWeight: 600 }}>
                                                        {stage.probability}% Thắng
                                                    </Tag>
                                                </div>

                                                <Progress
                                                    percent={stage.probability}
                                                    status={meta.status}
                                                    strokeColor={meta.color}
                                                    showInfo={false}
                                                    size="small"
                                                />

                                                <div style={{ fontSize: 13, color: "#555", minHeight: 48 }}>
                                                    {meta.desc}
                                                </div>

                                                <Divider style={{ margin: "4px 0" }} />

                                                <div style={{ fontSize: 12 }}>
                                                    <Text type="secondary">Trọng tâm giai đoạn:</Text>
                                                    <div style={{ fontWeight: 500, color: "#333", marginTop: 2 }}>
                                                        {meta.focus}
                                                    </div>
                                                </div>
                                            </Space>
                                        </Card>
                                    </Col>
                                );
                            })}
                        </Row>
                    </Card>

                    {/* 1.3 Vòng đời Khách hàng B2B */}
                    <Card
                        title={
                            <Space>
                                <SyncOutlined style={{ color: "#faad14" }} />
                                <span>3. Chu kỳ Vòng đời Khách hàng Doanh nghiệp (Customer Lifecycle Stages)</span>
                            </Space>
                        }
                        bordered={false}
                        className="crm-panel"
                    >
                        <Paragraph type="secondary">
                            Định nghĩa chuẩn 13 nấc thang phát triển tài khoản khách hàng doanh nghiệp, giúp Sales và bộ phận
                            chăm sóc xác định đúng chiến lược tương tác theo từng chu kỳ.
                        </Paragraph>

                        <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                            {lifecycleGroups.map((grp) => (
                                <Col xs={24} lg={8} key={grp.title}>
                                    <Card
                                        size="small"
                                        title={
                                            <Space>
                                                <Badge status={grp.badge as any} />
                                                <Text strong style={{ fontSize: 14 }}>
                                                    {grp.title}
                                                </Text>
                                            </Space>
                                        }
                                        bordered
                                        style={{ height: "100%", backgroundColor: "#fafafa" }}
                                    >
                                        <Space direction="vertical" size={10} style={{ width: "100%" }}>
                                            {grp.items.map((item, idx) => (
                                                <div
                                                    key={item.value}
                                                    style={{
                                                        display: "flex",
                                                        alignItems: "center",
                                                        justifyContent: "space-between",
                                                        padding: "6px 10px",
                                                        backgroundColor: "#fff",
                                                        borderRadius: 6,
                                                        border: "1px solid #f0f0f0",
                                                    }}
                                                >
                                                    <Space>
                                                        <Text type="secondary" style={{ width: 18, fontSize: 12 }}>
                                                            #{idx + 1}
                                                        </Text>
                                                        <Text strong>{item.label}</Text>
                                                    </Space>
                                                    <Tag style={{ margin: 0, fontSize: 11 }}>{item.value}</Tag>
                                                </div>
                                            ))}
                                        </Space>
                                    </Card>
                                </Col>
                            ))}
                        </Row>
                    </Card>
                </Space>
            )}

            {/* PHẦN 2: DANH MỤC PHÂN LOẠI & THAM SỐ B2B */}
            {subTab === "categories" && (
                <Space orientation="vertical" size={24} style={{ width: "100%" }}>
                    {/* 2.1 Lý do Mất Cơ hội */}
                    <Card
                        title={
                            <Space>
                                <FallOutlined style={{ color: "#f5222d" }} />
                                <span>1. Danh mục Lý do Thất bại khi Chốt Cơ hội (Lost Reasons)</span>
                            </Space>
                        }
                        bordered={false}
                        className="crm-panel"
                    >
                        <Paragraph type="secondary">
                            Khi một Cơ hội chuyển sang trạng thái <strong>Thất bại (Lost)</strong>, nhân viên kinh doanh
                            bắt buộc phải phân loại theo danh mục này để phục vụ báo cáo phân tích rủi ro và điều chỉnh giá.
                        </Paragraph>

                        <Table
                            dataSource={lostReasonsList}
                            rowKey="key"
                            pagination={false}
                            size="small"
                            columns={[
                                {
                                    title: "Mã lý do",
                                    dataIndex: "key",
                                    key: "key",
                                    width: 180,
                                    render: (val: string) => <Tag color="error">{val}</Tag>,
                                },
                                {
                                    title: "Tên lý do chuẩn hóa",
                                    dataIndex: "label",
                                    key: "label",
                                    width: 320,
                                    render: (text: string) => <Text strong>{text}</Text>,
                                },
                                {
                                    title: "Ý nghĩa & Phân tích nguyên nhân thực tế",
                                    key: "desc",
                                    render: (_, record) => (
                                        <Text type="secondary">
                                            {lostReasonDescriptions[record.key] || "Nguyên nhân khác phát sinh trong quá trình đàm phán."}
                                        </Text>
                                    ),
                                },
                            ]}
                        />
                    </Card>

                    {/* 2.2 Phân hạng Khách hàng (Tiers) */}
                    <Card
                        title={
                            <Space>
                                <CrownOutlined style={{ color: "#fa8c16" }} />
                                <span>2. Tiêu chí Phân hạng Khách hàng Doanh nghiệp (Customer Tiers)</span>
                            </Space>
                        }
                        bordered={false}
                        className="crm-panel"
                    >
                        <Row gutter={[16, 16]}>
                            {(masterData?.customerTiers || [{ value: "A", label: "Hạng A" }, { value: "B", label: "Hạng B" }, { value: "C", label: "Hạng C" }]).map(
                                (tier) => {
                                    const meta = tierMeta[tier.value] || {
                                        label: tier.label,
                                        tag: "blue",
                                        scope: "Khách hàng tiêu chuẩn",
                                        strategy: "Duy trì tương tác định kỳ",
                                    };
                                    return (
                                        <Col xs={24} md={8} key={tier.value}>
                                            <Card
                                                size="small"
                                                bordered
                                                style={{
                                                    height: "100%",
                                                    backgroundColor: tier.value === "A" ? "#fff0f6" : tier.value === "B" ? "#fffbe6" : "#f0f5ff",
                                                    borderColor: tier.value === "A" ? "#ffadd2" : tier.value === "B" ? "#ffe58f" : "#adc6ff",
                                                }}
                                            >
                                                <Space direction="vertical" size={10} style={{ width: "100%" }}>
                                                    <Space>
                                                        <Tag color={meta.tag} style={{ fontSize: 14, padding: "2px 10px", fontWeight: 700 }}>
                                                            {tier.label}
                                                        </Tag>
                                                    </Space>
                                                    <Title level={5} style={{ margin: 0 }}>
                                                        {meta.label}
                                                    </Title>
                                                    <div>
                                                        <Text type="secondary" style={{ fontSize: 12 }}>Tiêu chuẩn quy mô:</Text>
                                                        <div style={{ fontSize: 13, marginTop: 2, color: "#333" }}>{meta.scope}</div>
                                                    </div>
                                                    <Divider style={{ margin: "6px 0" }} />
                                                    <div>
                                                        <Text type="secondary" style={{ fontSize: 12 }}>Chiến lược phục vụ:</Text>
                                                        <div style={{ fontSize: 13, marginTop: 2, color: "#333" }}>{meta.strategy}</div>
                                                    </div>
                                                </Space>
                                            </Card>
                                        </Col>
                                    );
                                },
                            )}
                        </Row>
                    </Card>

                    {/* 2.3 Danh mục Sản phẩm kinh doanh trên trangthietbi.vn */}
                    <Card
                        title={
                            <Space>
                                <ShopOutlined style={{ color: "#c8102e" }} />
                                <span>3. Danh mục Ngành hàng Cung ứng trên trangthietbi.vn (Product Categories)</span>
                            </Space>
                        }
                        bordered={false}
                        className="crm-panel"
                    >
                        <Paragraph type="secondary" style={{ marginBottom: 16 }}>
                            18 danh mục sản phẩm chính thức được Công ty D&T phân phối trên hệ thống website{" "}
                            <strong>trangthietbi.vn</strong> phục vụ khối khách hàng doanh nghiệp và nhà máy FDI:
                        </Paragraph>

                        <Space wrap size={[8, 12]}>
                            {(masterData?.productCategories || []).map((cat) => (
                                <Tag
                                    key={cat.value}
                                    color="red"
                                    style={{
                                        fontSize: 13,
                                        padding: "5px 12px",
                                        borderRadius: 4,
                                    }}
                                >
                                    <ShopOutlined /> {cat.label} ({cat.value})
                                </Tag>
                            ))}
                        </Space>
                    </Card>

                    {/* 2.4 Lĩnh vực Sản xuất của Khách hàng & Nguồn tiếp cận */}
                    <Row gutter={[16, 16]}>
                        {/* Lĩnh vực sản xuất của Khách hàng (Customer Industries) */}
                        <Col xs={24} lg={12}>
                            <Card
                                title={
                                    <Space>
                                        <ApartmentOutlined style={{ color: "#722ed1" }} />
                                        <span>4. Lĩnh vực Sản xuất của Khách hàng (Customer Industries)</span>
                                    </Space>
                                }
                                bordered={false}
                                className="crm-panel"
                                style={{ height: "100%" }}
                            >
                                <Paragraph type="secondary" style={{ marginBottom: 16 }}>
                                    11 nhóm lĩnh vực hoạt động của nhà máy/doanh nghiệp mua hàng (dùng trong hồ sơ Khách
                                    hàng CRM để tư vấn đúng tiêu chuẩn PPE, phòng sạch, ESD):
                                </Paragraph>

                                <Space wrap size={[8, 12]}>
                                    {(masterData?.industries || []).map((ind) => (
                                        <Tag
                                            key={ind.value}
                                            color="purple"
                                            style={{
                                                fontSize: 13,
                                                padding: "4px 10px",
                                                borderRadius: 4,
                                            }}
                                        >
                                            <ApartmentOutlined /> {ind.label} ({ind.value})
                                        </Tag>
                                    ))}
                                </Space>
                            </Card>
                        </Col>

                        {/* Kênh Tiếp cận (Source Channels) */}
                        <Col xs={24} lg={12}>
                            <Card
                                title={
                                    <Space>
                                        <GlobalOutlined style={{ color: "#13c2c2" }} />
                                        <span>5. Nguồn Tiếp cận Khách hàng (Source Channels)</span>
                                    </Space>
                                }
                                bordered={false}
                                className="crm-panel"
                                style={{ height: "100%" }}
                            >
                                <Paragraph type="secondary" style={{ marginBottom: 16 }}>
                                    15 kênh Inbound, Outbound và Giới thiệu đối tác được chuẩn hóa để đo lường ROI Marketing:
                                </Paragraph>

                                <Space wrap size={[8, 12]}>
                                    {(masterData?.sourceChannels || []).map((ch) => (
                                        <Tag
                                            key={ch.value}
                                            color="cyan"
                                            style={{
                                                fontSize: 13,
                                                padding: "4px 10px",
                                                borderRadius: 4,
                                            }}
                                        >
                                            <TagOutlined /> {ch.label}
                                        </Tag>
                                    ))}
                                </Space>
                            </Card>
                        </Col>
                    </Row>

                    {/* 2.5 Loại hình Doanh nghiệp & Hình thức Mua hàng */}
                    <Row gutter={[16, 16]}>
                        <Col xs={24} md={12}>
                            <Card
                                title={
                                    <Space>
                                        <AuditOutlined style={{ color: "#2f54eb" }} />
                                        <span>6. Loại hình Doanh nghiệp (Company Types)</span>
                                    </Space>
                                }
                                bordered={false}
                                className="crm-panel"
                            >
                                <Space direction="vertical" size={12} style={{ width: "100%" }}>
                                    {(masterData?.companyTypes || [
                                        { value: "fdi", label: "Doanh nghiệp FDI" },
                                        { value: "domestic", label: "Doanh nghiệp Việt Nam" },
                                    ]).map((ct) => (
                                        <div
                                            key={ct.value}
                                            style={{
                                                padding: "10px 14px",
                                                backgroundColor: "#fafafa",
                                                borderRadius: 6,
                                                display: "flex",
                                                justifyContent: "space-between",
                                                alignItems: "center",
                                                border: "1px solid #f0f0f0",
                                            }}
                                        >
                                            <Space>
                                                <Badge status="processing" />
                                                <Text strong>{ct.label}</Text>
                                            </Space>
                                            <Tag color="blue">{ct.value}</Tag>
                                        </div>
                                    ))}
                                </Space>
                            </Card>
                        </Col>

                        <Col xs={24} md={12}>
                            <Card
                                title={
                                    <Space>
                                        <SolutionOutlined style={{ color: "#fa541c" }} />
                                        <span>7. Phương thức Mua sắm (Procurement Methods)</span>
                                    </Space>
                                }
                                bordered={false}
                                className="crm-panel"
                            >
                                <Space wrap size={[8, 12]}>
                                    {(masterData?.procurementMethods || [
                                        { value: "direct_quotation", label: "Chào giá cạnh tranh" },
                                        { value: "bidding", label: "Đấu thầu / eAuction" },
                                        { value: "framework_contract", label: "Hợp đồng nguyên tắc hàng năm" },
                                        { value: "direct_purchase", label: "Chỉ định thầu / mua trực tiếp" },
                                    ]).map((pm) => (
                                        <Tag
                                            key={pm.value}
                                            color="volcano"
                                            style={{
                                                fontSize: 13,
                                                padding: "4px 10px",
                                                borderRadius: 4,
                                            }}
                                        >
                                            <SolutionOutlined /> {pm.label} ({pm.value})
                                        </Tag>
                                    ))}
                                </Space>
                            </Card>
                        </Col>
                    </Row>
                </Space>
            )}
        </Space>
    );
}
