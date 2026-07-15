<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserRegistration;

class UserRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function view(User $user, UserRegistration $registration): bool
    {
        return $user->canAdministerCompany($registration->company);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, UserRegistration $registration): bool
    {
        return $user->canAdministerCompany($registration->company);
    }

    public function delete(User $user, UserRegistration $registration): bool
    {
        return $user->isGlobalAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }
}
