import { useEffect, useState } from "react";
import {
    Checkbox,
    Col,
    DatePicker,
    Form,
    Input,
    Modal,
    Row,
    Select,
} from "antd";
import dayjs from "dayjs";
import { emptyToNull, submitForm } from "@/lib/form";
import type { ContactItem, CustomerOptions, PlantItem } from "@/types/customer";

interface Props {
    open: boolean;
    customerId: number;
    contact: ContactItem | null;
    plants: PlantItem[];
    options: CustomerOptions;
    onClose: () => void;
}

const LANGUAGES = [
    { value: "vi", label: "Tiếng Việt" },
    { value: "en", label: "Tiếng Anh" },
    { value: "ko", label: "Tiếng Hàn" },
    { value: "zh", label: "Tiếng Trung" },
    { value: "ja", label: "Tiếng Nhật" },
];

export default function ContactModal({
    open,
    customerId,
    contact,
    plants,
    options,
    onClose,
}: Props) {
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        form.resetFields();
        form.setFieldsValue(
            contact
                ? {
                      ...contact,
                      birthday: contact.birthday
                          ? dayjs(contact.birthday)
                          : null,
                      left_at: contact.left_at ? dayjs(contact.left_at) : null,
                  }
                : {
                      buying_role: "purchasing",
                      language: "vi",
                      is_primary: false,
                      marketing_opt_out: false,
                  },
        );
    }, [open, contact, form]);

    const onFinish = (values: Record<string, unknown>) => {
        setSaving(true);
        const data = emptyToNull({
            ...values,
            birthday: values.birthday
                ? (values.birthday as dayjs.Dayjs).format("YYYY-MM-DD")
                : null,
            left_at: values.left_at
                ? (values.left_at as dayjs.Dayjs).format("YYYY-MM-DD")
                : null,
        });
        const url = contact
            ? `/customers/${customerId}/contacts/${contact.id}`
            : `/customers/${customerId}/contacts`;
        submitForm(form, contact ? "put" : "post", url, data, {
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal
            open={open}
            title={
                contact
                    ? `Sửa người liên hệ: ${contact.full_name}`
                    : "Thêm người liên hệ"
            }
            okText="Lưu"
            cancelText="Hủy"
            confirmLoading={saving}
            onOk={() => form.submit()}
            onCancel={onClose}
            width={760}
            destroyOnHidden
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={onFinish}
                requiredMark="optional"
            >
                <Row gutter={16}>
                    <Col xs={24} md={12}>
                        <Form.Item
                            name="full_name"
                            label="Họ tên"
                            rules={[
                                {
                                    required: true,
                                    message: "Vui lòng nhập họ tên",
                                },
                            ]}
                        >
                            <Input autoFocus />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="title" label="Chức vụ">
                            <Input placeholder="VD: Purchasing Manager" />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item
                            name="buying_role"
                            label="Vai trò mua hàng"
                            rules={[{ required: true }]}
                        >
                            <Select options={options.buyingRoles} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="influence" label="Mức ảnh hưởng">
                            <Select allowClear options={options.influences} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="attitude" label="Thái độ với D&T">
                            <Select allowClear options={options.attitudes} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="phone" label="Điện thoại">
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item
                            name="email"
                            label="Email"
                            rules={[
                                {
                                    type: "email",
                                    message: "Email không hợp lệ",
                                },
                            ]}
                        >
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="zalo" label="Zalo">
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="department" label="Phòng ban">
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="plant_id" label="Nhà máy phụ trách">
                            <Select
                                allowClear
                                options={plants.map((p) => ({
                                    value: p.id,
                                    label: p.name,
                                }))}
                                placeholder={
                                    plants.length
                                        ? "Chọn nhà máy"
                                        : "Chưa có nhà máy"
                                }
                            />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item
                            name="preferred_channel"
                            label="Kênh liên lạc ưa thích"
                        >
                            <Select
                                allowClear
                                options={options.contactChannels}
                            />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="language" label="Ngôn ngữ">
                            <Select allowClear options={LANGUAGES} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="birthday" label="Sinh nhật">
                            <DatePicker
                                format="DD/MM/YYYY"
                                style={{ width: "100%" }}
                            />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item
                            name="left_at"
                            label="Ngày nghỉ việc"
                            tooltip="Ghi nhận khi người này nghỉ – lịch sử vẫn được giữ"
                        >
                            <DatePicker
                                format="DD/MM/YYYY"
                                style={{ width: "100%" }}
                            />
                        </Form.Item>
                    </Col>
                    <Col span={24}>
                        <Form.Item name="notes" label="Ghi chú">
                            <Input.TextArea rows={2} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="is_primary" valuePropName="checked">
                            <Checkbox>Người liên hệ chính</Checkbox>
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item
                            name="marketing_opt_out"
                            valuePropName="checked"
                        >
                            <Checkbox>Từ chối nhận tin tiếp thị</Checkbox>
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Modal>
    );
}
