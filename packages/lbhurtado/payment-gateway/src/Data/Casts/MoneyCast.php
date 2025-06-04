<?php

namespace LBHurtado\PaymentGateway\Data\Casts;

use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Casts\Cast;
use Illuminate\Support\Number;
use Brick\Money\Money;

class MoneyCast implements Cast
{
    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): mixed {
        if ($value instanceof Money) {
            return $value;
        }

        if (is_array($value) && isset($value['amount'], $value['currency'])) {
            return Money::of($value['amount'], $value['currency']);
        }

        if (is_numeric($value)) {
            return Money::of($value, Number::defaultCurrency()); // default currency
        }

        throw new \InvalidArgumentException("Cannot cast value to Money: " . print_r($value, true));
    }
}

