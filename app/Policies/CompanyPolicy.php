<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->canAccessTenant($company);
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->canAccessTenant($company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->is_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }
}
