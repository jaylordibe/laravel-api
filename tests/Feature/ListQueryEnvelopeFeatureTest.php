<?php

namespace Tests\Feature;

use App\Http\Requests\ActivityLogRequest;
use App\Http\Requests\AppVersionRequest;
use App\Http\Requests\BaseRequest;
use App\Http\Requests\DeviceTokenRequest;
use App\Http\Requests\UserRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Every list endpoint accepts exactly its Request's sortable fields — each one must run against the
 * real database — and answers anything outside the query envelope's allowlists with the 400 envelope.
 */
class ListQueryEnvelopeFeatureTest extends TestCase
{

    /**
     * @return array<string, array{string, class-string<BaseRequest>}>
     */
    public static function listEndpoints(): array
    {
        return [
            'activity logs' => ['/api/activity-logs', ActivityLogRequest::class],
            'app versions' => ['/api/app-versions', AppVersionRequest::class],
            'device tokens' => ['/api/device-tokens', DeviceTokenRequest::class],
            'users' => ['/api/users', UserRequest::class],
        ];
    }

    /**
     * The sortable fields a Request declares, read from the Request so the list has one source.
     *
     * @param class-string<BaseRequest> $requestClass
     *
     * @return array<int, string>
     */
    private function sortableFieldsOf(string $requestClass): array
    {
        return (new ReflectionClassConstant($requestClass, 'SORTABLE_FIELDS'))->getValue();
    }

    #[Test]
    #[DataProvider('listEndpoints')]
    public function everySortableFieldSortsInBothDirections(string $path, string $requestClass): void
    {
        $this->actingAsSystemAdmin();

        foreach ($this->sortableFieldsOf($requestClass) as $sortField) {
            foreach (['asc', 'desc'] as $sortDirection) {
                $this
                    ->get("{$path}?sortField={$sortField}&sortDirection={$sortDirection}")
                    ->assertOk()
                    ->assertJsonStructure(['data', 'links', 'meta']);
            }
        }
    }

    #[Test]
    #[DataProvider('listEndpoints')]
    public function anythingOutsideTheEnvelopeIsRejected(string $path, string $requestClass): void
    {
        self::assertNotContains('password', $this->sortableFieldsOf($requestClass));
        $this->actingAsSystemAdmin();
        $rejections = [
            'sortField=password' => 'The requested sort field is not supported.',
            'sortField=' . urlencode('id desc, created_at') => 'The requested sort field is not supported.',
            'sortDirection=sideways' => 'The sort direction must be asc or desc.',
            'columns=id' => 'Column selection is not supported.',
            'relations=createdByUser' => 'The requested relation is not supported.',
        ];

        foreach ($rejections as $query => $message) {
            $this
                ->get("{$path}?{$query}")
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => $message]);
        }
    }

}
