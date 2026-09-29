<?php

namespace Tests\Unit;

use App\Exceptions\BadRequestException;
use App\Http\Requests\ActivityLogRequest;
use App\Http\Requests\BaseRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\UserRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The query envelope (relations, columns, sort) is client input that reaches Eloquent. Only values a
 * request explicitly allows may pass; everything else is rejected here, before any query is built.
 */
class BaseRequestMetaTest extends TestCase
{

    /**
     * Build a request of the given class carrying the given query.
     *
     * @param class-string<BaseRequest> $requestClass
     * @param array<string, mixed> $query
     *
     * @return BaseRequest
     */
    private function makeRequest(string $requestClass, array $query): BaseRequest
    {
        return $requestClass::create('/', 'GET', $query);
    }

    /**
     * Assert that reading the value throws the 400 envelope's exception with the given message.
     *
     * @param callable $read
     * @param string $message
     *
     * @return void
     */
    private function assertRejected(callable $read, string $message): void
    {
        try {
            $read();
        } catch (BadRequestException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame(400, $exception->getCode());

            return;
        }

        self::fail("Expected a BadRequestException: {$message}");
    }

    #[Test]
    public function noRelationsAreLoadedWhenNoneAreRequested(): void
    {
        self::assertSame([], $this->makeRequest(GenericRequest::class, [])->getRelations());
        self::assertSame([], $this->makeRequest(GenericRequest::class, ['relations' => ''])->getRelations());
        self::assertSame([], $this->makeRequest(GenericRequest::class, ['relations' => []])->getRelations());
    }

    #[Test]
    public function anAllowedRelationIsReturnedByName(): void
    {
        self::assertSame(['causer'], $this->makeRequest(ActivityLogRequest::class, ['relations' => 'causer'])->getRelations());
        self::assertSame(['causer'], $this->makeRequest(ActivityLogRequest::class, ['relations' => ['causer']])->getRelations());
        self::assertSame(['causer'], $this->makeRequest(ActivityLogRequest::class, ['relations' => 'causer|causer'])->getRelations());
    }

    /**
     * @return array<string, array{class-string<BaseRequest>, mixed}>
     */
    public static function disallowedRelations(): array
    {
        return [
            'real relation, not allowed by default' => [GenericRequest::class, 'createdByUser'],
            'real relation, not allowed for users' => [UserRequest::class, 'roles'],
            'unknown name' => [ActivityLogRequest::class, 'doesNotExist'],
            'model method that is not a relation' => [ActivityLogRequest::class, 'save'],
            'another model method' => [GenericRequest::class, 'newQuery'],
            'nested path' => [ActivityLogRequest::class, 'causer.roles'],
            'column suffix' => [ActivityLogRequest::class, 'causer:id,email'],
            'column suffix with an alias' => [GenericRequest::class, 'createdByUser:id,password as address'],
            'case variant' => [ActivityLogRequest::class, 'Causer'],
            'allowed name padded' => [ActivityLogRequest::class, ' causer'],
            'one bad name among good ones' => [ActivityLogRequest::class, 'causer|subject'],
            'empty segment' => [ActivityLogRequest::class, 'causer|'],
            'array with a non-string' => [ActivityLogRequest::class, [['causer']]],
            'keyed array' => [ActivityLogRequest::class, ['causer' => 'save']],
        ];
    }

    #[Test]
    #[DataProvider('disallowedRelations')]
    public function aRelationOutsideTheAllowlistIsRejected(string $requestClass, mixed $relations): void
    {
        $request = $this->makeRequest($requestClass, ['relations' => $relations]);

        $this->assertRejected(fn () => $request->getRelations(), 'The requested relation is not supported.');
        $this->assertRejected(fn () => $request->getMetaData(), 'The requested relation is not supported.');
    }

    #[Test]
    public function allColumnsAreSelectedWhenNoneAreRequested(): void
    {
        self::assertSame(['*'], $this->makeRequest(GenericRequest::class, [])->getColumns());
        self::assertSame(['*'], $this->makeRequest(GenericRequest::class, [])->getMetaData()->columns);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function columnSelections(): array
    {
        return [
            'single column' => ['id'],
            'pipe-separated' => ['id|email'],
            'aliased expression' => ['password as address'],
            'array' => [['id']],
        ];
    }

    #[Test]
    #[DataProvider('columnSelections')]
    public function columnSelectionIsRejected(mixed $columns): void
    {
        $request = $this->makeRequest(UserRequest::class, ['columns' => $columns]);

        $this->assertRejected(fn () => $request->getColumns(), 'Column selection is not supported.');
        $this->assertRejected(fn () => $request->getMetaData(), 'Column selection is not supported.');
    }

    #[Test]
    public function sortingDefaultsToNewestFirst(): void
    {
        $meta = $this->makeRequest(GenericRequest::class, [])->getMetaData();

        self::assertSame('created_at', $meta->sortField);
        self::assertSame('desc', $meta->sortDirection);

        $blank = $this->makeRequest(GenericRequest::class, ['sortField' => '', 'sortDirection' => ''])->getMetaData();

        self::assertSame('created_at', $blank->sortField);
        self::assertSame('desc', $blank->sortDirection);
    }

    #[Test]
    public function anAllowedSortFieldAndDirectionAreAccepted(): void
    {
        $meta = $this->makeRequest(UserRequest::class, ['sortField' => 'last_name', 'sortDirection' => 'ASC'])->getMetaData();

        self::assertSame('last_name', $meta->sortField);
        self::assertSame('asc', $meta->sortDirection);
    }

    /**
     * @return array<string, array{class-string<BaseRequest>, mixed}>
     */
    public static function disallowedSortFields(): array
    {
        return [
            'hidden column' => [UserRequest::class, 'password'],
            'column of another resource' => [GenericRequest::class, 'last_name'],
            'unknown column' => [UserRequest::class, 'doesNotExist'],
            'qualified column' => [UserRequest::class, 'users.last_name'],
            'expression' => [UserRequest::class, 'last_name desc, id'],
            'array' => [UserRequest::class, ['last_name']],
        ];
    }

    #[Test]
    #[DataProvider('disallowedSortFields')]
    public function aSortFieldOutsideTheAllowlistIsRejected(string $requestClass, mixed $sortField): void
    {
        $request = $this->makeRequest($requestClass, ['sortField' => $sortField]);

        $this->assertRejected(fn () => $request->getSortField(), 'The requested sort field is not supported.');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidSortDirections(): array
    {
        return [
            'unknown word' => ['sideways'],
            'expression' => ['desc, id'],
            'array' => [['desc']],
        ];
    }

    #[Test]
    #[DataProvider('invalidSortDirections')]
    public function aSortDirectionOtherThanAscOrDescIsRejected(mixed $sortDirection): void
    {
        $request = $this->makeRequest(GenericRequest::class, ['sortDirection' => $sortDirection]);

        $this->assertRejected(fn () => $request->getSortDirection(), 'The sort direction must be asc or desc.');
    }

}
