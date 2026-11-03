<?php

namespace App\Http\Controllers\Care;

use App\Domain\Care\Actions\ChangeTaskStatus;
use App\Domain\Care\Actions\SaveTask;
use App\Domain\Care\Models\Task;
use App\Domain\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        return $user;
    }

    public function index(Request $request): Response
    {
        $user = $this->actor($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'bucket' => [
                'nullable',
                Rule::in([
                    'open',
                    'today',
                    'overdue',
                    'completed',
                    'cancelled',
                    'all',
                ]),
            ],
            'priority' => [
                'nullable',
                Rule::in(array_keys(Task::PRIORITIES)),
            ],
            'assignee_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'lead_id' => [
                'nullable',
                'integer',
                'min:1',
                'prohibits:customer_id',
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $bucket = $filters['bucket'] ?? 'open';

        $base = Task::query()->visibleTo($user);
        $initialReference = null;

        if (! empty($filters['customer_id'])) {
            $customer = Customer::query()
                ->visibleTo($user)
                ->findOrFail($filters['customer_id']);

            $base->where('customer_id', $customer->id);

            $initialReference = [
                'value' => "customer:{$customer->id}",
                'label' => "{$customer->code} · {$customer->name}",
            ];
        }

        if (! empty($filters['lead_id'])) {
            $lead = Lead::query()
                ->visibleTo($user)
                ->findOrFail($filters['lead_id']);

            $base->where('lead_id', $lead->id);

            $initialReference = [
                'value' => "lead:{$lead->id}",
                'label' => "Lead/Cơ hội · {$lead->title}",
            ];
        }

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);

            $base->where(function ($query) use ($term) {
                $query->whereLike('title', "%{$term}%")
                    ->orWhereLike('description', "%{$term}%");
            });
        }

        if (filled($filters['priority'] ?? null)) {
            $base->where('priority', $filters['priority']);
        }

        if ($user->isManager() && ! empty($filters['assignee_id'])) {
            $base->where('assignee_id', $filters['assignee_id']);
        }

        $now = CarbonImmutable::now('UTC');

        $start = CarbonImmutable::now('Asia/Ho_Chi_Minh')
            ->startOfDay()
            ->utc();

        $end = $start->addDay();

        $summary = [
            'open' => (clone $base)
                ->whereIn('status', Task::OPEN_STATUSES)
                ->count(),

            'today' => (clone $base)
                ->whereIn('status', Task::OPEN_STATUSES)
                ->where('due_at', '>=', $start)
                ->where('due_at', '<', $end)
                ->count(),

            'overdue' => (clone $base)
                ->whereIn('status', Task::OPEN_STATUSES)
                ->where('due_at', '<', $now)
                ->count(),
        ];

        $query = clone $base;

        match ($bucket) {
            'open' => $query->whereIn('status', Task::OPEN_STATUSES),

            'today' => $query
                ->whereIn('status', Task::OPEN_STATUSES)
                ->where('due_at', '>=', $start)
                ->where('due_at', '<', $end),

            'overdue' => $query
                ->whereIn('status', Task::OPEN_STATUSES)
                ->where('due_at', '<', $now),

            'completed', 'cancelled' => $query->where('status', $bucket),

            default => null,
        };

        $tasks = $query
            ->with(['assignee:id,name', 'customer:id,code,name', 'lead:id,title'])
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Task $task) => $this->row($task));

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => (object) [...$filters, 'bucket' => $bucket],
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
            'initialReference' => $initialReference,
        ]);
    }

    public function references(Request $request): JsonResponse
    {
        $user = $this->actor($request);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
        ]);

        $term = trim($data['q'] ?? '');

        $customers = Customer::query()
            ->visibleTo($user)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(fn ($query) => $query
                    ->whereLike('name', "%{$term}%")
                    ->orWhereLike('code', "%{$term}%"));
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'code', 'name'])
            ->map(fn ($customer) => [
                'value' => "customer:{$customer->id}",
                'label' => "{$customer->code} · {$customer->name}",
            ]);

        $leads = Lead::query()
            ->visibleTo($user)
            ->when(
                $term !== '',
                fn ($query) => $query->whereLike('title', "%{$term}%"),
            )
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'title', 'kind'])
            ->map(fn ($lead) => [
                'value' => "lead:{$lead->id}",
                'label' => ($lead->kind === 'opportunity' ? 'Cơ hội' : 'Lead')
                    ." · {$lead->title}",
            ]);

        return response()->json([
            'options' => $customers->concat($leads)->values(),
        ]);
    }

    public function store(
        Request $request,
        SaveTask $save,
    ): RedirectResponse {
        $user = $this->actor($request);

        $save->handle($this->validateTask($request, false), $user);

        return back()->with('success', 'Đã tạo nhiệm vụ.');
    }

    public function update(
        Request $request,
        int $task,
        SaveTask $save,
    ): RedirectResponse {
        $user = $this->actor($request);

        Task::query()->visibleTo($user)->findOrFail($task);

        $save->handle($this->validateTask($request, true), $user, $task);

        return back()->with('success', 'Đã cập nhật nhiệm vụ.');
    }

    public function status(
        Request $request,
        int $task,
        ChangeTaskStatus $change,
    ): RedirectResponse {
        $user = $this->actor($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'version' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $change->handle($task, $data, $user);

        return back()->with('success', 'Đã cập nhật trạng thái nhiệm vụ.');
    }

    private function validateTask(Request $request, bool $updating): array
    {
        $user = $request->user();

        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(array_keys(Task::TYPES))],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],

            'assignee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
                ...($user->isManager() ? [] : [Rule::in([$user->id])]),
            ],

            'reference' => [
                'nullable',
                'string',
                'regex:/^(customer|lead):[1-9][0-9]*$/',
            ],

            'due_at' => [
                'required',
                'date',
                'regex:/T.*(?:Z|[+-][0-9]{2}:[0-9]{2})$/',
            ],

            'version' => [
                $updating ? 'required' : 'nullable',
                'integer',
                'min:0',
            ],
        ], [
            'due_at.regex' => 'Hạn xử lý phải có thông tin múi giờ.',
            'assignee_id.in' => 'Bạn chỉ được tạo nhiệm vụ cho chính mình.',
        ], [
            'title' => 'tên nhiệm vụ',
            'description' => 'mô tả',
            'type' => 'loại nhiệm vụ',
            'priority' => 'mức ưu tiên',
            'assignee_id' => 'người thực hiện',
            'reference' => 'hồ sơ liên quan',
            'due_at' => 'hạn xử lý',
        ]);
    }

    private function row(Task $task): array
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