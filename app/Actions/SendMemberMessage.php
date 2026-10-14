<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SendMemberMessage
{
    public const string RECIPIENTS_USERS = 'users';

    public const string RECIPIENTS_GROUP = 'group';

    public const string RECIPIENTS_BROADCAST = 'broadcast';

    public function __construct(private DispatchPushNotification $dispatchPushNotification) {}

    /**
     * Send a push message from one company member to other members, a group, or everyone.
     *
     * Selected users each get their own PushNotification so delivery status stays per recipient.
     *
     * @param  array{
     *     title: string,
     *     body: string|null,
     *     recipients: array{type: string, user_ids?: array<int, int>, group_id?: int}
     * }  $message
     * @return array<int, PushNotification>
     */
    public function handle(Company $company, User $sender, array $message): array
    {
        $targets = $this->targets($company, $message['recipients']);

        return array_map(
            fn (array $target): PushNotification => $this->dispatchPushNotification->handle(
                $company,
                [
                    'target' => $target,
                    'title' => $message['title'],
                    'body' => $message['body'],
                    'channels' => ['push'],
                    'data' => [
                        'sender_id' => $sender->id,
                        'sender_name' => $sender->name,
                    ],
                ],
                $sender,
            ),
            $targets,
        );
    }

    /**
     * @param  array{type: string, user_ids?: array<int, int>, group_id?: int}  $recipients
     * @return array<int, array{type: string, id?: int}>
     */
    private function targets(Company $company, array $recipients): array
    {
        if ($recipients['type'] === self::RECIPIENTS_BROADCAST) {
            return [['type' => PushNotification::TARGET_BROADCAST]];
        }

        if ($recipients['type'] === self::RECIPIENTS_GROUP) {
            $groupId = (int) ($recipients['group_id'] ?? 0);

            if (! $company->groups()->whereKey($groupId)->exists()) {
                throw ValidationException::withMessages([
                    'recipients.group_id' => ['The selected group does not belong to this company.'],
                ]);
            }

            return [['type' => PushNotification::TARGET_GROUP, 'id' => $groupId]];
        }

        $userIds = array_values(array_unique($recipients['user_ids'] ?? []));

        $memberIds = User::query()
            ->membersOf($company)
            ->whereKey($userIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (count($memberIds) !== count($userIds)) {
            throw ValidationException::withMessages([
                'recipients.user_ids' => ['One or more selected people are not members of this company.'],
            ]);
        }

        return array_map(
            fn (int $userId): array => ['type' => PushNotification::TARGET_USER, 'id' => $userId],
            $userIds,
        );
    }
}
