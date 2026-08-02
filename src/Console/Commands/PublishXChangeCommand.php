<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;
use LBHurtado\XChange\Services\Publication\PublicationPublisher;
use LBHurtado\XChange\Services\Publication\PublicationVerifier;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;
use Throwable;

final class PublishXChangeCommand extends Command
{
    protected $signature = 'x-change:publish
        {--scope=build : build, install, or advanced}
        {--force : Overwrite resources allowed by the selected publication scope}
        {--only=* : Restrict publication to these declared IDs}
        {--except=* : Exclude these declared IDs}
        {--dry-run : Render the publication plan without changing files}
        {--verify : Verify declared publication targets after publishing}
        {--json : Render a machine-readable result}';

    protected $description = 'Publish the declared X-Change package resources for one safe scope.';

    public function handle(
        PublicationCatalog $catalog,
        PublicationPublisher $publisher,
        PublicationVerifier $verifier,
        PublishedAssetDriftDetector $publishedAssets,
    ): int {
        $scope = PublicationScope::tryFrom(mb_strtolower(trim((string) $this->option('scope'))));

        if (! $scope instanceof PublicationScope) {
            return $this->renderFailure('Publication scope must be build, install, or advanced.');
        }

        try {
            $result = $publisher->publish(
                $catalog,
                $scope,
                (bool) $this->option('force'),
                (bool) $this->option('dry-run'),
                (bool) $this->option('verify'),
                array_values((array) $this->option('only')),
                array_values((array) $this->option('except')),
            );

            $publishedIds = array_column($result['results'], 'id');

            if (
                $scope === PublicationScope::Build
                && ! (bool) $this->option('dry-run')
                && in_array('x-change.ui', $publishedIds, true)
            ) {
                $result['generated_headers'] = $publishedAssets->applyGeneratedHeaders();
            }

            if (
                $scope === PublicationScope::Build
                && (bool) $this->option('verify')
                && ! (bool) $this->option('dry-run')
            ) {
                $result['verification'] = $verifier->inspectBuild(
                    array_values((array) $this->option('only')),
                    array_values((array) $this->option('except')),
                );

                if (! $result['verification']['passed']) {
                    throw new \RuntimeException($result['verification']['message']);
                }
            }
        } catch (Throwable $exception) {
            return $this->renderFailure($exception->getMessage());
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info(sprintf(
                'X-Change %s publication is ready (%d resources).',
                $scope->value,
                count($result['results']),
            ));
        }

        return self::SUCCESS;
    }

    private function renderFailure(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'schema' => 'x-change.publication.v1',
                'success' => false,
                'message' => $message,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
