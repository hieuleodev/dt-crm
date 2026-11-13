<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        $lead = $this->route('lead');

        // Nhân viên chỉ sửa hồ sơ của mình; không thể đổi người phụ trách hay loại bản ghi.
        if ($lead instanceof Lead) {
            return $user->isManager()
                || $lead->owner_id === $user->id;
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
        $lead = $this->route('lead');

        $kind = $lead instanceof Lead
            ? $lead->kind
            : 'lead';

        return [
            'title' => ['required', 'string', 'max:180'],
            'contact_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],

            'stage' => [
                'required',
                Rule::in(array_keys(Lead::STAGES[$kind])),
            ],

            'estimated_value' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999999.99',
                'decimal:0,2',
            ],

            'notes' => ['nullable', 'string', 'max:5000'],

            // Không nhận metadata để tránh đổi owner/loại ngoài luồng nghiệp vụ riêng.
            'owner_id' => ['prohibited'],
            'kind' => ['prohibited'],
            'converted_at' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'tên nhu cầu',
            'contact_name' => 'người liên hệ',
            'company' => 'doanh nghiệp',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'stage' => 'trạng thái',
            'estimated_value' => 'giá trị dự kiến',
            'notes' => 'ghi chú',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'string' => ':Attribute không đúng định dạng.',
            'max.string' => ':Attribute không được vượt quá :max ký tự.',
            'email' => 'Email không hợp lệ.',
            'stage.in' => 'Trạng thái không phù hợp với loại bản ghi.',
            'estimated_value.numeric' => 'Giá trị dự kiến phải là số.',
            'estimated_value.min' => 'Giá trị dự kiến không được âm.',
            'estimated_value.max' => 'Giá trị dự kiến vượt giới hạn cho phép.',
            'estimated_value.decimal' => 'Chỉ nhập tối đa 2 chữ số thập phân.',
            'prohibited' => 'Không được thay đổi :attribute bằng thao tác này.',
        ];
    }
}
