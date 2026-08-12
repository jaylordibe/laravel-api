<?php

namespace Tests\Unit;

use App\Utils\DatabaseUtil;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the engine-portability helpers used by repository search.
 *
 * The bug these exist for is a silent one: on MySQL a `LIKE` comparison is
 * case-insensitive because of the column collation, and on PostgreSQL it is not.
 * Moving this template to PostgreSQL therefore turned every search into a
 * case-sensitive one with no error anywhere — the query still runs, it just stops
 * matching.
 */
class DatabaseUtilTest extends TestCase
{

    #[Test]
    public function itUsesIlikeOnPostgres(): void
    {
        // The suite runs against the real engine the application targets, so this
        // asserts the actual behaviour rather than a mocked driver name.
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertSame('ilike', DatabaseUtil::caseInsensitiveLikeOperator());
    }

    #[Test]
    public function itEscapesLikeWildcards(): void
    {
        // Unescaped, a search for "_" matches every single character and "%"
        // matches everything — so "no results" silently becomes "the whole table".
        self::assertSame('100\\% off', DatabaseUtil::escapeLikeWildcards('100% off'));
        self::assertSame('a\\_b', DatabaseUtil::escapeLikeWildcards('a_b'));
    }

    #[Test]
    public function itEscapesTheEscapeCharacterFirst(): void
    {
        // Backslash must be doubled before the wildcards are escaped, otherwise a
        // user-supplied backslash escapes the escape this method just added.
        self::assertSame('a\\\\b', DatabaseUtil::escapeLikeWildcards('a\\b'));
    }

    #[Test]
    public function itBuildsAContainsPattern(): void
    {
        self::assertSame('%jay%', DatabaseUtil::containsPattern('jay'));
        self::assertSame('%50\\%%', DatabaseUtil::containsPattern('50%'));
    }

    #[Test]
    public function itLeavesOrdinaryTermsUnchanged(): void
    {
        self::assertSame("o'brien", DatabaseUtil::escapeLikeWildcards("o'brien"));
    }

}
