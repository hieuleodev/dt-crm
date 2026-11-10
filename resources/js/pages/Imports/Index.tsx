import { useState, type FormEvent } from "react";
import { Head, useForm, usePage } from "@inertiajs/react";
import {
    Alert,
    Button,
    Card,
    Col,
    Row,
    Select,
    Space,
    Typography,
    Upload,
} from "antd";
import { DownloadOutlined, UploadOutlined } from "@ant-design/icons";

import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import type { PageProps } from "@/types";

type ImportType = "customer" | "lead";

const IMPORT_TYPES: { value: ImportType; label: string }[] = [
    { value: "customer", label: "Khách hàng" },
    { value: "lead", label: "Lead" },
];

const IMPORT_LABELS: Record<ImportType, string> = {
    customer: "khách hàng",
    lead: "Lead",
};

interface ImportForm {
    type: ImportType;
    file: File | null;
}

export default function Index() {
    const { flash } = usePage<PageProps>().props;
    const form = useForm<ImportForm>({ type: "customer", file: null });
    const [fileKey, setFileKey] = useState(0);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post("/imports", {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.setData("file", null);
                setFileKey((current) => current + 1);
            },
        });
    }

    function changeType(type: ImportType) {
        form.setData({ type, file: null });
        form.clearErrors();
        setFileKey((current) => current + 1);
    }

    const result = flash.importResult;

    return (
        <AppLayout>
            <Head title="Nhập dữ liệu" />
            <div className="crm-import">
                <PageHeader
                    title="Nhập dữ liệu"
                    subtitle="Tải danh sách Khách hàng hoặc Lead bằng tệp CSV, Excel (.xlsx)."
                />

                {result && (
                    <Alert
                        className="crm-import__result"
                        showIcon
                        type="success"
                        title={`Đã nhập ${result.created} ${IMPORT_LABELS[result.type]}; bỏ qua ${result.duplicates} dòng trùng.`}
                    />
                )}

                <Row gutter={[16, 16]}>
                    <Col xs={24} lg={12}>
                        <Card title="Tải mẫu dữ liệu">
                            <div className="crm-import__card-content">
                                <Typography.Paragraph className="crm-muted">
                                    Chọn đúng loại dữ liệu và giữ nguyên tên
                                    các cột trong mẫu. Mỗi tệp chỉ chứa một
                                    loại dữ liệu.
                                </Typography.Paragraph>
                                <Space wrap>
                                    <Button
                                        href={`/imports/templates/${form.data.type}/csv`}
                                        icon={<DownloadOutlined />}
                                    >
                                        Mẫu CSV
                                    </Button>
                                    <Button
                                        href={`/imports/templates/${form.data.type}/xlsx`}
                                        icon={<DownloadOutlined />}
                                    >
                                        Mẫu Excel (.xlsx)
                                    </Button>
                                </Space>
                            </div>
                        </Card>
                    </Col>

                    <Col xs={24} lg={12}>
                        <Card title="Tải tệp lên">
                            <form
                                className="crm-import__form"
                                onSubmit={submit}
                            >
                                <div className="crm-import__field">
                                    <label htmlFor="import-type">
                                        Loại dữ liệu
                                    </label>
                                    <Select
                                        id="import-type"
                                        value={form.data.type}
                                        options={IMPORT_TYPES}
                                        onChange={changeType}
                                        disabled={form.processing}
                                    />
                                </div>

                                <div className="crm-import__field">
                                    <label>
                                        Tệp CSV hoặc XLSX
                                    </label>
                                    <Upload
                                        key={fileKey}
                                        accept=".csv,.xlsx"
                                        maxCount={1}
                                        beforeUpload={(file) => {
                                            form.setData("file", file);
                                            form.clearErrors("file");

                                            return false;
                                        }}
                                        onRemove={() => {
                                            form.setData("file", null);

                                            return true;
                                        }}
                                    >
                                        <Button
                                            icon={<UploadOutlined />}
                                            disabled={form.processing}
                                        >
                                            Chọn tệp
                                        </Button>
                                    </Upload>
                                    <Typography.Text className="crm-muted">
                                        Tối đa 5 MB và 1.000 dòng dữ liệu.
                                    </Typography.Text>
                                </div>

                                {form.errors.file && (
                                    <Alert
                                        showIcon
                                        type="error"
                                        title={form.errors.file}
                                    />
                                )}

                                <Alert
                                    showIcon
                                    type="info"
                                    title="Nếu tệp có dòng sai định dạng, hệ thống sẽ không nhập dòng nào. Dòng trùng sẽ được bỏ qua."
                                />

                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    icon={<UploadOutlined />}
                                    loading={form.processing}
                                    disabled={!form.data.file}
                                >
                                    Nhập {IMPORT_LABELS[form.data.type]}
                                </Button>
                            </form>
                        </Card>
                    </Col>
                </Row>
            </div>
        </AppLayout>
    );
}
