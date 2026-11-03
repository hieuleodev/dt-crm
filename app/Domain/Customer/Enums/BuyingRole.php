<?php

namespace App\Domain\Customer\Enums;

use App\Domain\Shared\Concerns\HasOptions;

enum BuyingRole: string
{
    use HasOptions;

    case Purchasing = 'purchasing';
    case Ehs = 'ehs';
    case Qa = 'qa';
    case Warehouse = 'warehouse';
    case Admin = 'admin';
    case Production = 'production';
    case Management = 'management';
    case Accounting = 'accounting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Purchasing => 'Mua hàng (Purchasing)',
            self::Ehs => 'EHS / An toàn',
            self::Qa => 'QA / QC',
            self::Warehouse => 'Kho',
            self::Admin => 'Hành chính',
            self::Production => 'Sản xuất',
            self::Management => 'Ban giám đốc',
            self::Accounting => 'Kế toán',
            self::Other => 'Khác',
        };
    }
}