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

        // Giá trị dự kiến có thể để trống. Cột tiền trong cơ sở dữ liệu dùng 0 thay cho giá trị rỗng.
        if ($this->has('estimated_value') && in_array($this->input('estimated_value'), [null, ''], true)) {
            $values['estimated_value'] = '0';
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
            // Lựa chọn sản phẩm phải trỏ đến một biến thể đã có trong danh mục CRM.
            'sapo_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('sapo_products', 'sapo_variant_id'),
            ],
            'contact_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:180'],
            // Bắt buộc email khi tạo hoặc sửa. Luồng chuyển đổi vẫn xử lý Lead cũ chỉ có số điện thoại.
            ...self::contactRules(requireEmail: true),

            'stage' => [
                'required',
                Rule::in(array_keys(Lead::STAGES[$kind])),
            ],

            'estimated_value' => [
                'sometimes',
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

    /** @return array{email: list<string>, phone: list<string>} */
    public static function contactRules(bool $requireEmail = false): array
    {
        // Chấp nhận mã quốc gia và dấu phân cách thông dụng, nhưng phải có đủ chữ số.
        return [
            'email' => ['bail', $requireEmail ? 'required' : 'nullable', 'string', 'max:255', 'email'],
            'phone' => ['bail', 'nullable', 'string', 'max:30', 'regex:/\A\+?(?:[ .()\-]*[0-9]){8,15}[ .()\-]*\z/'],
        ];
    }

    /** @return array{'email.email': string, 'phone.regex': string} */
    public static function contactMessages(): array
    {
        return [
            'email.email' => 'Email không hợp lệ.',
            'phone.regex' => 'Số điện thoại phải có từ 8 đến 15 chữ số và có thể bắt đầu bằng dấu +.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'tên nhu cầu',
            'sapo_variant_id' => 'sản phẩm',
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
            ...self::contactMessages(),
            'email.required' => 'Vui lòng nhập email.',
            'stage.in' => 'Trạng thái không phù hợp với loại bản ghi.',
            'sapo_variant_id.exists' => 'Sản phẩm không còn trong danh mục Sapo đã đồng bộ.',
            'estimated_value.numeric' => 'Giá trị dự kiến phải là số.',
            'estimated_value.min' => 'Giá trị dự kiến không được âm.',
            'estimated_value.max' => 'Giá trị dự kiến vượt giới hạn cho phép.',
            'estimated_value.decimal' => 'Chỉ nhập tối đa 2 chữ số thập phân.',
            'prohibited' => 'Không được thay đổi :attribute bằng thao tác này.',
        ];
    }
}
