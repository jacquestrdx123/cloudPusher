<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

class InviteCompanyMember
{
    /**
     * @param  array{phone?: string|null, email?: string|null}  $data
     * @return array{user: User, created: bool}
     */
    public function handle(Company $company, User $actor, array $data): array
    {
        if (! $actor->canAdministerCompany($company)) {
            throw ValidationException::withMessages([
                'member' => ['Only company admins can add members.'],
            ]);
        }

        $phone = isset($data['phone']) && filled($data['phone'])
            ? PhoneNumber::normalize((string) $data['phone'])
            : null;
        $email = isset($data['email']) && filled($data['email'])
            ? strtolower(trim((string) $data['email']))
            : null;

        if ($phone === null && $email === null) {
            throw ValidationException::withMessages([
                'phone' => ['Provide a phone number or email of an existing user.'],
            ]);
        }

        $user = User::query()
            ->when($phone !== null, fn ($query) => $query->where('phone', $phone))
            ->when($email !== null, fn ($query) => $query->where('email', $email))
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'phone' => ['No user found with that phone or email. They must register first.'],
            ]);
        }

        if ($user->belongsToCompany($company)) {
            return [
                'user' => $user->load(['companies' => fn ($query) => $query->whereKey($company->getKey())]),
                'created' => false,
            ];
        }

        $user->companies()->syncWithoutDetaching([
            $company->id => ['is_company_admin' => false],
        ]);

        return [
            'user' => $user->load(['companies' => fn ($query) => $query->whereKey($company->getKey())]),
            'created' => true,
        ];
    }
}
