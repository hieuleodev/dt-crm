<?php

namespace App\Domain\Customer\Enums;

use App\Domain\Shared\Concerns\HasOptions;

enum CustomerStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Blacklisted = 'blacklisted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Inactive => 'Ngừng giao dịch',
            self::Blacklisted => 'Không hợp tác',
        };
    }
}