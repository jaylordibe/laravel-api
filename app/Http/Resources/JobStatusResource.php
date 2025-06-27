<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class JobStatusResource extends BaseResource
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
        $data = parent::transformAttributes();

        // Load the relations

        return $data;
    }

}
