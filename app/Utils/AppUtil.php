<?php

namespace App\Utils;

use Illuminate\Support\Str;

class AppUtil
{

    /**
     * Check if an email is valid.
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
     * Check if a Philippine phone number is valid.
     *
     * @param string $phoneNumber
     *
     * @return bool
     */
    public static function isValidPhilippinePhoneNumber(string $phoneNumber): bool
    {
        $pattern = '/^(?:\+?63|0)\d{10}$/';

        return (bool) preg_match($pattern, $phoneNumber);
    }

    /**
     * Generate a unique token.
     *
     * @param string $prefix
     *
     * @return string
     */
    public static function generateUniqueToken(string $prefix = ''): string
    {
        return $prefix . now()->format('YmdHis') . Str::upper(Str::random());
    }

    /**
     * Generate an OTP.
     *
     * @param int $length
     *
     * @return string
     */
    public static function generateOtp(int $length = 6): string
    {
        return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

}
