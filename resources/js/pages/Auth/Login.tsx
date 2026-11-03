import { Head, useForm } from "@inertiajs/react";
import { Button, Card, Checkbox, Form, Input, Typography } from "antd";
import { LockOutlined, MailOutlined } from "@ant-design/icons";

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    return (
        <div
            style={{
                minHeight: "100vh",
                display: "grid",
                placeItems: "center",
                background: "#f5f7fb",
                padding: 16,
            }}
        >
            <Head title="Đăng nhập" />
            <Card style={{ width: "100%", maxWidth: 380 }}>
                <Typography.Title level={4} style={{ marginTop: 0 }}>
                    D&T CRM
                </Typography.Title>
                <Typography.Paragraph type="secondary">
                    Đăng nhập để bắt đầu ngày làm việc
                </Typography.Paragraph>
                <Form layout="vertical" onFinish={() => post("/login")}>
                    <Form.Item
                        label="Email"
                        validateStatus={errors.email ? "error" : ""}
                        help={errors.email}
                    >
                        <Input
                            prefix={<MailOutlined />}
                            type="email"
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData("email", e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item
                        label="Mật khẩu"
                        validateStatus={errors.password ? "error" : ""}
                        help={errors.password}
                    >
                        <Input.Password
                            prefix={<LockOutlined />}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) =>
                                setData("password", e.target.value)
                            }
                        />
                    </Form.Item>
                    <Form.Item>
                        <Checkbox
                            checked={data.remember}
                            onChange={(e) =>
                                setData("remember", e.target.checked)
                            }
                        >
                            Ghi nhớ đăng nhập
                        </Checkbox>
                    </Form.Item>
                    <Button
                        type="primary"
                        htmlType="submit"
                        block
                        loading={processing}
                    >
                        Đăng nhập
                    </Button>
                </Form>
            </Card>
        </div>
    );
}
