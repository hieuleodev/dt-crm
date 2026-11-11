<?php

namespace App\Http\Controllers\Care;

use App\Data\Care\TaskData;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        return $user;
    }

    public function index(Request $request): Response|JsonResponse
    {
        $user = $this->actor($request);

        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'view' => ['nullable', Rule::in(['month', 'week', 'day', 'agenda'])],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::in(array_keys(Task::TYPES))],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'status' => [
                'nullable',
                Rule::in([...array_keys(Task::STATUSES), 'open', 'all']),
            ],
            'assignee_id' => [
                'nullable',
                'integer',
                'min:1',
                ...($user->isManager() ? [] : [Rule::in([$user->id])]),
            ],
            'q' => ['nullable', 'string', 'max:180'],
        ]);

        $view = $filters['view'] ?? 'month';
        $tz = 'Asia/Ho_Chi_Minh';
        $anchor = CarbonImmutable::parse(
            $filters['date'] ?? CarbonImmutable::now($tz)->toDateString(),
            $tz,
        );

        if (! empty($filters['start']) && ! empty($filters['end'])) {
            $startUtc = CarbonImmutable::parse($filters['start'], $tz)->startOfDay()->utc();
            $endUtc = CarbonImmutable::parse($filters['end'], $tz)->endOfDay()->utc();
        } else {
            [$startUtc, $endUtc] = match ($view) {
                'week' => [
                    $anchor->startOfWeek(CarbonImmutable::MONDAY)->startOfDay()->utc(),
                    $anchor->endOfWeek(CarbonImmutable::SUNDAY)->endOfDay()->utc(),
                ],
                'day' => [
                    $anchor->startOfDay()->utc(),
                    $anchor->endOfDay()->utc(),
                ],
                'agenda' => [
                    $anchor->startOfDay()->utc(),
                    $anchor->addDays(30)->endOfDay()->utc(),
                ],
                default => [ // month view with padded weeks
                    $anchor->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY)->startOfDay()->utc(),
                    $anchor->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->endOfDay()->utc(),
                ],
            };
        }

        $base = Task::query()->visibleTo($user);

        // Sales chỉ xem lịch của mình; quản lý mới xem toàn đội hoặc lọc nhân viên.
        if ($user->isManager() && ! empty($filters['assignee_id'])) {
            $base->where('assignee_id', $filters['assignee_id']);
        } elseif (! $user->isManager()) {
            $base->where('assignee_id', $user->id);
        }

        if (filled($filters['type'] ?? null)) {
            $base->where('type', $filters['type']);
        }

        if (filled($filters['priority'] ?? null)) {
            $base->where('priority', $filters['priority']);
        }

        $statusFilter = $filters['status'] ?? null;
        if ($statusFilter === 'open') {
            $base->whereIn('status', Task::OPEN_STATUSES);
        } elseif (filled($statusFilter) && $statusFilter !== 'all') {
            $base->where('status', $statusFilter);
        }

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $base->where(function ($query) use ($term) {
                $query->whereLike('title', "%{$term}%")
                    ->orWhereLike('description', "%{$term}%");
            });
        }

        $periodTasks = (clone $base)
            ->where('due_at', '>=', $startUtc)
            ->where('due_at', '<=', $endUtc)
            ->with(['assignee:id,name', 'customer:id,code,name', 'lead:id,title'])
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $nowUtc = CarbonImmutable::now('UTC');

        $summary = [
            'total' => $periodTasks->count(),
            'open' => $periodTasks->whereIn('status', Task::OPEN_STATUSES)->count(),
            'completed' => $periodTasks->where('status', 'completed')->count(),
            'overdue' => $periodTasks->filter(
                fn (Task $t) => $t->isOpen() && $t->due_at->isBefore($nowUtc),
            )->count(),
        ];

        $taskRows = $periodTasks->map(fn (Task $task) => TaskData::from($task))->values()->all();

        if ($request->wantsJson()) {
            return response()->json([
                'tasks' => $taskRows,
                'summary' => $summary,
                'filters' => [
                    'date' => $anchor->toDateString(),
                    'view' => $view,
                    ...$filters,
                ],
            ]);
        }

        return Inertia::render('Calendar/Index', [
            'tasks' => $taskRows,
            'filters' => (object) [
                'date' => $anchor->toDateString(),
                'view' => $view,
                ...$filters,
            ],
            'summary' => $summary,
            'options' => [
                'types' => Task::TYPES,
                'priorities' => Task::PRIORITIES,
                'statuses' => Task::STATUSES,
            ],
            'assignees' => $user->isManager()
                ? User::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : [$user->only('id', 'name')],
        ]);
    }
}
