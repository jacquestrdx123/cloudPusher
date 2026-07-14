<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizePushNotificationStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 10, 15, 30, 60];

    public function __construct(public PushNotification $pushNotification) {}

    /**
     * Aggregate delivery outcomes and set the parent notification status.
     */
    public function handle(): void
    {
        $notification = $this->pushNotification->fresh();

        if ($notification === null) {
            return;
        }

        if (! in_array($notification->status, [
            PushNotification::STATUS_PROCESSING,
        ], true)) {
            return;
        }

        $sentCount = $notification->deliveries()
            ->where('status', NotificationDelivery::STATUS_SENT)
            ->count();

        $failedCount = $notification->deliveries()
            ->where('status', NotificationDelivery::STATUS_FAILED)
            ->count();

        $totalDeliveries = $sentCount + $failedCount;

        if ($notification->recipients_count > 0 && $totalDeliveries === 0 && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);

            return;
        }

        $notification->update([
            'status' => $this->resolveStatus($notification, $sentCount, $failedCount),
        ]);
    }

    private function resolveStatus(PushNotification $notification, int $sentCount, int $failedCount): string
    {
        if ($notification->recipients_count === 0) {
            return PushNotification::STATUS_SENT;
        }

        if ($sentCount === 0 && $failedCount === 0) {
            return PushNotification::STATUS_FAILED;
        }

        if ($failedCount > 0 && $sentCount > 0) {
            return PushNotification::STATUS_PARTIAL;
        }

        if ($failedCount > 0) {
            return PushNotification::STATUS_FAILED;
        }

        return PushNotification::STATUS_SENT;
    }
}
