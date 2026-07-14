<?php

use App\Models\Company;
use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;

it('lists inbox deliveries for a user by email', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['email' => 'mobile@acme.test']);
    $notification = PushNotification::factory()->forUser($user)->create([
        'company_id' => $company->id,
        'title' => 'Alert',
        'body' => 'Check this',
    ]);

    NotificationDelivery::factory()->for($notification)->for($user)->create([
        'channel' => 'fcm',
        'status' => NotificationDelivery::STATUS_SENT,
        'sent_at' => now(),
    ]);

    test()->getJson(
        route('api.v1.inbox.index', $company).'?'.http_build_query(['user' => ['email' => 'mobile@acme.test']]),
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )
        ->assertSuccessful()
        ->assertJsonPath('data.0.notification.title', 'Alert')
        ->assertJsonPath('data.0.channel', 'fcm');
});

it('returns 404 when inbox user does not belong to company', function () {
    $company = Company::factory()->create();

    test()->getJson(
        route('api.v1.inbox.index', $company).'?'.http_build_query(['user' => ['email' => 'missing@acme.test']]),
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )->assertNotFound();
});
