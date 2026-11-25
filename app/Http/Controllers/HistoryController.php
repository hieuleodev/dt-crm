<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Lead;
use App\Models\Plant;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, 403);
        abort_if($request->filled('actor_id') && ! $user->isManager(), 403);

        $filters = $request->validate([
            'type' => ['nullable', 'string', 'in:lead,opportunity,task'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'activity_page' => ['nullable', 'integer', 'min:1'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $type = $filters['type'] ?? 'lead';
        $start = isset($filters['from'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], 'Asia/Ho_Chi_Minh')->utc()
            : null;
        $end = isset($filters['to'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], 'Asia/Ho_Chi_Minh')->addDay()->utc()
            : null;

        $leadHistory = Lead::query()
            ->visibleTo($user)
            ->where('kind', 'lead')
            ->where('stage', 'disqualified')
            ->when($start, fn (Builder $query) => $query->where('updated_at', '>=', $start))
            ->when($end, fn (Builder $query) => $query->where('updated_at', '<', $end));

        $opportunityHistory = Lead::query()
            ->visibleTo($user)
            ->where('kind', 'opportunity')
            ->whereIn('stage', ['won', 'lost'])
            ->when($start, fn (Builder $query) => $query->where('updated_at', '>=', $start))
            ->when($end, fn (Builder $query) => $query->where('updated_at', '<', $end));

        $taskHistory = Task::query()
            ->visibleTo($user)
            ->whereIn('status', ['completed', 'cancelled'])
            ->when($start, fn (Builder $query) => $query->whereRaw(
                'COALESCE(completed_at, updated_at) >= ?',
                [$start->toDateTimeString()],
            ))
            ->when($end, fn (Builder $query) => $query->whereRaw(
                'COALESCE(completed_at, updated_at) < ?',
                [$end->toDateTimeString()],
            ));

        $breakdown = [
            'lead' => [
                'total' => (clone $leadHistory)->count(),
                'successful' => 0,
                'failed' => (clone $leadHistory)->count(),
            ],
            'opportunity' => [
                'total' => (clone $opportunityHistory)->count(),
                'successful' => (clone $opportunityHistory)->where('stage', 'won')->count(),
                'failed' => (clone $opportunityHistory)->where('stage', 'lost')->count(),
            ],
            'task' => [
                'total' => (clone $taskHistory)->count(),
                'successful' => (clone $taskHistory)->where('status', 'completed')->count(),
                'failed' => (clone $taskHistory)->where('status', 'cancelled')->count(),
            ],
        ];
        $summary = [
            'successful' => $breakdown['opportunity']['successful'] + $breakdown['task']['successful'],
            'failed' => $breakdown['lead']['failed'] + $breakdown['opportunity']['failed'] + $breakdown['task']['failed'],
            'breakdown' => $breakdown,
        ];

        $records = match ($type) {
            'opportunity' => $opportunityHistory
                ->with(['owner:id,name', 'customer:id,code,name'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Lead $lead): array => [
                    'id' => $lead->id,
                    'title' => $lead->title,
                    'company' => $lead->customer?->name ?? $lead->company,
                    'contact_name' => $lead->contact_name,
                    'status' => $lead->stage,
                    'status_label' => Lead::STAGES['opportunity'][$lead->stage],
                    'owner' => $lead->owner?->name,
                    'value' => (string) $lead->estimated_value,
                    'closed_at' => $lead->updated_at?->toIso8601String(),
                    'url' => route('opportunities.index', ['q' => $lead->title]),
                    'lost_reason' => $lead->lost_reason,
                ]),
            'task' => $taskHistory
                ->with(['assignee:id,name', 'customer:id,code,name', 'lead:id,title,kind'])
                ->orderByRaw('COALESCE(completed_at, updated_at) DESC')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'type_label' => Task::TYPES[$task->type] ?? $task->type,
                    'status' => $task->status,
                    'status_label' => Task::STATUSES[$task->status],
                    'owner' => $task->assignee?->name,
                    'reference' => $task->customer?->name ?? $task->lead?->title,
                    'result' => $task->result,
                    'closed_at' => ($task->completed_at ?? $task->updated_at)?->toIso8601String(),
                    'url' => route('tasks.index', [
                        'bucket' => $task->status,
                        'q' => $task->title,
                    ]),
                ]),
            default => $leadHistory
                ->with('owner:id,name')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Lead $lead): array => [
                    'id' => $lead->id,
                    'title' => $lead->title,
                    'company' => $lead->company,
                    'contact_name' => $lead->contact_name,
                    'status' => $lead->stage,
                    'status_label' => Lead::STAGES['lead'][$lead->stage],
                    'owner' => $lead->owner?->name,
                    'value' => (string) $lead->estimated_value,
                    'closed_at' => $lead->updated_at?->toIso8601String(),
                    'url' => route('leads.index', ['q' => $lead->title, 'kind' => 'lead']),
                ]),
        };

        return Inertia::render('History/Index', [
            'records' => $records,
            'filters' => [
                'type' => $type,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'actor_id' => isset($filters['actor_id']) ? (int) $filters['actor_id'] : null,
            ],
            'summary' => $summary,
            'activity' => $user->isManager()
                ? $this->activity($filters, $start, $end)
                : null,
            'actors' => $user->isManager()
                ? User::withTrashed()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function activity(array $filters, ?CarbonImmutable $start, ?CarbonImmutable $end): LengthAwarePaginator
    {
        $models = [
            Customer::class => 'Khách hàng', Contact::class => 'Người liên hệ',
            Plant::class => 'Nhà máy', CustomerNote::class => 'Ghi chú chăm sóc',
            Lead::class => 'Lead / Cơ hội', Task::class => 'Nhiệm vụ / Lịch chăm sóc',
        ];
        $types = [];
        foreach ($models as $model => $label) {
            foreach (AuditLog::typesFor($model) as $type) {
                $types[$type] = $label;
            }
        }

        $logs = AuditLog::query()
            ->whereIn('auditable_type', array_keys($types))
            ->when($filters['actor_id'] ?? null, fn (Builder $query, mixed $actorId) => $query->where('user_id', $actorId))
            ->when($start, fn (Builder $query) => $query->where('created_at', '>=', $start))
            ->when($end, fn (Builder $query) => $query->where('created_at', '<', $end))
            ->with(['user' => fn ($query) => $query->withTrashed()->select(['id', 'name'])])
            ->latest('id')
            ->paginate(15, ['*'], 'activity_page')
            ->withQueryString();

        $initialValues = AuditLog::query()
            ->where('event', 'created')
            ->whereIn('auditable_type', $logs->getCollection()->pluck('auditable_type')->unique())
            ->whereIn('auditable_id', $logs->getCollection()->pluck('auditable_id')->unique())
            ->get(['auditable_type', 'auditable_id', 'new_values'])
            ->keyBy(fn (AuditLog $log): string => $log->auditable_type.':'.$log->auditable_id);

        $fields = [
            'title' => 'Tên công việc', 'name' => 'Tên', 'full_name' => 'Họ tên',
            'status' => 'Trạng thái', 'stage' => 'Giai đoạn', 'due_at' => 'Lịch hẹn',
            'assignee_id' => 'Người thực hiện', 'owner_id' => 'Người phụ trách',
            'completed_at' => 'Ngày hoàn thành', 'result' => 'Kết quả',
            'description' => 'Mô tả', 'body' => 'Nội dung chăm sóc',
            'phone' => 'Điện thoại', 'email' => 'Email', 'address' => 'Địa chỉ',
            'customer_id' => 'Khách hàng', 'kind' => 'Loại hồ sơ',
            'estimated_value' => 'Giá trị', 'product_lines' => 'Sản phẩm và báo giá',
            'notes' => 'Ghi chú', 'priority' => 'Ưu tiên', 'lifecycle_stage' => 'Vòng đời khách hàng',
        ];

        return $logs->through(function (AuditLog $log) use ($types, $initialValues, $fields): array {
            $initial = $initialValues->get($log->auditable_type.':'.$log->auditable_id)?->new_values ?? [];
            $values = [...$initial, ...($log->old_values ?? []), ...($log->new_values ?? [])];

            return [
                'id' => $log->id,
                'actor' => $log->user?->name ?? ($log->user_id ? 'Tài khoản không còn khả dụng' : 'Hệ thống'),
                'actor_id' => $log->user_id,
                'action' => match ($log->event) {
                    'created' => 'Tạo mới', 'updated' => 'Cập nhật', 'deleted' => 'Xóa',
                    default => $log->event,
                },
                'subject_type' => $types[$log->auditable_type],
                'subject' => Str::limit((string) ($values['title'] ?? $values['name'] ?? $values['full_name'] ?? $values['body'] ?? '#'.$log->auditable_id), 180),
                'changed_fields' => array_values(array_intersect_key($fields, $log->new_values ?? $log->old_values ?? [])),
                'at' => $log->created_at?->toIso8601String(),
            ];
        });
    }
}
