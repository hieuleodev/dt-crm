export interface TaskReference {
    value: string;
    label: string;
    href?: string | null;
}

export interface TaskRow {
    id: number;
    title: string;
    description: string | null;
    type: string;
    priority: string;
    status: string;
    assignee_id: number;
    assignee: string;
    due_at: string;
    completed_at: string | null;
    result: string | null;
    version: number;
    overdue: boolean;
    reference: TaskReference | null;
}

export interface TaskOptions {
    types: Record<string, string>;
    priorities: Record<string, string>;
    statuses: Record<string, string>;
}

export interface TaskAssignee {
    id: number;
    name: string;
}
