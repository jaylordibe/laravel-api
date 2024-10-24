<?php

namespace App\Utils;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DateUtil
{

    /**
     * Checks if a datetime string is a valid UTC ISO string.
     *
     * @param string $datetimeString
     *
     * @return bool
     */
    public static function isValidUtcIsoString(string $datetimeString): bool
    {
        try {
            $parsedDate = Carbon::parse($datetimeString);

            return self::stripMilliseconds($parsedDate->toISOString()) === self::stripMilliseconds($datetimeString);
        } catch (InvalidFormatException $e) {
            Log::error("Failed to parse attribute as Carbon: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Strips milliseconds from a datetime string (UTC ISO string).
     * Laravel, by default, truncates the milliseconds when storing datetime values in the database.
     * This is because many databases, including MySQL, by default, do not store milliseconds in datetime fields.
     *
     * @param string $datetimeString
     *
     * @return string
     */
    public static function stripMilliseconds(string $datetimeString): string
    {
        return preg_replace('/\.\d+Z/', '.000000Z', $datetimeString);
    }

}
