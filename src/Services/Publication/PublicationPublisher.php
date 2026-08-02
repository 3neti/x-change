<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Publication;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use RuntimeException;

final readonly class PublicationPublisher
{
    public function __construct(
        private Kernel $artisan,
        private Filesystem $files,
    ) {}

    /**
     * @return array{schema: string, success: bool, scope: string, dry_run: bool, force: bool, results: list<array<string, mixed>>}
     */
    public function publish(
        PublicationCatalog $catalog,
        PublicationScope $scope,
        bool $force,
        bool $dryRun,
        bool $verify,
        array $only = [],
        array $except = [],
    ): array {
        if ($scope === PublicationScope::Build && ! $force) {
            throw new RuntimeException('Build publication requires explicit --force because generated files are package-owned.');
        }

        $definitions = $this->select($catalog->definitions($scope), $only, $except);
        $this->assertReady($definitions, $force);
        $results = [];

        foreach ($definitions as $definition) {
            if (! $definition->available) {
                $results[] = $this->result($definition, 'skipped_unavailable');

                continue;
            }

            if ($dryRun) {
                $results[] = $this->result($definition, 'would_publish');

                continue;
            }

            $exitCode = $this->artisan->call('vendor:publish', [
                $definition->invocation === PublicationInvocation::Tag ? '--tag' : '--provider' => $definition->target,
                '--force' => $definition->overwritePolicy === PublicationOverwritePolicy::AlwaysGenerated || $force,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException("Publication [{$definition->id}] failed.");
            }

            $missing = $verify ? $this->missingPaths($definition) : [];

            if ($missing !== []) {
                throw new RuntimeException(sprintf(
                    'Publication [%s] is incomplete; missing generated paths: %s.',
                    $definition->id,
                    implode(', ', $missing),
                ));
            }

            $results[] = $this->result($definition, 'published');
        }

        return [
            'schema' => 'x-change.publication.v1',
            'success' => true,
            'scope' => $scope->value,
            'dry_run' => $dryRun,
            'force' => $force,
            'results' => $results,
        ];
    }

    /**
     * @param  list<PublicationDefinitionData>  $definitions
     * @param  list<string>  $only
     * @param  list<string>  $except
     * @return list<PublicationDefinitionData>
     */
    private function select(array $definitions, array $only, array $except): array
    {
        $known = array_column($definitions, 'id');
        $requested = array_values(array_unique([...$only, ...$except]));
        $unknown = array_values(array_diff($requested, $known));

        if ($unknown !== []) {
            throw new RuntimeException('Unknown publication IDs: '.implode(', ', $unknown).'.');
        }

        return array_values(array_filter(
            $definitions,
            static fn (PublicationDefinitionData $definition): bool => ($only === [] || in_array($definition->id, $only, true))
                && ! in_array($definition->id, $except, true),
        ));
    }

    /** @param list<PublicationDefinitionData> $definitions */
    private function assertReady(array $definitions, bool $force): void
    {
        foreach ($definitions as $definition) {
            if ($definition->required && ! $definition->available) {
                throw new RuntimeException("Required publication [{$definition->id}] is unavailable.");
            }

            if ($definition->overwritePolicy === PublicationOverwritePolicy::ExplicitForceOnly && ! $force) {
                throw new RuntimeException("Publication [{$definition->id}] requires explicit --force.");
            }
        }
    }

    /** @return list<string> */
    private function missingPaths(PublicationDefinitionData $definition): array
    {
        return array_values(array_filter(
            $definition->verificationPaths,
            fn (string $path): bool => ! $this->files->exists($path),
        ));
    }

    /** @return array<string, mixed> */
    private function result(PublicationDefinitionData $definition, string $status): array
    {
        return [
            'id' => $definition->id,
            'owner' => $definition->owner,
            'scope' => $definition->scope->value,
            'target' => $definition->target,
            'status' => $status,
            'required' => $definition->required,
            'generated' => $definition->generated,
            'description' => $definition->description,
        ];
    }
}
