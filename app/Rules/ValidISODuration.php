<?php

namespace App\Rules;

use Closure;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidISODuration implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        try {
            CarbonInterval::fromString($value);
        } catch (\Exception) {
            $fail('The :attribute must be a valid ISO-8601 duration string (e.g. PT1H30M or P2D).');
        }
    }
}
