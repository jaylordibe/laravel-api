<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BaseResource extends JsonResource
{

    /**
     * Transforms the resource's attributes into a camelCased array,
     * respecting hidden attributes and custom exclusions.
     *
     * @return array
     */
    public function transformAttributes(): array
    {
        $allAttributes = $this->resource->toArray();
        $customHiddenAttributes = ['deleted_at', 'created_by', 'updated_by', 'deleted_by'];
        $transformedData = [];

        foreach ($allAttributes as $key => $value) {
            if (in_array($key, $customHiddenAttributes)) {
                continue;
            }

            // Convert the key to camelCase and assign the value.
            $transformedData[Str::camel($key)] = $value;
        }

        return $transformedData;
    }

    /**
     * The default toArray method.
     * You would typically override this in child resources.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray(Request $request): array
    {
        return $this->transformAttributes();
    }

}
