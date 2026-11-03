<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerOwnerHistory;
use App\Domain\Customer\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveCustomer
{
    public function handle(array $data, User $actor, ?Customer $customer = null): Customer
    {
        return DB::transaction(function () use ($data, $actor, $customer) {
            $tags = $data['tags'] ?? null;
            unset($data['tags']);

            $data['tax_code'] = Customer::normalizeTaxCode($data['tax_code'] ?? null);
            $isNew = $customer === null;
            $customer ??= new Customer(['created_by' => $actor->id]);

            if ($isNew) {
                $data['code'] = filled($data['code'] ?? null) ? $data['code'] : $this->nextCode();
                $data['owner_id'] ??= $actor->id;
            } else {
                unset($data['owner_id']); 
                if (blank($data['code'] ?? null)) {
                    unset($data['code']); 
                }
            }

            $customer->fill($data);

            if ($customer->isDirty('lifecycle_stage')) {
                $customer->lifecycle_stage_at = now();
            }
            if ($customer->isDirty('marketing_consent') && $customer->marketing_consent) {
                $customer->consent_at = now();
            }

            $customer->save();

            if ($isNew && $customer->owner_id) {
                CustomerOwnerHistory::create([
                    'customer_id' => $customer->id,
                    'to_user_id' => $customer->owner_id,
                    'changed_by' => $actor->id,
                    'reason' => 'Tạo khách hàng',
                ]);
            }

            if (is_array($tags)) {
                $ids = collect($tags)->map(fn ($t) => trim((string) $t))->filter()->unique()
                    ->map(fn ($name) => Tag::firstOrCreate(['name' => $name])->id);
                $customer->tags()->sync($ids);
            }

            DataCompleteness::refresh($customer);

            return $customer;
        });
    }

    private function nextCode(): string
    {
        $last = Customer::withTrashed()->where('code', 'like', 'KH%')->orderByDesc('id')->value('code');
        $n = $last && preg_match('/^KH(\d+)$/', $last, $m) ? (int) $m[1] + 1 : 1;

        do {
            $code = 'KH'.str_pad((string) $n++, 5, '0', STR_PAD_LEFT);
        } while (Customer::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}