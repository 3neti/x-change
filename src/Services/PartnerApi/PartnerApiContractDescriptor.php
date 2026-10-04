<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use JsonException;
use RuntimeException;

final class PartnerApiContractDescriptor
{
    /** @return array{version: string, sha256: string} */
    public function describe(): array
    {
        $path = $this->path();

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The Partner API contract is unavailable.');
        }

        try {
            $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The Partner API contract is invalid.', previous: $exception);
        }

        $version = data_get($document, 'info.version');
        $sha256 = hash_file('sha256', $path);

        if (! is_string($version) || trim($version) === '' || ! is_string($sha256)) {
            throw new RuntimeException('The Partner API contract has no authoritative version or digest.');
        }

        return [
            'version' => $version,
            'sha256' => $sha256,
        ];
    }

    public function path(): string
    {
        return dirname(__DIR__, 3).'/resources/api/x-change-partner-api.openapi.json';
    }
}
