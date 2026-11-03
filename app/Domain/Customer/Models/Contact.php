<?php

namespace App\Domain\Customer\Models;

use App\Domain\Customer\Enums\Attitude;
use App\Domain\Customer\Enums\BuyingRole;
use App\Domain\Customer\Enums\ContactChannel;
use App\Domain\Customer\Enums\Influence;
use App\Domain\Shared\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id', 'customer_id', 'created_at', 'updated_at', 'deleted_at'];

    protected static function booted(): void
    {
        static::saving(function (Contact $c) {
            $c->phone = Customer::normalizePhone($c->phone);
            $c->email = $c->email ? mb_strtolower(trim($c->email)) : null;
        });
    }

    protected function casts(): array
    {
        return [
            'buying_role' => BuyingRole::class,
            'influence' => Influence::class,
            'attitude' => Attitude::class,
            'preferred_channel' => ContactChannel::class,
            'birthday' => 'date',
            'left_at' => 'date',
            'is_primary' => 'boolean',
            'marketing_opt_out' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }
}