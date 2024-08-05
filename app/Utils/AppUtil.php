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
        // Remove any non-digit characters except the leading '+'
        $phoneNumber = preg_replace('/[^\d+]/', '', $phoneNumber);

        // Check if the phone number matches any of the valid patterns
        $pattern = '/^(?:\+63|63|0)?\d{10}$/';

        return (bool) preg_match($pattern, $phoneNumber);
    }

    /**
     * Transform a Philippine phone number to international format.
     *
     * @param string $phoneNumber
     *
     * @return string
     */
    public static function transformPhilippinePhoneNumberToInternationalFormat(string $phoneNumber): string
    {
        // Remove any non-digit characters except the leading '+'
        $phoneNumber = preg_replace('/[^\d+]/', '', $phoneNumber);

        // Check if the phone number starts with '+63'
        if (str_starts_with($phoneNumber, '+63')) {
            return $phoneNumber;
        }

        // Check if the phone number starts with '63' and prepend '+'
        if (str_starts_with($phoneNumber, '63')) {
            return '+' . $phoneNumber;
        }

        // If the phone number starts with '0', remove the '0' and prepend '+63'
        if (str_starts_with($phoneNumber, '0')) {
            return '+63' . substr($phoneNumber, 1);
        }

        // For any other case, assume it should be prefixed with '+63'
        return '+63' . $phoneNumber;
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
