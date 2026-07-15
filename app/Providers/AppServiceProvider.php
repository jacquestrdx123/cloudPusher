<?php

namespace App\Providers;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('push-api', function (Request $request) {
            $company = $request->route('company');

            return Limit::perMinute((int) config('pushservice.rate_limit'))
                ->by($company instanceof Company ? (string) $company->getKey() : ((string) $company ?: $request->ip()));
        });

        RateLimiter::for('push-webhook', function (Request $request) {
            $company = $request->route('company');

            return Limit::perMinute((int) config('pushservice.webhook_rate_limit'))
                ->by($company instanceof Company ? (string) $company->getKey() : ((string) $company ?: $request->ip()));
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            $phone = (string) $request->input('phone', '');

            return Limit::perMinute((int) config('pushservice.auth.otp_rate_limit', 5))
                ->by($request->ip().'|'.$phone);
        });
    }

    // Notification delivery/pruning listeners (RecordNotificationDelivery,
    // PruneInvalidDeviceTokens) are auto-discovered from app/Listeners via their
    // handle() type-hints. Do NOT also register them explicitly here — that
    // double-binds them and records each delivery twice.
}
