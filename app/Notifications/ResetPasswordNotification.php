<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's password reset link, sent through the queue.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{

    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param string $token
     */
    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

}
