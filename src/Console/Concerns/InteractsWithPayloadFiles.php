<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Concerns;

use RuntimeException;

trait InteractsWithPayloadFiles
{
    /**
     * @return array<string, mixed>
     */
    protected function loadPayloadFromConfigOption(): array
    {
        $path = $this->option('config');

        if (! is_string($path) || trim($path) === '') {
            return [];
        }

        $resolved = $this->laravel->basePath(trim($path));

        if (! is_file($resolved)) {
            $resolved = trim($path);
        }

        if (! is_file($resolved)) {
            throw new RuntimeException(sprintf('Payload config file [%s] was not found.', $path));
        }

        $decoded = json_decode((string) file_get_contents($resolved), true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Payload config file [%s] must contain a JSON object.', $path));
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  ...$payloads
     * @return array<string, mixed>
     */
    protected function mergePayloads(array ...$payloads): array
    {
        $merged = [];

        foreach ($payloads as $payload) {
            $merged = array_replace_recursive($merged, $payload);
        }

        return $merged;
    }
}
