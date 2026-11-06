<?php

namespace App\Enums\Customer;

use App\Concerns\HasOptions;

enum ContactChannel: string
{
    use HasOptions;

    case Phone = 'phone';
    case Zalo = 'zalo';
    case Email = 'email';
    case Sms = 'sms';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Điện thoại',
            self::Zalo => 'Zalo',
            self::Email => 'Email',
            self::Sms => 'SMS',
            self::Meeting => 'Gặp trực tiếp',
        };
    }
}
