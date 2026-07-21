<?php

use App\Enums\LeadStatus;
use App\Models\Lead;

it('shows the contact form with closed-circuit messaging', function () {
    $this->get(route('contact'))
        ->assertSuccessful()
        ->assertSee('closed-circuit system', false)
        ->assertSee('we must register you', false)
        ->assertSee('Request access', false);
});

it('stores a lead from a valid contact submission', function () {
    $this->post(route('contact.store'), [
        'name' => 'Jane Doe',
        'email' => 'Jane@Example.com',
        'company_name' => 'Acme Corp',
        'phone' => '+27821234567',
        'message' => 'We would like access for our operations team.',
    ])
        ->assertRedirect(route('contact'))
        ->assertSessionHas('status');

    $lead = Lead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->name)->toBe('Jane Doe')
        ->and($lead->email)->toBe('jane@example.com')
        ->and($lead->company_name)->toBe('Acme Corp')
        ->and($lead->phone)->toBe('+27821234567')
        ->and($lead->message)->toBe('We would like access for our operations team.')
        ->and($lead->status)->toBe(LeadStatus::New);
});

it('rejects invalid contact submissions', function () {
    $this->from(route('contact'))
        ->post(route('contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'company_name' => '',
            'message' => '',
        ])
        ->assertRedirect(route('contact'))
        ->assertSessionHasErrors(['name', 'email', 'company_name', 'message']);

    expect(Lead::query()->count())->toBe(0);
});
