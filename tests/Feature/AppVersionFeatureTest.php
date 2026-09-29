<?php

namespace Tests\Feature;

use App\Enums\AppPlatform;
use App\Models\AppVersion;
use App\Models\User;
use App\Utils\AppUtil;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppVersionFeatureTest extends TestCase
{

    private string $resource = '/api/app-versions';

    #[Test]
    public function testCreateAppVersion(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = [
            'version' => fake()->unique()->numerify('##.##.##'),
            'description' => fake()->text(),
            'platform' => fake()->randomElement(AppPlatform::cases())->value,
            'releaseDate' => now()->millisecond(0)->toISOString(),
            'downloadUrl' => fake()->url(),
            'forceUpdate' => fake()->boolean()
        ];
        $response = $this->withToken($token)->post($this->resource, $payload);

        $response->assertCreated()->assertJson($payload);
    }

    /**
     * Publishing a release is an admin action: `latest` is public, so a planted version with a
     * future release date and forceUpdate would reach every client.
     */
    #[Test]
    public function aUserWithoutPermissionCannotCreateUpdateOrDeleteAppVersions(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        /** @var AppVersion $appVersion */
        $appVersion = AppVersion::factory()->create();
        $before = $appVersion->only(['version', 'force_update', 'download_url', 'deleted_at']);
        $attackerUrl = 'https://attacker.example/' . AppUtil::generateUniqueToken();
        $payload = [
            'version' => '99.99.99',
            'platform' => $appVersion->platform->value,
            // In the past, so even a regression that let it through could never become `latest`.
            'releaseDate' => now()->subYears(10)->millisecond(0)->toISOString(),
            'downloadUrl' => $attackerUrl,
            'forceUpdate' => true
        ];

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->post($this->resource, $payload)->assertForbidden();
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->put("{$this->resource}/{$appVersion->id}", $payload)->assertForbidden();
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->delete("{$this->resource}/{$appVersion->id}")->assertForbidden();

        self::assertSame($before, $appVersion->refresh()->only(['version', 'force_update', 'download_url', 'deleted_at']));
        self::assertFalse(AppVersion::withTrashed()->where('download_url', $attackerUrl)->exists());
    }

    #[Test]
    public function testGetPaginatedAppVersions(): void
    {
        $token = $this->loginSystemAdminUser();
        AppVersion::factory()->count(15)->create();
        $response = $this->withToken($token)->get($this->resource);

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $links = $response->json('links');
        $this->assertIsArray($links);
        $this->assertNotEmpty($links);

        $meta = $response->json('meta');
        $this->assertIsArray($meta);
        $this->assertNotEmpty($meta);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedRelations(): array
    {
        return [
            'audit relation' => ['createdByUser'],
            'aliased relation columns' => [urlencode('createdByUser:id,password as address')],
            'model method as a relation' => ['save'],
        ];
    }

    /**
     * An unsupported relation is a 400 in the standard envelope — never a 500 — on the list and on a
     * single record, and nothing it names is loaded, changed or echoed back.
     */
    #[Test]
    #[DataProvider('unsupportedRelations')]
    public function anUnsupportedRelationIsRejected(string $relations): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var AppVersion $appVersion */
        $appVersion = AppVersion::factory()->create();

        foreach ([$this->resource, "{$this->resource}/{$appVersion->id}"] as $path) {
            $this->forgetAuthenticatedUsers();
            $this->withToken($token)
                ->get("{$path}?relations={$relations}")
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'The requested relation is not supported.']);
        }

        self::assertNull($appVersion->refresh()->deleted_at);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsupportedListQueries(): array
    {
        return [
            'column selection' => ['columns=' . urlencode('id|created_by as version'), 'Column selection is not supported.'],
            'unknown sort field' => ['sortField=download_url', 'The requested sort field is not supported.'],
            'bad sort direction' => ['sortDirection=sideways', 'The sort direction must be asc or desc.'],
        ];
    }

    #[Test]
    #[DataProvider('unsupportedListQueries')]
    public function anUnsupportedListQueryIsRejected(string $query, string $message): void
    {
        $token = $this->loginSystemAdminUser();

        $this->withToken($token)
            ->get("{$this->resource}?{$query}")
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => $message]);
    }

    /**
     * Three app versions with distinct creation and release times, oldest first.
     *
     * @return array<int>
     */
    private function createDatedAppVersions(): array
    {
        return collect([3, 2, 1])
            ->map(fn (int $daysAgo): int => AppVersion::factory()->create([
                'created_at' => now()->subDays($daysAgo),
                'release_date' => now()->subDays($daysAgo)
            ])->id)
            ->all();
    }

    /**
     * The given ids in the order a list request returns them.
     *
     * @param string $token
     * @param string $query
     * @param array<int> $ids
     *
     * @return array<int>
     */
    private function listedOrderOf(string $token, string $query, array $ids): array
    {
        $this->forgetAuthenticatedUsers();

        return collect($this->withToken($token)->get("{$this->resource}?perPage=-1{$query}")->assertOk()->json('data'))
            ->pluck('id')
            ->intersect($ids)
            ->values()
            ->all();
    }

    #[Test]
    public function aListCanBeSortedByAnAllowedFieldInEitherDirection(): void
    {
        $token = $this->loginSystemAdminUser();
        $oldestFirst = $this->createDatedAppVersions();

        self::assertSame($oldestFirst, $this->listedOrderOf($token, '&sortField=release_date&sortDirection=asc', $oldestFirst));
        self::assertSame(array_reverse($oldestFirst), $this->listedOrderOf($token, '&sortField=release_date&sortDirection=DESC', $oldestFirst));
    }

    #[Test]
    public function aListIsNewestFirstByDefault(): void
    {
        $token = $this->loginSystemAdminUser();
        $oldestFirst = $this->createDatedAppVersions();

        self::assertSame(array_reverse($oldestFirst), $this->listedOrderOf($token, '', $oldestFirst));
    }

    #[Test]
    public function aWriteCarryingAnUnsupportedQueryValueIsRejectedAndChangesNothing(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var AppVersion $appVersion */
        $appVersion = AppVersion::factory()->create();
        $before = $appVersion->only(['version', 'description', 'download_url']);
        $payload = [
            'version' => $appVersion->version,
            'description' => 'Changed by a rejected request',
            'platform' => $appVersion->platform->value,
            'releaseDate' => now()->millisecond(0)->toISOString(),
            'forceUpdate' => false
        ];

        $this->withToken($token)->put("{$this->resource}/{$appVersion->id}?relations=createdByUser", $payload)
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'The requested relation is not supported.']);
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->put("{$this->resource}/{$appVersion->id}", $payload + ['columns' => 'id'])
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'Column selection is not supported.']);

        self::assertSame($before, $appVersion->refresh()->only(['version', 'description', 'download_url']));
    }

    #[Test]
    public function testGetAppVersionById(): void
    {
        $token = $this->loginSystemAdminUser();
        $appVersion = AppVersion::factory()->create();
        $response = $this->withToken($token)->get("{$this->resource}/{$appVersion->id}");

        $response->assertOk()->assertJson(['id' => $appVersion->id]);
    }

    #[Test]
    public function testGetLatestAppVersionByPlatform(): void
    {
        /** @var AppVersion $appVersion */
        $appVersion = AppVersion::factory()->create([
            'release_date' => now()->addMonth()
        ]);
        $response = $this->get("{$this->resource}/latest?platform={$appVersion->platform->value}");

        $response->assertOk()->assertJson(['id' => $appVersion->id]);
    }

    #[Test]
    public function testUpdateAppVersion(): void
    {
        $token = $this->loginSystemAdminUser();
        $appVersion = AppVersion::factory()->create();
        $payload = [
            'version' => $appVersion->version,
            'description' => fake()->text(),
            'platform' => fake()->randomElement(AppPlatform::cases())->value,
            'releaseDate' => now()->millisecond(0)->toISOString(),
            'downloadUrl' => fake()->url(),
            'forceUpdate' => fake()->boolean()
        ];
        $response = $this->withToken($token)->put("{$this->resource}/{$appVersion->id}", $payload);

        // For assertion
        $payload['id'] = $appVersion->id;

        $response->assertOk()->assertJson($payload);
    }

    #[Test]
    public function testDeleteAppVersion(): void
    {
        $token = $this->loginSystemAdminUser();
        $appVersion = AppVersion::factory()->create();
        $response = $this->withToken($token)->delete("{$this->resource}/{$appVersion->id}");

        $response->assertOk()->assertJsonStructure(['success']);
    }

    #[Test]
    public function paginationReportsExactTotalsAndPages(): void
    {
        // Each test runs in its own rolled-back transaction, so these are the only app versions.
        $token = $this->loginSystemAdminUser();
        $oldestFirst = collect(range(25, 1))
            ->map(fn (int $daysAgo): int => AppVersion::factory()->create(['created_at' => now()->subDays($daysAgo)])->id)
            ->all();

        $response = $this->withToken($token)->getJson("{$this->resource}?perPage=10&page=3")->assertOk();

        self::assertSame(25, $response->json('meta.total'));
        self::assertSame(10, $response->json('meta.per_page'));
        self::assertSame(3, $response->json('meta.current_page'));
        self::assertSame(3, $response->json('meta.last_page'));
        // Newest first, so the last page holds the five oldest.
        self::assertSame(array_reverse(array_slice($oldestFirst, 0, 5)), collect($response->json('data'))->pluck('id')->all());
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidAppVersionFields(): array
    {
        return [
            'unknown platform' => ['platform', 'windows-phone'],
            'release date not a date' => ['releaseDate', 'soon'],
            'release date not UTC ISO' => ['releaseDate', '2026-01-01 10:00:00'],
            'force update not a boolean' => ['forceUpdate', 'sometimes'],
            'download URL not a URL' => ['downloadUrl', 'not a url'],
            'version missing' => ['version', null],
        ];
    }

    #[Test]
    #[DataProvider('invalidAppVersionFields')]
    public function anInvalidFieldIsAValidationErrorAndCreatesNothing(string $field, mixed $value): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = [
            'version' => '1.2.3',
            'platform' => fake()->randomElement(AppPlatform::cases())->value,
            'releaseDate' => now()->millisecond(0)->toISOString(),
            'forceUpdate' => false
        ];
        $payload[$field] = $value;

        $this->withToken($token)->postJson($this->resource, $payload)
            ->assertBadRequest()
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn (string $message): bool => str_contains(strtolower($message), strtolower(Str::snake($field, ' '))));

        self::assertSame(0, AppVersion::withTrashed()->count());
    }

    #[Test]
    public function anUnparseableReleaseDateFilterIsABadRequest(): void
    {
        $token = $this->loginSystemAdminUser();

        foreach (['releaseDateStart' => 'releaseDateStart=soon', 'releaseDateEnd' => 'releaseDateEnd[]=2024-01-01'] as $filter => $query) {
            $this->forgetAuthenticatedUsers();
            $this->withToken($token)->getJson("{$this->resource}?{$query}")
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => "The {$filter} must be a valid date."]);
        }
    }

}
