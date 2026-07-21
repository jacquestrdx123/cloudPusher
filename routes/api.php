<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\InboxController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/{company:slug}/push', [WebhookController::class, 'push'])
    ->middleware(['webhook.signature', 'throttle:push-webhook'])
    ->name('webhooks.push');

Route::post('v1/auth/login', [AuthController::class, 'login'])
    ->middleware(['throttle:mobile-login'])
    ->name('api.v1.auth.login');

Route::post('v1/{company:slug}/auth/register', [RegistrationController::class, 'store'])
    ->middleware(['throttle:mobile-login'])
    ->name('api.v1.auth.register');

Route::prefix('v1/auth')
    ->middleware(['user.token', 'throttle:push-api'])
    ->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])
            ->name('api.v1.auth.me');

        Route::post('logout', [AuthController::class, 'logout'])
            ->name('api.v1.auth.logout');
    });

Route::prefix('v1')
    ->middleware(['user.token', 'throttle:push-api'])
    ->group(function (): void {
        Route::get('inbox', [InboxController::class, 'unifiedIndex'])
            ->name('api.v1.inbox.unified');

        Route::patch('inbox/read-all', [InboxController::class, 'unifiedMarkAllRead'])
            ->name('api.v1.inbox.unified-mark-all-read');

        Route::patch('inbox/{inbox}/read', [InboxController::class, 'unifiedMarkRead'])
            ->name('api.v1.inbox.unified-mark-read');

        Route::post('device-tokens', [DeviceTokenController::class, 'storeForUser'])
            ->name('api.v1.device-tokens.store-for-user');

        Route::delete('device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroyForUser'])
            ->name('api.v1.device-tokens.destroy-for-user');
    });

Route::prefix('v1/{company:slug}/registrations')
    ->middleware(['user.token', 'throttle:push-api'])
    ->group(function (): void {
        Route::get('/', [RegistrationController::class, 'index'])
            ->name('api.v1.registrations.index');

        Route::post('{registration}/approve', [RegistrationController::class, 'approve'])
            ->name('api.v1.registrations.approve');

        Route::post('{registration}/reject', [RegistrationController::class, 'reject'])
            ->name('api.v1.registrations.reject');
    });

Route::prefix('v1/{company:slug}/members')
    ->middleware(['user.token', 'throttle:push-api'])
    ->group(function (): void {
        Route::post('/', [MemberController::class, 'store'])
            ->name('api.v1.members.store');

        Route::delete('{user}', [MemberController::class, 'destroy'])
            ->name('api.v1.members.destroy');
    });

Route::prefix('v1/{company:slug}')
    ->middleware(['company.or.user.token', 'throttle:push-api'])
    ->group(function (): void {
        Route::post('device-tokens', [DeviceTokenController::class, 'store'])
            ->name('api.v1.device-tokens.store');

        Route::delete('device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy'])
            ->name('api.v1.device-tokens.destroy');

        Route::get('inbox', [InboxController::class, 'index'])
            ->name('api.v1.inbox.index');

        Route::patch('inbox/read-all', [InboxController::class, 'markAllRead'])
            ->name('api.v1.inbox.mark-all-read');

        Route::get('inbox/{inbox}', [InboxController::class, 'show'])
            ->name('api.v1.inbox.show');

        Route::patch('inbox/{inbox}/read', [InboxController::class, 'markRead'])
            ->name('api.v1.inbox.mark-read');
    });

Route::prefix('v1/{company:slug}')
    ->middleware(['company.token', 'throttle:push-api'])
    ->group(function (): void {
        Route::get('notifications', [NotificationController::class, 'index'])
            ->name('api.v1.notifications.index');

        Route::get('notifications/{notification}', [NotificationController::class, 'show'])
            ->name('api.v1.notifications.show');

        Route::post('notifications', [NotificationController::class, 'store'])
            ->name('api.v1.notifications.store');
    });
