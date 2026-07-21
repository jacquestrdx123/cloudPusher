<?php

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Company;
use App\Models\Lead;
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

it('allows global admins to list leads', function () {
    Lead::factory()->create([
        'name' => 'Jane Doe',
        'company_name' => 'Acme Corp',
    ]);

    Livewire::test(ListLeads::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Lead::query()->get());
});

it('allows global admins to update lead status and notes', function () {
    $lead = Lead::factory()->create([
        'status' => LeadStatus::New,
        'notes' => null,
    ]);

    Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
        ->assertOk()
        ->fillForm([
            'status' => LeadStatus::Contacted->value,
            'notes' => 'Called the ops manager.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($lead->fresh()->status)->toBe(LeadStatus::Contacted)
        ->and($lead->fresh()->notes)->toBe('Called the ops manager.');
});

it('hides leads from company admins who are not global admins', function () {
    $companyAdmin = User::factory()->forCompany($this->company, true)->create();

    $this->actingAs($companyAdmin);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->company);

    Livewire::test(ListLeads::class)
        ->assertForbidden();
});
