<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use App\Notifications\WebhookPushNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Fcm\FcmChannel;

class RecordNotificationDelivery
{
    /**
     * Map the internal channel identifier to a stored delivery label.
     */
    private const array CHANNEL_LABELS = [
        FcmChannel::class => 'fcm',
        ApnChannel::class => 'apns',
        'mail' => 'mail',
        'vonage' => 'sms',
    ];

    /**
     * Record a delivery row whenever one of our webhook notifications is
     * sent or fails on a given channel.
     */
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        if (! $event->notification instanceof WebhookPushNotification) {
            return;
        }

        $failed = $event instanceof NotificationFailed;

        NotificationDelivery::create([
            'push_notification_id' => $event->notification->pushNotificationId,
            'user_id' => $event->notifiable->getKey(),
            'channel' => self::CHANNEL_LABELS[$event->channel] ?? $event->channel,
            'status' => $failed ? NotificationDelivery::STATUS_FAILED : NotificationDelivery::STATUS_SENT,
            'error' => $failed ? $this->errorMessage($event) : null,
            'sent_at' => $failed ? null : now(),
        ]);
    }

    private function errorMessage(NotificationFailed $event): string
    {
        return Str::limit(json_encode($event->data) ?: 'Delivery failed.', 1000);
    }
}
