<?php

namespace Tests\Unit;

use App\Http\Requests\GenericRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every list endpoint takes its page size from BaseRequest::getPerPage. A negative size must
 * never reach the query builder, which silently drops a negative LIMIT and returns the table.
 */
class BaseRequestPerPageTest extends TestCase
{

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function pageSizes(): array
    {
        return [
            'default when absent' => [null, 10],
            'valid value' => [25, 25],
            'maximum' => [1000, 1000],
            'sentinel -1 means the maximum' => [-1, 1000],
            'zero' => [0, 10],
            'negative' => [-2, 10],
            'large negative' => [-1000, 10],
            'above the maximum' => [1001, 10],
            'not a number' => ['abc', 10],
        ];
    }

    #[Test]
    #[DataProvider('pageSizes')]
    public function itKeepsThePageSizeWithinBounds(mixed $perPage, int $expected): void
    {
        $request = GenericRequest::create('/', 'GET', $perPage === null ? [] : ['perPage' => $perPage]);

        self::assertSame($expected, $request->getPerPage());
    }

}
