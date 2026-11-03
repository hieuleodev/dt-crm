<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Enums\BuyingRole;
use App\Domain\Customer\Models\Customer;


class DataCompleteness
{
    @return array{score: int, missing: list<string>} 
    public static function evaluate(Customer $customer): array
    {
        $contacts = $customer->relationLoaded('contacts') ? $customer->contacts : $customer->contacts()->get();
        $active = $contacts->whereNull('left_at');

        $checks = [
            'Mã số thuế' => filled($customer->tax_code),
            'Loại hình doanh nghiệp' => filled($customer->company_type),
            'Ngành' => filled($customer->industry),
            'Tỉnh / KCN' => filled($customer->province) || filled($customer->industrial_zone),
            'Số công nhân' => filled($customer->employee_count),
            'Kênh khách tìm đến' => filled($customer->source_channel),
            'Người phụ trách' => filled($customer->owner_id),
            'Người liên hệ có SĐT hoặc email' => $active->contains(fn ($c) => filled($c->phone) || filled($c->email)),
            'Người phụ trách mua hàng (Purchasing)' => $active->contains(fn ($c) => $c->buying_role === BuyingRole::Purchasing),
            'Người liên hệ EHS hoặc QA' => $active->contains(fn ($c) => in_array($c->buying_role, [BuyingRole::Ehs, BuyingRole::Qa], true)),
        ];

        $passed = count(array_filter($checks));

        return [
            'score' => (int) round(100 * $passed / count($checks)),
            'missing' => array_keys(array_filter($checks, fn ($ok) => ! $ok)),
        ];
    }

    public static function refresh(Customer $customer): void
    {
        $customer->forceFill(['data_completeness' => self::evaluate($customer)['score']])->saveQuietly();
    }
}