<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ImportDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->is_active
            && $user->isManager();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['customer', 'lead'])],
            'file' => [
                'required',
                'file',
                'max:5120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile
                        || ! in_array(strtolower($value->getClientOriginalExtension()), ['csv', 'xlsx'], true)) {
                        $fail('Chỉ chấp nhận tệp CSV hoặc XLSX.');
                    }
                },
            ],
        ];
    }
}
