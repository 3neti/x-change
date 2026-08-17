<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LBHurtado\XChange\Support\Time\UtcInstant;
use Throwable;

final class TimezoneAwareInstant implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            UtcInstant::parseOffsetRequired((string) $value);
        } catch (Throwable) {
            $fail("The {$attribute} must be a valid instant with Z or a numeric timezone offset.");
        }
    }
}
