<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\SaveLeadRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->is_active, 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'kind' => ['nullable', Rule::in(['lead', 'opportunity'])],
            'stage' => [
                'nullable',
                Rule::in(array_merge(
                    array_keys(Lead::STAGES['lead']),
                    array_keys(Lead::STAGES['opportunity']),
                )),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = trim($filters['q'] ?? '');
        $kind = $filters['kind'] ?? '';
        $stage = $filters['stage'] ?? '';

        $query = Lead::query()
            ->visibleTo($request->user())
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->when($stage, fn ($query) => $query->where('stage', $stage))
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

        $summary = [
            'total' => (clone $query)->count(),

            'leads' => (clone $query)
                ->where('kind', 'lead')
                ->count(),

            'opportunities' => (clone $query)
                ->where('kind', 'opportunity')
                ->count(),

            'open_value' => (string) (clone $query)
                ->where('kind', 'opportunity')
                ->whereIn('stage', ['proposal', 'negotiation'])
                ->sum('estimated_value'),
        ];

        $leads = $query
            ->with('owner:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'filters' => compact('q', 'kind', 'stage'),
            'stages' => Lead::STAGES,
            'summary' => $summary,
        ]);
    }

    public function store(SaveLeadRequest $request): RedirectResponse
    {
        $lead = new Lead($request->validated());

        // Lead mới do nhân viên tạo luôn thuộc về chính người tạo.
        $lead->owner_id = $request->user()->id;
        $lead->kind = 'lead';

        $lead->save();

        return back()->with('success', 'Đã tạo Lead.');
    }

    public function update(
        SaveLeadRequest $request,
        Lead $lead,
    ): RedirectResponse {
        $lead->update($request->validated());

        return back()->with('success', 'Đã cập nhật thông tin.');
    }

    public function convert(
        Request $request,
        Lead $lead,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user->is_active
                && ($user->isManager() || $lead->owner_id === $user->id),
            403,
        );

        $updated = Lead::query()
            ->visibleTo($user)
            ->whereKey($lead->id)
            ->where('kind', 'lead')
            ->where('stage', 'qualified')
            ->update([
                'kind' => 'opportunity',
                'stage' => 'proposal',
                'converted_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->with(
                'error',
                'Chỉ Lead đủ điều kiện và chưa chuyển đổi mới có thể tạo Cơ hội.',
            );
        }

        return back()->with('success', 'Đã chuyển Lead thành Cơ hội.');
    }
}
