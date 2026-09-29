<?php

namespace Tests\Feature;

use App\Enums\AppPlatform;
use App\Models\AppVersion;
use App\Models\User;
use App\Utils\AppUtil;
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
        // Unique per run, so a row left behind by a broken run can never satisfy this test's check.
        $attackerUrl = 'https://attacker.example/' . AppUtil::generateUniqueToken();
        $payload = [
            'version' => '99.99.99',
            'platform' => $appVersion->platform->value,
            // In the past: should a regression ever let this through, the leaked row can never become `latest`.
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

}
