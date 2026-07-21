<?php

use App\Actions\IssueUserApiToken;
use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Support\Facades\DB;

it('permanently deletes the authenticated account when the password is correct', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create([
        'password' => 'password',
    ]);
    DeviceToken::factory()->for($user)->create();

    $token = app(IssueUserApiToken::class)->handle($user)['plain_text_token'];
    $userId = $user->id;

    test()->deleteJson(route('api.v1.auth.account.destroy'), [
        'password' => 'password',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertNoContent();

    expect(User::query()->whereKey($userId)->exists())->toBeFalse()
        ->and(UserApiToken::query()->where('user_id', $userId)->exists())->toBeFalse()
        ->and(DeviceToken::query()->where('user_id', $userId)->exists())->toBeFalse()
        ->and(DB::table('company_user')->where('user_id', $userId)->exists())->toBeFalse();

    test()->getJson(route('api.v1.auth.me'), [
        'Authorization' => 'Bearer '.$token,
    ])->assertUnauthorized();
});

it('rejects account deletion with an incorrect password', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create([
        'password' => 'password',
    ]);

    $token = app(IssueUserApiToken::class)->handle($user)['plain_text_token'];

    test()->deleteJson(route('api.v1.auth.account.destroy'), [
        'password' => 'wrong-password',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('rejects account deletion when unauthenticated', function () {
    test()->deleteJson(route('api.v1.auth.account.destroy'), [
        'password' => 'password',
    ])->assertUnauthorized();
});
