<?php

namespace App\Utils;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

class MathUtil
{

    /**
     * Divides two BigDecimal numbers and returns the result as a BigDecimal.
     *
     * @param BigDecimal $dividend
     * @param BigDecimal $divisor
     *
     * @return BigDecimal
     * @throws MathException
     */
    public static function divide(BigDecimal $dividend, BigDecimal $divisor): BigDecimal
    {
        if ($divisor->isZero()) {
            return BigDecimal::zero();
        }

        return $dividend->dividedBy($divisor, 20, RoundingMode::DOWN);
    }

}
