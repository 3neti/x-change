<?php

namespace LBHurtado\Voucher\Data\Casts;

use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Casts\Cast;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;

class CarbonIntervalCast implements Cast
{
    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): mixed {
        $name = $property->name;
        Log::debug("[CarbonIntervalCast] Casting “{$name}”", ['raw' => $value, 'type' => gettype($value)]);

        // Already a CarbonInterval?
        if ($value instanceof CarbonInterval) {
            Log::debug("[CarbonIntervalCast] “{$name}” is already a CarbonInterval, returning as-is");
            return $value;
        }

        // Empty string -> null
        if ($value === '') {
            Log::debug("[CarbonIntervalCast] “{$name}” is empty string, casting to null");
            return null;
        }

        // Null stays null
        if ($value === null) {
            Log::debug("[CarbonIntervalCast] “{$name}” is null, returning null");
            return null;
        }

        // Numeric → seconds
        if (is_numeric($value)) {
            Log::debug("[CarbonIntervalCast] “{$name}” numeric, interpreting as seconds");
            return CarbonInterval::seconds((int) $value);
        }

        // String → try parse
        if (is_string($value)) {
            Log::debug("[CarbonIntervalCast] “{$name}” string, attempting CarbonInterval::make()");
            try {
                $ci = CarbonInterval::make($value);
                Log::debug("[CarbonIntervalCast] “{$name}” parsed successfully", ['interval' => $ci]);
                return $ci;
            } catch (\Throwable $e) {
                Log::error("[CarbonIntervalCast] “{$name}” failed to parse as CarbonInterval", [
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);
                throw new \InvalidArgumentException("Cannot cast “{$name}” to CarbonInterval: {$value}");
            }
        }

        // Anything else → fatal
        Log::error("[CarbonIntervalCast] “{$name}” unsupported type", ['value' => $value]);
        throw new \InvalidArgumentException("Cannot cast “{$name}” to CarbonInterval: " . print_r($value, true));
    }
}
