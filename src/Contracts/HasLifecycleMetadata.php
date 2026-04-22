<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface HasLifecycleMetadata
{
    /**
     * Persist lifecycle metadata under a logical namespace.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function putLifecycleMetadata(string $key, array $attributes): void;

    /**
     * Read lifecycle metadata from a logical namespace.
     *
     * @return array<string,mixed>
     */
    public function getLifecycleMetadata(string $key): array;
}
