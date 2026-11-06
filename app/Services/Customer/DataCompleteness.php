<?php

namespace App\Services\Customer;

use App\Models\Customer;

class DataCompleteness
{
    /**
     * @return array{score: int, missing: list<string>}
     */
    public static function evaluate(Customer $customer): array
    {
        $checks = [
            'Tên khách hàng' => filled($customer->name),
            'Email' => filled($customer->email),
            'Số điện thoại' => filled($customer->phone),
            'Địa chỉ' => filled($customer->address),
            'Loại khách' => filled($customer->company_type),
            'Ngành nghề' => filled($customer->industry),
            'Trạng thái' => filled($customer->status),
        ];

        $passed = count(array_filter($checks));

        return [
            'score' => (int) round(
                100 * $passed / count($checks),
            ),

            'missing' => array_keys(
                array_filter($checks, fn (bool $ok) => ! $ok),
            ),
        ];
    }

    public static function refresh(Customer $customer): void
    {
        $customer->forceFill(['data_completeness' => self::evaluate($customer)['score']])->saveQuietly();
    }
}
