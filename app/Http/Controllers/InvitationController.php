<?php

namespace App\Http\Controllers;

use App\Actions\AcceptCompanyInvitation;
use App\Http\Requests\AcceptCompanyInvitationRequest;
use App\Models\CompanyInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public const string STATE_FORM = 'form';

    public const string STATE_INVALID = 'invalid';

    public const string STATE_EXPIRED = 'expired';

    public const string STATE_ACCEPTED = 'accepted';

    /**
     * Show the set-password form for an invitation link.
     */
    public function show(string $token): View
    {
        $invitation = CompanyInvitation::findByPlainToken($token);

        return view('invitation', [
            'state' => $this->stateFor($invitation),
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    /**
     * Set the invited user's password.
     */
    public function accept(
        AcceptCompanyInvitationRequest $request,
        string $token,
        AcceptCompanyInvitation $acceptCompanyInvitation,
    ): RedirectResponse|View {
        $invitation = CompanyInvitation::findByPlainToken($token);
        $state = $this->stateFor($invitation);

        if ($invitation === null || $state !== self::STATE_FORM) {
            return view('invitation', [
                'state' => $state,
                'invitation' => $invitation,
                'token' => $token,
            ]);
        }

        $user = $acceptCompanyInvitation->handle($invitation, $request->string('password')->toString());

        return redirect()
            ->route('invitation.accepted')
            ->with('invitation_accepted', [
                'name' => $user->name,
                'company' => $invitation->company->name,
                'phone' => $user->phone,
            ]);
    }

    /**
     * Confirmation shown after the password has been set.
     */
    public function accepted(): View
    {
        return view('invitation', [
            'state' => self::STATE_ACCEPTED,
            'invitation' => null,
            'token' => null,
        ]);
    }

    private function stateFor(?CompanyInvitation $invitation): string
    {
        if ($invitation === null || ! $invitation->isPending()) {
            return self::STATE_INVALID;
        }

        if ($invitation->isExpired()) {
            return self::STATE_EXPIRED;
        }

        return self::STATE_FORM;
    }
}
