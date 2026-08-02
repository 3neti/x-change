<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use LBHurtado\XChange\Services\Deployment\DeploymentCheckpointRepository;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestGenerator;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestRepository;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

final class DeployXChangeCommand extends Command
{
    protected $signature = 'x-change:deploy
        {environment=production : Remote environment name}
        {--target= : Override or initialize the deployment target}
        {--profile= : Override or initialize the provider profile}
        {--application= : Laravel Cloud application ID or name}
        {--path= : Defaults to x-change.deployment.yaml}
        {--confirm-production : Explicit non-interactive production consent}
        {--plan : Validate and display the deployment plan without execution}
        {--json : Output JSON}';

    protected $description = 'Validate, deploy, monitor, and commission an x-change application.';

    public function handle(
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
        DeploymentCheckpointRepository $checkpoints,
    ): int {
        $path = trim((string) ($this->option('path') ?: base_path(
            'x-change.deployment.yaml',
        )));

        try {
            if (! is_file($path) || $this->option('target') !== null || $this->option('profile') !== null) {
                $manifests->write($path, $generator->generate(
                    target: trim((string) ($this->option('target') ?: 'laravel-cloud')),
                    profileName: $this->option('profile'),
                ));
            }

            $manifest = $manifests->read($path);
        } catch (Throwable $exception) {
            return $this->renderResult(false, $exception->getMessage());
        }

        $target = (string) $manifest['deployment']['target'];
        $environment = trim((string) $this->argument('environment'));
        $application = trim((string) ($this->option('application')
            ?: $manifest['application']['slug']));
        $plan = [
            'manifest' => $path,
            'application' => $application,
            'environment' => $environment,
            'target' => $target,
            'profile' => $manifest['deployment']['profile'],
            'steps' => ['validate', 'platform-deploy', 'monitor', 'commission', 'verify'],
        ];

        if ($this->option('plan')) {
            return $this->renderResult(true, 'planned', $plan);
        }

        if ($target !== 'laravel-cloud') {
            return $this->renderResult(
                false,
                "Execution adapter [{$target}] is not enabled; use --plan.",
                $plan,
            );
        }

        $interactive = $this->input->isInteractive() && ! $this->option('json');

        if ($interactive) {
            intro("X-CHANGE DEPLOYMENT · {$application} · {$environment}");
        }

        $confirmed = (bool) $this->option('confirm-production') || (
            $interactive
            && confirm("Deploy and commission {$application} in {$environment}?", false)
        );

        if (! $confirmed) {
            return $this->renderResult(false, 'Production deployment was not confirmed.', $plan);
        }

        $commands = [
            'deploy' => ['cloud', 'deploy', $application, $environment, '-n'],
            'deploy:monitor' => ['cloud', 'deploy:monitor', $application, $environment, '-n'],
            'command:run:commission' => [
                'cloud',
                'command:run',
                $environment,
                '--cmd=php artisan x-change:commission --no-interaction',
                '-n',
            ],
            'command:run:assets' => [
                'cloud',
                'command:run',
                $environment,
                '--cmd=php artisan x-change:doctor --assets --strict',
                '-n',
            ],
        ];

        foreach ($commands as $operation => $command) {
            $cloudCommand = (string) $command[1];
            $help = Process::path(base_path())
                ->timeout(30)
                ->run(['cloud', $cloudCommand, '-h', '-n']);

            if (! $help->successful()) {
                return $this->renderResult(false, 'Laravel Cloud CLI command is unavailable.', [
                    ...$plan,
                    'failed_operation' => $operation,
                ]);
            }

            $result = Process::path(base_path())
                ->timeout(900)
                ->idleTimeout(120)
                ->run($command, function (string $type, string $output): void {
                    if (! $this->option('json')) {
                        $this->output->write($output);
                    }
                });

            if (! $result->successful()) {
                $checkpoints->record(
                    $environment,
                    (string) $manifest['recipe']['hash'],
                    (string) $manifest['manifest_hash'],
                    (string) $operation,
                    'failed',
                );

                return $this->renderResult(false, 'Deployment command failed safely.', [
                    ...$plan,
                    'failed_operation' => $operation,
                ]);
            }

            $checkpoints->record(
                $environment,
                (string) $manifest['recipe']['hash'],
                (string) $manifest['manifest_hash'],
                (string) $operation,
                'succeeded',
            );
        }

        if ($interactive) {
            outro("{$application} deployed and commissioned.");
        }

        return $this->renderResult(true, 'operational', $plan);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderResult(
        bool $success,
        string $status,
        array $context = [],
    ): int {
        if ($this->option('json')) {
            $this->line(json_encode([
                'schema' => 'x-change.deploy.v1',
                'success' => $success,
                'status' => $status,
                ...$context,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($success) {
            $this->components->info(
                $status === 'planned'
                    ? 'Deployment plan is valid; no remote changes were made.'
                    : 'X-Change deployment completed.',
            );
        } else {
            $this->components->error($status);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
