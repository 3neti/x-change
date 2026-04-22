<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Contract\OpenApi\Support;

use RuntimeException;

class OpenApiDocument
{
    /**
     * @param array<string,mixed> $document
     */
    public function __construct(
        protected array $document,
    ) {}

    public static function load(string $path): self
    {
        if (! file_exists($path)) {
            throw new RuntimeException("OpenAPI spec file not found at [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("OpenAPI spec at [{$path}] is not valid JSON.");
        }

        return new self($decoded);
    }

    /**
     * @return array<string,mixed>
     */
    public function raw(): array
    {
        return $this->document;
    }

    public function openApiVersion(): ?string
    {
        $value = $this->document['openapi'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function paths(): array
    {
        $paths = $this->document['paths'] ?? [];

        return is_array($paths) ? $paths : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function operation(string $method, string $path): ?array
    {
        $paths = $this->paths();

        $pathItem = $paths[$path] ?? null;

        if (! is_array($pathItem)) {
            return null;
        }

        $operation = $pathItem[strtolower($method)] ?? null;

        return is_array($operation) ? $operation : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function schemas(): array
    {
        $schemas = $this->document['components']['schemas'] ?? [];

        return is_array($schemas) ? $schemas : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function schema(string $name): ?array
    {
        $schemas = $this->schemas();
        $schema = $schemas[$name] ?? null;

        return is_array($schema) ? $schema : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function responseSchema(string $method, string $path, string $statusCode): ?array
    {
        $operation = $this->operation($method, $path);

        if (! is_array($operation)) {
            return null;
        }

        $responses = $operation['responses'] ?? null;

        if (! is_array($responses)) {
            return null;
        }

        $response = $responses[$statusCode] ?? null;

        if (! is_array($response)) {
            return null;
        }

        $content = $response['content']['application/json']['schema'] ?? null;

        return is_array($content) ? $content : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function requestSchema(string $method, string $path): ?array
    {
        $operation = $this->operation($method, $path);

        if (! is_array($operation)) {
            return null;
        }

        $schema = $operation['requestBody']['content']['application/json']['schema'] ?? null;

        return is_array($schema) ? $schema : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function dereference(array $schema): ?array
    {
        if (! isset($schema['$ref']) || ! is_string($schema['$ref'])) {
            return $schema;
        }

        $prefix = '#/components/schemas/';

        if (! str_starts_with($schema['$ref'], $prefix)) {
            return null;
        }

        $name = substr($schema['$ref'], strlen($prefix));

        return $this->schema($name);
    }
}
