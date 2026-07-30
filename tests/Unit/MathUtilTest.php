<?php

namespace Tests\Unit;

use App\Utils\MathUtil;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MathUtilTest extends TestCase
{

    #[Test]
    public function testDivideReturnsExactQuotientForTerminatingDivision(): void
    {
        $quotient = MathUtil::divide('10', '4');

        $this->assertSame('2.50000000000000000000', $quotient->__toString());
    }

    #[Test]
    public function testDivideTruncatesRepeatingQuotientToTwentyDecimalPlaces(): void
    {
        $quotient = MathUtil::divide('1', '3');

        $this->assertSame('0.33333333333333333333', $quotient->__toString());
    }

    #[Test]
    public function testDivideTruncatesTowardsZeroInsteadOfRoundingUp(): void
    {
        $quotient = MathUtil::divide('2', '3');

        $this->assertSame('0.66666666666666666666', $quotient->__toString());
    }

    #[Test]
    public function testDivideTruncatesTowardsZeroForNegativeQuotients(): void
    {
        $quotient = MathUtil::divide('-2', '3');

        $this->assertSame('-0.66666666666666666666', $quotient->__toString());
    }

    #[Test]
    public function testDivideReturnsZeroWhenDivisorIsZero(): void
    {
        $quotient = MathUtil::divide('100', '0');

        $this->assertTrue($quotient->isZero());
    }

    #[Test]
    public function testDivideAcceptsIntegerAndBigDecimalOperands(): void
    {
        $quotient = MathUtil::divide(BigDecimal::of('7.5'), 3);

        $this->assertSame('2.50000000000000000000', $quotient->__toString());
    }

    #[Test]
    public function testDividePreservesPrecisionBeyondFloatRange(): void
    {
        $quotient = MathUtil::divide('0.1', '3');

        $this->assertSame('0.03333333333333333333', $quotient->__toString());
    }

}
