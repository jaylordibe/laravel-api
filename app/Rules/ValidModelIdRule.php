<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidModelIdRule implements ValidationRule
{

    protected string $model;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->model::where('id', $value)->doesntExist()) {
            $fail('The :attribute must be a valid id.');
        }
    }

}
