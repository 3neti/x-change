<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Contract\OpenApi\Support;

use PHPUnit\Framework\Assert;

class OpenApiResponseMatcher
{
    public function __construct(
        protected OpenApiDocument $document,
    ) {}

    /**
     * @param array<string,mixed> $json
     */
    public function assertMatchesResponseSchema(
        string $method,
        string $path,
        string $statusCode,
        array $json,
    ): void {
        $schema = $this->document->responseSchema($method, $path, $statusCode);

        Assert::assertNotNull(
            $schema,
            sprintf('No response schema found for %s %s [%s].', strtoupper($method), $path, $statusCode)
        );

        $resolved = $this->document->dereference($schema);

        Assert::assertIsArray(
            $resolved,
            sprintf('Could not resolve response schema for %s %s [%s].', strtoupper($method), $path, $statusCode)
        );

        $diffs = $this->validateObjectShape($resolved, $json, '$');

        Assert::assertSame(
            [],
            $diffs,
            "OpenAPI response mismatch:\n- ".implode("\n- ", $diffs)
        );
    }

    /**
     * @param array<string,mixed> $schema
     * @param mixed $value
     * @return array<int,string>
     */
    protected function validateObjectShape(array $schema, mixed $value, string $pointer): array
    {
        $diffs = [];

        $type = $schema['type'] ?? null;

        if ($type === 'object') {
            if (! is_array($value)) {
                return [sprintf('%s expected object, got %s', $pointer, get_debug_type($value))];
            }

            $required = $schema['required'] ?? [];
            $properties = $schema['properties'] ?? [];

            foreach ($required as $field) {
                if (! array_key_exists((string) $field, $value)) {
                    $diffs[] = sprintf('%s missing required property [%s]', $pointer, $field);
                }
            }

            if (is_array($properties)) {
                foreach ($properties as $field => $propertySchema) {
                    if (! array_key_exists((string) $field, $value)) {
                        continue;
                    }

                    if (! is_array($propertySchema)) {
                        continue;
                    }

                    $resolved = $this->document->dereference($propertySchema);

                    if (! is_array($resolved)) {
                        continue;
                    }

                    $diffs = [
                        ...$diffs,
                        ...$this->validateObjectShape(
                            $resolved,
                            $value[(string) $field],
                            $pointer.'.'.$field
                        ),
                    ];
                }
            }

            return $diffs;
        }

        if ($type === 'array') {
            if (! is_array($value)) {
                return [sprintf('%s expected array, got %s', $pointer, get_debug_type($value))];
            }

            $itemSchema = $schema['items'] ?? null;

            if (! is_array($itemSchema)) {
                return $diffs;
            }

            $resolved = $this->document->dereference($itemSchema);

            if (! is_array($resolved)) {
                return $diffs;
            }

            foreach (array_values($value) as $index => $item) {
                $diffs = [
                    ...$diffs,
                    ...$this->validateObjectShape($resolved, $item, $pointer.'['.$index.']'),
                ];
            }

            return $diffs;
        }

        if ($type === 'string' && ! is_string($value) && $value !== null) {
            $diffs[] = sprintf('%s expected string|null, got %s', $pointer, get_debug_type($value));
        }

        if ($type === 'integer' && ! is_int($value) && $value !== null) {
            $diffs[] = sprintf('%s expected integer|null, got %s', $pointer, get_debug_type($value));
        }

        if ($type === 'number' && ! is_int($value) && ! is_float($value) && $value !== null) {
            $diffs[] = sprintf('%s expected number|null, got %s', $pointer, get_debug_type($value));
        }

        if ($type === 'boolean' && ! is_bool($value) && $value !== null) {
            $diffs[] = sprintf('%s expected boolean|null, got %s', $pointer, get_debug_type($value));
        }

        return $diffs;
    }
}
