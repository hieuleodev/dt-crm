<?php

namespace App\Http\Requests\Opportunities;

use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        $opportunity = $this->route('opportunity');

        if ($opportunity instanceof Lead) {
            if ($opportunity->kind !== 'opportunity') {
                return false;
            }

            return $user->isManager()
                || $opportunity->owner_id === $user->id;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ([
            'title',
            'contact_name',
            'company',
            'email',
            'phone',
            'lost_reason',
            'notes',
        ] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }

        if ($this->input('customer_id') === '') {
            $values['customer_id'] = null;
        }

        if ($this->input('expected_close_date') === '') {
            $values['expected_close_date'] = null;
        }

        if ($this->input('owner_id') === '') {
            $values['owner_id'] = null;
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'customer_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if ($value === null) {
                        return;
                    }

                    $exists = Customer::query()
                        ->visibleTo($user)
                        ->whereKey($value)
                        ->exists();

                    if (! $exists) {
                        $fail('Khách hàng đã chọn không hợp lệ hoặc bạn không có quyền truy cập.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:180'],
            'contact_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],

            'stage' => [
                'required',
                Rule::in(array_keys(Lead::STAGES['opportunity'])),
            ],

            'estimated_value' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999999.99',
                'decimal:0,2',
            ],

            'probability' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],

            'expected_close_date' => [
                'nullable',
                'date',
            ],

            'lost_reason' => [
                Rule::requiredIf(fn (): bool => $this->input('stage') === 'lost'),
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => ['nullable', 'string', 'max:5000'],

            'owner_id' => $user->isManager()
                ? [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where('is_active', true),
                ]
                : ['prohibited'],
            'kind' => ['prohibited'],
            'converted_at' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'khách hàng doanh nghiệp',
            'owner_id' => 'người phụ trách',
            'title' => 'tên cơ hội',
            'contact_name' => 'người liên hệ',
            'company' => 'doanh nghiệp',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'stage' => 'giai đoạn',
            'estimated_value' => 'giá trị dự kiến',
            'probability' => 'xác suất thành công',
            'expected_close_date' => 'ngày dự kiến chốt',
            'lost_reason' => 'lý do thất bại',
            'notes' => 'ghi chú',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'lost_reason.required' => 'Vui lòng chọn hoặc nhập lý do thất bại khi cơ hội ở giai đoạn Thất bại.',
            'email' => 'Email chưa đúng định dạng.',
            'max.string' => ':Attribute tối đa :max ký tự.',
            'numeric' => ':Attribute phải là số.',
            'integer' => ':Attribute phải là số nguyên.',
            'min.numeric' => ':Attribute không được nhỏ hơn :min.',
            'max.numeric' => ':Attribute vượt quá giới hạn cho phép.',
            'decimal' => ':Attribute tối đa 2 chữ số thập phân.',
            'date' => ':Attribute không hợp lệ.',
            'in' => ':Attribute đã chọn không hợp lệ.',
            'prohibited' => ':Attribute không được phép thay đổi.',
        ];
    }
}
