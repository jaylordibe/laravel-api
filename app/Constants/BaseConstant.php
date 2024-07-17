<?php

namespace App\Constants;

use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionException;

class BaseConstant
{

    /**
     * Get constant value.
     *
     * @param string $search
     *
     * @return string|null
     */
    public static function fromString(string $search): ?string
    {
        $constants = self::asList();

        foreach ($constants as $constant) {
            if ($constant === $search) {
                return $constant;
            }
        }

        return null;
    }

    /**
     * Get list of constants from the called class(a class which extends BaseConstant).
     *
     * @param bool $preserveKeys - optional
     *     Default - false.
     *     If true, it returns the list of constants with their corresponding keys.
     *
     * @return array
     */
    public static function asList(bool $preserveKeys = false): array
    {
        $reflectionClass = new ReflectionClass(get_called_class());
        $constants = $reflectionClass->getConstants();

        if ($preserveKeys) {
            return $constants;
        }

        $constantValues = [];

        foreach ($constants as $constant) {
            $constantValues[] = $constant;
        }

        return $constantValues;
    }

}
