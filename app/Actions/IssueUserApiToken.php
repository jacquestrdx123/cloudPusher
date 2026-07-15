<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserApiToken;

class IssueUserApiToken
{
    /**
     * @return array{token: UserApiToken, plain_text_token: string}
     */
    public function handle(User $user, string $name = 'mobile'): array
    {
        $days = (int) config('pushservice.auth.token_ttl_days', 90);

        return UserApiToken::issue(
            $user,
            $name,
            $days > 0 ? now()->addDays($days) : null,
        );
    }
}
