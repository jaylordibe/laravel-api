<?php

namespace App\Utils;

class AppUtil
{

    /**
     * Check if email is valid.
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
