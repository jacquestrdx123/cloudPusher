<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->canAdministerCompany($company);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->canAdministerCompany($company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isGlobalAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }
}
