<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Contract\OpenApi\Support;

class OpenApiPathFinder
{
    public function __construct(
        protected OpenApiDocument $document,
    ) {}

    public function hasOperation(string $method, string $path): bool
    {
        return $this->document->operation($method, $path) !== null;
    }

    /**
     * @return array<int,string>
     */
    public function missingOperations(array $expected): array
    {
        $missing = [];

        foreach ($expected as $item) {
            $method = (string) ($item['method'] ?? '');
            $path = (string) ($item['path'] ?? '');

            if (! $this->hasOperation($method, $path)) {
                $missing[] = sprintf('%s %s', strtoupper($method), $path);
            }
        }

        return $missing;
    }
}
