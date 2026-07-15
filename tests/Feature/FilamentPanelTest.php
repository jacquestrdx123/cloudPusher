<?php

use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\DeviceTokens\Pages\ListDeviceTokens;
use App\Filament\Resources\PushNotifications\Pages\ListPushNotifications;
use App\Filament\Resources\UserGroups\Pages\ListUserGroups;
use App\Filament\Resources\UserRegistrations\Pages\ListUserRegistrations;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create();

    $this->actingAs($this->admin);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->company);
});

it('denies panel access to users who are not platform or company admins', function () {
    $panelUser = User::factory()->create([
        'is_admin' => false,
        'is_company_admin' => false,
    ]);

    $this->actingAs($panelUser)
        ->get('/admin')
        ->assertForbidden();
});

it('allows company admins to open the panel', function () {
    $companyAdmin = User::factory()->for($this->company)->companyAdmin()->create();

    $this->actingAs($companyAdmin)
        ->get('/admin')
        ->assertRedirect();
});

it('redirects admins from the panel root to their default tenant', function () {
    $this->get('/admin')
        ->assertRedirect(Filament::getUrl($this->company));
});

it('renders every resource list page', function (string $page) {
    Company::factory()->has(User::factory()->count(2))->create();
    User::factory()->for($this->company)->count(2)->create();
    PushNotification::factory()->for($this->company)->create();

    Livewire::test($page)
        ->assertOk();
})->with([
    ListCompanies::class,
    ListUsers::class,
    ListUserGroups::class,
    ListDeviceTokens::class,
    ListPushNotifications::class,
    ListUserRegistrations::class,
]);
