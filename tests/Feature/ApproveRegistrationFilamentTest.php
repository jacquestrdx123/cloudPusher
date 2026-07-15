<?php

use App\Enums\UserRegistrationStatus;
use App\Filament\Resources\UserRegistrations\Pages\ListUserRegistrations;
use App\Models\Company;
use App\Models\User;
use App\Models\UserRegistration;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('allows company admins to access the admin panel for their company', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->companyAdmin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect();

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('allows company admins to approve registrations from the filament table', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->companyAdmin()->create();
    $registration = UserRegistration::factory()->for($company)->create([
        'phone' => '+27829998877',
        'email' => 'pending@example.com',
    ]);

    $this->actingAs($admin);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($company);

    Livewire::test(ListUserRegistrations::class)
        ->assertOk()
        ->callTableAction('approve', $registration);

    expect($registration->fresh()->status)->toBe(UserRegistrationStatus::Approved)
        ->and(User::query()->where('email', 'pending@example.com')->exists())->toBeTrue();
});
