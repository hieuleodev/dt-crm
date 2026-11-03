<?php

namespace App\Domain\Care\Data;

use App\Domain\Care\Models\Task;

class TaskData
{
    public static function from(Task $task): array
    {
        $reference = null;

        if ($task->customer_id) {
            $reference = [
                'value' => "customer:{$task->customer_id}",
                'label' => $task->customer
                    ? "{$task->customer->code} · {$task->customer->name}"
                    : 'Khách hàng không còn khả dụng',
                'href' => $task->customer
                    ? route('customers.show', $task->customer_id)
                    : null,
            ];
        } elseif ($task->lead_id) {
            $reference = [
                'value' => "lead:{$task->lead_id}",
                'label' => $task->lead?->title ?? 'Lead không còn khả dụng',
                'href' => $task->lead
                    ? route('leads.index', ['q' => $task->lead->title])
                    : null,
            ];
        }

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'type' => $task->type,
            'priority' => $task->priority,
            'status' => $task->status,
            'assignee_id' => $task->assignee_id,
            'assignee' => $task->assignee?->name ?? 'Không còn hoạt động',
            'due_at' => $task->due_at->utc()->toIso8601String(),
            'completed_at' => $task->completed_at?->utc()->toIso8601String(),
            'result' => $task->result,
            'version' => $task->version,
            'overdue' => $task->isOverdue(),
            'reference' => $reference,
        ];
    }
}