<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZaloOaConnection extends Model
{
    protected $fillable = [
        'oa_id',
        'oa_name',
        'access_token',
        'refresh_token',
        'expires_at',
        'connected_by',
        'connected_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public function isExpiringSoon(int $minutes = 30): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->lessThanOrEqualTo(now()->addMinutes($minutes));
    }
}
