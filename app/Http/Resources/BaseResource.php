<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BaseResource extends JsonResource
{

    /**
     * Get the loaded attributes from the model.
     *
     * @return array
     */
    public function getLoadedAttributes(): array
    {
        $data = [];
        $attributes = $this->resource->getAttributes();
        $modelHiddenAttributes = $this->resource->getHidden();
        $customHiddenAttributes = ['deleted_at', 'created_by', 'updated_by', 'deleted_by'];
        $excludedAttributes = array_merge($modelHiddenAttributes, $customHiddenAttributes);

        foreach (array_keys($attributes) as $key) {
            if (in_array($key, $excludedAttributes)) {
                continue;
            }

            $data[Str::camel($key)] = $this->resource->getAttribute($key);
        }

        return $data;
    }

}
