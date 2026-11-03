<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerOwnerHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReassignOwner
{
    public function handle(Customer $customer, ?int $toUserId, User $actor, ?string $reason = null): void
    {
        if ($customer->owner_id === $toUserId) {
            return;
        }

        DB::transaction(function () use ($customer, $toUserId, $actor, $reason) {
            CustomerOwnerHistory::create([
                'customer_id' => $customer->id,
                'from_user_id' => $customer->owner_id,
                'to_user_id' => $toUserId,
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);
            $customer->update(['owner_id' => $toUserId]);
            DataCompleteness::refresh($customer);
        });
    }
}