<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends BaseResource
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

        $includeAccessControl = filter_var($request->input('includeAccessControl', false), FILTER_VALIDATE_BOOLEAN);

        if ($includeAccessControl) {
            $data['roles'] = $this->getRoleNames()->toArray();
            $data['permissions'] = $this->getAllPermissions()->pluck('name')->toArray();
        }

        $data['profilePhotoUrl'] = $this->profile_photo_url ?: 'https://i.imgur.com/UJ0N2SN.jpg';

        // Load the relations

        return $data;
    }

}
