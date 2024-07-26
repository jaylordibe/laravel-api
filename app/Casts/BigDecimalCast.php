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
     * @return BigDecimal
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): BigDecimal
    {
        if (is_null($value)) {
            return BigDecimal::zero();
        }

        try {
            return BigDecimal::of($value);
        } catch (MathException $e) {
            Log::warning('BigDecimalCast:get', [
                'value' => $value,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return BigDecimal::zero();
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
     * @return string
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $newValue = 0;

        if (is_null($value)) {
            return (string) $newValue;
        }

        try {
            $newValue = BigDecimal::of($value);
        } catch (MathException $e) {
            Log::warning('BigDecimalCast:get', [
                'value' => $value,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $newValue = BigDecimal::zero();
        }

        return (string) $newValue;
    }

}
