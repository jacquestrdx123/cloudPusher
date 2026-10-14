<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteNewCompanyUser
{
    public const string STATUS_INVITED = 'invited';

    public const string STATUS_ADDED = 'added';

    public function __construct(private SendCompanyInvitation $sendCompanyInvitation) {}

    /**
     * Add a person to the company, creating and inviting them when they have no account yet.
     *
     * @param  array{name: string, email: string, phone: string, is_company_admin?: bool}  $data
     * @return array{status: string, user: User, invitation: CompanyInvitation|null}
     */
    public function handle(Company $company, User $actor, array $data): array
    {
        if (! $actor->canAdministerCompany($company)) {
            throw ValidationException::withMessages([
                'member' => ['Only company admins can add members.'],
            ]);
        }

        $phone = PhoneNumber::normalize($data['phone']);
        $email = strtolower(trim($data['email']));
        $isCompanyAdmin = (bool) ($data['is_company_admin'] ?? false);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid mobile number.'],
            ]);
        }

        $existing = $this->existingUser($phone, $email);

        if ($existing instanceof User) {
            $this->attach($company, $existing, $isCompanyAdmin);

            return [
                'status' => self::STATUS_ADDED,
                'user' => $this->withMembership($existing, $company),
                'invitation' => null,
            ];
        }

        return DB::transaction(function () use ($company, $actor, $data, $phone, $email, $isCompanyAdmin): array {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make(Str::random(48)),
                'is_admin' => false,
            ]);

            $this->attach($company, $user, $isCompanyAdmin);

            $invitation = $this->sendCompanyInvitation->handle($company, $user, $actor);

            return [
                'status' => self::STATUS_INVITED,
                'user' => $this->withMembership($user, $company),
                'invitation' => $invitation,
            ];
        });
    }

    private function existingUser(string $phone, string $email): ?User
    {
        $byPhone = User::query()->where('phone', $phone)->first();
        $byEmail = User::query()->where('email', $email)->first();

        if ($byPhone instanceof User && $byEmail instanceof User && $byPhone->isNot($byEmail)) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number and e-mail address belong to two different existing users.'],
            ]);
        }

        return $byPhone ?? $byEmail;
    }

    private function attach(Company $company, User $user, bool $isCompanyAdmin): void
    {
        if ($user->belongsToCompany($company)) {
            if ($isCompanyAdmin) {
                $user->companies()->updateExistingPivot($company->id, ['is_company_admin' => true]);
            }

            return;
        }

        $user->companies()->attach($company->id, ['is_company_admin' => $isCompanyAdmin]);
    }

    private function withMembership(User $user, Company $company): User
    {
        return $user->load(['companies' => fn ($query) => $query->whereKey($company->getKey())]);
    }
}
