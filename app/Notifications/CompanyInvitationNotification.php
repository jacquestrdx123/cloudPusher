<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public ?string $inviterName,
        public string $acceptUrl,
        public CarbonInterface $expiresAt,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (config('pushservice.providers.sms') && $notifiable instanceof User && filled($notifiable->phone)) {
            $channels[] = 'vonage';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->inviterName ?? 'Your company admin';

        return (new MailMessage)
            ->subject("You have been added to {$this->companyName} on cloudPusher")
            ->greeting('Hello'.($notifiable instanceof User ? " {$notifiable->name}" : '').',')
            ->line("{$inviter} added you to {$this->companyName} on cloudPusher.")
            ->line('Set your password to activate your account.')
            ->action('Set your password', $this->acceptUrl)
            ->line('This link expires on '.$this->expiresAt->toFormattedDayDateString().'.')
            ->line('Then install the cloudPusher app and sign in with your mobile number and new password.');
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content("You've been added to {$this->companyName} on cloudPusher. Set your password: {$this->acceptUrl}");
    }
}
