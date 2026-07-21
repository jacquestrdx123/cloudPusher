<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RemoveCompanyMember
{
    public function handle(Company $company, User $actor, User $member): void
    {
        if (! $actor->canAdministerCompany($company)) {
            throw ValidationException::withMessages([
                'member' => ['Only company admins can remove members.'],
            ]);
        }

        if ((int) $actor->id === (int) $member->id) {
            throw ValidationException::withMessages([
                'member' => ['You cannot remove yourself from the company.'],
            ]);
        }

        if (! $member->belongsToCompany($company)) {
            throw ValidationException::withMessages([
                'member' => ['This user is not a member of the company.'],
            ]);
        }

        $member->companies()->detach($company->id);
    }
}
