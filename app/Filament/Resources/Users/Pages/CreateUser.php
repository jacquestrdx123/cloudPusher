<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\ProtectsAdminRoleFields;
use App\Filament\Resources\Users\UserResource;
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
}
