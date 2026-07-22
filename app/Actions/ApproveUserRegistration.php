<?php

namespace App\Actions;

use App\Enums\UserRegistrationStatus;
use App\Models\User;
use App\Models\UserRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveUserRegistration
{
    public function handle(UserRegistration $registration, User $reviewer, ?string $notes = null): User
    {
        if (! $registration->isPending()) {
            throw ValidationException::withMessages([
                'registration' => ['Only pending registrations can be approved.'],
            ]);
        }

        if (! $reviewer->canAdministerCompany($registration->company)) {
            throw ValidationException::withMessages([
                'registration' => ['You are not allowed to approve this registration.'],
            ]);
        }

        return DB::transaction(function () use ($registration, $reviewer, $notes): User {
            $user = $this->resolveUser($registration);

            $user->companies()->syncWithoutDetaching([
                $registration->company_id => ['is_company_admin' => false],
            ]);

            $registration->update([
                'status' => UserRegistrationStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'user_id' => $user->id,
            ]);

            return $user->load('companies');
        });
    }

    /**
     * Prefer an existing platform user (by phone, then email) so approval only
     * associates them with the company instead of creating a duplicate.
     */
    private function resolveUser(UserRegistration $registration): User
    {
        $byPhone = User::query()->where('phone', $registration->phone)->first();
        $byEmail = User::query()->where('email', $registration->email)->first();

        if ($byPhone instanceof User && $byEmail instanceof User && $byPhone->isNot($byEmail)) {
            throw ValidationException::withMessages([
                'registration' => ['Phone and email belong to different existing users.'],
            ]);
        }

        $existing = $byPhone ?? $byEmail;

        if ($existing instanceof User) {
            return $existing;
        }

        return User::query()->create([
            'name' => $registration->name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'password' => $registration->password,
            'is_admin' => false,
        ]);
    }
}
