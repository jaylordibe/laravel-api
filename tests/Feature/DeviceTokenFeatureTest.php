<?php

namespace Tests\Feature;

use App\Constants\AppPlatformConstant;
use App\Constants\DeviceOsConstant;
use App\Constants\DeviceTypeConstant;
use App\Models\DeviceToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceTokenFeatureTest extends TestCase
{

    private string $resource = '/api/device-tokens';

    #[Test]
    public function testCreate(): void
    {
        $token = $this->loginSystemAdminUser();
        $authUser = $this->getAuthUser($token);
        $payload = [
            'token' => fake()->sha256(),
            'appPlatform' => fake()->randomElement(AppPlatformConstant::asList()),
            'deviceType' => fake()->randomElement(DeviceTypeConstant::asList()),
            'deviceOs' => fake()->randomElement(DeviceOsConstant::asList()),
            'deviceOsVersion' => fake()->numerify('##.##.##')
        ];
        $response = $this->withToken($token)->post($this->resource, $payload);

        $expected = [
            'userId' => $authUser->id,
            'token' => $payload['token'],
            'appPlatform' => $payload['appPlatform'],
            'deviceType' => $payload['deviceType'],
            'deviceOs' => $payload['deviceOs'],
            'deviceOsVersion' => $payload['deviceOsVersion']
        ];
        $response->assertCreated()->assertJson($expected);
    }

    #[Test]
    public function testGetPaginated(): void
    {
        $token = $this->loginSystemAdminUser();
        DeviceToken::factory()->count(15)->create();
        $response = $this->withToken($token)->get($this->resource);

        $expected = [
            'data',
            'links',
            'meta'
        ];
        $response->assertOk()->assertJsonStructure($expected);

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
    public function testGetById(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var DeviceToken $deviceToken */
        $deviceToken = DeviceToken::factory()->create();
        $response = $this->withToken($token)->get("{$this->resource}/{$deviceToken->id}");

        $expected = [
            'id' => $deviceToken->id
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testUpdate(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var DeviceToken $deviceToken */
        $deviceToken = DeviceToken::factory()->create();
        $payload = [
            'token' => fake()->sha256(),
            'appPlatform' => fake()->randomElement(AppPlatformConstant::asList()),
            'deviceType' => fake()->randomElement(DeviceTypeConstant::asList()),
            'deviceOs' => fake()->randomElement(DeviceOsConstant::asList()),
            'deviceOsVersion' => fake()->numerify('##.##.##')
        ];
        $response = $this->withToken($token)->put("{$this->resource}/{$deviceToken->id}", $payload);

        // For assertion
        $expected = [
            'id' => $deviceToken->id
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testDelete(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var DeviceToken $deviceToken */
        $deviceToken = DeviceToken::factory()->create();
        $response = $this->withToken($token)->delete("{$this->resource}/{$deviceToken->id}");

        $expected = [
            'success'
        ];
        $response->assertOk()->assertJsonStructure($expected);
    }

}
