<?php

namespace App\Domain\Care\Models;

use App\Domain\Customer\Models\Customer;
use App\Domain\Shared\Concerns\Auditable;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Customer\Models\CustomerNote;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use Auditable;

    protected $table = 'care_tasks';

    protected $guarded = ['id'];

    public const TYPES = [
        'call' => 'Gọi điện',
        'email' => 'Gửi email',
        'zalo' => 'Liên hệ Zalo',
        'meeting' => 'Gặp / khảo sát',
        'quotation' => 'Theo dõi báo giá',
        'sample' => 'Theo dõi mẫu thử',
        'vendor_registration' => 'Đăng ký nhà cung cấp',
        'follow_up' => 'Chăm sóc / theo dõi',
        'internal' => 'Công việc nội bộ',
    ];

    public const PRIORITIES = [
        'normal' => 'Bình thường',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ];

    public const STATUSES = [
        'pending' => 'Chờ thực hiện',
        'in_progress' => 'Đang thực hiện',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];

    public const OPEN_STATUSES = ['pending', 'in_progress'];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isManager()) {
            return $query;
        }

        return $query
            ->where('assignee_id', $user->id)
            ->where(function (Builder $query) use ($user) {
                $query->whereNull('customer_id')
                    ->orWhereHas(
                        'customer',
                        fn (Builder $customer) => $customer->visibleTo($user),
                    );
            })
            ->where(function (Builder $query) use ($user) {
                $query->whereNull('lead_id')
                    ->orWhereHas(
                        'lead',
                        fn (Builder $lead) => $lead->visibleTo($user),
                    );
            });
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at->isBefore(now('UTC'));
    }
    public function customerNote(): HasOne
{
    return $this->hasOne(CustomerNote::class, 'task_id');
}
}