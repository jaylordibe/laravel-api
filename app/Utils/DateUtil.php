<?php

namespace App\Utils;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DateUtil
{

    public static function isValidISOString(string $datetimeString): bool
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
     * Strips milliseconds from an ISO string.
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
