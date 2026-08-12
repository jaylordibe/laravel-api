<?php

namespace App\Utils;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

class MathUtil
{

    /**
     * Divides two numbers and returns the result as a BigDecimal.
     *
     * `float` is deliberately excluded from both parameters. `BigDecimal::of()`
     * stringifies a float at PHP's `precision` setting, so a caller passing one
     * loses digits before this method ever sees the value — silently, with no
     * error and no failing test. Pass a string, an int, or a BigNumber.
     *
     * @param BigNumber|int|string $dividend
     * @param BigNumber|int|string $divisor
     *
     * @return BigDecimal
     * @throws MathException
     */
    public static function divide(BigNumber|int|string $dividend, BigNumber|int|string $divisor): BigDecimal
    {
        $dividend = BigDecimal::of($dividend);
        $divisor = BigDecimal::of($divisor);

        if ($divisor->isZero()) {
            return BigDecimal::zero();
        }

        return $dividend->dividedBy($divisor, 20, RoundingMode::DOWN);
    }

}
