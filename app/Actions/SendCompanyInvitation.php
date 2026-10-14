<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;

class SendCompanyInvitation
{
    /**
     * Issue a fresh invitation token for the user and notify them with the set-password link.
     */
    public function handle(Company $company, User $user, ?User $inviter, ?CompanyInvitation $invitation = null): CompanyInvitation
    {
        $plainTextToken = CompanyInvitation::generateToken();
        $attributes = [
            'token_hash' => CompanyInvitation::hashToken($plainTextToken),
            'expires_at' => CompanyInvitation::defaultExpiry(),
        ];

        if ($invitation instanceof CompanyInvitation) {
            $invitation->update($attributes);
        } else {
            $invitation = CompanyInvitation::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'invited_by_user_id' => $inviter?->id,
                ...$attributes,
            ]);
        }

        $user->notify(new CompanyInvitationNotification(
            companyName: $company->name,
            inviterName: $inviter?->name,
            acceptUrl: route('invitation.show', $plainTextToken),
            expiresAt: $invitation->expires_at,
        ));

        return $invitation;
    }
}
