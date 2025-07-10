<?php

namespace LBHurtado\Wallet\Data\Transformers;

use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;
use Spatie\LaravelData\Support\DataProperty;
use Brick\Money\Money;

class MoneyToStringTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        // Check if the value is an instance of the Money class
        if ($value instanceof Money) {
            // Return the string representation of the monetary value
            return $value->isZero() ? '' : (string) $value->getMinorAmount()->toInt();
        }

        // Return the value unchanged if it's not of type Money
        return $value;
    }
}
