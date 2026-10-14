<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\InviteNewCompanyUser;
use App\Actions\ResendCompanyInvitation;
use App\Actions\RevokeCompanyInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanyInvitationRequest;
use App\Http\Resources\Api\CompanyInvitationResource;
use App\Http\Resources\Api\CompanyMemberResource;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CompanyInvitationController extends Controller
{
    /**
     * List pending invitations for the company (company admin).
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $this->authorizeCompanyAdmin($request, $company);

        $invitations = $company->invitations()
            ->pending()
            ->with([
                'invitedBy',
                'user.companies' => fn ($query) => $query->whereKey($company->getKey()),
            ])
            ->latest()
            ->get()
            ->each(fn (CompanyInvitation $invitation) => $invitation->setRelation('company', $company));

        return CompanyInvitationResource::collection($invitations);
    }

    /**
     * Add a person to the company, inviting them by e-mail/SMS when they have no account yet.
     */
    public function store(
        StoreCompanyInvitationRequest $request,
        Company $company,
        InviteNewCompanyUser $inviteNewCompanyUser,
    ): JsonResponse {
        $actor = $this->authorizeCompanyAdmin($request, $company);

        $result = $inviteNewCompanyUser->handle($company, $actor, $request->payload());

        return response()->json([
            'data' => [
                'status' => $result['status'],
                'member' => new CompanyMemberResource($result['user']),
            ],
        ], $result['status'] === InviteNewCompanyUser::STATUS_INVITED ? 201 : 200);
    }

    /**
     * Send a pending invitation again with a fresh link (company admin).
     */
    public function resend(
        Request $request,
        Company $company,
        CompanyInvitation $invitation,
        ResendCompanyInvitation $resendCompanyInvitation,
    ): CompanyInvitationResource {
        $actor = $this->authorizeCompanyAdmin($request, $company);
        $this->ensureInvitationBelongsToCompany($invitation, $company);

        $invitation = $resendCompanyInvitation->handle($invitation, $actor);
        $invitation->setRelation('company', $company);

        return new CompanyInvitationResource($invitation->load([
            'invitedBy',
            'user.companies' => fn ($query) => $query->whereKey($company->getKey()),
        ]));
    }

    /**
     * Revoke a pending invitation and remove the person from the company (company admin).
     */
    public function destroy(
        Request $request,
        Company $company,
        CompanyInvitation $invitation,
        RevokeCompanyInvitation $revokeCompanyInvitation,
    ): Response {
        $this->authorizeCompanyAdmin($request, $company);
        $this->ensureInvitationBelongsToCompany($invitation, $company);

        $revokeCompanyInvitation->handle($invitation);

        return response()->noContent();
    }

    private function authorizeCompanyAdmin(Request $request, Company $company): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->canAdministerCompany($company)) {
            abort(403, 'Only company admins can manage invitations.');
        }

        return $user;
    }

    private function ensureInvitationBelongsToCompany(CompanyInvitation $invitation, Company $company): void
    {
        if ((int) $invitation->company_id !== (int) $company->id) {
            abort(404, 'Invitation not found.');
        }
    }
}
