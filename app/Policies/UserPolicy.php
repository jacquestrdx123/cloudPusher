<?php

namespace App\Policies;

use App\Models\User;
use Filament\Facades\Filament;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function view(User $actor, User $model): bool
    {
        return $this->manages($actor, $model);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function update(User $actor, User $model): bool
    {
        if ($model->isGlobalAdmin() && ! $actor->isGlobalAdmin()) {
            return false;
        }

        return $this->manages($actor, $model);
    }

    public function delete(User $actor, User $model): bool
    {
        if ($actor->is($model)) {
            return false;
        }

        if ($model->isGlobalAdmin()) {
            return $actor->isGlobalAdmin();
        }

        return $this->manages($actor, $model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    private function manages(User $actor, User $model): bool
    {
        if ($actor->isGlobalAdmin()) {
            return true;
        }

        if (! $actor->isCompanyAdmin()) {
            return false;
        }

        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            return $actor->isCompanyAdminOf($tenant) && $model->belongsToCompany($tenant);
        }

        return $actor->companies()
            ->wherePivot('is_company_admin', true)
            ->whereIn(
                'companies.id',
                $model->companies()->select('companies.id'),
            )
            ->exists();
    }
}
