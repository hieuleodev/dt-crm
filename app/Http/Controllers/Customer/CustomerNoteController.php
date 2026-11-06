<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Task;
use App\Models\User;
use App\Services\Care\SaveTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerNoteController extends Controller
{
    public function index(
        Request $request,
        Customer $customer,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);
        Gate::authorize('view', $customer);

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $notes = CustomerNote::query()
            ->where('customer_id', $customer->id)
            ->with([
                'author:id,name,deleted_at',

                'task' => fn ($query) => $query
                    ->visibleTo($user)
                    ->where('customer_id', $customer->id)
                    ->with('assignee:id,name'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(fn (CustomerNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'created_at' => $note->created_at
                    ->utc()
                    ->toIso8601String(),

                'author' => $note->author?->name
                    ?? 'Không có thông tin người tạo',

                'task' => $note->task ? [
                    'id' => $note->task->id,
                    'title' => $note->task->title,
                    'due_at' => $note->task->due_at
                        ->utc()
                        ->toIso8601String(),

                    'assignee' => $note->task->assignee?->name
                        ?? 'Không còn hoạt động',

                    'status' => $note->task->status,
                    'status_label' => Task::STATUSES[
                        $note->task->status
                    ] ?? $note->task->status,

                    'overdue' => $note->task->isOverdue(),
                ] : null,
            ]);

        $assignees = $user->isManager()
            ? User::query()
                ->where('is_active', true)
                ->where(function ($query) use ($customer) {
                    $query->whereIn('role', ['admin', 'manager']);

                    if ($customer->owner_id) {
                        $query->orWhere('id', $customer->owner_id);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name'])
            : [$user->only('id', 'name')];

        return response()->json([
            'notes' => $notes,
            'assignees' => $assignees,
        ]);
    }

    public function store(
        Request $request,
        Customer $customer,
        SaveTask $saveTask,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);
        Gate::authorize('update', $customer);

        $request->merge([
            'body' => is_string($request->input('body'))
                ? trim($request->input('body'))
                : $request->input('body'),
        ]);

        $createReminder = $request->boolean('create_reminder');

        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'body' => ['required', 'string', 'max:5000'],
            'create_reminder' => ['required', 'boolean'],

            'reminder_title' => [
                Rule::excludeIf(! $createReminder),
                'required',
                'string',
                'max:180',
            ],

            'reminder_due_at' => [
                Rule::excludeIf(! $createReminder),
                'required',
                'date',
                'after:now',
                'regex:/T.*(?:Z|[+-][0-9]{2}:[0-9]{2})$/',
            ],

            'assignee_id' => [
                Rule::excludeIf(! $createReminder),
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'body.required' => 'Vui lòng nhập nội dung ghi chú.',
            'reminder_title.required' => 'Vui lòng nhập tên việc cần nhắc.',
            'reminder_due_at.required' => 'Vui lòng chọn thời gian nhắc.',
            'reminder_due_at.after' => 'Thời gian nhắc phải ở tương lai.',
            'reminder_due_at.regex' => 'Thời gian nhắc không đúng định dạng.',
            'assignee_id.required' => 'Vui lòng chọn người thực hiện.',
        ]);

        DB::transaction(function () use (
            $customer,
            $user,
            $data,
            $createReminder,
            $saveTask,
        ) {
            $lockedCustomer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($customer->id);

            Gate::authorize('update', $lockedCustomer);

            $existing = CustomerNote::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('request_id', $data['request_id'])
                ->first();

            if ($existing) {
                abort_unless($existing->created_by === $user->id, 403);

                return;
            }

            $task = null;

            if ($createReminder) {
                $task = $saveTask->handle([
                    'title' => trim($data['reminder_title']),
                    'description' => $data['body'],
                    'type' => 'follow_up',
                    'priority' => 'normal',
                    'assignee_id' => $data['assignee_id'],
                    'reference' => "customer:{$lockedCustomer->id}",
                    'due_at' => $data['reminder_due_at'],
                ], $user);
            }

            CustomerNote::create([
                'customer_id' => $lockedCustomer->id,
                'created_by' => $user->id,
                'body' => $data['body'],
                'task_id' => $task?->id,
                'request_id' => $data['request_id'],
            ]);
        });

        return back()->with(
            'success',
            $createReminder
                ? 'Đã lưu ghi chú và tạo nhiệm vụ nhắc việc.'
                : 'Đã lưu ghi chú.',
        );
    }
}
