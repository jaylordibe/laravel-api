<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'status' => $this->status,
            'firstName' => $this->first_name,
            'middleName' => $this->middle_name,
            'lastName' => $this->last_name,
            'fullName' => "{$this->first_name} {$this->last_name}",
            'email' => $this->email,
            'username' => $this->username,
            'role' => $this->role,
            'phoneNumber' => $this->phone_number,
            'address' => $this->address,
            'birthday' => $this->birthday,
            'profileImage' => $this->profile_image ?: 'https://i.imgur.com/UJ0N2SN.jpg',
            'timezone' => $this->timezone,
            'branchId' => $this->branch_id
        ];
    }
}
