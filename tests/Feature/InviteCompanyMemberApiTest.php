<?php

use App\Actions\IssueUserApiToken;
use App\Models\Company;
use App\Models\User;

it('lets a company admin add an existing user by phone', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();
    $existing = User::factory()->create(['phone' => '+27825551212']);

    $issued = app(IssueUserApiToken::class)->handle($admin);

    test()->postJson(route('api.v1.members.store', $company), [
        'phone' => '+27 82 555 1212',
    ], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.id', $existing->id)
        ->assertJsonPath('data.company.slug', $company->slug);

    expect($existing->fresh()->belongsToCompany($company))->toBeTrue();
});

it('is idempotent when the user is already a member', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();
    $member = User::factory()->forCompany($company)->create(['phone' => '+27825551212']);

    $issued = app(IssueUserApiToken::class)->handle($admin);

    test()->postJson(route('api.v1.members.store', $company), [
        'phone' => '+27825551212',
    ], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $member->id);

    expect($member->companies()->whereKey($company->id)->count())->toBe(1);
});

it('returns validation error when the user does not exist', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();
    $issued = app(IssueUserApiToken::class)->handle($admin);

    test()->postJson(route('api.v1.members.store', $company), [
        'phone' => '+27820000000',
    ], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])->assertUnprocessable();
});

it('forbids non-admins from inviting members', function () {
    $company = Company::factory()->create();
    $member = User::factory()->forCompany($company)->create();
    $existing = User::factory()->create(['phone' => '+27825551212']);
    $issued = app(IssueUserApiToken::class)->handle($member);

    test()->postJson(route('api.v1.members.store', $company), [
        'phone' => '+27825551212',
    ], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])->assertForbidden();

    expect($existing->fresh()->belongsToCompany($company))->toBeFalse();
});

it('lets a company admin remove a member', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();
    $member = User::factory()->forCompany($company)->create();
    $issued = app(IssueUserApiToken::class)->handle($admin);

    test()->deleteJson(route('api.v1.members.destroy', [$company, $member]), [], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])->assertNoContent();

    expect($member->fresh()->belongsToCompany($company))->toBeFalse();
});

it('blocks self-registration when the identity already exists', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    User::factory()->forCompany($other)->create([
        'phone' => '+27821234567',
        'email' => 'taken@example.com',
    ]);

    test()->postJson(route('api.v1.auth.register', $company), [
        'name' => 'Jane Doe',
        'email' => 'taken@example.com',
        'phone' => '+27829999999',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.email.0',
            'This email address is already registered. Ask a company admin to add you to this company.',
        );
});
