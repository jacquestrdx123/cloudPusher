<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserGroup;

class UserGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function view(User $user, UserGroup $group): bool
    {
        return $user->canAdministerCompany($group->company);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function update(User $user, UserGroup $group): bool
    {
        return $user->canAdministerCompany($group->company);
    }

    public function delete(User $user, UserGroup $group): bool
    {
        return $user->canAdministerCompany($group->company);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }
}
