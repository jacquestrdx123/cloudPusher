<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('allows platform admins to access every company tenant', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    expect($this->admin->canAccessTenant($companyA))->toBeTrue()
        ->and($this->admin->canAccessTenant($companyB))->toBeTrue()
        ->and($this->admin->getTenants(Filament::getCurrentOrDefaultPanel()))
        ->toHaveCount(2);
});

it('scopes the users list to the current company tenant', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = User::factory()->for($companyA)->create(['name' => 'Tenant A User']);
    $userB = User::factory()->for($companyB)->create(['name' => 'Tenant B User']);

    Filament::setTenant($companyA);

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$userA])
        ->assertCanNotSeeTableRecords([$userB]);
});

it('returns not found when editing a user from another company', function () {
    $companyA = Company::factory()->create(['slug' => 'acme']);
    $companyB = Company::factory()->create(['slug' => 'other']);

    $foreignUser = User::factory()->for($companyB)->create();

    Filament::setTenant($companyA);

    $this->get(EditUser::getUrl(parameters: ['record' => $foreignUser], tenant: $companyA))
        ->assertNotFound();
});
