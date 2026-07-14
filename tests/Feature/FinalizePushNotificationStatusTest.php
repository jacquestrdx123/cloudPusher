<?php

use App\Jobs\FinalizePushNotificationStatus;
use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;

it('marks a notification as sent when all deliveries succeed', function () {
    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create([
        'status' => PushNotification::STATUS_PROCESSING,
        'recipients_count' => 1,
    ]);

    NotificationDelivery::factory()->for($notification)->for($user)->create([
        'status' => NotificationDelivery::STATUS_SENT,
        'sent_at' => now(),
    ]);

    (new FinalizePushNotificationStatus($notification))->handle();

    expect($notification->fresh()->status)->toBe(PushNotification::STATUS_SENT);
});

it('marks a notification as failed when every delivery fails', function () {
    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create([
        'status' => PushNotification::STATUS_PROCESSING,
        'recipients_count' => 1,
    ]);

    NotificationDelivery::factory()->for($notification)->for($user)->create([
        'status' => NotificationDelivery::STATUS_FAILED,
        'error' => 'Invalid token',
    ]);

    (new FinalizePushNotificationStatus($notification))->handle();

    expect($notification->fresh()->status)->toBe(PushNotification::STATUS_FAILED);
});

it('marks a notification as partial when some deliveries fail', function () {
    $users = User::factory()->count(2)->create();
    $notification = PushNotification::factory()->forUser($users->first())->create([
        'status' => PushNotification::STATUS_PROCESSING,
        'recipients_count' => 2,
    ]);

    NotificationDelivery::factory()->for($notification)->for($users[0])->create([
        'status' => NotificationDelivery::STATUS_SENT,
        'sent_at' => now(),
    ]);

    NotificationDelivery::factory()->for($notification)->for($users[1])->create([
        'status' => NotificationDelivery::STATUS_FAILED,
        'error' => 'Timeout',
    ]);

    (new FinalizePushNotificationStatus($notification))->handle();

    expect($notification->fresh()->status)->toBe(PushNotification::STATUS_PARTIAL);
});

it('marks a notification as failed when recipients exist but no deliveries were recorded', function () {
    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create([
        'status' => PushNotification::STATUS_PROCESSING,
        'recipients_count' => 1,
    ]);

    $job = new FinalizePushNotificationStatus($notification);
    $job->handle();

    expect($notification->fresh()->status)->toBe(PushNotification::STATUS_PROCESSING);
});

it('marks a zero-recipient notification as sent', function () {
    $notification = PushNotification::factory()->create([
        'status' => PushNotification::STATUS_PROCESSING,
        'recipients_count' => 0,
    ]);

    (new FinalizePushNotificationStatus($notification))->handle();

    expect($notification->fresh()->status)->toBe(PushNotification::STATUS_SENT);
});
