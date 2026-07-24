<?php

namespace App\Actions;

use App\Models\NotificationDelivery;
use App\Models\UserNotification;

class MarkNotificationDelivered
{
    /**
     * Record that the user opened a notification in the app (client delivery receipt).
     *
     * Provider acceptance remains `sent`; app-open confirmation promotes matching
     * push channel rows to `delivered`.
     */
    public function handle(UserNotification $inbox): int
    {
        return NotificationDelivery::query()
            ->where('push_notification_id', $inbox->push_notification_id)
            ->where('user_id', $inbox->user_id)
            ->whereIn('channel', NotificationDelivery::PUSH_CHANNELS)
            ->where('status', NotificationDelivery::STATUS_SENT)
            ->whereNull('delivered_at')
            ->update([
                'status' => NotificationDelivery::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);
    }
}
