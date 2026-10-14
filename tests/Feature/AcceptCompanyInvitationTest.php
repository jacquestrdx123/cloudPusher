<?php

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme Logistics']);
    $this->user = User::factory()->forCompany($this->company)->create([
        'name' => 'Thandi Mokoena',
        'phone' => '+27825550001',
    ]);
});

it('shows the set-password form for a valid invitation', function () {
    CompanyInvitation::factory()->for($this->company)->for($this->user)->withToken('valid-token')->create();

    $this->get(route('invitation.show', 'valid-token'))
        ->assertSuccessful()
        ->assertSee('Welcome, Thandi Mokoena')
        ->assertSee('Acme Logistics')
        ->assertSee('+27825550001')
        ->assertSee('Set password');
});

it('explains unusable links', function (string $state, string $message) {
    $factory = CompanyInvitation::factory()->for($this->company)->for($this->user)->withToken('used-token');

    match ($state) {
        'expired' => $factory->expired()->create(),
        'accepted' => $factory->accepted()->create(),
        'revoked' => $factory->revoked()->create(),
        default => null,
    };

    $this->get(route('invitation.show', 'used-token'))
        ->assertSuccessful()
        ->assertSee($message)
        ->assertDontSee('Set password');
})->with([
    'unknown token' => ['missing', 'This link is not valid'],
    'expired' => ['expired', 'This link has expired'],
    'accepted' => ['accepted', 'This link is not valid'],
    'revoked' => ['revoked', 'This link is not valid'],
]);

it('sets the password so the user can sign in to the app', function () {
    $invitation = CompanyInvitation::factory()->for($this->company)->for($this->user)->withToken('valid-token')->create();

    $this->post(route('invitation.accept', 'valid-token'), [
        'password' => 'Str0ng-Password!',
        'password_confirmation' => 'Str0ng-Password!',
    ])->assertRedirect(route('invitation.accepted'));

    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->followingRedirects()
        ->get(route('invitation.accepted'))
        ->assertSee("You're all set", false);

    $this->postJson(route('api.v1.auth.login'), [
        'phone' => '+27825550001',
        'password' => 'Str0ng-Password!',
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.id', $this->user->id);
});

it('shows the company and phone on the confirmation page', function () {
    CompanyInvitation::factory()->for($this->company)->for($this->user)->withToken('valid-token')->create();

    $this->followingRedirects()
        ->post(route('invitation.accept', 'valid-token'), [
            'password' => 'Str0ng-Password!',
            'password_confirmation' => 'Str0ng-Password!',
        ])
        ->assertSee('Acme Logistics')
        ->assertSee('+27825550001');
});

it('only lets a link be used once', function () {
    CompanyInvitation::factory()->for($this->company)->for($this->user)->withToken('valid-token')->create();

    $payload = ['password' => 'Str0ng-Password!', 'password_confirmation' => 'Str0ng-Password!'];

    $this->post(route('invitation.accept', 'valid-token'), $payload)->assertRedirect(route('invitation.accepted'));

    $this->post(route('invitation.accept', 'valid-token'), [
        'password' => 'Another-Passw0rd!',
        'password_confirmation' => 'Another-Passw0rd!',
    ])->assertSee('This link is not valid');

    $this->get(route('invitation.show', 'valid-token'))->assertSee('This link is not valid');
});

it('validates the new password', function () {
    CompanyInvitation::factory()->for($this->company)->for($this->user)->withToken('valid-token')->create();

    $this->from(route('invitation.show', 'valid-token'))
        ->post(route('invitation.accept', 'valid-token'), [
            'password' => 'Str0ng-Password!',
            'password_confirmation' => 'different',
        ])
        ->assertRedirect(route('invitation.show', 'valid-token'))
        ->assertSessionHasErrors('password');
});

it('does not accept an expired invitation', function () {
    $invitation = CompanyInvitation::factory()->for($this->company)->for($this->user)->withToken('old-token')->expired()->create();

    $this->post(route('invitation.accept', 'old-token'), [
        'password' => 'Str0ng-Password!',
        'password_confirmation' => 'Str0ng-Password!',
    ])->assertSee('This link has expired');

    expect($invitation->fresh()->accepted_at)->toBeNull();
});
