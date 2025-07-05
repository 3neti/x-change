<?php

namespace LBHurtado\ModelInput\Support;

use LBHurtado\ModelInput\Enums\InputType;
use RuntimeException;

class InputRuleBuilder
{
    /**
     * Build validation rules from an array of input field names.
     *
     * @param array<string> $fields
     * @return array<string, array>
     */
    public static function fromFields(array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(function ($field) {
                $inputType = InputType::tryFrom($field);

                if (! $inputType) {
                    throw new RuntimeException("Invalid input type: {$field}");
                }

                return [$field => $inputType->rules()];
            })
            ->all();
    }

    /**
     * Build validation rules from a collection or DTO that has a `toArray()` method.
     *
     * @param iterable<string>|object $source
     * @return array<string, array>
     */
    public static function from($source): array
    {
        $fields = $source instanceof \Traversable || is_array($source) ? $source : (method_exists($source, 'toArray')
            ? $source->toArray()
            : []);

        return self::fromFields($fields);
    }
}
