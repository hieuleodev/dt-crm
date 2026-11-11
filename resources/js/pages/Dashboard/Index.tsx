import { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Button, Card, Empty, Progress, Select, Space, Table, Tag } from "antd";
import type { TableColumnsType } from "antd";
import { ArrowRightOutlined, PlusOutlined, TeamOutlined, CheckCircleOutlined, ClockCircleOutlined, WarningOutlined, SolutionOutlined, UnorderedListOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import AppLayout from "@/layouts/AppLayout";
import PageHeader from "@/components/common/PageHeader";
import TaskModal from "@/components/tasks/TaskModal";
import type { PageProps } from "@/types";
import type { TaskAssignee, TaskOptions, TaskRow } from "@/types/task";

dayjs.extend(utc);
dayjs.extend(timezone);

interface TeamMember {
    id: number;
    name: string;
    role: string;
    open: number;
    in_progress: number;
    overdue: number;
    completed: number;
}

interface Props extends PageProps {
    canAssign: boolean;
    filters: { assignee_id: number | null };
    summary: { customers: number; leads: number; open: number; today: number; overdue: number; completedToday: number };
    statusCounts: { status: string; label: string; total: number }[];
    upcomingTasks: TaskRow[];
    team: TeamMember[];
    assignees: TaskAssignee[];
    options: TaskOptions;
}

export default function Index() {
    const { auth, canAssign, filters, summary, statusCounts, upcomingTasks, team, assignees, options } = usePage<Props>().props;
    const [editor, setEditor] = useState<{ assigneeId?: number } | null>(null);
    const [filtering, setFiltering] = useState(false);
    const taskUrl = (bucket = "open", assigneeId = filters.assignee_id) =>
        `/tasks?bucket=${bucket}${assigneeId ? `&assignee_id=${assigneeId}` : ""}`;
    const totalTasks = statusCounts.reduce((total, item) => total + item.total, 0);
    const completed = statusCounts.find((item) => item.status === "completed")?.total ?? 0;
    const cancelled = statusCounts.find((item) => item.status === "cancelled")?.total ?? 0;
    const actionable = totalTasks - cancelled;
    const percent = actionable ? Math.round(completed / actionable * 100) : 0;
    const metrics = [
        { key: "customers", label: canAssign ? "Khách hàng" : "Khách hàng phụ trách", value: summary.customers, icon: <TeamOutlined />, tone: "blue", href: "/customers" },
        { key: "leads", label: canAssign ? "Lead" : "Lead phụ trách", value: summary.leads, icon: <SolutionOutlined />, tone: "blue", href: "/leads?kind=lead" },
        { key: "open", label: canAssign ? "Việc chưa hoàn thành" : "Nhiệm vụ được giao", value: summary.open, icon: <UnorderedListOutlined />, tone: "neutral", href: taskUrl() },
        { key: "today", label: "Việc đến hạn hôm nay", value: summary.today, icon: <ClockCircleOutlined />, tone: "amber", href: taskUrl("today") },
        { key: "overdue", label: "Việc quá hạn", value: summary.overdue, icon: <WarningOutlined />, tone: "red", href: taskUrl("overdue") },
        { key: "completed", label: "Hoàn thành hôm nay", value: summary.completedToday, icon: <CheckCircleOutlined />, tone: "green" },
    ];
    const columns: TableColumnsType<TeamMember> = [
        { title: "Người thực hiện", key: "member", width: 230, render: (_, member) => <div className="crm-dashboard__member"><strong>{member.name}</strong><span>{member.role}</span></div> },
        { title: "Chưa hoàn thành", dataIndex: "open", align: "center", render: (value, member) => <Link href={taskUrl("open", member.id)}>{value}</Link> },
        { title: "Đang thực hiện", dataIndex: "in_progress", align: "center" },
        { title: "Quá hạn", dataIndex: "overdue", align: "center", render: (value, member) => <Link href={taskUrl("overdue", member.id)}><Tag color={value ? "red" : "default"}>{value}</Tag></Link> },
        { title: "Đã hoàn thành", dataIndex: "completed", align: "center" },
        { title: "Phân công", key: "assign", width: 140, render: (_, member) => <Button onClick={() => setEditor({ assigneeId: member.id })} aria-label={`Giao việc cho ${member.name}`}>Giao việc</Button> },
    ];

    return (
        <AppLayout>
            <Head title="Tổng quan" />
            <div className="crm-dashboard">
                <PageHeader title={canAssign ? "Tổng quan quản trị" : "Tổng quan"}
                    subtitle={`Xin chào, ${auth.user?.name}. ${canAssign ? "Theo dõi tiến độ và phân công công việc cho đội ngũ." : "Theo dõi khách hàng và các nhiệm vụ bạn phụ trách."}`}
                    extra={canAssign && <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditor({ assigneeId: filters.assignee_id ?? undefined })}>Giao nhiệm vụ</Button>} />

                {/* Chỉ quản lý xem bộ lọc đội ngũ và thống kê phân công. */}
                {canAssign && (
                    <div className="crm-dashboard__scope">
                        <div><strong>Phạm vi số liệu</strong><span>Lọc các chỉ số và danh sách việc theo người phụ trách.</span></div>
                        <Select aria-label="Phạm vi tổng quan" placeholder="Toàn bộ đội ngũ" allowClear showSearch optionFilterProp="label" loading={filtering} disabled={filtering} value={filters.assignee_id ?? undefined} options={assignees.map((member) => ({ value: member.id, label: member.name }))}
                            onChange={(value) => router.get("/", value ? { assignee_id: value } : {}, { preserveScroll: true, preserveState: true, onStart: () => setFiltering(true), onFinish: () => setFiltering(false) })} />
                    </div>
                )}

                <div className="crm-dashboard__metrics">
                    {metrics.map((metric) => <Card key={metric.key} className={`crm-dashboard__metric crm-dashboard__metric--${metric.tone}`}>
                        <div className="crm-dashboard__metric-top"><span>{metric.label}</span><span aria-hidden="true">{metric.icon}</span></div>
                        <strong>{metric.value.toLocaleString("vi-VN")}</strong>
                        {metric.href && <Link href={metric.href}>Xem danh sách <ArrowRightOutlined /></Link>}
                    </Card>)}
                </div>

                <div className="crm-dashboard__panels">
                    <Card title={canAssign ? "Việc cần ưu tiên" : "Công việc được giao"} extra={<Link href={taskUrl()}>Xem tất cả</Link>}>
                        {canAssign && <p className="crm-dashboard__hint">Các việc chưa hoàn thành, sắp xếp theo hạn xử lý gần nhất; việc quá hạn đứng trước.</p>}
                        {upcomingTasks.length ? <ul className="crm-dashboard__tasks">{upcomingTasks.map((task) => <li key={task.id}>
                            <div className="crm-dashboard__task-body">
                                <Link href={taskUrl("open", task.assignee_id)}>{task.title}</Link>
                                {task.reference && (
                                    task.reference.href
                                        ? <Link className="crm-dashboard__reference" href={task.reference.href}>{task.reference.label}</Link>
                                        : <span>{task.reference.label}</span>
                                )}
                                <span>{canAssign ? `${task.assignee} · ` : ""}{dayjs(task.due_at).tz("Asia/Ho_Chi_Minh").format("DD/MM/YYYY HH:mm")}</span>
                            </div>
                            <Space wrap>{task.overdue && <Tag color="red">Quá hạn</Tag>}<Tag color={task.priority === "urgent" ? "red" : task.priority === "high" ? "orange" : "default"}>{options.priorities[task.priority]}</Tag><Tag color={task.status === "in_progress" ? "blue" : "default"}>{options.statuses[task.status]}</Tag></Space>
                        </li>)}</ul> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={canAssign ? "Không có nhiệm vụ cần xử lý trong phạm vi này." : "Chưa có công việc được giao. Quản lý sẽ phân công công việc cho bạn."} />}
                    </Card>
                    <Card title="Tiến độ công việc">
                        <div className="crm-dashboard__progress"><Progress type="circle" percent={percent} size={128} strokeColor="#237545" /><p>{completed} / {actionable} việc đã hoàn thành</p><span>Không tính nhiệm vụ đã hủy · Toàn thời gian</span></div>
                        <ul className="crm-dashboard__statuses">{statusCounts.map((item) => <li key={item.status}><span>{item.label}</span><strong>{item.total}</strong></li>)}</ul>
                    </Card>
                </div>

                {canAssign && <Card title="Phân công và khối lượng công việc" extra={<Tag>Toàn bộ đội ngũ đang hoạt động</Tag>}>
                    <p className="crm-dashboard__hint">Chọn “Giao việc” để tạo nhiệm vụ cho đúng người. Cột đang thực hiện và quá hạn là các phần của số việc chưa hoàn thành.</p>
                    <Table<TeamMember> rowKey="id" columns={columns} dataSource={team} scroll={{ x: 850 }} pagination={{ pageSize: 5, showSizeChanger: false, hideOnSinglePage: true }} locale={{ emptyText: "Chưa có tài khoản đang hoạt động." }} />
                </Card>}

            </div>
            {canAssign && <TaskModal open={editor !== null} task={null} options={options} assignees={assignees} currentUserId={auth.user!.id} initialAssigneeId={editor?.assigneeId} initialReference={null} onClose={() => setEditor(null)} />}
        </AppLayout>
    );
}
