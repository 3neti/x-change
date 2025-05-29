<?php

namespace LBHurtado\Voucher\Data\Transformers;

use Carbon\CarbonInterval;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;
use Spatie\LaravelData\Support\DataProperty;

class TtlToStringTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        // If the value is null, return it unchanged
        if ($value === null) {
            return $value;
        }

        // If the value is an instance of CarbonInterval, format it to a string representation
        if ($value instanceof CarbonInterval) {
            return $value->forHumans([
                'parts' => 3, // Limit to 3 significant parts (e.g., "2 hours 45 minutes 3 seconds")
                'short' => true, // Use short format (e.g., "2h 45m 3s")
            ]);
        }

        // If the value is already a string, return it unchanged
        if (is_string($value)) {
            return $value;
        }

        // For unsupported types, return the value unchanged
        return $value;
    }
}
