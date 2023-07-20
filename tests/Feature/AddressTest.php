<?php

namespace Tests\Feature;

use App\Models\Address;
use Tests\TestCase;

class AddressTest extends TestCase
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

        $response->assertCreated()->assertJson($payload);
    }

    /**
     * A basic test in getting paginated addresses.
     */
    public function testGetPaginatedAddresses(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}");

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    /**
     * A basic test in getting an address by id.
     */
    public function testGetAddressById(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create();
        $response = $this->withToken($token)->get("{$this->resource}/{$address->getKey()}");

        $response->assertOk()->assertJson(['id' => $response->json()['id']]);
    }

    /**
     * A basic test in updating an address.
     */
    public function testUpdateAddress(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create();
        $payload = $this->getPayload();
        $response = $this->withToken($token)->put("{$this->resource}/{$address->getKey()}", $payload);

        // For assertion
        $payload['id'] = $address->getKey();

        $response->assertOk()->assertJson($payload);
    }

    /**
     * A basic test in deleting an address.
     */
    public function testDeleteAddress(): void
    {
        $token = $this->loginSystemAdminUser();
        $address = Address::factory()->create();
        $response = $this->withToken($token)->delete("{$this->resource}/{$address->getKey()}");

        $response->assertOk()->assertJsonStructure(['success']);
    }

}
