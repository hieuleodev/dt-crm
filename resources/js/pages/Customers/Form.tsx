import { useEffect, useRef, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Alert,
    Button,
    Card,
    Checkbox,
    Col,
    DatePicker,
    Form as AntForm,
    Input,
    InputNumber,
    Row,
    Select,
    Switch,
} from "antd";
import dayjs from "dayjs";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import { emptyToNull, submitForm } from "@/lib/form";
import type { PageProps } from "@/types";
import type {
    CustomerFormData,
    CustomerOptions,
    DuplicateMatch,
    OwnerOption,
} from "@/types/customer";

interface Props extends PageProps {
    customer: CustomerFormData | null;
    options: CustomerOptions;
    owners: OwnerOption[];
}

const CONSENT_CHANNELS = [
    { value: "zalo", label: "Zalo" },
    { value: "email", label: "Email" },
    { value: "sms", label: "SMS" },
    { value: "phone", label: "Gọi điện" },
];
const money = {
    formatter: (v?: number | string) =>
        `${v ?? ""}`.replace(/\B(?=(\d{3})+(?!\d))/g, "."),
    parser: (v?: string) => Number((v ?? "").replace(/\./g, "")),
};

export default function CustomerForm() {
    const { customer, options, owners } = usePage<Props>().props;
    const [form] = AntForm.useForm();
    const [saving, setSaving] = useState(false);
    const [duplicates, setDuplicates] = useState<DuplicateMatch[]>([]);
    const timer = useRef<number>(undefined);
    const isEdit = !!customer?.id;

    useEffect(() => {
        form.setFieldsValue(
            customer
                ? {
                      ...customer,
                      vendor_registered_at: customer.vendor_registered_at
                          ? dayjs(customer.vendor_registered_at)
                          : null,
                  }
                : {
                      lifecycle_stage: "prospect",
                      status: "active",
                      vendor_registered: false,
                      marketing_consent: false,
                      tags: [],
                      required_certificates: [],
                      consent_channels: [],
                  },
        );
    }, [customer, form]);

    const checkDuplicates = () => {
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(async () => {
            const { tax_code, name, phone, email } = form.getFieldsValue([
                "tax_code",
                "name",
                "phone",
                "email",
            ]);
            if (!tax_code && !name && !phone && !email)
                return setDuplicates([]);
            const params = new URLSearchParams(
                Object.entries({
                    tax_code,
                    name,
                    phone,
                    email,
                    except: customer?.id,
                })
                    .filter(([, v]) => v)
                    .map(([k, v]) => [k, String(v)]),
            );
            const res = await fetch(`/customers/check-duplicates?${params}`, {
                headers: { Accept: "application/json" },
            });
            if (res.ok) setDuplicates((await res.json()).matches);
        }, 500);
    };

    const onFinish = (values: Record<string, unknown>) => {
        setSaving(true);
        const data = emptyToNull({
            ...values,
            vendor_registered_at: values.vendor_registered_at
                ? (values.vendor_registered_at as dayjs.Dayjs).format(
                      "YYYY-MM-DD",
                  )
                : null,
        });
        submitForm(
            form,
            isEdit ? "put" : "post",
            isEdit ? `/customers/${customer!.id}` : "/customers",
            data,
            { onFinish: () => setSaving(false) },
        );
    };

    const cancelHref = isEdit ? `/customers/${customer!.id}` : "/customers";

    return (
        <AppLayout>
            <Head
                title={isEdit ? `Sửa ${customer!.name}` : "Thêm khách hàng"}
            />
            <PageHeader
                back={{
                    href: cancelHref,
                    label: isEdit ? "Hồ sơ khách hàng" : "Danh sách khách hàng",
                }}
                title={isEdit ? `Sửa: ${customer!.name}` : "Thêm khách hàng"}
                subtitle="Nhập càng đủ, hệ thống chăm sóc và phân tích càng chính xác. Ô có dấu * là bắt buộc."
            />

            {duplicates.length > 0 && (
                <Alert
                    className="crm-section"
                    type="warning"
                    showIcon
                    title="Có thể khách này đã có trong hệ thống"
                    description={
                        <ul className="crm-dup-list">
                            {duplicates.map((d) => (
                                <li key={d.id}>
                                    <Link href={`/customers/${d.id}`}>
                                        {d.name}
                                    </Link>{" "}
                                    ({d.code}) – {d.reasons.join(", ")}
                                    {d.owner
                                        ? ` – phụ trách: ${d.owner}`
                                        : " – chưa có người phụ trách"}
                                </li>
                            ))}
                        </ul>
                    }
                />
            )}

            <AntForm
                form={form}
                layout="vertical"
                onFinish={onFinish}
                requiredMark={(label, { required }) => (
                    <>
                        {label}
                        {required && <span className="crm-required">*</span>}
                    </>
                )}
            >
                <Card title="Định danh doanh nghiệp" className="crm-section">
                    <Row gutter={16}>
                        <Col xs={24} md={16}>
                            <AntForm.Item
                                name="name"
                                label="Tên công ty (theo đăng ký kinh doanh)"
                                rules={[
                                    {
                                        required: true,
                                        message: "Vui lòng nhập tên công ty",
                                    },
                                ]}
                            >
                                <Input
                                    onChange={checkDuplicates}
                                    placeholder="VD: Công ty TNHH Điện tử ABC Việt Nam"
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="short_name"
                                label="Tên viết tắt / tên giao dịch"
                            >
                                <Input />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="tax_code"
                                label="Mã số thuế"
                                tooltip="10 số, hoặc 10 số-3 số với chi nhánh"
                            >
                                <Input
                                    onChange={checkDuplicates}
                                    placeholder="0101234567"
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="code"
                                label="Mã khách hàng"
                                tooltip="Nên trùng mã trên MISA. Bỏ trống để hệ thống tự sinh (KH00001…)"
                            >
                                <Input
                                    disabled={isEdit}
                                    placeholder="Tự sinh nếu bỏ trống"
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item name="company_type" label="Loại hình">
                                <Select
                                    allowClear
                                    options={options.companyTypes}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <AntForm.Item
                                name="parent_group"
                                label="Tập đoàn mẹ"
                            >
                                <Input placeholder="VD: tập đoàn ở Hàn Quốc" />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <AntForm.Item
                                name="parent_country"
                                label="Quốc gia công ty mẹ"
                            >
                                <Input />
                            </AntForm.Item>
                        </Col>
                    </Row>
                </Card>

                <Card title="Liên hệ & địa chỉ" className="crm-section">
                    <Row gutter={16}>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="phone"
                                label="Điện thoại công ty"
                            >
                                <Input onChange={checkDuplicates} />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="email"
                                label="Email chung"
                                rules={[
                                    {
                                        type: "email",
                                        message: "Email không hợp lệ",
                                    },
                                ]}
                            >
                                <Input onChange={checkDuplicates} />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item name="website" label="Website">
                                <Input />
                            </AntForm.Item>
                        </Col>
                        <Col span={24}>
                            <AntForm.Item
                                name="address"
                                label="Địa chỉ xuất hóa đơn"
                            >
                                <Input />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <AntForm.Item
                                name="industrial_zone"
                                label="Khu công nghiệp"
                            >
                                <Input />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <AntForm.Item name="province" label="Tỉnh / thành">
                                <Input />
                            </AntForm.Item>
                        </Col>
                    </Row>
                </Card>

                <Card title="Quy mô & tiềm năng" className="crm-section">
                    <Row gutter={16}>
                        <Col xs={24} md={8}>
                            <AntForm.Item name="industry" label="Ngành">
                                <Select
                                    allowClear
                                    options={options.industries}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={16}>
                            <AntForm.Item
                                name="industry_detail"
                                label="Sản phẩm / dây chuyền chính"
                            >
                                <Input placeholder="VD: lắp ráp bo mạch SMT" />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="employee_count"
                                label="Số công nhân"
                            >
                                <InputNumber
                                    min={0}
                                    style={{ width: "100%" }}
                                    {...money}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="ppe_budget_estimate"
                                label="Tiềm năng PPE / năm (VND)"
                                tooltip="Ước tính: số công nhân × chi phí PPE / người / năm"
                            >
                                <InputNumber
                                    min={0}
                                    style={{ width: "100%" }}
                                    {...money}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col span={24}>
                            <AntForm.Item
                                name="current_suppliers"
                                label="Nhà cung cấp hiện tại / đối thủ"
                            >
                                <Input.TextArea rows={2} />
                            </AntForm.Item>
                        </Col>
                    </Row>
                </Card>

                <Card
                    title="Phân loại, nguồn & phụ trách"
                    className="crm-section"
                >
                    <Row gutter={16}>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="lifecycle_stage"
                                label="Giai đoạn"
                                rules={[{ required: true }]}
                            >
                                <Select options={options.lifecycleStages} />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item name="tier" label="Hạng khách">
                                <Select allowClear options={options.tiers} />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="status"
                                label="Trạng thái"
                                rules={[{ required: true }]}
                            >
                                <Select options={options.statuses} />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="source_channel"
                                label="Khách biết D&T qua kênh nào?"
                            >
                                <Select
                                    allowClear
                                    options={options.sourceChannels}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="referred_by"
                                label="Người / đơn vị giới thiệu"
                            >
                                <Input />
                            </AntForm.Item>
                        </Col>
                        {!isEdit && owners.length > 0 && (
                            <Col xs={24} md={8}>
                                <AntForm.Item
                                    name="owner_id"
                                    label="Người phụ trách"
                                    tooltip="Bỏ trống = giao cho chính bạn"
                                >
                                    <Select
                                        allowClear
                                        showSearch
                                        optionFilterProp="label"
                                        options={owners.map((o) => ({
                                            value: o.id,
                                            label: o.name,
                                        }))}
                                    />
                                </AntForm.Item>
                            </Col>
                        )}
                        <Col span={24}>
                            <AntForm.Item name="tags" label="Nhãn">
                                <Select
                                    mode="tags"
                                    placeholder="Gõ rồi Enter để thêm nhãn, VD: FDI Hàn, Phòng sạch"
                                />
                            </AntForm.Item>
                        </Col>
                    </Row>
                </Card>

                <Card
                    title="Mua hàng & yêu cầu nhà cung cấp"
                    className="crm-section"
                >
                    <Row gutter={16}>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="procurement_method"
                                label="Cách mua hàng"
                            >
                                <Select
                                    allowClear
                                    options={options.procurementMethods}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="payment_terms"
                                label="Điều khoản thanh toán"
                            >
                                <Input placeholder="VD: 30 ngày kể từ hóa đơn" />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="credit_limit"
                                label="Hạn mức công nợ (VND)"
                            >
                                <InputNumber
                                    min={0}
                                    style={{ width: "100%" }}
                                    {...money}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="vendor_registered"
                                label="Đã đăng ký nhà cung cấp"
                                valuePropName="checked"
                            >
                                <Switch />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="vendor_registered_at"
                                label="Ngày được duyệt"
                            >
                                <DatePicker
                                    format="DD/MM/YYYY"
                                    style={{ width: "100%" }}
                                />
                            </AntForm.Item>
                        </Col>
                        <Col span={24}>
                            <AntForm.Item
                                name="required_certificates"
                                label="Chứng từ / chứng chỉ khách yêu cầu"
                            >
                                <Select
                                    mode="tags"
                                    options={options.certificates}
                                    placeholder="Chọn hoặc gõ thêm"
                                />
                            </AntForm.Item>
                        </Col>
                    </Row>
                </Card>

                <Card title="Đồng ý nhận tin & ghi chú" className="crm-section">
                    <Row gutter={16}>
                        <Col xs={24} md={8}>
                            <AntForm.Item
                                name="marketing_consent"
                                label="Khách đồng ý nhận tin"
                                valuePropName="checked"
                                tooltip="Bắt buộc trước khi gửi Zalo / email tự động"
                            >
                                <Switch />
                            </AntForm.Item>
                        </Col>
                        <Col xs={24} md={16}>
                            <AntForm.Item
                                name="consent_channels"
                                label="Qua kênh"
                            >
                                <Checkbox.Group options={CONSENT_CHANNELS} />
                            </AntForm.Item>
                        </Col>
                        <Col span={24}>
                            <AntForm.Item name="notes" label="Ghi chú">
                                <Input.TextArea rows={3} />
                            </AntForm.Item>
                        </Col>
                    </Row>
                </Card>

                <div className="crm-form-actions">
                    <Button onClick={() => router.visit(cancelHref)}>
                        Hủy
                    </Button>
                    <Button type="primary" htmlType="submit" loading={saving}>
                        {isEdit ? "Lưu thay đổi" : "Tạo khách hàng"}
                    </Button>
                </div>
            </AntForm>
        </AppLayout>
    );
}
