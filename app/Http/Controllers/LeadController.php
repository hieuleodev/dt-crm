<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\SaveLeadRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\SapoProduct;
use App\Models\User;
use App\Services\Customer\SaveCustomerFromOpportunity;
use App\Services\LeadProducts;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(private LeadProducts $leadProducts) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);

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
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = trim($filters['q'] ?? '');
        $kind = $filters['kind'] ?? '';
        $stage = $filters['stage'] ?? '';
        $ownerId = $user->isManager() && isset($filters['owner_id']) ? (int) $filters['owner_id'] : null;

        if ($kind !== '' && ! $user->canAccessWorkArea($kind === 'lead' ? 'leads' : 'opportunities')) {
            abort(403);
        }

        $query = Lead::query()
            ->visibleInLeadList($user, $ownerId)
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
            ->with(['owner:id,name', 'customer' => fn ($query) => $query
                ->visibleTo($user)->select(['id', 'code', 'name', 'owner_id'])->with('owner:id,name')])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Lấy ảnh cho các dòng đã lưu của trang hiện tại trong một truy vấn, kể cả Lead cũ chưa có ảnh.
        $variantIds = $leads->getCollection()
            ->flatMap(fn (Lead $lead): array => array_column($lead->products, 'key'))
            ->reject(fn (string $key): bool => $key === 'legacy')
            ->unique()
            ->values();

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'productImages' => SapoProduct::query()->whereIn('sapo_variant_id', $variantIds)
                ->whereNotNull('image_url')->pluck('image_url', 'sapo_variant_id'),
            'filters' => [...compact('q', 'kind', 'stage'), 'owner_id' => $ownerId],
            'selectedOwnerName' => $ownerId !== null ? User::query()->whereKey($ownerId)->value('name') : null,
            'stages' => Lead::STAGES,
            'summary' => $summary,
            'canLinkCustomers' => $user->isManager(),
            'canSyncProducts' => $user->isManager(),
            'productsLastSyncedAt' => SapoProduct::query()->max('synced_at'),
        ]);
    }

    public function customerOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->isManager() && $user->canAccessWorkArea('customers'), 403);

        $validated = $request->validate(['q' => ['nullable', 'string', 'max:180']]);
        $search = trim($validated['q'] ?? '');

        $customers = Customer::query()
            ->visibleTo($user)
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('code', "%{$search}%")))
            ->with(['contacts' => fn (HasMany $contacts) => $contacts
                ->select(['id', 'customer_id', 'full_name', 'email', 'phone'])
                ->whereNull('left_at')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->limit(1)])
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'code', 'name', 'email', 'phone']);

        return response()->json([
            'options' => $customers->map(function (Customer $customer): array {
                $contact = $customer->contacts->first();

                return [
                    'value' => $customer->id,
                    'label' => "{$customer->code} · {$customer->name}",
                    'details' => [
                        'contact_name' => $contact?->full_name ?? '',
                        'company' => $customer->name,
                        'email' => $contact?->email ?: ($customer->email ?? ''),
                        'phone' => $contact?->phone ?: ($customer->phone ?? ''),
                    ],
                ];
            }),
        ]);
    }

    public function store(SaveLeadRequest $request): RedirectResponse
    {
        $lead = new Lead($this->withProductSelection($request->validated()));

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
        abort_unless(
            $request->user()->canAccessWorkArea(
                $lead->kind === 'opportunity' ? 'opportunities' : 'leads',
            ),
            403,
        );

        DB::transaction(function () use ($request, $lead): void {
            $currentLead = Lead::query()->visibleTo($request->user())->lockForUpdate()->find($lead->id);
            abort_if($currentLead === null, 403);

            if ($currentLead->kind !== $lead->kind) {
                throw ValidationException::withMessages(['stage' => 'Hồ sơ đã chuyển thành Cơ hội. Hãy tải lại danh sách trước khi sửa.']);
            }

            $currentLead->update($this->withProductSelection($request->validated(), $currentLead));
        }, 3);

        return back()->with('success', 'Đã cập nhật thông tin.');
    }

    public function convert(
        Request $request,
        Lead $lead,
        SaveCustomerFromOpportunity $saveCustomer,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user->is_active
                && $user->canAccessWorkArea('opportunities')
                && Lead::query()->visibleTo($user)->whereKey($lead->id)->exists(),
            403,
        );

        // Kiểm tra lại giai đoạn và khóa bản ghi để hai yêu cầu không thể chuyển cùng một Lead.
        $updated = DB::transaction(function () use ($user, $lead, $request, $saveCustomer): bool {
            $qualifiedLead = Lead::query()
                ->visibleTo($user)
                ->whereKey($lead->id)
                ->where('kind', 'lead')
                ->where('stage', 'qualified')
                ->lockForUpdate()
                ->first();

            if (! $qualifiedLead) {
                return false;
            }

            // Kiểm tra lại dữ liệu Lead cũ trước khi đưa email và số điện thoại sang hồ sơ Khách hàng.
            Validator::make(
                $qualifiedLead->only(['email', 'phone']),
                SaveLeadRequest::contactRules(),
                SaveLeadRequest::contactMessages(),
            )->validate();

            $updates = [
                'kind' => 'opportunity',
                'stage' => 'proposal',
                'probability' => Lead::STAGE_PROBABILITIES['proposal'],
                'converted_at' => now(),
                'updated_at' => now(),
            ];

            $updates += $this->leadProducts->quote($qualifiedLead, $this->conversionRows($request, $qualifiedLead));

            // Lưu Khách hàng và liên kết Cơ hội trong cùng giao dịch để tránh hồ sơ dang dở.
            $qualifiedLead->forceFill($updates);
            if ($qualifiedLead->customer_id === null) {
                $qualifiedLead->customer_id = $saveCustomer->handle($qualifiedLead, $user)->id;
            } else {
                // Cơ hội dùng người phụ trách trên bản ghi. Giữ quyền xử lý của người phụ trách khách hàng khi chuyển Lead.
                $customerOwnerId = $qualifiedLead->customer()->lockForUpdate()->value('owner_id');
                $qualifiedLead->owner_id = $customerOwnerId ?? $qualifiedLead->owner_id;
            }
            $qualifiedLead->save();

            return true;
        }, 3);

        if (! $updated) {
            return back()->with(
                'error',
                'Chỉ Lead đủ điều kiện và chưa chuyển đổi mới có thể tạo Cơ hội.',
            );
        }

        return back()->with('success', 'Đã chuyển Lead thành Cơ hội và lưu hồ sơ Khách hàng.');
    }

    /** @return list<array{key: string, quantity: string|int|float, unit_price: string|int|float}> */
    private function conversionRows(Request $request, Lead $lead): array
    {
        if ($request->has('products') || count($lead->products) > 1) {
            return $request->validate(LeadProducts::rules(required: true), LeadProducts::messages())['products'];
        }

        $validated = $request->validate([
            'quantity' => [
                'bail',
                $lead->products !== [] ? 'required' : 'nullable',
                'integer',
                'min:1',
                'max:1000000',
            ],
            'unit_price' => [
                $lead->products !== [] ? 'required' : 'nullable',
                'numeric',
                'min:0',
                'max:9999999999999.99',
                'decimal:0,2',
            ],
        ], [
            'quantity.required' => 'Vui lòng nhập số lượng sản phẩm.',
            'quantity.integer' => 'Số lượng phải là số nguyên dương.',
            'quantity.min' => 'Số lượng phải lớn hơn hoặc bằng 1.',
            'quantity.max' => 'Số lượng không được vượt quá 1.000.000.',
            'unit_price.required' => 'Vui lòng nhập đơn giá.',
            'unit_price.decimal' => 'Đơn giá chỉ được có tối đa 2 chữ số thập phân.',
        ]);

        return $lead->products === [] ? [] : [['key' => $lead->products[0]['key'], ...$validated]];
    }

    /**
     * Lấy tên và giá từ biến thể trong danh mục CRM. Xóa lựa chọn sẽ xóa các trường sản phẩm đi kèm.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withProductSelection(array $data, ?Lead $lead = null): array
    {
        if ($lead?->kind === 'opportunity' && $lead->product_total_managed) {
            unset($data['estimated_value'], $data['products'], $data['sapo_variant_id']);
        }

        if (array_key_exists('products', $data)) {
            $products = $data['products'];
            unset($data['products'], $data['sapo_variant_id']);

            return array_replace($data, $this->leadProducts->selection($lead, $products));
        }

        if (! array_key_exists('sapo_variant_id', $data)) {
            return $data;
        }

        $variantId = $data['sapo_variant_id'] ?? null;
        unset($data['sapo_variant_id']);

        // Giữ tên và đơn giá đã chốt khi sửa thông tin khác của cùng một sản phẩm.
        if ($lead !== null && (string) $variantId === (string) $lead->sapo_variant_id) {
            return $data;
        }

        if (count($lead?->products ?? []) > 1) {
            throw ValidationException::withMessages(['products' => 'Hãy mở lại form để cập nhật danh sách nhiều sản phẩm.']);
        }

        $products = $variantId === null ? [] : [$this->leadProducts->fromCatalog(
            SapoProduct::query()->where('sapo_variant_id', $variantId)->firstOrFail(),
        )];

        return array_replace($data, $this->leadProducts->attributes($products));
    }
}
