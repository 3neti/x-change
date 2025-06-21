<?php

namespace LBHurtado\OmniChannel\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class DoesNotMatchAppDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !str_contains($value, '@')) {
            return; // Let other email rules handle malformed emails
        }

        $emailDomain = strtolower(substr(strrchr($value, '@'), 1));
        $appDomain = strtolower(parse_url(config('app.url'), PHP_URL_HOST));

        if ($emailDomain === $appDomain) {
            $fail("The :attribute must not be a temporary system-generated address.");
        }
    }
}
