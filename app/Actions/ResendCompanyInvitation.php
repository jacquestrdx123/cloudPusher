<?php

namespace App\Actions;

use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ResendCompanyInvitation
{
    public function __construct(private SendCompanyInvitation $sendCompanyInvitation) {}

    /**
     * Rotate the token, extend the expiry, and send the invitation again.
     */
    public function handle(CompanyInvitation $invitation, User $actor): CompanyInvitation
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => ['Only pending invitations can be resent.'],
            ]);
        }

        return $this->sendCompanyInvitation->handle(
            $invitation->company,
            $invitation->user,
            $actor,
            $invitation,
        );
    }
}
