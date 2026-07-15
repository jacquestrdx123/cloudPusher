<?php

namespace App\Actions;

use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\WebhookPushNotification;
use Illuminate\Support\Facades\Log;

class RecordUndeliverableNotification
{
    /**
     * Persist failed delivery rows (and a log line) when a recipient has no
     * usable channel — Laravel would otherwise send nothing silently.
     */
    public function handle(
        PushNotification $pushNotification,
        User $user,
        WebhookPushNotification $notification,
    ): void {
        $attempts = $notification->undeliverableAttempts($user);

        if ($attempts === []) {
            return;
        }

        foreach ($attempts as $attempt) {
            NotificationDelivery::query()->create([
                'push_notification_id' => $pushNotification->id,
                'user_id' => $user->id,
                'channel' => $attempt['channel'],
                'status' => NotificationDelivery::STATUS_FAILED,
                'error' => $attempt['error'],
                'sent_at' => null,
            ]);
        }

        Log::warning('Push notification recipient had no deliverable channels.', [
            'push_notification_id' => $pushNotification->id,
            'user_id' => $user->id,
            'requested_channels' => $notification->channels,
            'errors' => array_column($attempts, 'error'),
        ]);
    }
}
