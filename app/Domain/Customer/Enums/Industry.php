<?php

namespace App\Domain\Customer\Enums;

use App\Domain\Shared\Concerns\HasOptions;

enum Industry: string
{
    use HasOptions;

    case Electronics = 'electronics';
    case Food = 'food';
    case Steel = 'steel';
    case OilGas = 'oil_gas';
    case Construction = 'construction';
    case Glass = 'glass';
    case Mechanical = 'mechanical';
    case Garment = 'garment';
    case Chemical = 'chemical';
    case Logistics = 'logistics';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electronics => 'Điện tử',
            self::Food => 'Thực phẩm',
            self::Steel => 'Thép',
            self::OilGas => 'Dầu khí',
            self::Construction => 'Xây dựng',
            self::Glass => 'Kính',
            self::Mechanical => 'Cơ khí',
            self::Garment => 'Dệt may / da giày',
            self::Chemical => 'Hóa chất',
            self::Logistics => 'Kho vận',
            self::Other => 'Khác',
        };
    }
}