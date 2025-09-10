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
     * @param BigNumber|int|float|string $dividend
     * @param BigNumber|int|float|string $divisor
     *
     * @return BigDecimal
     * @throws MathException
     */
    public static function divide(BigNumber|int|float|string $dividend, BigNumber|int|float|string $divisor): BigDecimal
    {
        $dividend = BigDecimal::of($dividend);
        $divisor = BigDecimal::of($divisor);

        if ($divisor->isZero()) {
            return BigDecimal::zero();
        }

        return $dividend->dividedBy($divisor, 20, RoundingMode::DOWN);
    }

}
