<?php

use App\Actions\IssueUserApiToken;
use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserNotification;

it('lists inbox items across all companies for the authenticated user', function () {
    $acme = Company::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
    $beta = Company::factory()->create(['slug' => 'beta', 'name' => 'Beta']);
    $user = User::factory()->forCompany($acme)->create();
    $user->companies()->syncWithoutDetaching([$beta->id => ['is_company_admin' => false]]);

    UserNotification::factory()->create([
        'company_id' => $acme->id,
        'user_id' => $user->id,
        'title' => 'From Acme',
    ]);
    UserNotification::factory()->create([
        'company_id' => $beta->id,
        'user_id' => $user->id,
        'title' => 'From Beta',
    ]);

    $issued = app(IssueUserApiToken::class)->handle($user);

    test()->getJson(route('api.v1.inbox.unified'), [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['title' => 'From Acme'])
        ->assertJsonFragment(['title' => 'From Beta'])
        ->assertJsonFragment(['slug' => 'acme'])
        ->assertJsonFragment(['slug' => 'beta']);
});

it('filters the unified inbox by company slug', function () {
    $acme = Company::factory()->create(['slug' => 'acme']);
    $beta = Company::factory()->create(['slug' => 'beta']);
    $user = User::factory()->forCompany($acme)->create();
    $user->companies()->syncWithoutDetaching([$beta->id => ['is_company_admin' => false]]);

    UserNotification::factory()->create([
        'company_id' => $acme->id,
        'user_id' => $user->id,
        'title' => 'From Acme',
    ]);
    UserNotification::factory()->create([
        'company_id' => $beta->id,
        'user_id' => $user->id,
        'title' => 'From Beta',
    ]);

    $issued = app(IssueUserApiToken::class)->handle($user);

    test()->getJson(route('api.v1.inbox.unified').'?company=beta', [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'From Beta')
        ->assertJsonPath('data.0.company.slug', 'beta');
});

it('marks all inbox items read across companies or one company', function () {
    $acme = Company::factory()->create(['slug' => 'acme']);
    $beta = Company::factory()->create(['slug' => 'beta']);
    $user = User::factory()->forCompany($acme)->create();
    $user->companies()->syncWithoutDetaching([$beta->id => ['is_company_admin' => false]]);

    UserNotification::factory()->unread()->create([
        'company_id' => $acme->id,
        'user_id' => $user->id,
    ]);
    UserNotification::factory()->unread()->create([
        'company_id' => $beta->id,
        'user_id' => $user->id,
    ]);

    $issued = app(IssueUserApiToken::class)->handle($user);

    test()->patchJson(route('api.v1.inbox.unified-mark-all-read').'?company=acme', [], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])->assertNoContent();

    expect(UserNotification::query()->where('user_id', $user->id)->where('company_id', $acme->id)->whereNull('read_at')->count())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $user->id)->where('company_id', $beta->id)->whereNull('read_at')->count())->toBe(1);

    test()->patchJson(route('api.v1.inbox.unified-mark-all-read'), [], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])->assertNoContent();

    expect(UserNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count())->toBe(0);
});

it('registers a device token without a company slug', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    $issued = app(IssueUserApiToken::class)->handle($user);

    test()->postJson(route('api.v1.device-tokens.store-for-user'), [
        'platform' => 'fcm',
        'token' => 'unified-device-token-1',
        'name' => 'Pixel',
    ], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.platform', 'fcm');

    expect(DeviceToken::query()->where('user_id', $user->id)->where('token', 'unified-device-token-1')->exists())->toBeTrue();
});
