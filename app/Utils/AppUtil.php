<?php

namespace App\Utils;

use Illuminate\Support\Str;

class AppUtil
{

    /**
     * Check if email is valid.
     *
     * @param string $email
     *
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate unique token.
     *
     * @param string $prefix
     *
     * @return string
     */
    public static function generateUniqueToken(string $prefix = ''): string
    {
        return $prefix . now()->format('YmdHis') . Str::upper(Str::random());
    }

}
