<?php

namespace App\Http\Concerns;

use App\Models\Company;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

trait ResolvesCompanyApiUser
{
    private function resolveUser(Request $request, Company $company): User
    {
        $authenticated = $request->user();

        if ($authenticated instanceof User && $authenticated->belongsToCompany($company)) {
            return $authenticated;
        }

        $userRef = $request->validate([
            'user' => ['required', 'array'],
            'user.id' => ['nullable', 'integer'],
            'user.email' => ['nullable', 'email'],
            'user.phone' => ['nullable', 'string', 'max:32'],
        ])['user'];

        if (! isset($userRef['id']) && ! isset($userRef['email']) && ! isset($userRef['phone'])) {
            abort(422, 'A user reference requires "id", "email", or "phone".');
        }

        $phone = isset($userRef['phone'])
            ? PhoneNumber::normalize((string) $userRef['phone'])
            : null;

        $user = $company->users()
            ->when(isset($userRef['id']), fn ($query) => $query->whereKey($userRef['id']))
            ->when(isset($userRef['email']), fn ($query) => $query->where('email', $userRef['email']))
            ->when($phone, fn ($query) => $query->where('phone', $phone))
            ->first();

        if ($user === null) {
            abort(404, 'The requested user does not exist for this company.');
        }

        return $user;
    }
}
