<?php

namespace App\Domain\Customer\Enums;

use App\Domain\Shared\Concerns\HasOptions;

enum CustomerTier: string
{
    use HasOptions;

    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Hạng A',
            self::B => 'Hạng B',
            self::C => 'Hạng C',
        };
    }
}