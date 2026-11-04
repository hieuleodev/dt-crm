<?php

namespace App\Http\Requests\Customer;

use App\Domain\Customer\Enums\CompanyType;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ([
            'name',
            'email',
            'phone',
            'address',
            'notes',
        ] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],

            'company_type' => [
                'nullable',
                Rule::enum(CompanyType::class),
            ],

            'industry' => [
                'nullable',
                Rule::enum(Industry::class),
            ],

            'status' => [
                'required',
                Rule::enum(CustomerStatus::class),
            ],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên khách hàng.',
            'email.email' => 'Email không hợp lệ.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'company_type.enum' => 'Loại khách không hợp lệ.',
            'industry.enum' => 'Ngành nghề không hợp lệ.',
            'status.enum' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên khách hàng',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'address' => 'địa chỉ',
            'company_type' => 'loại khách',
            'industry' => 'ngành nghề',
            'status' => 'trạng thái',
            'notes' => 'mô tả',
        ];
    }
}