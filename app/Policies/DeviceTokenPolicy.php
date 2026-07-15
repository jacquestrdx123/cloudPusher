<?php

namespace App\Policies;

use App\Models\DeviceToken;
use App\Models\User;

class DeviceTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function view(User $user, DeviceToken $deviceToken): bool
    {
        return $user->canAdministerCompany($deviceToken->user?->company);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function update(User $user, DeviceToken $deviceToken): bool
    {
        return $user->canAdministerCompany($deviceToken->user?->company);
    }

    public function delete(User $user, DeviceToken $deviceToken): bool
    {
        return $user->canAdministerCompany($deviceToken->user?->company);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }
}
