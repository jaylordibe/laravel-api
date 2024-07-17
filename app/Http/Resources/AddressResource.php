<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'userId' => $this->user_id,
            'address' => $this->address,
            'villageOrBarangay' => $this->village_or_barangay,
            'cityOrMunicipality' => $this->city_or_municipality,
            'stateOrProvince' => $this->state_or_province,
            'zipOrPostalCode' => $this->zip_or_postal_code,
            'country' => $this->country,
            'completeAddress' => $this->complete_address
        ];
    }

}
