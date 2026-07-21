<?php

use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\RelationManagers\UsersRelationManager;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('lets global admins attach an existing user to a company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $admin = User::factory()->admin()->create();
    $member = User::factory()->forCompany($companyA)->create();

    $this->actingAs($admin);
    Filament::setTenant($companyB);

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $companyB,
        'pageClass' => EditCompany::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('attach')->table(), [
            'recordId' => $member->getKey(),
            'is_company_admin' => true,
        ])
        ->assertHasNoFormErrors();

    expect($member->fresh()->belongsToCompany($companyB))->toBeTrue()
        ->and($member->fresh()->isCompanyAdminOf($companyB))->toBeTrue()
        ->and($member->companies)->toHaveCount(2);
});

it('lets global admins detach a user from a company', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->admin()->create();
    $member = User::factory()->forCompany($company)->create();

    $this->actingAs($admin);
    Filament::setTenant($company);

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $company,
        'pageClass' => EditCompany::class,
    ])
        ->callAction(TestAction::make('detach')->table($member))
        ->assertHasNoActionErrors();

    expect($member->fresh()->belongsToCompany($company))->toBeFalse();
});
