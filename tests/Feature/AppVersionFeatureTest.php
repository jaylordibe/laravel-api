<?php

namespace Tests\Feature;

use App\Constants\AppVersionPlatformConstant;
use App\Models\AppVersion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppVersionFeatureTest extends TestCase
{

    private string $resource = '/api/app-versions';

    /**
     * Get app version payload.
     *
     * @return array
     */
    private function getPayload(): array
    {
        return [
            'version' => fake()->unique()->numerify('##.##.##'),
            'description' => fake()->text(),
            'platform' => fake()->randomElement(AppVersionPlatformConstant::asList()),
            'releaseDate' => now()->millisecond(0)->toISOString(),
            'downloadUrl' => fake()->url(),
            'forceUpdate' => fake()->boolean()
        ];
    }

    #[Test]
    public function testCreateAppVersion(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = $this->getPayload();
        $response = $this->withToken($token)->post($this->resource, $payload);

        $response->assertOk()->assertJson($payload);
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
        $appVersion = AppVersion::factory()->create(['release_date' => now()->addMonth()]);
        $response = $this->get("{$this->resource}/latest?platform={$appVersion->platform}");

        $response->assertOk()->assertJson(['id' => $appVersion->id]);
    }

    #[Test]
    public function testUpdateAppVersion(): void
    {
        $token = $this->loginSystemAdminUser();
        $appVersion = AppVersion::factory()->create();
        $payload = $this->getPayload();
        $payload['version'] = $appVersion->version;
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
