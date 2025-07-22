<?php

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Enums\VoucherInputField;

if (!function_exists('traverse')) {
    /**
     * Traverse nested arrays/objects using dot notation.
     * Special case: if the node is an array of VoucherInputField enums,
     * and the next segment matches an enum case name, return a boolean
     * indicating presence.
     *
     * @param mixed $model
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function traverse($model, $key, $default = null)
    {
        if (is_null($key)) {
            return $model;
        }

        // Handle top-level array access
        if (is_array($model)) {
            return Arr::get($model, $key, $default);
        }

        // Handle ArrayAccess (like Collection or DotData)
        if ($model instanceof \ArrayAccess && isset($model[$key])) {
            return $model[$key];
        }

        // Traverse dot-notation path
        foreach (explode('.', $key) as $segment) {
            if (is_array($model)) {
                // 🎯 Special handling for VoucherInputField enums in arrays
                if (!empty($model) && $model[0] instanceof VoucherInputField) {
                    $enum = VoucherInputField::tryFrom($segment);
                    $model = in_array($enum, $model, true);
                } else {
                    $model = $model[$segment] ?? null;
                }
            } elseif (is_object($model)) {
                try {
                    $model = $model->{$segment} ?? null;
                } catch (\Throwable $e) {
                    return value($default);
                }
            } else {
                return value($default);
            }
        }

        return $model ?? value($default);
    }
}
