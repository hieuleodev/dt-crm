<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class PlantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:60'],
            'industrial_zone' => ['nullable', 'string', 'max:120'],
            'employee_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'hazard_areas' => ['nullable', 'array', 'max:20'],
            'hazard_areas.*' => ['string', 'max:60'],
            'receiving_hours' => ['nullable', 'string', 'max:100'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tên nhà máy', 'employee_count' => 'số công nhân'];
    }
}