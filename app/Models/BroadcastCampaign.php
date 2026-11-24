<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastCampaign extends Model
{
    public const PURPOSES = [
        'repurchase_reminder' => 'Nhắc nhở mua lại',
        'promotion' => 'Quảng cáo & Khuyến mãi',
    ];

    public const CHANNELS = [
        'zalo' => 'Zalo OA (Đa bài viết)',
        'email' => 'Email (Rich Media)',
    ];

    public const STATUSES = [
        'draft' => 'Bản nháp',
        'scheduled' => 'Đã hẹn lịch',
        'queued' => 'Đang xếp hàng gửi',
        'sending' => 'Đang gửi',
        'completed' => 'Hoàn thành',
        'partial' => 'Gửi một phần',
        'failed' => 'Thất bại',
    ];

    protected $fillable = [
        'name',
        'purpose',
        'channels',
        'status',
        'scheduled_at',
        'created_by',
        'total_recipients',
        'sent_count',
        'failed_count',
        'skipped_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(BroadcastArticle::class, 'campaign_id')->orderBy('sort_order');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class, 'campaign_id');
    }
}
