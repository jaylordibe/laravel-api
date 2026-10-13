<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * Tells the CURRENT address that a change to another address was requested, through the queue after the
 * transaction commits. The current address still controls recovery, and a password reset cancels the change.
 */
class EmailChangeRequestedNotification extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param string $pendingEmail The address the change was requested to.
     */
    public function __construct(public readonly string $pendingEmail)
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
            ->subject(Lang::get('Your email address change was requested'))
            ->line(Lang::get('A request was made to change the email address of your account to :email.', ['email' => $this->pendingEmail]))
            ->line(Lang::get('Your account keeps this address until the change is confirmed from the new one.'))
            ->line(Lang::get('If you did not request this, reset your password now: that cancels the change and signs out every session.'));
    }

}
