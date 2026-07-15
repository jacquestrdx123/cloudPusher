<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApproveUserRegistration;
use App\Actions\RegisterCompanyUser;
use App\Actions\RejectUserRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterCompanyUserRequest;
use App\Http\Resources\Api\UserRegistrationResource;
use App\Models\Company;
use App\Models\User;
use App\Models\UserRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegistrationController extends Controller
{
    /**
     * Submit a registration request for a company (public).
     */
    public function store(
        RegisterCompanyUserRequest $request,
        Company $company,
        RegisterCompanyUser $registerCompanyUser,
    ): JsonResponse {
        $registration = $registerCompanyUser->handle($company, $request->validated());

        return (new UserRegistrationResource($registration->load('company')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * List registration requests for the company (company admin).
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $this->authorizeCompanyAdmin($request, $company);

        $registrations = UserRegistration::query()
            ->where('company_id', $company->id)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return UserRegistrationResource::collection($registrations);
    }

    /**
     * Approve a pending registration (company admin).
     */
    public function approve(
        Request $request,
        Company $company,
        UserRegistration $registration,
        ApproveUserRegistration $approveUserRegistration,
    ): UserRegistrationResource {
        $this->authorizeCompanyAdmin($request, $company);
        $this->ensureRegistrationBelongsToCompany($registration, $company);

        /** @var User $reviewer */
        $reviewer = $request->user();

        $approveUserRegistration->handle(
            $registration,
            $reviewer,
            $request->string('notes')->toString() ?: null,
        );

        return new UserRegistrationResource($registration->fresh()->load('company'));
    }

    /**
     * Reject a pending registration (company admin).
     */
    public function reject(
        Request $request,
        Company $company,
        UserRegistration $registration,
        RejectUserRegistration $rejectUserRegistration,
    ): UserRegistrationResource {
        $this->authorizeCompanyAdmin($request, $company);
        $this->ensureRegistrationBelongsToCompany($registration, $company);

        /** @var User $reviewer */
        $reviewer = $request->user();

        $rejectUserRegistration->handle(
            $registration,
            $reviewer,
            $request->string('notes')->toString() ?: null,
        );

        return new UserRegistrationResource($registration->fresh()->load('company'));
    }

    private function authorizeCompanyAdmin(Request $request, Company $company): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->canAdministerCompany($company)) {
            abort(403, 'Only company admins can manage registrations.');
        }
    }

    private function ensureRegistrationBelongsToCompany(UserRegistration $registration, Company $company): void
    {
        if ((int) $registration->company_id !== (int) $company->id) {
            abort(404, 'Registration not found.');
        }
    }
}
