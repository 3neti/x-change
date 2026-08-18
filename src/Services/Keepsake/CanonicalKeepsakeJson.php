<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use JsonException;

final class CanonicalKeepsakeJson
{
    /** @throws JsonException */
    public function encode(mixed $value, bool $pretty = true): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->normalize($value), $flags)."\n";
    }

    public function hash(mixed $value): string
    {
        return hash('sha256', $this->encode($value, false));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
