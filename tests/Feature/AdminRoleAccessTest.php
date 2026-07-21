<?php

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('allows only global admins to open the companies list', function () {
    $company = Company::factory()->create();
    $global = User::factory()->admin()->create();
    $companyAdmin = User::factory()->forCompany($company, true)->create();

    $this->actingAs($global);
    Filament::setTenant($company);
    Livewire::test(ListCompanies::class)->assertOk();

    expect(CompanyResource::canViewAny())->toBeTrue();

    $this->actingAs($companyAdmin);
    Filament::setTenant($company);

    expect(CompanyResource::canViewAny())->toBeFalse();

    $this->get(ListCompanies::getUrl(panel: 'admin', tenant: $company))
        ->assertForbidden();
});

it('lets company admins manage users in their company only', function () {
    $acme = Company::factory()->create(['slug' => 'acme']);
    $beta = Company::factory()->create(['slug' => 'beta']);

    $acmeAdmin = User::factory()->forCompany($acme, true)->create();
    $betaUser = User::factory()->forCompany($beta)->create();

    expect($acmeAdmin->can('view', $betaUser))->toBeFalse()
        ->and($acmeAdmin->can('update', $betaUser))->toBeFalse()
        ->and($acmeAdmin->can('viewAny', User::class))->toBeTrue();

    $this->actingAs($acmeAdmin);
    Filament::setTenant($acme);

    Livewire::test(ListUsers::class)->assertOk();
});

it('prevents company admins from editing global admins', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->forCompany($company, true)->create();
    $global = User::factory()->admin()->create();

    expect($companyAdmin->can('update', $global))->toBeFalse()
        ->and($companyAdmin->can('delete', $global))->toBeFalse()
        ->and($global->can('update', $companyAdmin))->toBeTrue();
});

it('scopes company admin tenant access to their company', function () {
    $acme = Company::factory()->create();
    $beta = Company::factory()->create();
    $companyAdmin = User::factory()->forCompany($acme, true)->create();

    expect($companyAdmin->canAccessTenant($acme))->toBeTrue()
        ->and($companyAdmin->canAccessTenant($beta))->toBeFalse()
        ->and($companyAdmin->getTenants(Filament::getPanel('admin')))->toHaveCount(1);
});
