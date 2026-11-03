<?php

namespace App\Http\Requests\Customer;

use App\Domain\Customer\Enums\CompanyType;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\CustomerTier;
use App\Domain\Customer\Enums\Industry;
use App\Domain\Customer\Enums\LifecycleStage;
use App\Domain\Customer\Enums\ProcurementMethod;
use App\Domain\Customer\Enums\SourceChannel;
use App\Domain\Customer\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => filled($this->input('code')) ? mb_strtoupper(trim($this->input('code'))) : null,
            'tax_code' => Customer::normalizeTaxCode($this->input('tax_code')),
        ]);

        if (! $this->user()->isManager()) {
            $this->merge(['owner_id' => $this->user()->id]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('customer')?->id;

        return [
            'code' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'tax_code' => ['nullable', 'regex:/^\d{10}(-\d{3})?$/', Rule::unique('customers', 'tax_code')->ignore($id)],
            'company_type' => ['nullable', Rule::enum(CompanyType::class)],
            'parent_group' => ['nullable', 'string', 'max:255'],
            'parent_country' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:60'],
            'industrial_zone' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', Rule::enum(Industry::class)],
            'industry_detail' => ['nullable', 'string', 'max:255'],
            'employee_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'ppe_budget_estimate' => ['nullable', 'integer', 'min:0'],
            'current_suppliers' => ['nullable', 'string', 'max:2000'],
            'source_channel' => ['nullable', Rule::enum(SourceChannel::class)],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'tier' => ['nullable', Rule::enum(CustomerTier::class)],
            'lifecycle_stage' => ['required', Rule::enum(LifecycleStage::class)],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'procurement_method' => ['nullable', Rule::enum(ProcurementMethod::class)],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'integer', 'min:0'],
            'vendor_registered' => ['boolean'],
            'vendor_registered_at' => ['nullable', 'date'],
            'required_certificates' => ['nullable', 'array', 'max:20'],
            'required_certificates.*' => ['string', 'max:60'],
            'marketing_consent' => ['boolean'],
            'consent_channels' => ['nullable', 'array'],
            'consent_channels.*' => [Rule::in(['zalo', 'email', 'sms', 'phone'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'tax_code.regex' => 'Mã số thuế gồm 10 chữ số, hoặc 10 số-3 số với chi nhánh (VD: 0101234567-001).',
            'tax_code.unique' => 'Mã số thuế đã có trong hệ thống – có thể khách đã được tạo, hãy tìm trước khi thêm mới.',
            'code.unique' => 'Mã khách hàng đã được dùng.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã khách hàng', 'name' => 'tên công ty', 'short_name' => 'tên viết tắt', 'tax_code' => 'mã số thuế',
            'company_type' => 'loại hình', 'phone' => 'điện thoại', 'email' => 'email', 'industry' => 'ngành',
            'employee_count' => 'số công nhân', 'ppe_budget_estimate' => 'tiềm năng PPE/năm', 'lifecycle_stage' => 'giai đoạn',
            'status' => 'trạng thái', 'owner_id' => 'người phụ trách', 'credit_limit' => 'hạn mức công nợ', 'tags' => 'nhãn',
        ];
    }
}