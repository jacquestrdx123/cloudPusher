<?php

use App\Actions\IssueUserApiToken;
use App\Models\Company;
use App\Models\User;
use App\Models\UserNotification;

it('allows a user to belong to multiple companies', function () {
    $acme = Company::factory()->create(['slug' => 'acme']);
    $beta = Company::factory()->create(['slug' => 'beta']);

    $user = User::factory()->forCompany($acme)->create();
    $user->companies()->syncWithoutDetaching([
        $beta->id => ['is_company_admin' => false],
    ]);

    expect($user->belongsToCompany($acme))->toBeTrue()
        ->and($user->belongsToCompany($beta))->toBeTrue()
        ->and($user->isCompanyAdminOf($acme))->toBeFalse()
        ->and($user->companies)->toHaveCount(2);
});

it('returns all memberships on login and me', function () {
    $acme = Company::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
    $beta = Company::factory()->create(['slug' => 'beta', 'name' => 'Beta']);

    $user = User::factory()->forCompany($acme, true)->create([
        'phone' => '+27821234567',
        'password' => 'password',
    ]);
    $user->companies()->syncWithoutDetaching([
        $beta->id => ['is_company_admin' => false],
    ]);

    $login = test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'password',
    ])->assertSuccessful();

    $login
        ->assertJsonPath('user.companies.0.slug', fn ($slug) => in_array($slug, ['acme', 'beta'], true))
        ->assertJsonCount(2, 'user.companies');

    $token = $login->json('token');

    test()->getJson(route('api.v1.auth.me'), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.companies');
});

it('rejects company-scoped routes for non-members', function () {
    $acme = Company::factory()->create();
    $beta = Company::factory()->create();
    $user = User::factory()->forCompany($acme)->create();
    $issued = app(IssueUserApiToken::class)->handle($user);

    UserNotification::factory()->forUser($user)->create();

    test()->getJson(route('api.v1.inbox.index', $beta), [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])->assertUnauthorized();
});
