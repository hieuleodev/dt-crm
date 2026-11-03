<?php

namespace App\Domain\Customer\Enums;

use App\Domain\Shared\Concerns\HasOptions;

enum LifecycleStage: string
{
    use HasOptions;

    case Prospect = 'prospect';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case VendorRegistration = 'vendor_registration';
    case Sampling = 'sampling';
    case Quoting = 'quoting';
    case Negotiating = 'negotiating';
    case NewCustomer = 'new_customer';
    case Repeat = 'repeat';
    case Loyal = 'loyal';
    case AtRisk = 'at_risk';
    case Dormant = 'dormant';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Tiềm năng',
            self::Contacted => 'Đã liên hệ',
            self::Qualified => 'Đã khảo sát nhu cầu',
            self::VendorRegistration => 'Đăng ký nhà cung cấp',
            self::Sampling => 'Gửi mẫu thử',
            self::Quoting => 'Báo giá / đấu thầu',
            self::Negotiating => 'Đàm phán',
            self::NewCustomer => 'Khách mới',
            self::Repeat => 'Mua lặp lại',
            self::Loyal => 'Trung thành',
            self::AtRisk => 'Có nguy cơ',
            self::Dormant => 'Ngủ đông',
            self::Lost => 'Đã mất',
        };
    }
}