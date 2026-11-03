import { useEffect, useState } from "react";
import { Col, Form, Input, InputNumber, Modal, Row, Select } from "antd";
import { emptyToNull, submitForm } from "@/lib/form";
import type { CustomerOptions, PlantItem } from "@/types/customer";

interface Props {
    open: boolean;
    customerId: number;
    plant: PlantItem | null;
    options: CustomerOptions;
    onClose: () => void;
}

export default function PlantModal({
    open,
    customerId,
    plant,
    options,
    onClose,
}: Props) {
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        form.resetFields();
        form.setFieldsValue(plant ?? { hazard_areas: [] });
    }, [open, plant, form]);

    const onFinish = (values: Record<string, unknown>) => {
        setSaving(true);
        const url = plant
            ? `/customers/${customerId}/plants/${plant.id}`
            : `/customers/${customerId}/plants`;
        submitForm(form, plant ? "put" : "post", url, emptyToNull(values), {
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal
            open={open}
            title={
                plant ? `Sửa nhà máy: ${plant.name}` : "Thêm nhà máy / địa điểm"
            }
            okText="Lưu"
            cancelText="Hủy"
            confirmLoading={saving}
            onOk={() => form.submit()}
            onCancel={onClose}
            width={720}
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
                            name="name"
                            label="Tên nhà máy"
                            rules={[
                                {
                                    required: true,
                                    message: "Vui lòng nhập tên nhà máy",
                                },
                            ]}
                        >
                            <Input
                                autoFocus
                                placeholder="VD: Nhà máy số 2 – Yên Phong"
                            />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="employee_count" label="Số công nhân">
                            <InputNumber min={0} style={{ width: "100%" }} />
                        </Form.Item>
                    </Col>
                    <Col span={24}>
                        <Form.Item name="address" label="Địa chỉ giao hàng">
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="industrial_zone" label="KCN">
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="province" label="Tỉnh / thành">
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col span={24}>
                        <Form.Item
                            name="hazard_areas"
                            label="Khu vực có rủi ro (để tư vấn PPE đúng)"
                        >
                            <Select
                                mode="tags"
                                options={options.hazardAreas}
                                placeholder="Chọn hoặc gõ thêm"
                            />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="receiving_hours" label="Giờ nhận hàng">
                            <Input placeholder="VD: 8h–11h, 13h–16h" />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item
                            name="delivery_notes"
                            label="Yêu cầu khi giao hàng"
                        >
                            <Input placeholder="VD: kèm CO/CQ, phiếu xuất kho" />
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Modal>
    );
}
