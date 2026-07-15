<?php

namespace App\Actions;

use App\Enums\UserRegistrationStatus;
use App\Models\User;
use App\Models\UserRegistration;
use Illuminate\Validation\ValidationException;

class RejectUserRegistration
{
    public function handle(UserRegistration $registration, User $reviewer, ?string $notes = null): UserRegistration
    {
        if (! $registration->isPending()) {
            throw ValidationException::withMessages([
                'registration' => ['Only pending registrations can be rejected.'],
            ]);
        }

        if (! $reviewer->canAdministerCompany($registration->company)) {
            throw ValidationException::withMessages([
                'registration' => ['You are not allowed to reject this registration.'],
            ]);
        }

        $registration->update([
            'status' => UserRegistrationStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $registration->fresh();
    }
}
