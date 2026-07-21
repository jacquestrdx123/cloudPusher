<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\ProtectsAdminRoleFields;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use ProtectsAdminRoleFields;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->sanitizeAdminRoleFields($data);
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        $this->syncTenantMembership(
            $user,
            (bool) ($this->form->getState()['is_company_admin'] ?? false),
        );
    }
}
