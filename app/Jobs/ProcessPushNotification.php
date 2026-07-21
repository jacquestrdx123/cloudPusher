<?php

namespace App\Jobs;

use App\Actions\RecordUndeliverableNotification;
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
        $recordUndeliverable = app(RecordUndeliverableNotification::class);
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

        $outbound = new WebhookPushNotification(
            pushNotificationId: $notification->id,
            title: $notification->title,
            body: $notification->body,
            channels: $notification->channels,
            data: $notification->data ?? [],
        );

        $deliverable = new Collection;

        foreach ($recipients as $user) {
            // Always record skipped push/mail/sms channels, even when another
            // channel (e.g. mail) still delivers — otherwise push fails silently.
            $recordUndeliverable->handle($notification, $user, $outbound);

            if ($outbound->via($user) !== []) {
                $deliverable->push($user);
            }
        }

        if ($deliverable->isNotEmpty()) {
            Notification::send($deliverable, $outbound);

            FinalizePushNotificationStatus::dispatch($notification)
                ->delay(now()->addSeconds((int) config('pushservice.finalize_delay_seconds', 10)));

            return;
        }

        FinalizePushNotificationStatus::dispatch($notification);
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
                ->membersOf($notification->company)
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
