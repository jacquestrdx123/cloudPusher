<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;

class ResolveNotificationTarget
{
    /**
     * @param  array{type: string, id?: int|null, email?: string|null, slug?: string|null}  $target
     */
    public function handle(Company $company, array $target): User|UserGroup|null
    {
        return $target['type'] === PushNotification::TARGET_USER
            ? $this->resolveUser($company, $target)
            : $this->resolveGroup($company, $target);
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function resolveUser(Company $company, array $target): ?User
    {
        return $company->users()
            ->when(isset($target['id']), fn ($query) => $query->whereKey($target['id']))
            ->when(isset($target['email']), fn ($query) => $query->where('email', $target['email']))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function resolveGroup(Company $company, array $target): ?UserGroup
    {
        return $company->groups()
            ->when(isset($target['id']), fn ($query) => $query->whereKey($target['id']))
            ->when(isset($target['slug']), fn ($query) => $query->where('slug', $target['slug']))
            ->first();
    }
}
