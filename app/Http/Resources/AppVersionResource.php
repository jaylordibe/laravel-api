<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppVersionResource extends JsonResource
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
            'version' => $this->version,
            'description' => $this->description,
            'platform' => $this->platform,
            'releaseDate' => $this->release_date,
            'downloadUrl' => $this->download_url,
            'forceUpdate' => $this->force_update
        ];
    }

}
