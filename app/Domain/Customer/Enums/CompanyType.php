<?php

namespace App\Domain\Customer\Enums;

use App\Domain\Shared\Concerns\HasOptions;

enum CompanyType: string
{
    use HasOptions;

    case Fdi = 'fdi';
    case Domestic = 'domestic';
    case Trading = 'trading';
    case Contractor = 'contractor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fdi => 'Doanh nghiệp FDI',
            self::Domestic => 'Doanh nghiệp Việt Nam',
            self::Trading => 'Đại lý / thương mại',
            self::Contractor => 'Nhà thầu',
            self::Other => 'Khác',
        };
    }
}