<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->is_active && ($user->isManager() || $customer->owner_id === $user->id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isManager();
    }

    public function reassign(User $user, Customer $customer): bool
    {
        return $user->isManager();
    }
}
