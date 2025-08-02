<?php

namespace App\Traits;

trait EnumTrait
{

    /**
     * Get an array of all case names.
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Get an array of all case values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get an associative array of [value => name].
     */
    public static function toArray(): array
    {
        return array_combine(self::values(), self::names());
    }

}
