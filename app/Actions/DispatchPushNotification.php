<?php

namespace App\Actions;

use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;
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
     *     channels?: array<int, string>|null
     * }  $payload
     */
    public function handle(Company $company, array $payload): PushNotification
    {
        $target = $payload['target'];
        $resolved = $this->resolveNotificationTarget->handle($company, $target);

        if ($resolved === null) {
            throw new InvalidArgumentException('The requested target does not exist for this company.');
        }

        $channels = $payload['channels'] ?? null;
        $channels = $channels ?: $company->resolvedDefaultChannels();

        $notification = PushNotification::create([
            'company_id' => $company->id,
            'target_type' => $target['type'],
            'user_id' => $resolved instanceof User ? $resolved->id : null,
            'user_group_id' => $resolved instanceof UserGroup ? $resolved->id : null,
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'data' => $payload['data'] ?? [],
            'channels' => array_values(array_unique($channels)),
        ]);

        ProcessPushNotification::dispatch($notification);

        return $notification;
    }
}
