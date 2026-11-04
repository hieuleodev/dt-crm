<?php

namespace App\Http\Requests\Customer;

use App\Domain\Customer\Enums\Attitude;
use App\Domain\Customer\Enums\BuyingRole;
use App\Domain\Customer\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'plant_id' => ['nullable', 'integer', Rule::exists('plants', 'id')->where('customer_id', $customer->id)],
            'buying_role' => ['required', Rule::enum(BuyingRole::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'zalo' => ['nullable', 'string', 'max:30'],
            'preferred_channel' => ['nullable', Rule::enum(ContactChannel::class)],
            'language' => ['nullable', Rule::in(['vi', 'en', 'ko', 'zh', 'ja'])],
            'birthday' => ['nullable', 'date'],
            'is_primary' => ['boolean'],
            'left_at' => ['nullable', 'date'],
            'marketing_opt_out' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['full_name' => 'họ tên', 'buying_role' => 'vai trò mua hàng', 'phone' => 'điện thoại', 'plant_id' => 'nhà máy'];
    }
}