<?php

use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/{company:slug}/push', [WebhookController::class, 'push'])
    ->middleware('webhook.signature')
    ->name('webhooks.push');

Route::prefix('v1/{company:slug}')
    ->middleware('company.token')
    ->group(function (): void {
        Route::post('device-tokens', [DeviceTokenController::class, 'store'])
            ->name('api.v1.device-tokens.store');

        Route::delete('device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy'])
            ->name('api.v1.device-tokens.destroy');

        Route::post('notifications', [NotificationController::class, 'store'])
            ->name('api.v1.notifications.store');
    });
