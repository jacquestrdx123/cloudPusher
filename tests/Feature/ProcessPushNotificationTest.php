<?php

use App\Actions\RecordUserInboxNotification;
use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserNotification;
use App\Notifications\WebhookPushNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmChannel;

it('sends to a single targeted user over the requested channel', function () {
    Notification::fake();

    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create(['channels' => ['mail']]);

    (new ProcessPushNotification($notification))->handle();

    Notification::assertSentTo($user, WebhookPushNotification::class, function ($n, $channels) {
        return in_array('mail', $channels, true);
    });

    expect($notification->fresh())
        ->status->toBe(PushNotification::STATUS_PROCESSING)
        ->recipients_count->toBe(1);
});

it('fans out to every member of a target group', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $group = UserGroup::factory()->for($company)->create();
    $members = User::factory()->forCompany($company)->count(3)->create();
    $group->users()->attach($members);

    $nonMember = User::factory()->forCompany($company)->create();

    $notification = PushNotification::factory()->forGroup($group)->create(['channels' => ['mail']]);

    (new ProcessPushNotification($notification))->handle();

    Notification::assertSentTo($members, WebhookPushNotification::class);
    Notification::assertNotSentTo($nonMember, WebhookPushNotification::class);

    expect($notification->fresh()->recipients_count)->toBe(3);
});

it('selects the fcm channel only when the provider is enabled and the user has a device', function () {
    config()->set('pushservice.providers.fcm', true);
    Notification::fake();

    $withDevice = User::factory()->create();
    DeviceToken::factory()->fcm()->for($withDevice)->create();

    $notification = PushNotification::factory()->forUser($withDevice)->create(['channels' => ['push']]);
    (new ProcessPushNotification($notification))->handle();

    Notification::assertSentTo($withDevice, WebhookPushNotification::class, function ($n, $channels) {
        return in_array(FcmChannel::class, $channels, true);
    });
});

it('records a failed delivery when push providers are disabled', function () {
    config()->set('pushservice.providers.fcm', false);
    config()->set('pushservice.providers.apns', false);
    Notification::fake();

    $user = User::factory()->create();
    DeviceToken::factory()->fcm()->for($user)->create();

    $notification = PushNotification::factory()->forUser($user)->create(['channels' => ['push']]);
    (new ProcessPushNotification($notification))->handle();

    Notification::assertNotSentTo($user, WebhookPushNotification::class);

    $delivery = NotificationDelivery::where('push_notification_id', $notification->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->channel)->toBe('push')
        ->and($delivery->status)->toBe(NotificationDelivery::STATUS_FAILED)
        ->and($delivery->error)->toContain('Push providers are disabled');
});

it('records a failed delivery when the user has no device tokens', function () {
    config()->set('pushservice.providers.fcm', true);
    config()->set('pushservice.providers.apns', false);
    Notification::fake();

    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create(['channels' => ['push']]);

    (new ProcessPushNotification($notification))->handle();

    Notification::assertNotSentTo($user, WebhookPushNotification::class);

    $delivery = NotificationDelivery::where('push_notification_id', $notification->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(NotificationDelivery::STATUS_FAILED)
        ->and($delivery->error)->toContain('no FCM device tokens');
});

it('records a failed push delivery even when mail still succeeds', function () {
    config()->set('pushservice.providers.fcm', true);
    config()->set('pushservice.providers.apns', false);
    config()->set('pushservice.providers.mail', true);
    Notification::fake();

    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create([
        'channels' => ['push', 'mail'],
    ]);

    (new ProcessPushNotification($notification))->handle();

    Notification::assertSentTo($user, WebhookPushNotification::class, function ($n, $channels) {
        return in_array('mail', $channels, true)
            && ! in_array(FcmChannel::class, $channels, true);
    });

    $pushFailure = NotificationDelivery::query()
        ->where('push_notification_id', $notification->id)
        ->where('channel', 'push')
        ->first();

    expect($pushFailure)->not->toBeNull()
        ->and($pushFailure->status)->toBe(NotificationDelivery::STATUS_FAILED)
        ->and($pushFailure->error)->toContain('no FCM device tokens');
});

it('records a delivery row per channel actually sent', function () {
    // Real send over the array mail transport so delivery events fire.
    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create(['channels' => ['mail']]);

    (new ProcessPushNotification($notification))->handle();

    $delivery = NotificationDelivery::where('push_notification_id', $notification->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->channel)->toBe('mail')
        ->and($delivery->status)->toBe(NotificationDelivery::STATUS_SENT)
        ->and($delivery->user_id)->toBe($user->id)
        ->and($delivery->sent_at)->not->toBeNull();
});

it('puts the notification in the inbox of a recipient who has no device tokens', function () {
    config()->set('pushservice.providers.fcm', true);
    config()->set('pushservice.providers.apns', true);
    Notification::fake();

    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create([
        'channels' => ['push'],
        'title' => 'Lunch order',
        'data' => ['sender_name' => 'Alex Reviewer'],
    ]);

    (new ProcessPushNotification($notification))->handle();

    $inbox = UserNotification::where('user_id', $user->id)->sole();

    expect($inbox)
        ->push_notification_id->toBe($notification->id)
        ->company_id->toBe($notification->company_id)
        ->title->toBe('Lunch order')
        ->channel->toBe(RecordUserInboxNotification::CHANNEL_INBOX)
        ->delivered_at->not->toBeNull()
        ->and($inbox->data)->toMatchArray(['sender_name' => 'Alex Reviewer']);
});

it('keeps one inbox row per recipient and fills in the channel once a provider delivers', function () {
    Notification::fake();

    $user = User::factory()->create();
    $notification = PushNotification::factory()->forUser($user)->create(['channels' => ['mail']]);

    (new ProcessPushNotification($notification))->handle();

    $outbound = new WebhookPushNotification(
        pushNotificationId: $notification->id,
        title: $notification->title,
        body: $notification->body,
        channels: ['mail'],
    );

    event(new NotificationSent($user, $outbound, 'mail'));

    expect(UserNotification::where('user_id', $user->id)->sole()->channel)->toBe('mail');
});
