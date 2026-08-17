<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LBHurtado\XChange\Support\Time\UtcInstant;
use Throwable;

final class UtcDateOrTimezoneAwareInstant implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            UtcInstant::parseDateOrOffsetRequired((string) $value);
        } catch (Throwable) {
            $fail("The {$attribute} must be a calendar date or a valid instant with an explicit timezone offset.");
        }
    }
}
