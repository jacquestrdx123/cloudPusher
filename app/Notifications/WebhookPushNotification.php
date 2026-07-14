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
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

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

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotification(
            title: $this->title,
            body: $this->body,
        )))->data($this->stringData());
    }

    public function toApn(object $notifiable): ApnMessage
    {
        $message = ApnMessage::create()
            ->title($this->title)
            ->body($this->body);

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
