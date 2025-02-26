<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DynamicResource extends JsonResource
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
        $data = [];

        // Get any loaded attributes from the model
        $attributes = $this->resource->getAttributes();

        $excludedAttributes = ['deleted_at', 'created_by', 'updated_by', 'deleted_by'];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $excludedAttributes)) {
                continue;
            }

            $data[Str::camel($key)] = $value;
        }

        // Get any loaded relations from the model
        $relations = $this->resource->getRelations();

        foreach ($relations as $relation => $relationValue) {
            $camelRelation = Str::camel($relation);

            if ($relationValue instanceof Model) {
                $data[$camelRelation] = new self($relationValue);
            } elseif ($relationValue instanceof Collection) {
                $data[$camelRelation] = self::collection($relationValue);
            } else {
                $data[$camelRelation] = $relationValue;
            }
        }

        return $data;
    }

}
