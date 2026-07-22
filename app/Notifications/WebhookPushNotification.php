<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Apn\ApnMessage;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

class WebhookPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $channels  Requested logical channels: push, mail, sms.
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $pushNotificationId,
        public string $title,
        public ?string $body,
        public array $channels,
        public array $data = [],
        public ?string $imageUrl = null,
        public ?string $sound = null,
        public ?string $category = null,
        public ?string $androidChannelId = null,
    ) {}

    /**
     * Resolve the concrete delivery channels for this recipient, honouring the
     * requested channels, enabled providers, and the recipient's own routes.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $via = [];

        if (in_array('push', $this->channels, true)) {
            if ($this->providerEnabled('fcm') && $notifiable->routeNotificationForFcm() !== []) {
                $via[] = FcmChannel::class;
            }

            if ($this->providerEnabled('apns') && $notifiable->routeNotificationForApn() !== []) {
                $via[] = ApnChannel::class;
            }
        }

        if (in_array('mail', $this->channels, true) && $this->providerEnabled('mail')) {
            $via[] = 'mail';
        }

        if (in_array('sms', $this->channels, true) && $this->providerEnabled('sms') && $notifiable->phone !== null) {
            $via[] = 'vonage';
        }

        return $via;
    }

    /**
     * Explain why requested channels could not be routed for this recipient.
     * Reports per logical channel even when other channels still deliver.
     *
     * @return array<int, array{channel: string, error: string}>
     */
    public function undeliverableAttempts(User $user): array
    {
        $attempts = [];

        if (in_array('push', $this->channels, true) && ! $this->canDeliverPush($user)) {
            $attempts[] = [
                'channel' => 'push',
                'error' => $this->pushUndeliverableReason($user),
            ];
        }

        if (in_array('mail', $this->channels, true) && ! $this->providerEnabled('mail')) {
            $attempts[] = [
                'channel' => 'mail',
                'error' => 'Mail provider is disabled (PUSH_MAIL_ENABLED=false).',
            ];
        }

        if (in_array('sms', $this->channels, true)) {
            if (! $this->providerEnabled('sms')) {
                $attempts[] = [
                    'channel' => 'sms',
                    'error' => 'SMS provider is disabled (PUSH_SMS_ENABLED=false).',
                ];
            } elseif ($user->phone === null) {
                $attempts[] = [
                    'channel' => 'sms',
                    'error' => 'User has no phone number for SMS delivery.',
                ];
            }
        }

        return $attempts;
    }

    private function canDeliverPush(User $user): bool
    {
        $fcmOk = $this->providerEnabled('fcm') && $user->routeNotificationForFcm() !== [];
        $apnsOk = $this->providerEnabled('apns') && $user->routeNotificationForApn() !== [];

        return $fcmOk || $apnsOk;
    }

    private function pushUndeliverableReason(User $user): string
    {
        $fcmEnabled = $this->providerEnabled('fcm');
        $apnsEnabled = $this->providerEnabled('apns');
        $hasFcm = $user->routeNotificationForFcm() !== [];
        $hasApns = $user->routeNotificationForApn() !== [];

        if (! $fcmEnabled && ! $apnsEnabled) {
            return 'Push providers are disabled (set PUSH_FCM_ENABLED and/or PUSH_APNS_ENABLED).';
        }

        $parts = [];

        if ($fcmEnabled && ! $hasFcm) {
            $parts[] = 'no FCM device tokens';
        } elseif (! $fcmEnabled) {
            $parts[] = 'FCM provider disabled';
        }

        if ($apnsEnabled && ! $hasApns) {
            $parts[] = 'no APNs device tokens';
        } elseif (! $apnsEnabled) {
            $parts[] = 'APNs provider disabled';
        }

        return 'Push could not be delivered: '.implode('; ', $parts).'.';
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $sound = $this->resolvedSound();
        $channelId = $this->resolvedAndroidChannelId();
        $androidSound = (string) config('pushservice.android_notification_sound', 'notification');

        $data = array_merge($this->stringData(), [
            'title' => $this->title,
            'body' => (string) $this->body,
            'push_notification_id' => (string) $this->pushNotificationId,
            'sound' => $sound,
            'image' => (string) ($this->imageUrl ?? ''),
        ]);

        $androidNotification = [
            'title' => $this->title,
            'body' => (string) $this->body,
            'channel_id' => $channelId,
            'sound' => $androidSound,
            'visibility' => 'PUBLIC',
        ];

        if ($this->imageUrl !== null && $this->imageUrl !== '') {
            $androidNotification['image'] = $this->imageUrl;
        }

        /*
         * Data-only message. A top-level `notification` block makes web (FCM
         * webpush) browsers auto-display the notification *and* invokes our
         * service worker's onBackgroundMessage handler — showing it twice. By
         * sending data only, the web client renders it once (foreground and
         * background) from the `data` payload above.
         *
         * Native Android still needs a notification block to render in the
         * system tray while backgrounded, so that is supplied through the
         * Android-specific config, which the webpush transport ignores.
         */
        return (new FcmMessage)
            ->data($data)
            ->android([
                'priority' => 'high',
                'notification' => $androidNotification,
            ]);
    }

    public function toApn(object $notifiable): ApnMessage
    {
        $message = ApnMessage::create()
            ->title($this->title)
            ->body($this->body)
            ->sound($this->resolvedSound())
            ->category($this->resolvedCategory())
            ->mutableContent()
            ->custom('push_notification_id', (string) $this->pushNotificationId);

        if ($this->imageUrl !== null && $this->imageUrl !== '') {
            $message->custom('media_url', $this->imageUrl);
            $message->custom('media_type', 'image');
        }

        foreach ($this->data as $key => $value) {
            $message->custom((string) $key, $value);
        }

        return $message;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title);

        if ($this->body !== null) {
            $mail->line($this->body);
        }

        return $mail;
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content(trim($this->title.': '.($this->body ?? '')));
    }

    private function providerEnabled(string $provider): bool
    {
        return (bool) config("pushservice.providers.{$provider}");
    }

    private function resolvedSound(): string
    {
        return $this->sound
            ?? (string) config('pushservice.notification_sound', 'default');
    }

    private function resolvedCategory(): string
    {
        return $this->category
            ?? (string) config('pushservice.notification_category', 'RICH_MESSAGE');
    }

    private function resolvedAndroidChannelId(): string
    {
        return $this->androidChannelId
            ?? (string) config('pushservice.android_channel_id', 'rich_messages_v1');
    }

    /**
     * FCM requires all data values to be strings. Also merges image into the
     * payload so the in-app inbox (and web SW) can render rich media.
     *
     * @return array<string, string>
     */
    private function stringData(): array
    {
        $data = $this->data;

        if ($this->imageUrl !== null && $this->imageUrl !== '' && ! array_key_exists('image', $data)) {
            $data['image'] = $this->imageUrl;
        }

        return array_map(
            fn ($value): string => is_scalar($value) ? (string) $value : (string) json_encode($value),
            $data,
        );
    }
}
