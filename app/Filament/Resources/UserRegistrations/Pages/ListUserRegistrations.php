<?php

namespace App\Filament\Resources\UserRegistrations\Pages;

use App\Filament\Resources\UserRegistrations\UserRegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListUserRegistrations extends ListRecords
{
    protected static string $resource = UserRegistrationResource::class;
}
