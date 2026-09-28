<?php

namespace App\Utils;

use Carbon\CarbonInterval;
use InvalidArgumentException;

class AuthUtil
{

    /**
     * The shortest sign-in session this API will issue, in minutes. Below it a
     * token can expire between two requests of one user action.
     */
    public const int MIN_TOKEN_TTL_MINUTES = 5;

    /**
     * The longest sign-in session this API will issue, in minutes (90 days). A
     * stolen token is usable until it expires, so a configuration mistake must
     * never be able to mint one that effectively never does.
     */
    public const int MAX_TOKEN_TTL_MINUTES = 129600;

    /**
     * The lifetime of a sign-in token, from `custom.auth.token_ttl_minutes`.
     *
     * The single place that value is parsed and bounded. It throws rather than
     * falling back, because both fallbacks fail silently: a value cast to 0
     * issues tokens that are already expired, locking everyone out, and a value
     * of null handed to Passport means its one-year default. Called at boot, so
     * a bad value stops the application from starting at all — including when
     * the `app:check-config` gate is switched off.
     *
     * @return CarbonInterval
     * @throws InvalidArgumentException
     */
    public static function personalAccessTokenLifetime(): CarbonInterval
    {
        $configured = config('custom.auth.token_ttl_minutes');
        $minutes = filter_var($configured, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => self::MIN_TOKEN_TTL_MINUTES,
                'max_range' => self::MAX_TOKEN_TTL_MINUTES,
            ],
        ]);

        if ($minutes === false) {
            throw new InvalidArgumentException(sprintf(
                'AUTH_TOKEN_TTL_MINUTES must be a whole number of minutes from %d to %d; got %s.',
                self::MIN_TOKEN_TTL_MINUTES,
                self::MAX_TOKEN_TTL_MINUTES,
                var_export($configured, true)
            ));
        }

        return CarbonInterval::minutes($minutes);
    }

}
