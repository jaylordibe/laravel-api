<?php

namespace Tests\Feature;

use App\Models\Address;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddressFeatureTest extends TestCase
{

    private string $resource = '/api/addresses';

    #[Test]
    public function testCreateAddress(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = [
            'address' => fake()->address(),
            'villageOrBarangay' => fake()->streetAddress(),
            'cityOrMunicipality' => fake()->city(),
            'stateOrProvince' => fake()->address(),
            'zipOrPostalCode' => fake()->postcode(),
            'country' => fake()->country()
        ];
        $response = $this->withToken($token)->post("{$this->resource}", $payload);

        $response->assertOk()->assertJson($payload);
    }

    #[Test]
    public function testGetPaginatedAddresses(): void
    {
        $token = $this->loginSystemAdminUser();
        Address::factory()->count(15)->create([
            'user_id' => $this->getAuthUser($token)->id
        ]);
        $response = $this->withToken($token)->get("{$this->resource}");
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
    public function testGetAddressById(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create([
            'user_id' => $this->getAuthUser($token)->id
        ]);
        $response = $this->withToken($token)->get("{$this->resource}/{$address->id}");

        $response->assertOk()->assertJson(['id' => $response->json('id')]);
    }

    #[Test]
    public function testUpdateAddress(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create([
            'user_id' => $this->getAuthUser($token)->id
        ]);
        $payload = [
            'address' => fake()->address(),
            'villageOrBarangay' => fake()->streetAddress(),
            'cityOrMunicipality' => fake()->city(),
            'stateOrProvince' => fake()->address(),
            'zipOrPostalCode' => fake()->postcode(),
            'country' => fake()->country()
        ];
        $response = $this->withToken($token)->put("{$this->resource}/{$address->id}", $payload);

        // For assertion
        $payload['id'] = $address->id;

        $response->assertOk()->assertJson($payload);
    }

    #[Test]
    public function testDeleteAddress(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create([
            'user_id' => $this->getAuthUser($token)->id
        ]);
        $response = $this->withToken($token)->delete("{$this->resource}/{$address->id}");

        $response->assertOk()->assertJsonStructure(['success']);
    }

}
