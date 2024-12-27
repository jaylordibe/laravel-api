<?php

namespace Tests\Feature;

use App\Constants\ActivityLogTypeConstant;
use App\Constants\AppPlatformConstant;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityFeatureTest extends TestCase
{

    private string $resource = '/api/activities';

    #[Test]
    public function testCreate(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = [
            'type' => fake()->randomElement(ActivityLogTypeConstant::asList()),
            'description' => fake()->sentence,
            'properties' => [
                'platform' => fake()->randomElement(AppPlatformConstant::asList())
            ]
        ];
        $response = $this->withToken($token)->post($this->resource, $payload);

        $expected = [
            'type' => $payload['type'],
            'description' => $payload['description']
        ];
        $response->assertCreated()->assertJson($expected);
    }

    #[Test]
    public function testGetPaginated(): void
    {
        $token = $this->loginSystemAdminUser();

        foreach (range(1, 15) as $index) {
            activity()->log('Activity ' . $index + 1);
        }

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

}
