<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ActivityLogResource extends BaseResource
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
        // Load the attributes
        $data = $this->getLoadedAttributes();

        // Load the relations
        $data['user'] = new UserResource($this->whenLoaded('causer'));

        return $data;
    }

}
