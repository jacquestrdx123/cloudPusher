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
    ]);
    expect($array['data']['sound'])->not->toBeEmpty();

    // Native Android still receives a tray notification via the android config.
    expect($array['android']['notification'])->toMatchArray([
        'title' => 'Hello',
        'body' => 'World',
    ]);
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
