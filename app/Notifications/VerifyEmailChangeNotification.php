<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * The link that confirms a requested email change, sent to the NEW address through the queue after the
 * transaction commits. The link opens the client app, which posts its token to confirm; the account keeps
 * its current email until then.
 */
class VerifyEmailChangeNotification extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param string $url The client-app confirmation link carrying the user id and token.
     */
    public function __construct(public readonly string $url)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(Lang::get('Confirm your new email address'))
            ->line(Lang::get('Please click the button below to confirm this as the new email address for your account.'))
            ->action(Lang::get('Confirm Email Address'), $this->url)
            ->line(Lang::get('If you did not request this change, no further action is required.'));
    }

}
