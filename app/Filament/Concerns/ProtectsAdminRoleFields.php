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

            if (! $actor->isCompanyAdmin()) {
                unset($data['is_company_admin']);
            }

            $tenant = Filament::getTenant();

            if ($tenant !== null) {
                $data['company_id'] = $tenant->getKey();
            } else {
                $data['company_id'] = $actor->company_id;
            }

            $data['is_admin'] = false;
        }

        if ($existing?->isGlobalAdmin() && ! $actor->isGlobalAdmin()) {
            unset($data['is_admin'], $data['is_company_admin'], $data['company_id']);
        }

        return $data;
    }
}
