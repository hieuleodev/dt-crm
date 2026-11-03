import { Head, usePage } from "@inertiajs/react";
import { Card, Col, Row, Statistic, Typography } from "antd";
import AppLayout from "@/layouts/AppLayout";
import type { PageProps } from "@/types";

export default function Index() {
    const { auth } = usePage<PageProps>().props;

    return (
        <AppLayout>
            <Head title="Tổng quan" />
            <Typography.Title level={3} style={{ marginTop: 0 }}>
                Xin chào, {auth.user?.name}
            </Typography.Title>
            <Row gutter={[16, 16]}>
                {[
                    "Khách cần chăm sóc hôm nay",
                    "Việc quá hạn",
                    "Khách đang kẹt",
                    "Khách có nguy cơ rời bỏ",
                ].map((t) => (
                    <Col xs={24} sm={12} lg={6} key={t}>
                        <Card>
                            <Statistic title={t} value="—" />
                        </Card>
                    </Col>
                ))}
            </Row>
        </AppLayout>
    );
}
