<?php

namespace Tests\Feature;

use App\Data\DeviceTokenData;
use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;
use App\Models\DeviceToken;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * A plain user, authenticated for the following requests.
     *
     * @return User
     */
    private function signedInUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);

        return $user;
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
        $user = $this->signedInUser();
        $payload = $this->payload();

        $response = $this->post($this->resource, $payload);

        $response->assertCreated()->assertJson(['userId' => $user->id] + $payload);
    }

    #[Test]
    public function testGetPaginatedListsOnlyTheCallersTokens(): void
    {
        $user = $this->signedInUser();
        $ownTokens = DeviceToken::factory()->count(3)->create(['user_id' => $user->id]);
        $foreignToken = DeviceToken::factory()->create();

        $response = $this->get($this->resource);

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $ids = collect($response->json('data'))->pluck('id');
        self::assertEqualsCanonicalizing($ownTokens->pluck('id')->all(), $ids->all());
        self::assertNotContains($foreignToken->id, $ids->all());
    }

    #[Test]
    public function aNegativePageSizeStillReturnsABoundedPage(): void
    {
        $user = $this->signedInUser();
        DeviceToken::factory()->count(12)->create(['user_id' => $user->id]);

        $response = $this->get("{$this->resource}?perPage=-2");

        $response->assertOk();
        self::assertCount(10, $response->json('data'));
        self::assertSame(10, $response->json('meta.per_page'));
    }

    #[Test]
    public function testGetPaginatedFiltersByDeviceOs(): void
    {
        $user = $this->signedInUser();
        $ios = DeviceToken::factory()->create(['user_id' => $user->id, 'device_os' => DeviceOs::IOS->value]);
        DeviceToken::factory()->create(['user_id' => $user->id, 'device_os' => DeviceOs::ANDROID->value]);

        $response = $this->get("{$this->resource}?deviceOs=" . DeviceOs::IOS->value);

        $response->assertOk();
        self::assertSame([$ios->id], collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function testGetById(): void
    {
        $user = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->get("{$this->resource}/{$deviceToken->id}")
            ->assertOk()
            ->assertJson(['id' => $deviceToken->id]);
    }

    #[Test]
    public function testUpdate(): void
    {
        $user = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);
        $payload = $this->payload();

        $this->put("{$this->resource}/{$deviceToken->id}", $payload)
            ->assertOk()
            ->assertJson(['id' => $deviceToken->id, 'userId' => $user->id, 'token' => $payload['token']]);
    }

    #[Test]
    public function testDelete(): void
    {
        $user = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->delete("{$this->resource}/{$deviceToken->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('device_tokens', ['id' => $deviceToken->id]);
    }

    #[Test]
    public function anotherUsersTokenCannotBeReadUpdatedOrDeleted(): void
    {
        $this->signedInUser();
        /** @var DeviceToken $victimToken */
        $victimToken = DeviceToken::factory()->create();
        $before = $victimToken->only(['user_id', 'token', 'device_os', 'deleted_at']);
        $uri = "{$this->resource}/{$victimToken->id}";

        // Each answers exactly as for an id that does not exist, so ids cannot be probed.
        $this->get($uri)->assertBadRequest()->assertJson(['message' => 'Device token not found.']);
        $this->put($uri, $this->payload())->assertBadRequest()->assertJson(['message' => 'Failed to update device token.']);
        $this->delete($uri)->assertBadRequest()->assertJson(['message' => 'Failed to delete device token.']);

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
        $this->signedInUser();
        $missingId = (int) DeviceToken::withTrashed()->max('id') + 1000;

        $this->get("{$this->resource}/{$missingId}")
            ->assertBadRequest()
            ->assertJson(['message' => 'Device token not found.']);
    }

    #[Test]
    public function relationsCannotBeLoadedOnDeviceTokens(): void
    {
        // A device token's owner is the caller; nothing about the owner is loaded through this resource.
        $user = $this->signedInUser();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

        foreach ([$this->resource, "{$this->resource}/{$deviceToken->id}"] as $path) {
            $this->get("{$path}?relations=user")
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'The requested relation is not supported.']);
        }
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidDeviceTokenFields(): array
    {
        return [
            'unknown platform' => ['appPlatform', 'windows-phone'],
            'unknown device type' => ['deviceType', 'toaster'],
            'unknown OS' => ['deviceOs', 'beos'],
            'token not a string' => ['token', ['nested']],
            'token missing' => ['token', null],
        ];
    }

    #[Test]
    #[DataProvider('invalidDeviceTokenFields')]
    public function anInvalidFieldIsAValidationErrorAndCreatesNothing(string $field, mixed $value): void
    {
        $this->signedInUser();
        $payload = $this->payload();
        $payload[$field] = $value;

        $this->postJson($this->resource, $payload)
            ->assertBadRequest()
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn (string $message): bool => str_contains(strtolower($message), strtolower(Str::snake($field, ' '))));

        self::assertSame(0, DeviceToken::withTrashed()->count());
    }

}
