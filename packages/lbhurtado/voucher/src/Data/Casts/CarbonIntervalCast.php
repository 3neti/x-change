<?php

namespace LBHurtado\Voucher\Data\Casts;

use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Casts\Cast;
use Carbon\CarbonInterval;

class CarbonIntervalCast implements Cast
{
    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): mixed {
        // If the value is already a CarbonInterval, return it unchanged
        if ($value instanceof CarbonInterval) {
            return $value;
        }

        // If the value is null, return null
        if ($value === null) {
            return null;
        }

        // If the value is numeric, assume it's seconds and create a CarbonInterval
        if (is_numeric($value)) {
            return CarbonInterval::seconds($value);
        }

        // If the value is a string, attempt to parse it as a CarbonInterval
        if (is_string($value)) {
            try {
                return CarbonInterval::make($value);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Cannot cast value to CarbonInterval: " . $value);
            }
        }

        // If the value is unsupported, throw an exception
        throw new \InvalidArgumentException("Cannot cast value to CarbonInterval: " . print_r($value, true));
    }
}
