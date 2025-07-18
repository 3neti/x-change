<?php

use Illuminate\Support\Arr;

if (!function_exists('traverse')) {
    function traverse($model, $key, $default = null)
    {
        if (is_null($key)) {
            return $model;
        }

        // If it's an array, use Arr::get
        if (is_array($model)) {
            return Arr::get($model, $key, $default);
        }

        // If it's a dot-accessible structure (like Dflydev\Data)
        if ($model instanceof \ArrayAccess && isset($model[$key])) {
            return $model[$key];
        }

        // Traverse object properties
        foreach (explode('.', $key) as $segment) {
            if (is_array($model)) {
                $model = $model[$segment] ?? null;
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
