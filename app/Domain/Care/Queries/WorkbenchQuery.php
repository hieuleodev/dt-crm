<?php

namespace App\Domain\Care\Queries;

use App\Domain\Care\Data\TaskData;
use App\Domain\Care\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;

class WorkbenchQuery
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function handle(User $user, array $filters): array
    {
        $now = CarbonImmutable::now('UTC');
        $localNow = $now->setTimezone(self::TIMEZONE);

        $start = $localNow->startOfDay()->utc();
        $end = $localNow->startOfDay()->addDay()->utc();
        $upcomingEnd = $end->addDays(7);

        $scope = $user->isManager()
            ? ($filters['scope'] ?? 'mine')
            : 'mine';

        $assigneeId = $scope === 'all'
            ? ($filters['assignee_id'] ?? null)
            : $user->id;

        $base = Task::query()->visibleTo($user);

        if ($assigneeId) {
            $base->where('assignee_id', $assigneeId);
        }

        $term = trim($filters['q'] ?? '');

        if ($term !== '') {
            $base->where(function ($query) use ($term) {
                $query->whereLike('title', "%{$term}%")
                    ->orWhereLike('description', "%{$term}%");
            });
        }

        if (filled($filters['priority'] ?? null)) {
            $base->where('priority', $filters['priority']);
        }

        $open = (clone $base)->whereIn('status', Task::OPEN_STATUSES);

        $overdue = (clone $open)->where('due_at', '<', $now);

        $today = (clone $open)
            ->where('due_at', '>=', $now)
            ->where('due_at', '<', $end);

        $upcoming = (clone $open)
            ->where('due_at', '>=', $end)
            ->where('due_at', '<', $upcomingEnd);

        $completed = (clone $base)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end);

        $summary = [
            'overdue' => (clone $overdue)->count(),
            'today' => (clone $today)->count(),
            'in_progress' => (clone $open)
                ->where('status', 'in_progress')
                ->count(),
            'completed' => (clone $completed)->count(),
            'upcoming' => (clone $upcoming)->count(),
        ];

        $bucket = $filters['bucket']
            ?? ($summary['overdue'] > 0 ? 'overdue' : 'today');

        $query = match ($bucket) {
            'overdue' => clone $overdue,
            'upcoming' => clone $upcoming,
            'completed' => clone $completed,
            default => clone $today,
        };

        $query->with([
            'assignee:id,name',
            'customer:id,code,name',
            'lead:id,title',
        ]);

        if ($bucket === 'completed') {
            $query->orderByDesc('completed_at');
        } else {
            $query
                ->orderByRaw(
                    "CASE priority
                        WHEN 'urgent' THEN 0
                        WHEN 'high' THEN 1
                        ELSE 2
                    END"
                )
                ->orderBy('due_at');
        }

        $tasks = $query
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Task $task) => TaskData::from($task));

        return [
            'tasks' => $tasks,
            'summary' => $summary,

            'filters' => [
                'scope' => $scope,
                'assignee_id' => $scope === 'all'
                    ? $assigneeId
                    : null,
                'bucket' => $bucket,
                'q' => $term,
                'priority' => $filters['priority'] ?? null,
            ],

            'generatedAt' => $now->toIso8601String(),
            'timezone' => self::TIMEZONE,
        ];
    }
}