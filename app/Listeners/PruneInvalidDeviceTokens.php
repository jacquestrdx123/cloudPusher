<?php

namespace App\Listeners;

use App\Models\DeviceToken;
use App\Notifications\WebhookPushNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Fcm\FcmChannel;

class PruneInvalidDeviceTokens
{
    /**
     * Remove device tokens that providers report as permanently invalid.
     */
    public function handle(NotificationFailed $event): void
    {
        if (! $event->notification instanceof WebhookPushNotification) {
            return;
        }

        if (! in_array($event->channel, [FcmChannel::class, ApnChannel::class], true)) {
            return;
        }

        if (! $this->isPermanentFailure($event->data)) {
            return;
        }

        $token = $this->extractToken($event->data);

        if ($token === null) {
            return;
        }

        DeviceToken::query()
            ->where('user_id', $event->notifiable->getKey())
            ->where('token', $token)
            ->delete();
    }

    /**
     * @param  array<string, mixed>|string|null  $data
     */
    private function isPermanentFailure(array|string|null $data): bool
    {
        $haystack = Str::lower(json_encode($data) ?: '');

        $permanentMarkers = [
            'notregistered',
            'invalidregistration',
            'unregistered',
            'baddevicetoken',
            'devicetokennotfortopic',
            'mismatchsenderid',
        ];

        foreach ($permanentMarkers as $marker) {
            if (Str::contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|string|null  $data
     */
    private function extractToken(array|string|null $data): ?string
    {
        if (is_string($data)) {
            return Str::length($data) > 20 ? $data : null;
        }

        if (! is_array($data)) {
            return null;
        }

        foreach (['token', 'device_token', 'registration_id'] as $key) {
            $value = Arr::get($data, $key);

            if (is_string($value) && Str::length($value) > 20) {
                return $value;
            }
        }

        return null;
    }
}
