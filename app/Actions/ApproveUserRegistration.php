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

        if (User::query()->where('phone', $registration->phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is already registered to another user.'],
            ]);
        }

        if (User::query()->where('email', $registration->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered to another user.'],
            ]);
        }

        return DB::transaction(function () use ($registration, $reviewer, $notes): User {
            $user = User::query()->create([
                'company_id' => $registration->company_id,
                'name' => $registration->name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'password' => $registration->password,
                'is_admin' => false,
                'is_company_admin' => false,
            ]);

            $registration->update([
                'status' => UserRegistrationStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'user_id' => $user->id,
            ]);

            return $user;
        });
    }
}
