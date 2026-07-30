<?php

namespace Tests\Unit;

use App\Casts\BigDecimalCast;
use App\Models\AppVersion;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BigDecimalCastTest extends TestCase
{

    private BigDecimalCast $cast;

    private AppVersion $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cast = new BigDecimalCast();
        $this->model = new AppVersion();
    }

    #[Test]
    public function testGetCastsDatabaseStringToBigDecimal(): void
    {
        $amount = $this->cast->get($this->model, 'amount', '123.45', []);

        $this->assertInstanceOf(BigDecimal::class, $amount);
        $this->assertSame('123.45', $amount->__toString());
    }

    #[Test]
    public function testGetPreservesTrailingZeroScaleFromDatabase(): void
    {
        $amount = $this->cast->get($this->model, 'amount', '10.00', []);

        $this->assertSame('10.00', $amount->__toString());
    }

    #[Test]
    public function testGetReturnsNullForNullValue(): void
    {
        $this->assertNull($this->cast->get($this->model, 'amount', null, []));
    }

    #[Test]
    public function testGetReturnsNullForUnparsableValue(): void
    {
        $this->assertNull($this->cast->get($this->model, 'amount', 'not-a-number', []));
    }

    #[Test]
    public function testSetConvertsBigDecimalToStringForStorage(): void
    {
        $amount = $this->cast->set($this->model, 'amount', BigDecimal::of('99.99'), []);

        $this->assertSame('99.99', $amount);
    }

    #[Test]
    public function testSetPreservesPrecisionBeyondFloatRange(): void
    {
        $amount = $this->cast->set($this->model, 'amount', '12345678901234567890.123456789', []);

        $this->assertSame('12345678901234567890.123456789', $amount);
    }

    #[Test]
    public function testSetReturnsNullForNullValue(): void
    {
        $this->assertNull($this->cast->set($this->model, 'amount', null, []));
    }

    #[Test]
    public function testSetReturnsNullForUnparsableValue(): void
    {
        $this->assertNull($this->cast->set($this->model, 'amount', 'not-a-number', []));
    }

}
