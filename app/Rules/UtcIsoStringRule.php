<?php

namespace App\Rules;

use App\Utils\DateUtil;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UtcIsoStringRule implements ValidationRule
{

    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!DateUtil::isValidUtcIsoString($value)) {
            $fail('The :attribute field must be a valid UTC ISO string.');
        }
    }

}
