<?php

namespace App\Http\Controllers;

use App\Http\Requests\Opportunities\SaveOpportunityRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Customer\DataCompleteness;
use App\Services\Customer\SaveCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'stage' => [
                'nullable',
                Rule::in(array_keys(Lead::STAGES['opportunity'])),
            ],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = trim($filters['q'] ?? '');
        $stage = $filters['stage'] ?? '';
        $customerId = isset($filters['customer_id']) ? (int) $filters['customer_id'] : null;

        $baseQuery = Lead::query()
            ->visibleTo($user)
            ->where('kind', 'opportunity')
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    foreach ([
                        'title',
                        'contact_name',
                        'company',
                        'email',
                        'phone',
                    ] as $index => $column) {
                        $method = $index === 0 ? 'whereLike' : 'orWhereLike';

                        $query->{$method}($column, "%{$q}%");
                    }
                });
            });

        $byStage = [];
        foreach (array_keys(Lead::STAGES['opportunity']) as $stageKey) {
            $stageQuery = (clone $baseQuery)->where('stage', $stageKey);
            $byStage[$stageKey] = [
                'count' => (clone $stageQuery)->count(),
                'value' => (string) (clone $stageQuery)->sum('estimated_value'),
            ];
        }

        $openItems = (clone $baseQuery)
            ->whereIn('stage', ['proposal', 'negotiation'])
            ->get(['stage', 'estimated_value', 'probability']);

        $weightedValue = 0.0;
        foreach ($openItems as $item) {
            $prob = $item->probability ?? (Lead::STAGE_PROBABILITIES[$item->stage] ?? 30);
            $weightedValue += ((float) $item->estimated_value) * ($prob / 100);
        }

        $filteredQuery = (clone $baseQuery)
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage));

        $summary = [
            'total' => (clone $filteredQuery)->count(),
            'all_count' => (clone $baseQuery)->count(),
            'open_count' => $openItems->count(),
            'open_value' => (string) (clone $baseQuery)
                ->whereIn('stage', ['proposal', 'negotiation'])
                ->sum('estimated_value'),
            'weighted_value' => (string) round($weightedValue, 2),
            'won_value' => (string) (clone $baseQuery)
                ->where('stage', 'won')
                ->sum('estimated_value'),
            'by_stage' => $byStage,
        ];

        $opportunities = $filteredQuery
            ->with([
                'owner:id,name',
                'customer:id,code,name,phone,email',
            ])
            ->withCount('tasks')
            ->orderByRaw('CASE WHEN expected_close_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expected_close_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $board = [];
        foreach (array_keys(Lead::STAGES['opportunity']) as $stageKey) {
            $board[$stageKey] = (clone $baseQuery)
                ->where('stage', $stageKey)
                ->with([
                    'owner:id,name',
                    'customer:id,code,name,phone,email',
                ])
                ->withCount('tasks')
                ->orderByRaw('CASE WHEN expected_close_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expected_close_date')
                ->orderByDesc('id')
                ->limit(40)
                ->get();
        }

        $customers = Customer::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'code', 'name', 'phone', 'email']);

        $owners = $user->isManager()
            ? User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : [$user->only('id', 'name')];

        return Inertia::render('Opportunities/Index', [
            'opportunities' => $opportunities,
            'board' => $board,
            'stageHistory' => $this->stageHistory(
                collect($board)->flatten(1)->pluck('id')
                    ->merge($opportunities->getCollection()->pluck('id'))
                    ->unique()->values()->all(),
            ),
            'filters' => [
                'q' => $q,
                'stage' => $stage,
                'customer_id' => $customerId,
            ],
            'stages' => Lead::STAGES['opportunity'],
            'stageProbabilities' => Lead::STAGE_PROBABILITIES,
            'lostReasons' => Lead::LOST_REASONS,
            'customers' => $customers,
            'owners' => $owners,
            'taskOptions' => [
                'types' => Task::TYPES,
                'priorities' => Task::PRIORITIES,
                'statuses' => Task::STATUSES,
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * @param  list<int>  $opportunityIds
     * @return array<int, list<array{id: int, from: ?string, to: string, by: string, at: ?string, reason: ?string}>>
     */
    private function stageHistory(array $opportunityIds): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->whereIn('auditable_type', AuditLog::typesFor(Lead::class))
            ->whereIn('auditable_id', $opportunityIds)
            ->whereIn('event', ['created', 'updated'])
            ->whereIn('new_values->stage', array_keys(Lead::STAGES['opportunity']))
            ->orderByDesc('id')
            ->get()
            ->filter(fn (AuditLog $log): bool => ($log->old_values['stage'] ?? null) !== $log->new_values['stage'])
            ->groupBy('auditable_id')
            ->map(fn (Collection $logs): array => $logs->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'from' => $log->old_values['stage'] ?? null,
                'to' => $log->new_values['stage'],
                'by' => $log->user?->name ?? 'Hệ thống',
                'at' => $log->created_at?->toIso8601String(),
                'reason' => $log->new_values['stage'] === 'lost' ? ($log->new_values['lost_reason'] ?? null) : null,
            ])->values()->all())
            ->all();
    }

    public function store(SaveOpportunityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->normalizePayload($request->validated());

        $ownerId = ($user->isManager() && ! empty($data['owner_id']))
            ? (int) $data['owner_id']
            : $user->id;
        unset($data['owner_id']);

        $opportunity = new Lead($data);
        $opportunity->owner_id = $ownerId;
        $opportunity->kind = 'opportunity';
        $opportunity->converted_at = now();
        $opportunity->save();

        return back()->with('success', 'Đã tạo Cơ hội kinh doanh mới.');
    }

    public function update(
        SaveOpportunityRequest $request,
        Lead $opportunity,
    ): RedirectResponse {
        $user = $request->user();
        $data = $this->normalizePayload($request->validated());

        if (! $user->isManager() || empty($data['owner_id'])) {
            unset($data['owner_id']);
        } else {
            $data['owner_id'] = (int) $data['owner_id'];
        }

        $opportunity->update($data);

        return back()->with('success', 'Đã cập nhật Cơ hội kinh doanh.');
    }

    public function destroy(
        Request $request,
        Lead $opportunity,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user
                && $user->is_active
                && $opportunity->kind === 'opportunity'
                && ($user->isManager() || $opportunity->owner_id === $user->id),
            403,
        );

        $opportunity->delete();

        return back()->with('success', 'Đã xóa Cơ hội kinh doanh.');
    }

    public function updateStage(
        Request $request,
        Lead $opportunity,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user
                && $user->is_active
                && $opportunity->kind === 'opportunity'
                && ($user->isManager() || $opportunity->owner_id === $user->id),
            403,
        );

        $validated = $request->validate([
            'stage' => [
                'required',
                Rule::in(array_keys(Lead::STAGES['opportunity'])),
            ],
            'lost_reason' => [
                Rule::requiredIf(fn (): bool => $request->input('stage') === 'lost'),
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'lost_reason.required' => 'Vui lòng nhập lý do thất bại.',
        ]);

        $nextStage = $validated['stage'];
        $probability = Lead::STAGE_PROBABILITIES[$nextStage] ?? $opportunity->probability;
        $lostReason = $nextStage === 'lost' ? trim((string) ($validated['lost_reason'] ?? '')) : null;

        $opportunity->update([
            'stage' => $nextStage,
            'probability' => $probability,
            'lost_reason' => $lostReason,
        ]);

        return back()->with('success', 'Đã chuyển giai đoạn Cơ hội.');
    }

    public function convertToCustomer(
        Request $request,
        Lead $opportunity,
        SaveCustomer $saveCustomer,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user
                && $user->is_active
                && $opportunity->kind === 'opportunity'
                && ($user->isManager() || $opportunity->owner_id === $user->id),
            403,
        );

        if ($opportunity->customer_id) {
            return back()->with('error', 'Cơ hội này đã được gắn với một hồ sơ Khách hàng.');
        }

        $lifecycleMap = [
            'proposal' => 'quoting',
            'negotiation' => 'negotiating',
            'won' => 'new_customer',
            'lost' => 'lost',
        ];

        $customer = $saveCustomer->handle([
            'name' => $opportunity->company ?: $opportunity->title,
            'phone' => $opportunity->phone,
            'email' => $opportunity->email,
            'owner_id' => $opportunity->owner_id,
            'lifecycle_stage' => $lifecycleMap[$opportunity->stage] ?? 'quoting',
            'ppe_budget_estimate' => $opportunity->estimated_value > 0 ? (int) $opportunity->estimated_value : null,
            'notes' => $opportunity->notes,
        ], $user);

        if (filled($opportunity->contact_name)) {
            $customer->contacts()->create([
                'full_name' => $opportunity->contact_name,
                'phone' => $opportunity->phone,
                'email' => $opportunity->email,
                'is_primary' => true,
            ]);
            DataCompleteness::refresh($customer);
        }

        $opportunity->update([
            'customer_id' => $customer->id,
            'company' => $customer->name,
        ]);

        return back()->with('success', "Đã tạo hồ sơ Khách hàng {$customer->code} · {$customer->name}.");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        $stage = $data['stage'];

        if ($stage === 'won') {
            $data['probability'] = 100;
            $data['lost_reason'] = null;
        } elseif ($stage === 'lost') {
            $data['probability'] = 0;
        } else {
            $data['probability'] = $data['probability'] ?? (Lead::STAGE_PROBABILITIES[$stage] ?? 30);
            $data['lost_reason'] = null;
        }

        if (! empty($data['customer_id']) && empty($data['company'])) {
            $customerName = Customer::query()
                ->whereKey($data['customer_id'])
                ->value('name');

            if ($customerName) {
                $data['company'] = $customerName;
            }
        }

        return $data;
    }
}
