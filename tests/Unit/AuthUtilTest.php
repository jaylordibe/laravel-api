<?php

namespace Tests\Unit;

use App\Utils\AuthUtil;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the sign-in session lifetime parser. Every rejected value here is one
 * that a looser parser would have turned into a silent failure: an `(int)` cast
 * makes a typo 0, so every token is born expired, and a null handed to Passport
 * becomes its one-year default.
 */
class AuthUtilTest extends TestCase
{

    #[Test]
    public function itReturnsTheConfiguredLifetimeInMinutes(): void
    {
        config()->set('custom.auth.token_ttl_minutes', '43200');

        self::assertSame(43200.0, AuthUtil::personalAccessTokenLifetime()->totalMinutes);
    }

    #[Test]
    public function itAcceptsBothBounds(): void
    {
        config()->set('custom.auth.token_ttl_minutes', AuthUtil::MIN_TOKEN_TTL_MINUTES);
        self::assertSame((float) AuthUtil::MIN_TOKEN_TTL_MINUTES, AuthUtil::personalAccessTokenLifetime()->totalMinutes);

        config()->set('custom.auth.token_ttl_minutes', AuthUtil::MAX_TOKEN_TTL_MINUTES);
        self::assertSame((float) AuthUtil::MAX_TOKEN_TTL_MINUTES, AuthUtil::personalAccessTokenLifetime()->totalMinutes);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidLifetimes(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'below the floor' => [AuthUtil::MIN_TOKEN_TTL_MINUTES - 1],
            'above the ceiling' => [AuthUtil::MAX_TOKEN_TTL_MINUTES + 1],
            'not a number' => ['abc'],
            'fractional' => ['60.5'],
            'empty' => [''],
            'unset' => [null],
        ];
    }

    #[Test]
    #[DataProvider('invalidLifetimes')]
    public function itRefusesAnInvalidLifetimeInsteadOfFallingBack(mixed $configured): void
    {
        config()->set('custom.auth.token_ttl_minutes', $configured);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AUTH_TOKEN_TTL_MINUTES');

        AuthUtil::personalAccessTokenLifetime();
    }

}
