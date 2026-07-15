<?php

namespace App\Actions;

use App\Enums\UserRegistrationStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\UserRegistration;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterCompanyUser
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string}  $data
     */
    public function handle(Company $company, array $data): UserRegistration
    {
        if (! $company->is_active) {
            throw ValidationException::withMessages([
                'company' => ['This company is not accepting registrations.'],
            ]);
        }

        $phone = PhoneNumber::normalize($data['phone']);
        $email = strtolower(trim($data['email']));

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid mobile number.'],
            ]);
        }

        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is already registered.'],
            ]);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered.'],
            ]);
        }

        $pendingExists = UserRegistration::query()
            ->pending()
            ->where(function ($query) use ($phone, $email): void {
                $query->where('phone', $phone)->orWhere('email', $email);
            })
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'phone' => ['A registration with this mobile number or email is already pending approval.'],
            ]);
        }

        return UserRegistration::query()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'status' => UserRegistrationStatus::Pending,
        ]);
    }
}
