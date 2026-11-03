<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
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

    protected $fillable = [
        'title',
        'contact_name',
        'company',
        'email',
        'phone',
        'stage',
        'estimated_value',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'converted_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isManager()) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }
}