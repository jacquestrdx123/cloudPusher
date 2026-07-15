<?php

use App\Models\Company;
use App\Models\User;
use App\Models\UserApiToken;
use App\Models\UserNotification;

it('logs in with phone and password and returns a personal api token', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create([
        'phone' => '+27821234567',
        'password' => 'password',
    ]);

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27 82 123 4567',
        'password' => 'password',
    ])
        ->assertSuccessful()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.phone', '+27821234567')
        ->assertJsonPath('user.company.slug', $company->slug)
        ->assertJsonStructure(['token']);

    expect(UserApiToken::where('user_id', $user->id)->count())->toBe(1);
});

it('rejects invalid login credentials', function () {
    $company = Company::factory()->create();
    User::factory()->for($company)->create([
        'phone' => '+27821234567',
        'password' => 'password',
    ]);

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

it('allows inbox access after password login', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create([
        'phone' => '+27821234567',
        'password' => 'password',
    ]);

    UserNotification::factory()->forUser($user)->create([
        'title' => 'Logged in alert',
    ]);

    $token = test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'password',
    ])->json('token');

    test()->getJson(route('api.v1.inbox.index', $company), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Logged in alert');
});

it('returns the authenticated user and supports logout', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create([
        'phone' => '+27821234567',
        'password' => 'password',
    ]);

    $token = test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'password',
    ])->json('token');

    test()->getJson(route('api.v1.auth.me'), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.company.slug', $company->slug);

    test()->postJson(route('api.v1.auth.logout'), [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertNoContent();

    test()->getJson(route('api.v1.auth.me'), [
        'Authorization' => 'Bearer '.$token,
    ])->assertUnauthorized();
});
