<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RequestLoginOtp
{
    /**
     * @return array{message: string, debug_code?: string}
     */
    public function handle(string $phone): array
    {
        $normalized = PhoneNumber::normalize($phone);
        $message = 'If this mobile number is registered, a login code has been sent.';

        $user = User::query()
            ->where('phone', $normalized)
            ->whereHas('companies', fn ($query) => $query->where('is_active', true))
            ->first();

        if ($user === null) {
            return ['message' => $message];
        }

        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('pushservice.auth.otp_ttl_minutes', 10);

        Cache::put($this->cacheKey($normalized), [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'user_id' => $user->id,
        ], now()->addMinutes($ttl));

        $this->deliver($user, $code);

        $response = ['message' => $message];

        if ($this->shouldExposeDebugCode()) {
            $response['debug_code'] = $code;
        }

        return $response;
    }

    public function cacheKey(string $normalizedPhone): string
    {
        return sprintf('login_otp:%s', $normalizedPhone);
    }

    private function deliver(User $user, string $code): void
    {
        $user->notify(new LoginOtpNotification($code));

        if (! config('pushservice.providers.sms')) {
            Log::info('Login OTP generated (SMS disabled — delivered via fallback channel).', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'code' => $code,
            ]);
        }
    }

    private function shouldExposeDebugCode(): bool
    {
        return app()->environment(['local', 'testing'])
            || ! (bool) config('pushservice.providers.sms');
    }
}
