<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Facades\Filament;

trait ProtectsAdminRoleFields
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeAdminRoleFields(array $data, ?User $existing = null): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if ($actor === null) {
            return $data;
        }

        if (! $actor->isGlobalAdmin()) {
            unset($data['is_admin']);
            $data['is_admin'] = false;
        }

        if ($existing?->isGlobalAdmin() && ! $actor->isGlobalAdmin()) {
            unset($data['is_admin']);
        }

        return $data;
    }

    protected function syncTenantMembership(User $user, bool $isCompanyAdmin): void
    {
        $tenant = Filament::getTenant();

        if ($tenant === null) {
            return;
        }

        /** @var User|null $actor */
        $actor = auth()->user();

        if ($actor === null || (! $actor->isGlobalAdmin() && ! $actor->isCompanyAdminOf($tenant))) {
            $isCompanyAdmin = false;
        }

        $user->companies()->syncWithoutDetaching([
            $tenant->getKey() => ['is_company_admin' => $isCompanyAdmin],
        ]);
    }
}
