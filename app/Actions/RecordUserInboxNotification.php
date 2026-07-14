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
     * Persist a user-facing inbox row when a notification is successfully delivered.
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
        }

        $inbox->save();

        return $inbox;
    }
}
