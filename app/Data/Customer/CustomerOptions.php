<?php

namespace App\Data\Customer;

use App\Enums\Customer as Enums;

class CustomerOptions
{
    public static function all(): array
    {
        return [
            'companyTypes' => Enums\CompanyType::options(),
            'industries' => Enums\Industry::options(),
            'tiers' => Enums\CustomerTier::options(),
            'statuses' => Enums\CustomerStatus::options(),
            'lifecycleStages' => Enums\LifecycleStage::options(),
            'sourceChannels' => Enums\SourceChannel::options(),
            'procurementMethods' => Enums\ProcurementMethod::options(),
            'buyingRoles' => Enums\BuyingRole::options(),
            'contactChannels' => Enums\ContactChannel::options(),
            'certificates' => array_map(fn ($v) => ['value' => $v, 'label' => $v], [
                'ISO 9001', 'ISO 14001', 'ISO 45001', 'CO (xuất xứ)', 'CQ (chất lượng)', 'Test report', 'SDS / MSDS', 'Hồ sơ năng lực',
            ]),
            'hazardAreas' => array_map(fn ($v) => ['value' => $v, 'label' => $v], [
                'Phòng sạch', 'Hóa chất', 'Nhiệt độ cao', 'Cơ khí / vật sắc', 'Điện', 'Làm việc trên cao', 'Tiếng ồn', 'Bụi',
            ]),
        ];
    }
}
