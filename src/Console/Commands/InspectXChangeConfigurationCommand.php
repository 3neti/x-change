<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Configuration\DeploymentConfigurationInspector;
use Throwable;

final class InspectXChangeConfigurationCommand extends Command
{
    protected $signature = 'x-change:configuration:inspect
        {--profile= : Override the configured profile for this inspection}
        {--runtime-tier= : Override the configured runtime tier for this inspection}
        {--json : Output JSON}
        {--strict : Fail when required deployment values are missing}';

    protected $description = 'Inspect the provider-neutral x-change deployment configuration.';

    public function handle(DeploymentConfigurationInspector $inspector): int
    {
        try {
            $result = $inspector->inspect(
                $this->option('profile'),
                $this->option('runtime-tier'),
            );
        } catch (Throwable $exception) {
            $result = [
                'ready' => false,
                'message' => $exception->getMessage(),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($result['ready']) {
            $this->components->info(
                "Deployment profile [{$result['profile']}] is configured for runtime tier [{$result['runtime_tier']}].",
            );
        } else {
            $this->components->warn($result['message'] ?? (
                'Missing deployment variables: '.implode(', ', $result['missing_variables']).'.'
            ));
        }

        return $this->option('strict') && ! $result['ready']
            ? self::FAILURE
            : self::SUCCESS;
    }
}
