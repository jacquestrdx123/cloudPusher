<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\User;
use InvalidArgumentException;

class RegisterDeviceToken
{
    /**
     * @param  array{
     *     user: array{id?: int|null, email?: string|null, phone?: string|null},
     *     platform: string,
     *     token: string,
     *     name?: string|null
     * }  $payload
     */
    public function handle(Company $company, array $payload): DeviceToken
    {
        $user = $this->resolveUser($company, $payload['user']);

        if ($user === null) {
            throw new InvalidArgumentException('The requested user does not exist for this company.');
        }

        return $this->upsert($user, $payload);
    }

    /**
     * @param  array{
     *     platform: string,
     *     token: string,
     *     name?: string|null
     * }  $payload
     */
    public function handleForUser(User $user, array $payload): DeviceToken
    {
        return $this->upsert($user, $payload);
    }

    /**
     * @param  array{platform: string, token: string, name?: string|null}  $payload
     */
    private function upsert(User $user, array $payload): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            [
                'platform' => $payload['platform'],
                'token' => $payload['token'],
            ],
            [
                'user_id' => $user->id,
                'name' => $payload['name'] ?? null,
                'last_used_at' => now(),
            ],
        );
    }

    /**
     * @param  array{id?: int|null, email?: string|null, phone?: string|null}  $userRef
     */
    private function resolveUser(Company $company, array $userRef): ?User
    {
        return $company->users()
            ->when(isset($userRef['id']), fn ($query) => $query->whereKey($userRef['id']))
            ->when(isset($userRef['email']), fn ($query) => $query->where('email', $userRef['email']))
            ->when(isset($userRef['phone']), fn ($query) => $query->where('phone', $userRef['phone']))
            ->first();
    }
}
