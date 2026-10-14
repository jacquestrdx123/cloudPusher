<?php

namespace App\Actions;

use App\Models\CompanyInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeCompanyInvitation
{
    /**
     * Cancel a pending invitation and remove the invited person from the company.
     *
     * The account itself is deleted when it was never activated and has no other companies.
     */
    public function handle(CompanyInvitation $invitation): void
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => ['Only pending invitations can be revoked.'],
            ]);
        }

        DB::transaction(function () use ($invitation): void {
            $invitation->update(['revoked_at' => now()]);

            $user = $invitation->user;
            $user->companies()->detach($invitation->company_id);

            $hasAcceptedInvitation = $user->invitations()->whereNotNull('accepted_at')->exists();

            if (! $hasAcceptedInvitation && ! $user->companies()->exists()) {
                $user->delete();
            }
        });
    }
}
