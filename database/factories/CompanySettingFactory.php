<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySetting>
 */
class CompanySettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'tax_id' => fake()->numerify('01########'),
            'hotline' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'website' => fake()->url(),
            'representative' => fake()->name(),
            'bank_name' => 'Vietcombank',
            'bank_account_number' => fake()->bankAccountNumber(),
            'bank_account_holder' => fake()->name(),
            'logo_url' => null,
            'footer_quote_note' => 'Báo giá có hiệu lực trong 30 ngày.',
        ];
    }
}
