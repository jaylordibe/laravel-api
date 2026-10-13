<?php

namespace App\Constants;

class UserConstant
{

    /**
     * The longest username accepted from a user (UpdateUsernameRequest) or generated (UserService).
     */
    const int MAX_USERNAME_LENGTH = 64;

    /**
     * The pending-email columns, as written to discard a pending email change. The one list every path that
     * cancels or applies a change writes, so a column added here is cleared everywhere.
     *
     * @var array<string, null>
     */
    const array CLEARED_PENDING_EMAIL = [
        'pending_email' => null,
        'pending_email_token' => null,
        'pending_email_expires_at' => null,
    ];

}
