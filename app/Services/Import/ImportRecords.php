<?php

namespace App\Services\Import;

use App\Enums\Customer\CompanyType;
use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerTier;
use App\Enums\Customer\Industry;
use App\Enums\Customer\LifecycleStage;
use App\Enums\Customer\SourceChannel;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\Customer\FindDuplicateCustomers;
use App\Services\Customer\SaveCustomer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportRecords
{
    private const HEADERS = [
        'customer' => [
            'code',
            'name',
            'short_name',
            'tax_code',
            'phone',
            'email',
            'website',
            'address',
            'province',
            'industrial_zone',
            'industry',
            'company_type',
            'employee_count',
            'source_channel',
            'tier',
            'lifecycle_stage',
            'status',
            'notes',
        ],
        'lead' => [
            'title',
            'contact_name',
            'company',
            'email',
            'phone',
            'stage',
            'estimated_value',
            'notes',
        ],
    ];

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly SaveCustomer $saveCustomer,
        private readonly FindDuplicateCustomers $findDuplicateCustomers,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(string $type): array
    {
        if (! isset(self::HEADERS[$type])) {
            throw new InvalidArgumentException('Unsupported import type.');
        }

        return self::HEADERS[$type];
    }

    /**
     * @return array{type: string, created: int, duplicates: int, total: int}
     */
    public function handle(User $actor, string $type, UploadedFile $file): array
    {
        $rows = $this->reader->read($file);
        $headerIndex = null;

        foreach ($rows as $index => $row) {
            if (! $this->isBlank($row['values'])) {
                $headerIndex = $index;

                break;
            }
        }

        if ($headerIndex === null) {
            $this->invalidImport('Tệp không có dòng tiêu đề.');
        }

        $headers = array_map(
            static fn (string $header): string => strtolower(trim($header)),
            $rows[$headerIndex]['values'],
        );
        $allowedHeaders = $this->headers($type);
        $requiredHeaders = $type === 'customer'
            ? ['name']
            : ['title', 'contact_name'];

        if (count($headers) !== count(array_unique($headers))) {
            $this->invalidImport('Tệp có tiêu đề cột bị lặp.');
        }

        $unknownHeaders = array_diff($headers, $allowedHeaders);

        if ($unknownHeaders !== []) {
            $this->invalidImport('Tệp có cột không thuộc mẫu: '.implode(', ', $unknownHeaders).'.');
        }

        $missingHeaders = array_diff($requiredHeaders, $headers);

        if ($missingHeaders !== []) {
            $this->invalidImport('Thiếu cột bắt buộc: '.implode(', ', $missingHeaders).'.');
        }

        $validatedRows = [];
        $rowErrors = [];
        $errorCount = 0;

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            if ($this->isBlank($row['values'])) {
                continue;
            }

            if (count($row['values']) > count($headers)) {
                $errorCount++;

                if (count($rowErrors) < 10) {
                    $rowErrors[] = "Dòng {$row['line']}: số ô dữ liệu nhiều hơn số cột tiêu đề.";
                }

                continue;
            }

            $values = array_pad($row['values'], count($headers), '');
            $data = array_combine($headers, $values);
            $data = array_map(
                static fn (string $value): ?string => trim($value) === '' ? null : trim($value),
                $data,
            );
            $validator = Validator::make(
                $data,
                $this->rules($type),
                [
                    'required' => 'Vui lòng nhập :attribute.',
                    'email' => ':Attribute không phải email hợp lệ.',
                    'enum' => ':Attribute không hợp lệ.',
                    'in' => ':Attribute không hợp lệ.',
                    'integer' => ':Attribute phải là số nguyên.',
                    'numeric' => ':Attribute phải là số.',
                    'min.numeric' => ':Attribute không được nhỏ hơn :min.',
                    'max.string' => ':Attribute vượt quá :max ký tự.',
                    'decimal' => ':Attribute chỉ được có tối đa 2 chữ số thập phân.',
                ],
            );
            $validator->setAttributeNames($this->attributeNames($type));

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errorCount++;

                    if (count($rowErrors) < 10) {
                        $rowErrors[] = "Dòng {$row['line']}: {$message}";
                    }
                }

                continue;
            }

            $validatedRows[] = $validator->validated();
        }

        if ($errorCount > 0) {
            if ($errorCount > count($rowErrors)) {
                $rowErrors[] = 'Còn '.($errorCount - count($rowErrors)).' lỗi khác.';
            }

            $this->invalidImport('Không có dữ liệu nào được nhập. '.implode(' ', $rowErrors));
        }

        if ($validatedRows === []) {
            $this->invalidImport('Tệp không có dòng dữ liệu để nhập.');
        }

        return DB::transaction(function () use ($actor, $type, $validatedRows): array {
            $created = 0;
            $duplicates = 0;

            foreach ($validatedRows as $row) {
                if ($this->isDuplicate($type, $row)) {
                    $duplicates++;

                    continue;
                }

                if ($type === 'customer') {
                    $this->saveCustomer->handle($row, $actor);
                } else {
                    $lead = new Lead([
                        ...$row,
                        'stage' => $row['stage'] ?? 'new',
                        'estimated_value' => $row['estimated_value'] ?? 0,
                    ]);
                    $lead->owner_id = $actor->id;
                    $lead->kind = 'lead';
                    $lead->save();
                }

                $created++;
            }

            return [
                'type' => $type,
                'created' => $created,
                'duplicates' => $duplicates,
                'total' => $created + $duplicates,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(string $type): array
    {
        if ($type === 'lead') {
            return [
                'title' => ['required', 'string', 'max:180'],
                'contact_name' => ['required', 'string', 'max:120'],
                'company' => ['nullable', 'string', 'max:180'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'stage' => ['nullable', Rule::in(array_keys(Lead::STAGES['lead']))],
                'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ];
        }

        return [
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:60'],
            'industrial_zone' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', Rule::enum(Industry::class)],
            'company_type' => ['nullable', Rule::enum(CompanyType::class)],
            'employee_count' => ['nullable', 'integer', 'min:0'],
            'source_channel' => ['nullable', Rule::enum(SourceChannel::class)],
            'tier' => ['nullable', Rule::enum(CustomerTier::class)],
            'lifecycle_stage' => ['nullable', Rule::enum(LifecycleStage::class)],
            'status' => ['nullable', Rule::enum(CustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(string $type): array
    {
        if ($type === 'lead') {
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

        return [
            'code' => 'mã khách hàng',
            'name' => 'tên khách hàng',
            'short_name' => 'tên viết tắt',
            'tax_code' => 'mã số thuế',
            'phone' => 'số điện thoại',
            'email' => 'email',
            'website' => 'website',
            'address' => 'địa chỉ',
            'province' => 'tỉnh thành',
            'industrial_zone' => 'khu công nghiệp',
            'industry' => 'ngành nghề',
            'company_type' => 'loại hình doanh nghiệp',
            'employee_count' => 'số nhân viên',
            'source_channel' => 'nguồn khách hàng',
            'tier' => 'hạng khách hàng',
            'lifecycle_stage' => 'giai đoạn khách hàng',
            'status' => 'trạng thái',
            'notes' => 'ghi chú',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isDuplicate(string $type, array $data): bool
    {
        if ($type === 'customer') {
            $taxCode = Customer::normalizeTaxCode($data['tax_code'] ?? null);
            $code = $data['code'] ?? null;

            if (($code && Customer::withTrashed()->where('code', $code)->exists())
                || ($taxCode && Customer::withTrashed()->where('tax_code', $taxCode)->exists())) {
                return true;
            }

            return $this->findDuplicateCustomers->handle(
                $taxCode,
                $data['name'],
                $data['phone'] ?? null,
                $data['email'] ?? null,
            ) !== [];
        }

        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $query = Lead::query();

        if ($email || $phone) {
            return $query->where(function ($query) use ($email, $phone): void {
                if ($email) {
                    $query->orWhereRaw('LOWER(email) = ?', [Str::lower($email)]);
                }

                if ($phone) {
                    $query->orWhere('phone', $phone);
                }
            })->exists();
        }

        return $query
            ->where('title', $data['title'])
            ->where('contact_name', $data['contact_name'])
            ->where('company', $data['company'] ?? null)
            ->exists();
    }

    /**
     * @param  list<string>  $values
     */
    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function invalidImport(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
