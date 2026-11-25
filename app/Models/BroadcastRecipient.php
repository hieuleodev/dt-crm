<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastRecipient extends Model
{
    public const STATUSES = [
        'pending' => 'Chờ gửi',
        'sent' => 'Đã gửi',
        'delivered' => 'Đã nhận',
        'seen' => 'Đã xem',
        'failed' => 'Thất bại',
        'skipped' => 'Bỏ qua',
    ];

    protected $fillable = [
        'campaign_id',
        'lead_id',
        'customer_id',
        'contact_id',
        'channel',
        'recipient_name',
        'recipient_address',
        'status',
        'provider_message_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'seen_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BroadcastCampaign::class, 'campaign_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
