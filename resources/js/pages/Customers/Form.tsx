import { useEffect, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Alert, Button, Card, Col, Form, Input, Row, Select } from "antd";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import { emptyToNull, submitForm } from "@/lib/form";
import type { PageProps } from "@/types";
import type {
    CustomerBasicFormData,
    CustomerOptions,
    DuplicateMatch,
} from "@/types/customer";

type Values = Omit<CustomerBasicFormData, "id">;

interface Props extends PageProps {
    customer: CustomerBasicFormData | null;
    options: CustomerOptions;
}

export default function CustomerForm() {
    const { customer, options } = usePage<Props>().props;

    const [form] = Form.useForm<Values>();
    const [saving, setSaving] = useState(false);
    const [duplicates, setDuplicates] = useState<DuplicateMatch[]>([]);
    const [duplicateError, setDuplicateError] = useState(false);

    const name = Form.useWatch("name", form);
    const email = Form.useWatch("email", form);
    const phone = Form.useWatch("phone", form);

    const isEdit = Boolean(customer?.id);

    const cancelHref = isEdit ? `/customers/${customer!.id}` : "/customers";

    useEffect(() => {
        form.resetFields();

        form.setFieldsValue({
            name: customer?.name ?? "",
            email: customer?.email ?? "",
            phone: customer?.phone ?? "",
            address: customer?.address ?? "",
            company_type: customer?.company_type ?? null,
            industry: customer?.industry ?? null,
            status: customer?.status ?? "active",
            notes: customer?.notes ?? "",
        });
    }, [customer, form]);

    useEffect(() => {
        const controller = new AbortController();

        const params = new URLSearchParams();

        for (const [key, value] of Object.entries({
            name,
            email,
            phone,
        })) {
            if (typeof value === "string" && value.trim()) {
                params.set(key, value.trim());
            }
        }

        if (params.size === 0) {
            setDuplicates([]);
            setDuplicateError(false);
            return;
        }

        if (customer?.id) {
            params.set("except", String(customer.id));
        }

        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    `/customers/check-duplicates?${params.toString()}`,
                    {
                        signal: controller.signal,
                        credentials: "same-origin",
                        headers: { Accept: "application/json" },
                    },
                );

                if (!response.ok) {
                    throw new Error("Duplicate check failed");
                }

                const data = await response.json();

                if (!controller.signal.aborted) {
                    setDuplicates(data.matches);
                    setDuplicateError(false);
                }
            } catch {
                if (!controller.signal.aborted) {
                    setDuplicates([]);
                    setDuplicateError(true);
                }
            }
        }, 500);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [name, email, phone, customer?.id]);

    function onFinish(values: Values) {
        if (saving) return;

        setSaving(true);

        // Chỉ gửi các trường của form mới.
        const data = emptyToNull({
            name: values.name.trim(),
            email: values.email?.trim() ?? "",
            phone: values.phone?.trim() ?? "",
            address: values.address?.trim() ?? "",
            company_type: values.company_type ?? null,
            industry: values.industry ?? null,
            status: values.status,
            notes: values.notes?.trim() ?? "",
        });

        submitForm(
            form,
            isEdit ? "put" : "post",
            isEdit ? `/customers/${customer!.id}` : "/customers",
            data,
            {
                onFinish: () => setSaving(false),
            },
        );
    }

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
            />

            {duplicates.length > 0 && (
                <Alert
                    className="crm-section"
                    type="warning"
                    showIcon
                    title="Có thể khách hàng đã tồn tại"
                    description={
                        <ul className="crm-dup-list">
                            {duplicates.map((item) => (
                                <li key={item.id}>
                                    <Link href={`/customers/${item.id}`}>
                                        {item.name}
                                    </Link>{" "}
                                    ({item.code}){" — "}
                                    {item.reasons.join(", ")}
                                </li>
                            ))}
                        </ul>
                    }
                />
            )}

            {duplicateError && (
                <Alert
                    className="crm-section"
                    type="warning"
                    showIcon
                    title="Chưa kiểm tra được khách hàng trùng"
                    description="Bạn có thể tiếp tục nhập. Hãy kiểm tra danh sách khách hàng trước khi tạo mới."
                />
            )}

            <Form
                form={form}
                layout="vertical"
                onFinish={onFinish}
                disabled={saving}
                aria-busy={saving}
            >
                <Card title="Thông tin khách hàng" className="crm-section">
                    <Row gutter={[24, 0]}>
                        <Col span={24}>
                            <Form.Item
                                name="name"
                                label="Tên khách hàng"
                                rules={[
                                    {
                                        required: true,
                                        whitespace: true,
                                        message:
                                            "Vui lòng nhập tên khách hàng.",
                                    },
                                ]}
                            >
                                <Input
                                    maxLength={255}
                                    placeholder="Nhập tên khách hàng hoặc doanh nghiệp"
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} md={12}>
                            <Form.Item
                                name="email"
                                label="Email"
                                rules={[
                                    {
                                        type: "email",
                                        message: "Email không hợp lệ.",
                                    },
                                ]}
                            >
                                <Input
                                    type="email"
                                    maxLength={255}
                                    autoComplete="email"
                                    placeholder="Ví dụ: contact@company.vn"
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} md={12}>
                            <Form.Item name="phone" label="Số điện thoại">
                                <Input
                                    type="tel"
                                    maxLength={30}
                                    autoComplete="tel"
                                    placeholder="Nhập số điện thoại"
                                />
                            </Form.Item>
                        </Col>

                        <Col span={24}>
                            <Form.Item name="address" label="Địa chỉ">
                                <Input
                                    maxLength={255}
                                    autoComplete="street-address"
                                    placeholder="Số nhà, đường, phường/xã, tỉnh/thành phố"
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} md={8}>
                            <Form.Item name="company_type" label="Loại khách">
                                <Select
                                    allowClear
                                    placeholder="Chọn loại khách"
                                    options={options.companyTypes}
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} md={8}>
                            <Form.Item name="industry" label="Ngành nghề">
                                <Select
                                    allowClear
                                    showSearch
                                    optionFilterProp="label"
                                    placeholder="Chọn ngành nghề"
                                    options={options.industries}
                                />
                            </Form.Item>
                        </Col>

                        <Col xs={24} md={8}>
                            <Form.Item
                                name="status"
                                label="Trạng thái"
                                rules={[
                                    {
                                        required: true,
                                        message: "Vui lòng chọn trạng thái.",
                                    },
                                ]}
                            >
                                <Select options={options.statuses} />
                            </Form.Item>
                        </Col>

                        <Col span={24}>
                            <Form.Item name="notes" label="Mô tả">
                                <Input.TextArea
                                    rows={4}
                                    maxLength={5000}
                                    showCount
                                    placeholder="Thông tin bổ sung về khách hàng..."
                                />
                            </Form.Item>
                        </Col>
                    </Row>
                </Card>

                <div className="crm-form-actions">
                    <Button
                        disabled={saving}
                        onClick={() => router.visit(cancelHref)}
                    >
                        Hủy
                    </Button>

                    <Button type="primary" htmlType="submit" loading={saving}>
                        {isEdit ? "Lưu thay đổi" : "Tạo khách hàng"}
                    </Button>
                </div>
            </Form>
        </AppLayout>
    );
}
