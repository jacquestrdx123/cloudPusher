<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\CompaniesRelationManager;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('lets global admins attach a user to additional companies', function () {
    $companyA = Company::factory()->create(['name' => 'Acme']);
    $companyB = Company::factory()->create(['name' => 'Beta']);
    $admin = User::factory()->admin()->create();
    $member = User::factory()->forCompany($companyA)->create();

    $this->actingAs($admin);
    Filament::setTenant($companyA);

    expect(CompaniesRelationManager::canViewForRecord($member, EditUser::class))->toBeTrue();

    Livewire::test(CompaniesRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => EditUser::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$companyA])
        ->assertCanNotSeeTableRecords([$companyB])
        ->callAction(TestAction::make('attach')->table(), [
            'recordId' => $companyB->getKey(),
            'is_company_admin' => true,
        ])
        ->assertHasNoFormErrors();

    expect($member->fresh()->belongsToCompany($companyB))->toBeTrue()
        ->and($member->fresh()->isCompanyAdminOf($companyB))->toBeTrue()
        ->and($member->companies)->toHaveCount(2);
});

it('hides the companies relation manager from company admins', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->forCompany($company, true)->create();
    $member = User::factory()->forCompany($company)->create();

    $this->actingAs($companyAdmin);
    Filament::setTenant($company);

    expect(CompaniesRelationManager::canViewForRecord($member, EditUser::class))->toBeFalse();
});

it('lets global admins update company admin status on an existing membership', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->admin()->create();
    $member = User::factory()->forCompany($company)->create();

    $this->actingAs($admin);
    Filament::setTenant($company);

    Livewire::test(CompaniesRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => EditUser::class,
    ])
        ->callAction(TestAction::make('edit')->table($company), [
            'is_company_admin' => true,
        ])
        ->assertHasNoFormErrors();

    expect($member->fresh()->isCompanyAdminOf($company))->toBeTrue();
});
