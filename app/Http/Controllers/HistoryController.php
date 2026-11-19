<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, 403);

        $filters = $request->validate([
            'type' => ['nullable', 'string', 'in:lead,opportunity,task'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
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
            ],
            'summary' => $summary,
        ]);
    }
}
