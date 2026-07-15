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
     *
     * @return array<int, array{channel: string, error: string}>
     */
    public function undeliverableAttempts(User $user): array
    {
        if ($this->via($user) !== []) {
            return [];
        }

        $attempts = [];

        if (in_array('push', $this->channels, true)) {
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
        $sound = (string) config('pushservice.notification_sound', 'default');

        $data = array_merge($this->stringData(), [
            'title' => $this->title,
            'body' => (string) $this->body,
            'push_notification_id' => (string) $this->pushNotificationId,
            'sound' => $sound,
        ]);

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
                'notification' => [
                    'title' => $this->title,
                    'body' => (string) $this->body,
                    'sound' => $sound,
                    'default_sound' => true,
                ],
            ]);
    }

    public function toApn(object $notifiable): ApnMessage
    {
        $message = ApnMessage::create()
            ->title($this->title)
            ->body($this->body)
            ->sound(config('pushservice.notification_sound', 'default'));

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

    /**
     * FCM requires all data values to be strings.
     *
     * @return array<string, string>
     */
    private function stringData(): array
    {
        return array_map(
            fn ($value): string => is_scalar($value) ? (string) $value : (string) json_encode($value),
            $this->data,
        );
    }
}
