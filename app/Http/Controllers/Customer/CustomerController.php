<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Customer\Actions\DataCompleteness;
use App\Domain\Customer\Actions\FindDuplicateCustomers;
use App\Domain\Customer\Actions\ReassignOwner;
use App\Domain\Customer\Actions\SaveCustomer;
use App\Domain\Customer\Data\CustomerOptions;
use App\Domain\Customer\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\Plant;
use App\Domain\Shared\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    private const SORTABLE = ['name', 'code', 'data_completeness', 'created_at', 'last_contacted_at'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['q', 'status', 'tier', 'industry', 'company_type', 'lifecycle_stage', 'owner_id', 'completeness', 'sort', 'direction']);
        $visible = Customer::query()->visibleTo($user);

        $query = (clone $visible)
            ->with(['owner:id,name', 'tags:id,name'])
            ->withCount(['contacts' => fn ($q) => $q->whereNull('left_at')]);

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $digits = preg_replace('/\D/', '', $term);
            $key = FindDuplicateCustomers::nameKey($term);
            $query->where(function ($w) use ($term, $digits, $key) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('short_name', 'like', "%{$term}%")
                    ->orWhereHas('contacts', fn ($c) => $c->where('full_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                if ($key !== '') {
                    $w->orWhere('name_key', 'like', "%{$key}%"); 
                }
                if (strlen($digits) >= 4) {
                    $w->orWhere('tax_code', 'like', "%{$digits}%")
                        ->orWhere('phone', 'like', "%{$digits}%")
                        ->orWhereHas('contacts', fn ($c) => $c->where('phone', 'like', "%{$digits}%"));
                }
            });
        }

        foreach (['status', 'tier', 'industry', 'company_type', 'lifecycle_stage'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($user->isManager() && filled($filters['owner_id'] ?? null)) {
            $filters['owner_id'] === 'none' ? $query->whereNull('owner_id') : $query->where('owner_id', $filters['owner_id']);
        }
        if (($filters['completeness'] ?? null) === 'low') {
            $query->where('data_completeness', '<', 60);
        }

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $customers = $query->orderBy($sort, $direction)->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Customer $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'short_name' => $c->short_name,
                'tax_code' => $c->tax_code,
                'company_type' => $c->company_type?->label(),
                'industry' => $c->industry?->label(),
                'location' => collect([$c->industrial_zone, $c->province])->filter()->implode(', ') ?: null,
                'tier' => $c->tier?->value,
                'lifecycle_stage' => ['value' => $c->lifecycle_stage->value, 'label' => $c->lifecycle_stage->label()],
                'status' => ['value' => $c->status->value, 'label' => $c->status->label()],
                'owner' => $c->owner?->name,
                'contacts_count' => $c->contacts_count,
                'data_completeness' => $c->data_completeness,
                'tags' => $c->tags->pluck('name'),
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => (object) $filters,
            'stats' => [
                'total' => (clone $visible)->count(),
                'lowCompleteness' => (clone $visible)->where('data_completeness', '<', 60)->count(),
                'unassigned' => $user->isManager() ? Customer::whereNull('owner_id')->count() : null,
                'newThisMonth' => (clone $visible)->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'options' => CustomerOptions::all(),
            'owners' => $user->isManager() ? $this->owners() : [],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('Customers/Form', [
            'customer' => null,
            'options' => CustomerOptions::all(),
            'owners' => $request->user()->isManager() ? $this->owners() : [],
        ]);
    }

    public function store(CustomerRequest $request, SaveCustomer $save): RedirectResponse
    {
        Gate::authorize('create', Customer::class);
        $customer = $save->handle($request->validated(), $request->user());

        return redirect()->route('customers.show', $customer)->with('success', "Đã tạo khách hàng {$customer->name}.");
    }

    public function show(Request $request, Customer $customer): Response
    {
        Gate::authorize('view', $customer);
        $customer->load(['owner:id,name', 'creator:id,name', 'tags:id,name', 'plants', 'contacts.plant:id,name',
            'ownerHistories.fromUser:id,name', 'ownerHistories.toUser:id,name', 'ownerHistories.changedBy:id,name']);
        $completeness = DataCompleteness::evaluate($customer);
        $user = $request->user();

        return Inertia::render('Customers/Show', [
            'customer' => [
                ...$this->formData($customer),
                'id' => $customer->id,
                'owner' => $customer->owner?->only('id', 'name'),
                'creator' => $customer->creator?->name,
                'created_at' => $customer->created_at?->toIso8601String(),
                'lifecycle_stage_at' => $customer->lifecycle_stage_at?->toIso8601String(),
                'consent_at' => $customer->consent_at?->toIso8601String(),
                'last_contacted_at' => $customer->last_contacted_at?->toIso8601String(),
                'data_completeness' => $completeness['score'],
                'missing' => $completeness['missing'],
            ],
            'contacts' => $customer->contacts->sortBy([['left_at', 'asc'], ['is_primary', 'desc'], ['full_name', 'asc']])->values()
                ->map(fn ($c) => [
                    ...$c->only('id', 'full_name', 'title', 'department', 'phone', 'email', 'zalo', 'language', 'is_primary', 'marketing_opt_out', 'notes', 'plant_id'),
                    'buying_role' => $c->buying_role?->value,
                    'preferred_channel' => $c->preferred_channel?->value,
                    'birthday' => $c->birthday?->toDateString(),
                    'left_at' => $c->left_at?->toDateString(),
                    'plant' => $c->plant?->name,
                ]),
            'plants' => $customer->plants->map(fn ($p) => $p->only('id', 'name', 'address', 'province', 'industrial_zone', 'employee_count', 'hazard_areas', 'receiving_hours', 'delivery_notes')),
            'ownerHistory' => $customer->ownerHistories->map(fn ($h) => [
                'id' => $h->id,
                'from' => $h->fromUser?->name,
                'to' => $h->toUser?->name,
                'by' => $h->changedBy?->name,
                'reason' => $h->reason,
                'at' => $h->created_at?->toIso8601String(),
            ]),
            'auditLogs' => $this->auditTrail($customer),
            'options' => CustomerOptions::all(),
            'owners' => $user->isManager() ? $this->owners() : [],
            'can' => [
                'update' => $user->can('update', $customer),
                'delete' => $user->can('delete', $customer),
                'reassign' => $user->can('reassign', $customer),
            ],
        ]);
    }

    public function edit(Request $request, Customer $customer): Response
    {
        Gate::authorize('update', $customer);
        $customer->load('tags:id,name');

        return Inertia::render('Customers/Form', [
            'customer' => ['id' => $customer->id, ...$this->formData($customer)],
            'options' => CustomerOptions::all(),
            'owners' => [],
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer, SaveCustomer $save): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $save->handle($request->validated(), $request->user(), $customer);

        return redirect()->route('customers.show', $customer)->with('success', 'Đã lưu thay đổi.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);
        $customer->delete();

        return redirect()->route('customers.index')->with('success', "Đã xóa khách hàng {$customer->name}.");
    }

    public function reassign(Request $request, Customer $customer, ReassignOwner $reassign): RedirectResponse
    {
        Gate::authorize('reassign', $customer);
        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['owner_id' => 'người phụ trách', 'reason' => 'lý do']);

        $reassign->handle($customer, $data['owner_id'] ?? null, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Đã chuyển người phụ trách.');
    }

    private function formData(Customer $c): array
    {
        $enum = fn ($v) => $v?->value;

        return [
            ...$c->only('code', 'name', 'short_name', 'tax_code', 'parent_group', 'parent_country', 'phone', 'email', 'website',
                'address', 'province', 'industrial_zone', 'industry_detail', 'employee_count', 'ppe_budget_estimate',
                'current_suppliers', 'referred_by', 'payment_terms', 'credit_limit', 'vendor_registered', 'marketing_consent', 'notes'),
            'company_type' => $enum($c->company_type),
            'industry' => $enum($c->industry),
            'source_channel' => $enum($c->source_channel),
            'tier' => $enum($c->tier),
            'lifecycle_stage' => $enum($c->lifecycle_stage),
            'status' => $enum($c->status),
            'procurement_method' => $enum($c->procurement_method),
            'vendor_registered_at' => $c->vendor_registered_at?->toDateString(),
            'required_certificates' => $c->required_certificates ?? [],
            'consent_channels' => $c->consent_channels ?? [],
            'tags' => $c->tags->pluck('name'),
        ];
    }

    private function owners(): array
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name'])->toArray();
    }

    private function auditTrail(Customer $customer): array
    {
        $targets = [
            Customer::class => [$customer->id],
            Contact::class => $customer->contacts()->withTrashed()->pluck('id')->all(),
            Plant::class => $customer->plants()->withTrashed()->pluck('id')->all(),
        ];

        $logs = AuditLog::query()->with('user:id,name')
            ->where(function ($q) use ($targets) {
                foreach ($targets as $type => $ids) {
                    if ($ids) {
                        $q->orWhere(fn ($w) => $w->where('auditable_type', $type)->whereIn('auditable_id', $ids));
                    }
                }
            })
            ->latest('id')->limit(50)->get();

        $userIds = $logs->flatMap(fn ($l) => [$l->old_values['owner_id'] ?? null, $l->new_values['owner_id'] ?? null])->filter()->unique();
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');
        $withNames = fn (?array $v) => $v && array_key_exists('owner_id', $v)
            ? [...$v, 'owner_id' => $v['owner_id'] ? ($names[$v['owner_id']] ?? "#{$v['owner_id']}") : null]
            : $v;

        return $logs->map(fn (AuditLog $l) => [
            'id' => $l->id,
            'event' => $l->event,
            'subject' => class_basename($l->auditable_type),
            'user' => $l->user?->name ?? 'Hệ thống',
            'old' => $withNames($l->old_values),
            'new' => $withNames($l->new_values),
            'at' => $l->created_at?->toIso8601String(),
        ])->all();
    }
}