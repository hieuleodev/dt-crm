<?php

namespace App\Enums\Customer;

use App\Concerns\HasOptions;

enum ProcurementMethod: string
{
    use HasOptions;

    case Po = 'po';
    case Framework = 'framework';
    case Eauction = 'eauction';
    case Tender = 'tender';

    public function label(): string
    {
        return match ($this) {
            self::Po => 'Đặt PO lẻ',
            self::Framework => 'Hợp đồng khung',
            self::Eauction => 'eAuction',
            self::Tender => 'Đấu thầu',
        };
    }
}
