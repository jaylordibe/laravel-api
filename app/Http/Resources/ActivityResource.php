<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'userId' => $this->causer_id,
//            'user' => new UserResource($this->whenLoaded('causer')),
            'type' => $this->log_name,
            'description' => $this->description,
            'properties' => $this->properties
        ];
    }

}
