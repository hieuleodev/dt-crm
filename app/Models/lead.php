<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use Auditable;

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
            'probability' => 'integer',
            'expected_close_date' => 'date:Y-m-d',
            'converted_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
        if (! $user->isManager()) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }
}
