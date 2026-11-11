<?php

namespace App\Http\Controllers;

use App\Data\Care\TaskData;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403);

        $canAssign = $user->isManager();
        $filters = $canAssign ? $request->validate([
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ]) : [];
        $assigneeId = $filters['assignee_id'] ?? null;
        $now = CarbonImmutable::now('UTC');
        $start = $now->setTimezone('Asia/Ho_Chi_Minh')->startOfDay()->utc();
        $end = $start->addDay();
        // Sales chỉ nhận số liệu và nhiệm vụ thuộc phạm vi hồ sơ được giao.
        $tasks = Task::query()->visibleTo($user)
            ->when($assigneeId, fn ($query) => $query->where('assignee_id', $assigneeId));
        $open = (clone $tasks)->whereIn('status', Task::OPEN_STATUSES);
        $statusCounts = (clone $tasks)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        $team = $canAssign ? User::query()->where('is_active', true)
            ->select(['id', 'name', 'email', 'role'])
            ->selectSub(Task::query()->selectRaw('COUNT(*)')->whereColumn('assignee_id', 'users.id')->whereIn('status', Task::OPEN_STATUSES), 'open_tasks')
            ->selectSub(Task::query()->selectRaw('COUNT(*)')->whereColumn('assignee_id', 'users.id')->where('status', 'in_progress'), 'in_progress_tasks')
            ->selectSub(Task::query()->selectRaw('COUNT(*)')->whereColumn('assignee_id', 'users.id')->whereIn('status', Task::OPEN_STATUSES)->where('due_at', '<', $now), 'overdue_tasks')
            ->selectSub(Task::query()->selectRaw('COUNT(*)')->whereColumn('assignee_id', 'users.id')->where('status', 'completed'), 'completed_tasks')
            ->orderBy('name')->get()->map(fn (User $member) => [
                'id' => $member->id, 'name' => $member->name, 'role' => $member->role->label(),
                'open' => (int) $member->open_tasks,
                'in_progress' => (int) $member->in_progress_tasks,
                'overdue' => (int) $member->overdue_tasks,
                'completed' => (int) $member->completed_tasks,
            ]) : [];

        return Inertia::render('Dashboard/Index', [
            'canAssign' => $canAssign,
            'filters' => ['assignee_id' => $assigneeId ? (int) $assigneeId : null],
            'summary' => [
                'customers' => Customer::query()->visibleTo($user)->when($assigneeId, fn ($query) => $query->where('owner_id', $assigneeId))->count(),
                'leads' => Lead::query()->visibleTo($user)->where('kind', 'lead')->when($assigneeId, fn ($query) => $query->where('owner_id', $assigneeId))->count(),
                'open' => (clone $open)->count(),
                'today' => (clone $open)->where('due_at', '>=', $now)->where('due_at', '<', $end)->count(),
                'overdue' => (clone $open)->where('due_at', '<', $now)->count(),
                'completedToday' => (clone $tasks)->where('status', 'completed')->where('completed_at', '>=', $start)->where('completed_at', '<', $end)->count(),
            ],
            'statusCounts' => collect(Task::STATUSES)->map(fn ($label, $status) => [
                'status' => $status, 'label' => $label, 'total' => (int) ($statusCounts[$status] ?? 0),
            ])->values(),
            // Giữ việc quá hạn và đến hạn hôm nay trước việc sắp tới.
            'upcomingTasks' => (clone $open)->with(['assignee:id,name', 'customer:id,code,name', 'lead:id,title'])
                ->orderByRaw('CASE WHEN due_at < ? THEN 0 WHEN due_at < ? THEN 1 ELSE 2 END', [$now, $end])
                ->orderBy('due_at')->orderBy('id')->limit(6)->get()->map(fn (Task $task) => TaskData::from($task)),
            'team' => $team,
            'assignees' => $canAssign ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : [$user->only('id', 'name')],
            'options' => ['types' => Task::TYPES, 'priorities' => Task::PRIORITIES, 'statuses' => Task::STATUSES],
            'generatedAt' => $now->toIso8601String(),
        ]);
    }
}
