<?php

namespace App\Domain\Customer\Policies;

use App\Domain\Customer\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isManager() || $customer->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
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