<?php

use App\Actions\IssueUserApiToken;
use App\Actions\MarkNotificationDelivered;
use App\Models\Company;
use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;

it('promotes sent push deliveries to delivered when the inbox item is opened', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    $notification = PushNotification::factory()->for($company)->forUser($user)->create();

    $inbox = UserNotification::factory()->forUser($user)->unread()->create([
        'company_id' => $company->id,
        'push_notification_id' => $notification->id,
    ]);

    $pushDelivery = NotificationDelivery::factory()->for($notification)->for($user)->sent()->create([
        'channel' => 'fcm',
    ]);
    $mailDelivery = NotificationDelivery::factory()->for($notification)->for($user)->sent()->create([
        'channel' => 'mail',
    ]);
    $otherUserDelivery = NotificationDelivery::factory()
        ->for($notification)
        ->for(User::factory()->forCompany($company))
        ->sent()
        ->create(['channel' => 'fcm']);

    $updated = app(MarkNotificationDelivered::class)->handle($inbox);

    expect($updated)->toBe(1)
        ->and($pushDelivery->fresh())
        ->status->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->delivered_at->not->toBeNull()
        ->and($mailDelivery->fresh())
        ->status->toBe(NotificationDelivery::STATUS_SENT)
        ->delivered_at->toBeNull()
        ->and($otherUserDelivery->fresh())
        ->status->toBe(NotificationDelivery::STATUS_SENT)
        ->delivered_at->toBeNull();
});

it('is idempotent when marking the same notification delivered twice', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    $notification = PushNotification::factory()->for($company)->forUser($user)->create();
    $inbox = UserNotification::factory()->forUser($user)->create([
        'company_id' => $company->id,
        'push_notification_id' => $notification->id,
    ]);

    NotificationDelivery::factory()->for($notification)->for($user)->sent()->create([
        'channel' => 'apns',
    ]);

    $action = app(MarkNotificationDelivered::class);

    expect($action->handle($inbox))->toBe(1)
        ->and($action->handle($inbox))->toBe(0);
});

it('marks push deliveries as delivered when the unified inbox item is opened', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    $notification = PushNotification::factory()->for($company)->forUser($user)->create();
    $inbox = UserNotification::factory()->forUser($user)->unread()->create([
        'company_id' => $company->id,
        'push_notification_id' => $notification->id,
    ]);
    $delivery = NotificationDelivery::factory()->for($notification)->for($user)->sent()->create([
        'channel' => 'fcm',
    ]);

    $issued = app(IssueUserApiToken::class)->handle($user);

    test()->patchJson(route('api.v1.inbox.unified-mark-read', $inbox), [], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.read', true);

    expect($delivery->fresh())
        ->status->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->delivered_at->not->toBeNull()
        ->and($inbox->fresh()->read_at)->not->toBeNull();
});

it('marks push deliveries as delivered when a company inbox item is opened', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['email' => 'mobile@acme.test']);
    $notification = PushNotification::factory()->for($company)->forUser($user)->create();
    $inbox = UserNotification::factory()->forUser($user)->unread()->create([
        'company_id' => $company->id,
        'push_notification_id' => $notification->id,
    ]);
    $delivery = NotificationDelivery::factory()->for($notification)->for($user)->sent()->create([
        'channel' => 'apns',
    ]);

    test()->patchJson(
        route('api.v1.inbox.mark-read', [$company, $inbox]).'?'.http_build_query(['user' => ['email' => 'mobile@acme.test']]),
        [],
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )
        ->assertSuccessful()
        ->assertJsonPath('data.read', true);

    expect($delivery->fresh())
        ->status->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->delivered_at->not->toBeNull();
});
