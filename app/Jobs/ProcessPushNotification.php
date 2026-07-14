<?php

namespace App\Jobs;

use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\WebhookPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class ProcessPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public PushNotification $pushNotification)
    {
        $this->onQueue(config('pushservice.queue'));
    }

    /**
     * Resolve the target recipients and fan out the notification.
     */
    public function handle(): void
    {
        $notification = $this->pushNotification;
        $notification->update(['status' => PushNotification::STATUS_PROCESSING]);

        $recipients = $this->recipients();

        $notification->update([
            'recipients_count' => $recipients->count(),
        ]);

        if ($recipients->isEmpty()) {
            FinalizePushNotificationStatus::dispatch($notification);

            return;
        }

        Notification::send($recipients, new WebhookPushNotification(
            pushNotificationId: $notification->id,
            title: $notification->title,
            body: $notification->body,
            channels: $notification->channels,
            data: $notification->data ?? [],
        ));

        FinalizePushNotificationStatus::dispatch($notification)
            ->delay(now()->addSeconds((int) config('pushservice.finalize_delay_seconds', 10)));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(): Collection
    {
        $notification = $this->pushNotification;

        if ($notification->target_type === PushNotification::TARGET_BROADCAST) {
            return User::query()
                ->with('deviceTokens')
                ->where('company_id', $notification->company_id)
                ->get();
        }

        if ($notification->target_type === PushNotification::TARGET_USER) {
            $user = User::with('deviceTokens')->find($notification->user_id);

            return $user ? new Collection([$user]) : new Collection;
        }

        $group = $notification->group()->with('users.deviceTokens')->first();

        return $group ? $group->users : new Collection;
    }
}
