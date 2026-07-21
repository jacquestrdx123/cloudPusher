<?php

use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;

it('lists stored inbox notifications for a user by email', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['email' => 'mobile@acme.test']);

    UserNotification::factory()->forUser($user)->create([
        'title' => 'Alert',
        'body' => 'Check this',
        'channel' => 'fcm',
        'delivered_at' => now(),
    ]);

    test()->getJson(
        route('api.v1.inbox.index', $company).'?'.http_build_query(['user' => ['email' => 'mobile@acme.test']]),
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Alert')
        ->assertJsonPath('data.0.channel', 'fcm')
        ->assertJsonPath('data.0.read', false)
        ->assertJsonPath('data.0.delivered_at', fn ($value) => $value !== null);
});

it('marks a single inbox notification as read', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['email' => 'mobile@acme.test']);
    $inbox = UserNotification::factory()->forUser($user)->unread()->create();

    test()->patchJson(
        route('api.v1.inbox.mark-read', [$company, $inbox]).'?'.http_build_query(['user' => ['email' => 'mobile@acme.test']]),
        [],
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )
        ->assertSuccessful()
        ->assertJsonPath('data.read', true)
        ->assertJsonPath('data.read_at', fn ($value) => $value !== null);

    expect($inbox->fresh()->read_at)->not->toBeNull();
});

it('marks all inbox notifications as read for a user', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['email' => 'mobile@acme.test']);

    UserNotification::factory()->forUser($user)->unread()->count(2)->create();

    test()->patchJson(
        route('api.v1.inbox.mark-all-read', $company).'?'.http_build_query(['user' => ['email' => 'mobile@acme.test']]),
        [],
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )->assertNoContent();

    expect(UserNotification::where('user_id', $user->id)->whereNull('read_at')->count())->toBe(0);
});

it('returns 404 when inbox user does not belong to company', function () {
    $company = Company::factory()->create();

    test()->getJson(
        route('api.v1.inbox.index', $company).'?'.http_build_query(['user' => ['email' => 'missing@acme.test']]),
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )->assertNotFound();
});

it('creates a user inbox row when a notification is delivered', function () {
    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create(['channels' => ['mail']]);

    (new ProcessPushNotification($notification))->handle();

    expect(UserNotification::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserNotification::first())
        ->delivered_at->not->toBeNull()
        ->read_at->toBeNull();
});
