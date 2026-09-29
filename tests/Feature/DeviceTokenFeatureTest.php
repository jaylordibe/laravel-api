<?php

namespace Tests\Feature;

use App\Data\DeviceTokenData;
use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;
use App\Models\DeviceToken;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Device tokens belong to one user. Every read and write is scoped to the caller, and another
 * user's token must answer exactly like one that does not exist — so the tests act as plain
 * users, never as an admin that could mask a missing owner check.
 */
class DeviceTokenFeatureTest extends TestCase
{

    private string $resource = '/api/device-tokens';

    /**
     * A signed-in plain user and their token.
     *
     * @return array{User, string}
     */
    private function signedInUser(): array
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->forgetAuthenticatedUsers();

        return [$user, $this->login($user->email)];
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'token' => fake()->sha256(),
            'appPlatform' => fake()->randomElement(AppPlatform::cases())->value,
            'deviceType' => fake()->randomElement(DeviceType::cases())->value,
            'deviceOs' => fake()->randomElement(DeviceOs::cases())->value,
            'deviceOsVersion' => fake()->numerify('##.##.##')
        ];
    }

    #[Test]
    public function testCreate(): void
    {
        [$user, $token] = $this->signedInUser();
        $payload = $this->payload();

        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->post($this->resource, $payload);

        $response->assertCreated()->assertJson(['userId' => $user->id] + $payload);
    }

    #[Test]
    public function testGetPaginatedListsOnlyTheCallersTokens(): void
    {
        [$user, $token] = $this->signedInUser();
        $ownTokens = DeviceToken::factory()->count(3)->create(['user_id' => $user->id]);
        $foreignToken = DeviceToken::factory()->create();

        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->get($this->resource);

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $ids = collect($response->json('data'))->pluck('id');
        self::assertEqualsCanonicalizing($ownTokens->pluck('id')->all(), $ids->all());
        self::assertNotContains($foreignToken->id, $ids->all());
    }

    #[Test]
    public function aNegativePageSizeStillReturnsABoundedPage(): void
    {
        [$user, $token] = $this->signedInUser();
        DeviceToken::factory()->count(12)->create(['user_id' => $user->id]);

        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->get("{$this->resource}?perPage=-2");

        $response->assertOk();
        self::assertCount(10, $response->json('data'));
        self::assertSame(10, $response->json('meta.per_page'));
    }

    #[Test]
    public function testGetPaginatedFiltersByDeviceOs(): void
    {
        [$user, $token] = $this->signedInUser();
        $ios = DeviceToken::factory()->create(['user_id' => $user->id, 'device_os' => DeviceOs::IOS->value]);
        DeviceToken::factory()->create(['user_id' => $user->id, 'device_os' => DeviceOs::ANDROID->value]);

        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->get("{$this->resource}?deviceOs=" . DeviceOs::IOS->value);

        $response->assertOk();
        self::assertSame([$ios->id], collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function testGetById(): void
    {
        [$user, $token] = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->get("{$this->resource}/{$deviceToken->id}")
            ->assertOk()
            ->assertJson(['id' => $deviceToken->id]);
    }

    #[Test]
    public function testUpdate(): void
    {
        [$user, $token] = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);
        $payload = $this->payload();

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->put("{$this->resource}/{$deviceToken->id}", $payload)
            ->assertOk()
            ->assertJson(['id' => $deviceToken->id, 'userId' => $user->id, 'token' => $payload['token']]);
    }

    #[Test]
    public function testDelete(): void
    {
        [$user, $token] = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->delete("{$this->resource}/{$deviceToken->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('device_tokens', ['id' => $deviceToken->id]);
    }

    #[Test]
    public function anotherUsersTokenCannotBeReadUpdatedOrDeleted(): void
    {
        [, $token] = $this->signedInUser();
        /** @var DeviceToken $victimToken */
        $victimToken = DeviceToken::factory()->create();
        $before = $victimToken->only(['user_id', 'token', 'device_os', 'deleted_at']);
        $uri = "{$this->resource}/{$victimToken->id}";

        // Each answers exactly as for an id that does not exist, so ids cannot be probed.
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->get($uri)->assertBadRequest()->assertJson(['message' => 'Device token not found.']);
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->put($uri, $this->payload())->assertBadRequest()->assertJson(['message' => 'Failed to update device token.']);
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->delete($uri)->assertBadRequest()->assertJson(['message' => 'Failed to delete device token.']);

        self::assertSame($before, $victimToken->refresh()->only(['user_id', 'token', 'device_os', 'deleted_at']));
    }

    #[Test]
    public function savingAnExistingTokenNeverChangesItsOwner(): void
    {
        // Defence in depth behind the owner-scoped lookup: even handed another user's id, an
        // update must not move a token to them.
        /** @var DeviceToken $deviceToken */
        $deviceToken = DeviceToken::factory()->create();
        $originalOwnerId = $deviceToken->user_id;
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        app(DeviceTokenRepository::class)->save(new DeviceTokenData(
            userId: $otherUser->id,
            token: fake()->sha256(),
            appPlatform: AppPlatform::MOBILE,
            deviceType: DeviceType::cases()[0],
            deviceOs: DeviceOs::ANDROID,
            deviceOsVersion: '1.0.0'
        ), $deviceToken);

        self::assertSame($originalOwnerId, $deviceToken->refresh()->user_id);
    }

    #[Test]
    public function aMissingTokenAnswersLikeAnotherUsersToken(): void
    {
        [, $token] = $this->signedInUser();
        $missingId = (int) DeviceToken::withTrashed()->max('id') + 1000;

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->get("{$this->resource}/{$missingId}")
            ->assertBadRequest()
            ->assertJson(['message' => 'Device token not found.']);
    }

    #[Test]
    public function relationsCannotBeLoadedOnDeviceTokens(): void
    {
        // A device token's owner is the caller; nothing about the owner is loaded through this resource.
        [$user, $token] = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

        foreach ([$this->resource, "{$this->resource}/{$deviceToken->id}"] as $path) {
            $this->forgetAuthenticatedUsers();
            $this->withToken($token)->get("{$path}?relations=user")
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'The requested relation is not supported.']);
        }
    }

}
