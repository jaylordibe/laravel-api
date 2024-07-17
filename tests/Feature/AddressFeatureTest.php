<?php

namespace Tests\Feature;

use App\Models\Address;
use Tests\TestCase;

class AddressFeatureTest extends TestCase
{

    private string $resource = '/api/addresses';

    /**
     * Get address payload.
     *
     * @return array
     */
    private function getPayload(): array
    {
        return [
            'address' => fake()->address(),
            'villageOrBarangay' => fake()->streetAddress(),
            'cityOrMunicipality' => fake()->city(),
            'stateOrProvince' => fake()->address(),
            'zipOrPostalCode' => fake()->postcode(),
            'country' => fake()->country()
        ];
    }

    /**
     * A basic test in creating an address.
     */
    public function testCreateAddress(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = $this->getPayload();
        $response = $this->withToken($token)->post("{$this->resource}", $payload);

        $response->assertOk()->assertJson($payload);
    }

    /**
     * A basic test in getting paginated addresses.
     */
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

    /**
     * A basic test in getting an address by id.
     */
    public function testGetAddressById(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create([
            'user_id' => $this->getAuthUser($token)->id
        ]);
        $response = $this->withToken($token)->get("{$this->resource}/{$address->id}");

        $response->assertOk()->assertJson(['id' => $response->json('id')]);
    }

    /**
     * A basic test in updating an address.
     */
    public function testUpdateAddress(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create([
            'user_id' => $this->getAuthUser($token)->id
        ]);
        $payload = $this->getPayload();
        $response = $this->withToken($token)->put("{$this->resource}/{$address->id}", $payload);

        // For assertion
        $payload['id'] = $address->id;

        $response->assertOk()->assertJson($payload);
    }

    /**
     * A basic test in deleting an address.
     */
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
