<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Publication;

use Illuminate\Support\ServiceProvider;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;

final readonly class PublicationVerifier
{
    public function __construct(
        private PublicationCatalog $catalog,
        private PublishedAssetDriftDetector $driftDetector,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     passed: bool,
     *     message: string,
     *     summary: array{resources: int, checked: int, ok: int, stale: int, missing: int, unavailable: int, unregistered: int},
     *     resources: array<int, array{id: string, owner: string, passed: bool, message: string}>,
     *     files: array<int, array{status: string, source: string|null, target: string|null, relative_path: string, reason: string, publication_id: string}>
     * }
     */
    public function inspectBuild(array $only = [], array $except = []): array
    {
        $summary = [
            'resources' => 0,
            'checked' => 0,
            'ok' => 0,
            'stale' => 0,
            'missing' => 0,
            'unavailable' => 0,
            'unregistered' => 0,
        ];
        $resources = [];
        $files = [];

        $definitions = array_filter(
            $this->catalog->definitions(PublicationScope::Build),
            static fn (PublicationDefinitionData $definition): bool => ($only === [] || in_array($definition->id, $only, true))
                && ! in_array($definition->id, $except, true),
        );

        foreach ($definitions as $definition) {
            $summary['resources']++;

            if (! $definition->available) {
                $summary['unavailable']++;
                $resources[] = $this->resourceResult(
                    $definition,
                    ! $definition->required,
                    $definition->required
                        ? 'required publication provider is unavailable'
                        : 'optional publication provider is unavailable',
                );

                continue;
            }

            $mappings = $this->publicationMappings($definition);

            if ($mappings === []) {
                $summary['unregistered']++;
                $resources[] = $this->resourceResult(
                    $definition,
                    false,
                    'publication target is not registered',
                );

                continue;
            }

            $result = $this->driftDetector->inspect($mappings);
            $relevantFiles = collect($result['files'])
                ->reject(static fn (array $file): bool => $file['status'] === 'extra')
                ->values();

            foreach ($relevantFiles as $file) {
                $summary['checked']++;
                $summary[$file['status']]++;
                $files[] = [...$file, 'publication_id' => $definition->id];
            }

            $passed = $relevantFiles->every(
                static fn (array $file): bool => $file['status'] === 'ok',
            );
            $resources[] = $this->resourceResult(
                $definition,
                $passed,
                $passed
                    ? 'published build inputs match package source'
                    : 'published build inputs are missing or stale',
            );
        }

        $passed = $summary['stale'] === 0
            && $summary['missing'] === 0
            && $summary['unavailable'] === 0
            && $summary['unregistered'] === 0;

        return [
            'name' => 'generated build inputs',
            'passed' => $passed,
            'message' => $passed
                ? 'all declared generated build inputs match package source'
                : 'generated build inputs are incomplete or have drift; run php artisan x-change:publish --scope=build --force --verify --no-interaction',
            'summary' => $summary,
            'resources' => $resources,
            'files' => $files,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function publicationMappings(PublicationDefinitionData $definition): array
    {
        return $definition->invocation === PublicationInvocation::Tag
            ? ServiceProvider::pathsToPublish(null, $definition->target)
            : ServiceProvider::pathsToPublish($definition->target);
    }

    /**
     * @return array{id: string, owner: string, passed: bool, message: string}
     */
    private function resourceResult(
        PublicationDefinitionData $definition,
        bool $passed,
        string $message,
    ): array {
        return [
            'id' => $definition->id,
            'owner' => $definition->owner,
            'passed' => $passed,
            'message' => $message,
        ];
    }
}
