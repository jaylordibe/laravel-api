<?php

namespace App\Rules;

use App\Utils\AppUtil;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PhilippinePhoneNumberRule implements ValidationRule
{

    /**
     * Run the validation rule.
     *
     * @param Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!AppUtil::isValidPhilippinePhoneNumber($value)) {
            $fail('The :attribute must be a valid Philipine phone number.');
        }
    }

}
