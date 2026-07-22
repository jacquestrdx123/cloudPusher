<?php

use App\Notifications\WebhookPushNotification;

it('builds a data-only fcm message so web clients do not double-display', function () {
    $notification = new WebhookPushNotification(
        pushNotificationId: 42,
        title: 'Hello',
        body: 'World',
        channels: ['push'],
        data: ['foo' => 'bar'],
    );

    $array = $notification->toFcm(new stdClass)->toArray();

    // No top-level notification block: a web (FCM webpush) browser would
    // auto-display it in addition to our service worker, showing it twice.
    expect($array)->not->toHaveKey('notification');

    // Title/body/metadata travel in the data payload for the web client to render.
    expect($array['data'])->toMatchArray([
        'title' => 'Hello',
        'body' => 'World',
        'push_notification_id' => '42',
        'foo' => 'bar',
        'image' => '',
    ]);
    expect($array['data']['sound'])->not->toBeEmpty();

    // Native Android still receives a tray notification via the android config.
    expect($array['android']['notification'])->toMatchArray([
        'title' => 'Hello',
        'body' => 'World',
    ]);
    expect($array['android']['priority'])->toBe('high');
    expect($array['android']['notification']['channel_id'])->not->toBeEmpty();
});

it('stringifies a null body in both the data payload and android config', function () {
    $notification = new WebhookPushNotification(
        pushNotificationId: 7,
        title: 'No body',
        body: null,
        channels: ['push'],
    );

    $array = $notification->toFcm(new stdClass)->toArray();

    expect($array['data']['body'])->toBe('')
        ->and($array['android']['notification']['body'])->toBe('');
});

it('includes rich media fields on the hybrid fcm payload', function () {
    $notification = new WebhookPushNotification(
        pushNotificationId: 99,
        title: 'Sale',
        body: 'Today only',
        channels: ['push'],
        imageUrl: 'https://cdn.example.com/promo.jpg',
        androidChannelId: 'rich_messages_v1',
    );

    $array = $notification->toFcm(new stdClass)->toArray();

    expect($array)->not->toHaveKey('notification')
        ->and($array['data']['image'])->toBe('https://cdn.example.com/promo.jpg')
        ->and($array['android']['notification'])->toMatchArray([
            'image' => 'https://cdn.example.com/promo.jpg',
            'channel_id' => 'rich_messages_v1',
            'visibility' => 'PUBLIC',
        ]);
});

it('builds an apns message with mutable content and media_url', function () {
    $notification = new WebhookPushNotification(
        pushNotificationId: 15,
        title: 'Rich',
        body: 'With image',
        channels: ['push'],
        data: ['foo' => 'bar'],
        imageUrl: 'https://cdn.example.com/hero.jpg',
        sound: 'chime.caf',
        category: 'RICH_MESSAGE',
    );

    $message = $notification->toApn(new stdClass);

    expect($message->title)->toBe('Rich')
        ->and($message->body)->toBe('With image')
        ->and($message->sound)->toBe('chime.caf')
        ->and($message->category)->toBe('RICH_MESSAGE')
        ->and($message->mutableContent)->toBe(1)
        ->and($message->custom)->toMatchArray([
            'push_notification_id' => '15',
            'media_url' => 'https://cdn.example.com/hero.jpg',
            'media_type' => 'image',
            'foo' => 'bar',
        ]);
});

it('omits media customs on apns when no image_url is set', function () {
    $notification = new WebhookPushNotification(
        pushNotificationId: 3,
        title: 'Plain',
        body: 'Text only',
        channels: ['push'],
    );

    $message = $notification->toApn(new stdClass);

    expect($message->mutableContent)->toBe(1)
        ->and($message->custom)->toHaveKey('push_notification_id')
        ->and($message->custom)->not->toHaveKey('media_url')
        ->and($message->custom)->not->toHaveKey('media_type');
});
