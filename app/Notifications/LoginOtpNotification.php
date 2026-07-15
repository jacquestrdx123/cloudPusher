<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (config('pushservice.providers.sms')) {
            return ['vonage'];
        }

        return ['mail'];
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        $minutes = (int) config('pushservice.auth.otp_ttl_minutes', 10);

        return (new VonageMessage)
            ->content("Your cloudPusher login code is {$this->code}. It expires in {$minutes} minutes.");
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('pushservice.auth.otp_ttl_minutes', 10);

        return (new MailMessage)
            ->subject('Your cloudPusher login code')
            ->line("Your login code is {$this->code}.")
            ->line("This code expires in {$minutes} minutes.");
    }
}
