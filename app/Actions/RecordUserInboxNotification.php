<?php

namespace App\Actions;

use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\WebhookPushNotification;
use Illuminate\Support\Carbon;

class RecordUserInboxNotification
{
    /**
     * Channel stored while no provider has confirmed delivery yet.
     */
    public const string CHANNEL_INBOX = 'inbox';

    /**
     * Persist a user-facing inbox row. Every recipient gets one when the notification is
     * fanned out, so it is readable in the app even if no push reaches the device; the
     * channel is filled in once a provider delivers it.
     */
    public function handle(
        WebhookPushNotification $notification,
        User $user,
        string $channel,
    ): UserNotification {
        $pushNotification = PushNotification::query()->findOrFail($notification->pushNotificationId);

        $inbox = UserNotification::query()->firstOrNew([
            'user_id' => $user->id,
            'push_notification_id' => $pushNotification->id,
        ]);

        if (! $inbox->exists) {
            $inbox->company_id = $pushNotification->company_id;
            $inbox->title = $notification->title;
            $inbox->body = $notification->body;
            $inbox->data = $notification->data;
            $inbox->channel = $channel;
            $inbox->delivered_at = Carbon::now();
        } elseif ($inbox->channel === self::CHANNEL_INBOX) {
            $inbox->channel = $channel;
        }

        $inbox->save();

        return $inbox;
    }
}
