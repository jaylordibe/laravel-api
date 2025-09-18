<?php

namespace App\Rules;

use Closure;
use DateTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
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
        if (!is_string($value)) {
            $fail('The :attribute field must be a valid UTC ISO string.');

            return;
        }

        // Strict UTC ISO 8601 with literal 'T' and 'Z'
        // Optional fractional seconds: 1–6 digits
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.(\d{1,6}))?Z$/';

        if (!preg_match($pattern, $value, $matches)) {
            $fail('The :attribute field must be a valid UTC ISO string.');

            return;
        }

        // Normalize for parsing: pad fractional part to 6 digits if present
        $base = substr($value, 0, 19); // YYYY-MM-DDTHH:MM:SS

        if (!empty($matches[1])) {
            $fraction = str_pad($matches[1], 6, '0', STR_PAD_RIGHT); // e.g. .123 -> .123000
            $normalized = $base . '.' . $fraction . 'Z';
            $format = 'Y-m-d\TH:i:s.u\Z'; // literal T and Z
        } else {
            $normalized = $base . 'Z';
            $format = 'Y-m-d\TH:i:s\Z';
        }

        // Parse strictly in UTC
        $date = Carbon::createFromFormat($format, $normalized, 'UTC');

        if (is_null($date)) {
            $fail('The :attribute field must be a valid UTC ISO string.');

            return;
        }

        // Check for parsing errors/warnings
        $errors = DateTime::getLastErrors();

        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            $fail('The :attribute field must be a valid UTC ISO string.');

            return;
        }

        // Ensure parsed value round-trips exactly
        if ($date->format($format) !== $normalized) {
            $fail('The :attribute field must be a valid UTC ISO string.');
        }
    }

}
