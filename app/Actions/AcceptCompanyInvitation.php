<?php

namespace App\Actions;

use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptCompanyInvitation
{
    /**
     * Set the invited user's password and mark the invitation as used.
     */
    public function handle(CompanyInvitation $invitation, string $password): User
    {
        if (! $invitation->isUsable()) {
            throw ValidationException::withMessages([
                'password' => ['This invitation link is no longer valid. Ask your company admin to resend it.'],
            ]);
        }

        return DB::transaction(function () use ($invitation, $password): User {
            $user = $invitation->user;
            $user->update(['password' => $password]);

            $invitation->update([
                'accepted_at' => now(),
                'token_hash' => CompanyInvitation::hashToken(CompanyInvitation::generateToken()),
            ]);

            CompanyInvitation::query()
                ->pending()
                ->where('user_id', $user->id)
                ->whereKeyNot($invitation->id)
                ->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
