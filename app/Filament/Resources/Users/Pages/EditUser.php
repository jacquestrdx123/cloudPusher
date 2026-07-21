<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\ProtectsAdminRoleFields;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use ProtectsAdminRoleFields;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();
        $tenant = Filament::getTenant();

        $data['is_company_admin'] = $tenant !== null
            ? $record->isCompanyAdminOf($tenant)
            : $record->isCompanyAdmin();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        return $this->sanitizeAdminRoleFields($data, $record);
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        $this->syncTenantMembership(
            $user,
            (bool) ($this->form->getState()['is_company_admin'] ?? false),
        );
    }
}
