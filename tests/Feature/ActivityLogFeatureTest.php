<?php

namespace Tests\Feature;

use App\Enums\ActivityLogType;
use App\Enums\AppPlatform;
use App\Enums\UserRole;
use App\Models\AppVersion;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLogFeatureTest extends TestCase
{

    private string $resource = '/api/activity-logs';

    #[Test]
    public function testCreate(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = [
            'logName' => fake()->randomElement(ActivityLogType::cases())->value,
            'description' => fake()->sentence,
            'properties' => [
                'platform' => fake()->randomElement(AppPlatform::cases())->value
            ]
        ];
        $response = $this->withToken($token)->post($this->resource, $payload);

        $expected = [
            'logName' => $payload['logName'],
            'description' => $payload['description']
        ];
        $response->assertCreated()->assertJson($expected);
    }

    /**
     * Log $count activities caused by $user and return their ids.
     *
     * @param User $user
     * @param int $count
     *
     * @return array<int>
     */
    private function logActivitiesFor(User $user, int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): int => activity()->causedBy($user)->log("Activity {$index}")->id)
            ->all();
    }

    /**
     * The ids returned by a list request.
     *
     * @param string $token
     * @param string $query
     *
     * @return array<int>
     */
    private function listedIds(string $token, string $query = ''): array
    {
        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->get("{$this->resource}?perPage=-1{$query}");
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);

        return collect($response->json('data'))->pluck('id')->all();
    }

    #[Test]
    public function testGetPaginatedListsOnlyTheCallersActivity(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $ownIds = $this->logActivitiesFor($user, 3);
        $otherIds = $this->logActivitiesFor($otherUser, 2);
        $token = $this->login($user->email);

        // Omitted, 0 and a non-number all mean "my own" — never "everyone's".
        foreach (['', '&userId=0', '&userId=abc', "&userId={$user->id}"] as $query) {
            $ids = $this->listedIds($token, $query);
            self::assertEqualsCanonicalizing($ownIds, $ids, "Query '{$query}' must list only the caller's activity.");
            self::assertEmpty(array_intersect($otherIds, $ids));
        }
    }

    #[Test]
    public function activityCreatedThroughTheApiAppearsInTheCallersOwnList(): void
    {
        // The API records the causer by id; the list filters on the User causer type — both ends
        // must agree, or a user's own activity would silently vanish from their list.
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);

        $this->forgetAuthenticatedUsers();
        $createdId = $this->withToken($token)->post($this->resource, [
            'logName' => fake()->randomElement(ActivityLogType::cases())->value,
            'description' => fake()->sentence,
            'properties' => ['platform' => fake()->randomElement(AppPlatform::cases())->value]
        ])->assertCreated()->json('id');

        self::assertSame([$createdId], $this->listedIds($token));
    }

    #[Test]
    public function activityCausedByANonUserWithTheSameIdIsNotListed(): void
    {
        // causer_id alone is not an owner: another model type can carry the same number.
        /** @var User $user */
        $user = User::factory()->create();
        [$ownId] = $this->logActivitiesFor($user, 1);
        $foreign = activity()->log('Caused by something that is not a user');
        $foreign->causer_type = AppVersion::class;
        $foreign->causer_id = $user->id;
        $foreign->save();
        $token = $this->login($user->email);

        self::assertSame([$ownId], $this->listedIds($token));
    }

    #[Test]
    public function aUserWithoutPermissionCannotListAnotherUsersActivity(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $this->logActivitiesFor($otherUser, 2);
        $token = $this->login($user->email);

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->get("{$this->resource}?userId={$otherUser->id}")->assertForbidden();
    }

    #[Test]
    public function anAdminCanListAnotherUsersActivity(): void
    {
        /** @var User $admin */
        $admin = User::factory()->withRole(UserRole::SYSTEM_ADMIN)->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $otherIds = $this->logActivitiesFor($otherUser, 2);
        $token = $this->login($admin->email);

        self::assertEqualsCanonicalizing($otherIds, $this->listedIds($token, "&userId={$otherUser->id}"));
    }

    #[Test]
    public function theCauserCanBeLoadedAndIsRenderedAsAUser(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->logActivitiesFor($user, 1);
        $token = $this->login($user->email);

        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->get("{$this->resource}?relations=causer")->assertOk();

        $item = $response->json('data.0');
        self::assertSame($user->id, $item['user']['id']);
        self::assertSame($user->email, $item['user']['email']);
        self::assertArrayNotHasKey('causer', $item);
        self::assertArrayNotHasKey('password', $item['user']);
    }

    #[Test]
    public function onlyTheCauserRelationCanBeLoaded(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);

        foreach (['subject', 'causer.roles', 'causer:id,email'] as $relations) {
            $this->forgetAuthenticatedUsers();
            $this->withToken($token)->get("{$this->resource}?relations=" . urlencode($relations))
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'The requested relation is not supported.']);
        }
    }

    #[Test]
    public function anUnparseableDateFilterIsABadRequest(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);

        foreach (['startDate', 'endDate'] as $filter) {
            foreach (["{$filter}=not-a-date", "{$filter}[]=2024-01-01"] as $query) {
                $this->forgetAuthenticatedUsers();
                $this->withToken($token)->getJson("{$this->resource}?{$query}")
                    ->assertBadRequest()
                    ->assertExactJson(['success' => false, 'message' => "The {$filter} must be a valid date."]);
            }
        }
    }

}
