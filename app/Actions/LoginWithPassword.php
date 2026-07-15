<?php

namespace App\Actions;

use App\Enums\UserRegistrationStatus;
use App\Models\User;
use App\Models\UserRegistration;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginWithPassword
{
    public function __construct(public IssueUserApiToken $issueUserApiToken) {}

    /**
     * @return array{user: User, plain_text_token: string, token_type: string}
     */
    public function handle(string $phone, string $password): array
    {
        $normalized = PhoneNumber::normalize($phone);

        $user = User::query()
            ->with('company')
            ->where('phone', $normalized)
            ->whereNotNull('company_id')
            ->whereHas('company', fn ($query) => $query->where('is_active', true))
            ->first();

        if ($user !== null && Hash::check($password, $user->password)) {
            $issued = $this->issueUserApiToken->handle($user);

            return [
                'user' => $user,
                'plain_text_token' => $issued['plain_text_token'],
                'token_type' => 'Bearer',
            ];
        }

        $this->throwForPendingOrInvalidRegistration($normalized, $password);

        throw ValidationException::withMessages([
            'phone' => ['These credentials do not match our records.'],
        ]);
    }

    private function throwForPendingOrInvalidRegistration(string $normalizedPhone, string $password): void
    {
        $registration = UserRegistration::query()
            ->where('phone', $normalizedPhone)
            ->latest()
            ->first();

        if ($registration === null || ! Hash::check($password, $registration->password)) {
            return;
        }

        if ($registration->status === UserRegistrationStatus::Pending) {
            throw ValidationException::withMessages([
                'phone' => ['Your account has not been approved yet. Please wait for a company admin to approve your registration.'],
            ]);
        }

        if ($registration->status === UserRegistrationStatus::Rejected) {
            throw ValidationException::withMessages([
                'phone' => ['Your registration was rejected. Please contact your company admin or register again.'],
            ]);
        }
    }
}
