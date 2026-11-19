<?php

namespace App\Services\Customer;

use App\Enums\Customer\LifecycleStage;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SaveCustomerFromOpportunity
{
    public function __construct(private SaveCustomer $saveCustomer) {}

    /**
     * Chạy trong giao dịch chuyển đổi để hồ sơ Khách hàng và Cơ hội được lưu cùng nhau.
     */
    public function handle(Lead $opportunity, User $actor): Customer
    {
        // Xếp các lượt chuyển của cùng người phụ trách theo thứ tự trước khi kiểm tra trùng.
        User::query()->whereKey($opportunity->owner_id)->lockForUpdate()->first(['id']);

        $phone = Customer::normalizePhone($opportunity->phone);
        $email = filled($opportunity->email) ? mb_strtolower(trim($opportunity->email)) : null;
        $existing = $this->findExistingCustomer($opportunity, $phone, $email);

        if ($existing !== null) {
            return $existing;
        }

        $lifecycleStage = match ($opportunity->stage) {
            'negotiation' => LifecycleStage::Negotiating,
            'won' => LifecycleStage::NewCustomer,
            'lost' => LifecycleStage::Lost,
            default => LifecycleStage::Quoting,
        };

        $customer = $this->saveCustomer->handle([
            'name' => $opportunity->company ?: ($opportunity->contact_name ?: $opportunity->title),
            'phone' => $phone,
            'email' => $email,
            'owner_id' => $opportunity->owner_id,
            'lifecycle_stage' => $lifecycleStage,
            'ppe_budget_estimate' => $opportunity->estimated_value > 0 ? (int) $opportunity->estimated_value : null,
            'notes' => $opportunity->notes,
        ], $actor);

        if (filled($opportunity->contact_name)) {
            $customer->contacts()->create([
                'full_name' => $opportunity->contact_name,
                'phone' => $phone,
                'email' => $email,
                'is_primary' => true,
            ]);
        }

        return $customer;
    }

    private function findExistingCustomer(Lead $opportunity, ?string $phone, ?string $email): ?Customer
    {
        if ($phone === null && $email === null) {
            return null;
        }

        $phones = $phone === null ? [] : [$phone];
        if ($phone !== null && preg_match('/\A(?:0|\+84)([1-9][0-9]{8,9})\z/', $phone, $matches) === 1) {
            $phones = ['0'.$matches[1], '+84'.$matches[1]];
        }

        $matchesContact = static function (Builder $query) use ($phones, $email): void {
            $query->whereIn('phone', $phones);
            if ($email !== null) {
                $query->orWhere('email', $email);
            }
        };

        // Đối chiếu chính xác thông tin liên hệ trong cùng phạm vi phụ trách.
        $customers = Customer::query()
            ->where('owner_id', $opportunity->owner_id)
            ->when(filled($opportunity->company), fn (Builder $query) => $query
                ->where('name_key', FindDuplicateCustomers::nameKey($opportunity->company)))
            ->where(fn (Builder $query) => $query->where($matchesContact)->orWhereHas('contacts', $matchesContact))
            ->limit(2)
            ->get();

        if ($customers->count() > 1) {
            throw ValidationException::withMessages([
                'customer_id' => 'Có nhiều hồ sơ khách hàng trùng thông tin liên hệ. Hãy kiểm tra hồ sơ trùng trước khi chuyển đổi.',
            ]);
        }

        return $customers->first();
    }
}
