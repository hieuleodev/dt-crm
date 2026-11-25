<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastArticle extends Model
{
    protected $fillable = [
        'campaign_id',
        'sort_order',
        'title',
        'summary',
        'cover_image_url',
        'cta_label',
        'cta_url',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BroadcastCampaign::class, 'campaign_id');
    }
}
