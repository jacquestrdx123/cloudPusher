<?php

namespace App\Actions;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyLoginOtp
{
    public function __construct(public IssueUserApiToken $issueUserApiToken) {}

    /**
     * @return array{user: User, plain_text_token: string, token_type: string}
     */
    public function handle(string $phone, string $code): array
    {
        $normalized = PhoneNumber::normalize($phone);
        $cacheKey = (new RequestLoginOtp)->cacheKey($normalized);
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! isset($payload['code_hash'], $payload['user_id'])) {
            throw ValidationException::withMessages([
                'code' => ['The login code is invalid or has expired.'],
            ]);
        }

        $maxAttempts = (int) config('pushservice.auth.otp_max_attempts', 5);
        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= $maxAttempts) {
            Cache::forget($cacheKey);

            throw ValidationException::withMessages([
                'code' => ['Too many invalid attempts. Request a new login code.'],
            ]);
        }

        if (! Hash::check($code, $payload['code_hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $payload, now()->addMinutes((int) config('pushservice.auth.otp_ttl_minutes', 10)));

            throw ValidationException::withMessages([
                'code' => ['The login code is invalid or has expired.'],
            ]);
        }

        $user = User::query()
            ->with('company')
            ->whereKey($payload['user_id'])
            ->whereNotNull('company_id')
            ->whereHas('company', fn ($query) => $query->where('is_active', true))
            ->first();

        if ($user === null || PhoneNumber::normalize((string) $user->phone) !== $normalized) {
            Cache::forget($cacheKey);

            throw ValidationException::withMessages([
                'phone' => ['The requested user does not exist.'],
            ]);
        }

        Cache::forget($cacheKey);

        $issued = $this->issueUserApiToken->handle($user);

        return [
            'user' => $user,
            'plain_text_token' => $issued['plain_text_token'],
            'token_type' => 'Bearer',
        ];
    }
}
