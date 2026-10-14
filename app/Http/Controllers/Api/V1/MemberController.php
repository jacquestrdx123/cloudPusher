<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\InviteCompanyMember;
use App\Actions\RemoveCompanyMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InviteCompanyMemberRequest;
use App\Http\Resources\Api\CompanyMemberResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MemberController extends Controller
{
    /**
     * List company members so any member can pick message recipients.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $search = trim($request->string('search')->toString());

        $members = User::query()
            ->membersOf($company)
            ->with(['companies' => fn ($query) => $query->whereKey($company->getKey())])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $matches) => $matches
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 100));

        return CompanyMemberResource::collection($members);
    }

    /**
     * Add an existing user to the company (company admin).
     */
    public function store(
        InviteCompanyMemberRequest $request,
        Company $company,
        InviteCompanyMember $inviteCompanyMember,
    ): JsonResponse {
        $this->authorizeCompanyAdmin($request, $company);

        /** @var User $actor */
        $actor = $request->user();

        $result = $inviteCompanyMember->handle($company, $actor, $request->validated());

        return (new CompanyMemberResource($result['user']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    /**
     * Remove a member from the company (company admin).
     */
    public function destroy(
        Request $request,
        Company $company,
        User $user,
        RemoveCompanyMember $removeCompanyMember,
    ): Response {
        $this->authorizeCompanyAdmin($request, $company);

        /** @var User $actor */
        $actor = $request->user();

        $removeCompanyMember->handle($company, $actor, $user);

        return response()->noContent();
    }

    private function authorizeCompanyAdmin(Request $request, Company $company): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->canAdministerCompany($company)) {
            abort(403, 'Only company admins can manage members.');
        }
    }
}
