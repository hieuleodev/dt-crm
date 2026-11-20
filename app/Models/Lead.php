<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use Auditable;

    protected $appends = ['products', 'product_total_managed'];

    protected $hidden = ['product_lines'];

    public const STAGES = [
        'lead' => [
            'new' => 'Mới',
            'contacted' => 'Đang liên hệ',
            'qualified' => 'Đủ điều kiện',
            'disqualified' => 'Không phù hợp',
        ],

        'opportunity' => [
            'proposal' => 'Báo giá',
            'negotiation' => 'Đàm phán',
            'won' => 'Thành công',
            'lost' => 'Thất bại',
        ],
    ];

    public const STAGE_PROBABILITIES = [
        'proposal' => 30,
        'negotiation' => 70,
        'won' => 100,
        'lost' => 0,
    ];

    public const LOST_REASONS = [
        'price' => 'Giá cao hơn đối thủ',
        'competitor' => 'Khách chọn nhà cung cấp khác',
        'spec_mismatch' => 'Chưa đáp ứng tiêu chuẩn kỹ thuật / mẫu thử',
        'budget_cancelled' => 'Khách hủy / hoãn kế hoạch mua sắm',
        'payment_terms' => 'Không thống nhất được điều khoản thanh toán / công nợ',
        'other' => 'Lý do khác',
    ];

    protected $fillable = [
        'owner_id',
        'customer_id',
        'title',
        'requested_product',
        'sapo_product_id',
        'sapo_variant_id',
        'requested_quantity',
        'requested_unit_price',
        'product_lines',
        'contact_name',
        'company',
        'email',
        'phone',
        'stage',
        'estimated_value',
        'probability',
        'expected_close_date',
        'lost_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'requested_quantity' => 'decimal:3',
            'requested_unit_price' => 'decimal:2',
            'product_lines' => 'array',
            'probability' => 'integer',
            'expected_close_date' => 'date:Y-m-d',
            'converted_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Khôi phục dòng sản phẩm cũ từ các cột đơn khi bản ghi chưa lưu danh sách sản phẩm. */
    protected function products(): Attribute
    {
        return Attribute::get(function (): array {
            if ($this->product_lines !== null) {
                return $this->product_lines;
            }

            if ($this->requested_product === null || $this->requested_product === '') {
                return [];
            }

            return [[
                'key' => $this->sapo_variant_id === null ? 'legacy' : (string) $this->sapo_variant_id,
                'sapo_product_id' => $this->sapo_product_id === null ? null : (string) $this->sapo_product_id,
                'name' => $this->requested_product,
                'sku' => null,
                'quantity' => $this->requested_quantity,
                'unit_price' => $this->requested_unit_price,
            ]];
        });
    }

    protected function productTotalManaged(): Attribute
    {
        return Attribute::get(fn (): bool => $this->product_lines !== null && $this->requested_quantity !== null);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'lead_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isManager() ? $query : $query->assignedTo($user->id);
    }

    /**
     * Lead theo người phụ trách hiện tại của khách hàng đã liên kết.
     * Lead chưa có người phụ trách từ hồ sơ khách hàng và Cơ hội dùng người phụ trách trên bản ghi.
     */
    public function scopeAssignedTo(Builder $query, int $ownerId): Builder
    {
        return $query->where(function (Builder $query) use ($ownerId) {
            $query->where(fn (Builder $lead) => $lead->where('kind', 'lead')
                ->whereHas('customer', fn (Builder $customer) => $customer->where('owner_id', $ownerId)))
                ->orWhere(fn (Builder $lead) => $lead->where('owner_id', $ownerId)
                    ->whereNot(fn (Builder $linkedLead) => $linkedLead->where('kind', 'lead')
                        ->whereHas('customer', fn (Builder $customer) => $customer->whereNotNull('owner_id'))));
        });
    }

    /**
     * Dùng chung phạm vi dữ liệu cho số đếm trên Tổng quan và danh sách Lead & Cơ hội.
     */
    public function scopeVisibleInLeadList(Builder $query, User $user, ?int $ownerId = null): Builder
    {
        $allowedKinds = array_keys(array_filter(
            ['lead' => 'leads', 'opportunity' => 'opportunities'],
            fn (string $permission): bool => $user->canAccessWorkArea($permission),
        ));

        return $query->visibleTo($user)
            ->whereIn('kind', $allowedKinds)
            ->when($ownerId !== null, fn (Builder $query) => $query->assignedTo($ownerId));
    }
}
