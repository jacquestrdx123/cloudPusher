<?php

namespace App\Actions;

use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class DispatchPushNotification
{
    public function __construct(private ResolveNotificationTarget $resolveNotificationTarget) {}

    /**
     * @param  array{
     *     target: array{type: string, id?: int|null, email?: string|null, slug?: string|null},
     *     title: string,
     *     body?: string|null,
     *     data?: array<string, mixed>|null,
     *     channels?: array<int, string>|null,
     *     scheduled_at?: string|null
     * }  $payload
     */
    public function handle(Company $company, array $payload): PushNotification
    {
        $target = $payload['target'];
        $userId = null;
        $groupId = null;

        if ($target['type'] !== PushNotification::TARGET_BROADCAST) {
            $resolved = $this->resolveNotificationTarget->handle($company, $target);

            if ($resolved === null) {
                throw new InvalidArgumentException('The requested target does not exist for this company.');
            }

            $userId = $resolved instanceof User ? $resolved->id : null;
            $groupId = $resolved instanceof UserGroup ? $resolved->id : null;
        }

        $channels = $payload['channels'] ?? null;
        $channels = $channels ?: $company->resolvedDefaultChannels();

        $scheduledAt = isset($payload['scheduled_at'])
            ? Carbon::parse($payload['scheduled_at'])
            : null;

        $isScheduled = $scheduledAt !== null && $scheduledAt->isFuture();

        $notification = PushNotification::create([
            'company_id' => $company->id,
            'target_type' => $target['type'],
            'user_id' => $userId,
            'user_group_id' => $groupId,
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'data' => $payload['data'] ?? [],
            'channels' => array_values(array_unique($channels)),
            'status' => $isScheduled ? PushNotification::STATUS_SCHEDULED : PushNotification::STATUS_PENDING,
            'scheduled_at' => $scheduledAt,
        ]);

        $dispatch = ProcessPushNotification::dispatch($notification);

        if ($isScheduled) {
            $dispatch->delay($scheduledAt);
        }

        return $notification;
    }
}
