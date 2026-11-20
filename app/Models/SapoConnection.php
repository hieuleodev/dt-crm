<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapoConnection extends Model
{
    protected $fillable = [
        'store_domain',
        'access_token',
        'connected_at',
    ];

    /**
     * Laravel mã hóa access token khi lưu và giải mã khi máy chủ đọc để gọi Sapo.
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }
}
