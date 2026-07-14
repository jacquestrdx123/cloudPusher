<?php

namespace App\Providers;

use App\Listeners\PruneInvalidDeviceTokens;
use App\Listeners\RecordNotificationDelivery;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->configureNotificationListeners();
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
    }

    protected function configureNotificationListeners(): void
    {
        Event::listen(NotificationSent::class, RecordNotificationDelivery::class);
        Event::listen(NotificationFailed::class, RecordNotificationDelivery::class);
        Event::listen(NotificationFailed::class, PruneInvalidDeviceTokens::class);
    }
}
