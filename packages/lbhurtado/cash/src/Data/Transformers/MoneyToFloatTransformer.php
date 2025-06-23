<?php

namespace LBHurtado\Cash\Data\Transformers;

use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;
use Spatie\LaravelData\Support\DataProperty;
use Brick\Money\Money;

class MoneyToFloatTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        // Check if the value is an instance of the Money class
        if ($value instanceof Money) {
            // Return the float representation of the monetary value
            return $value->getAmount()->toFloat();
        }

        // Return the value unchanged if it's not of type Money
        return $value;
    }
}
