<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTokenResource extends JsonResource
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
            'token' => $this->token,
            'appPlatform' => $this->app_platform,
            'deviceType' => $this->device_type,
            'deviceOs' => $this->device_os,
            'deviceOsVersion' => $this->device_os_version
        ];
    }

}
