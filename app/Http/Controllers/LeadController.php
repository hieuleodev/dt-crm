<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\SaveLeadRequest;
use App\Models\Lead;
use App\Models\SapoProduct;
use App\Services\Customer\SaveCustomerFromOpportunity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
        // Màn danh sách có cả Lead và Cơ hội, mỗi loại đi theo quyền của phân hệ tương ứng.
        $allowedKinds = array_keys(array_filter(
            ['lead' => 'leads', 'opportunity' => 'opportunities'],
            fn (string $permission) => $request->user()->canAccessWorkArea($permission),
        ));

        if ($kind !== '' && ! $request->user()->canAccessWorkArea($kind === 'lead' ? 'leads' : 'opportunities')) {
            abort(403);
        }

        $query = Lead::query()
            ->visibleTo($request->user())
            ->whereIn('kind', $allowedKinds)
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
            'canSyncProducts' => $request->user()->isManager(),
            'productsLastSyncedAt' => SapoProduct::query()->max('synced_at'),
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

        $lead->update($this->withProductSelection($request->validated(), $lead));

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
                && ($user->isManager() || $lead->owner_id === $user->id),
            403,
        );

        // Chỉ bắt buộc số lượng và đơn giá khi Lead đã chọn sản phẩm.
        $validated = $request->validate([
            'quantity' => [
                'bail',
                $lead->requested_product ? 'required' : 'nullable',
                'integer',
                'min:1',
                'max:1000000',
            ],
            'unit_price' => [
                $lead->requested_product ? 'required' : 'nullable',
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

        // Kiểm tra lại giai đoạn và khóa bản ghi để hai yêu cầu không thể chuyển cùng một Lead.
        $updated = DB::transaction(function () use ($user, $lead, $validated, $saveCustomer): bool {
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

            if ($qualifiedLead->requested_product) {
                // Nhân số lượng với đơn giá và làm tròn đến hai chữ số để lưu giá trị Cơ hội.
                $estimatedValue = BigDecimal::of($validated['quantity'])
                    ->multipliedBy($validated['unit_price'])
                    ->toScale(2, RoundingMode::HalfUp);

                if ($estimatedValue->isGreaterThan('9999999999999.99')) {
                    throw ValidationException::withMessages([
                        'unit_price' => 'Tổng giá trị sản phẩm vượt giới hạn cho phép.',
                    ]);
                }

                $updates['requested_quantity'] = $validated['quantity'];
                $updates['requested_unit_price'] = $validated['unit_price'];
                $updates['estimated_value'] = (string) $estimatedValue;
            }

            // Lưu Khách hàng và liên kết Cơ hội trong cùng giao dịch để tránh hồ sơ dang dở.
            $qualifiedLead->forceFill($updates);
            if ($qualifiedLead->customer_id === null) {
                $qualifiedLead->customer_id = $saveCustomer->handle($qualifiedLead, $user)->id;
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

    /**
     * Lấy tên và giá từ biến thể trong danh mục CRM. Xóa lựa chọn sẽ xóa các trường sản phẩm đi kèm.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withProductSelection(array $data, ?Lead $lead = null): array
    {
        if (! array_key_exists('sapo_variant_id', $data)) {
            return $data;
        }

        $variantId = $data['sapo_variant_id'] ?? null;
        unset($data['sapo_variant_id']);

        // Giữ tên và đơn giá đã chốt khi sửa thông tin khác của cùng một sản phẩm.
        if ($lead !== null && (string) $variantId === (string) $lead->sapo_variant_id) {
            return $data;
        }

        if ($variantId === null || $variantId === '') {
            return [
                ...$data,
                'requested_product' => null,
                'sapo_product_id' => null,
                'sapo_variant_id' => null,
                'requested_quantity' => null,
                'requested_unit_price' => null,
            ];
        }

        $product = SapoProduct::query()
            ->where('sapo_variant_id', $variantId)
            ->firstOrFail();
        // Ghi tên và giá lên hồ sơ khi lưu Lead. Các lần đồng bộ sau cập nhật bảng danh mục riêng.
        $variantName = $product->variant_name && $product->variant_name !== 'Default Title'
            ? " · {$product->variant_name}"
            : '';

        return [
            ...$data,
            'requested_product' => mb_substr($product->product_name.$variantName, 0, 180),
            'sapo_product_id' => $product->sapo_product_id,
            'sapo_variant_id' => $product->sapo_variant_id,
            'requested_unit_price' => $product->unit_price,
        ];
    }
}
