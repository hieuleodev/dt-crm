import { useEffect, useState } from "react";
import { Form, Input, Modal, Select } from "antd";
import { submitForm } from "@/lib/form";
import type { OwnerOption } from "@/types/customer";

interface Props {
    open: boolean;
    customerId: number;
    currentOwnerId: number | null;
    owners: OwnerOption[];
    onClose: () => void;
}

export default function ReassignModal({
    open,
    customerId,
    currentOwnerId,
    owners,
    onClose,
}: Props) {
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) form.setFieldsValue({ owner_id: currentOwnerId, reason: "" });
    }, [open, currentOwnerId, form]);

    return (
        <Modal
            open={open}
            title="Chuyển người phụ trách"
            okText="Chuyển"
            cancelText="Hủy"
            confirmLoading={saving}
            onOk={() => form.submit()}
            onCancel={onClose}
            destroyOnHidden
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={(values) => {
                    setSaving(true);
                    submitForm(
                        form,
                        "post",
                        `/customers/${customerId}/reassign`,
                        values,
                        {
                            onSuccess: onClose,
                            onFinish: () => setSaving(false),
                        },
                    );
                }}
            >
                <Form.Item name="owner_id" label="Người phụ trách mới">
                    <Select
                        allowClear
                        placeholder="Bỏ trống = chưa giao"
                        options={owners.map((o) => ({
                            value: o.id,
                            label: o.name,
                        }))}
                        showSearch
                        optionFilterProp="label"
                    />
                </Form.Item>
                <Form.Item name="reason" label="Lý do">
                    <Input placeholder="VD: nhân viên nghỉ việc, chia lại khu vực…" />
                </Form.Item>
            </Form>
        </Modal>
    );
}
