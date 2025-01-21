<?php

namespace App\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BigDecimalCast implements CastsAttributes
{

    /**
     * Cast the given value.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     *
     * @return BigDecimal|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        try {
            if (is_null($value)) {
                return null;
            }

            return BigDecimal::of($value);
        } catch (MathException $e) {
            Log::warning('BigDecimalCast:get', [
                'value' => $value,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     *
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        try {
            if (is_null($value)) {
                return null;
            }

            return BigDecimal::of($value)->__toString();
        } catch (MathException $e) {
            Log::warning('BigDecimalCast:set', [
                'value' => $value,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

}
