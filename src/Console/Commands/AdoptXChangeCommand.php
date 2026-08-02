<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Deployment\HostComposerScriptAdopter;
use Throwable;

final class AdoptXChangeCommand extends Command
{
    protected $signature = 'x-change:adopt
        {--dry-run : Inspect without changing host files}
        {--composer-path= : Override the host composer.json path}
        {--no-host-shell : Adopt Composer commands without changing the host shell}
        {--json : Render a machine-readable result}';

    protected $description = 'Adopt the x-change host shell and safe Composer deployment aliases.';

    public function handle(HostComposerScriptAdopter $composer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $hostExitCode = $this->option('no-host-shell')
                ? self::SUCCESS
                : $this->call('x-change:host:adopt', [
                    '--dry-run' => $dryRun,
                    '--json' => (bool) $this->option('json'),
                ]);

            if ($hostExitCode !== self::SUCCESS) {
                return self::FAILURE;
            }

            $result = $composer->adopt(
                trim((string) ($this->option('composer-path') ?: base_path('composer.json'))),
                ! $dryRun,
            );

            if (! $dryRun && $this->call('x-change:publish', [
                '--scope' => 'build',
                '--force' => true,
                '--verify' => true,
                '--no-interaction' => true,
            ]) !== self::SUCCESS) {
                return $this->renderResult(false, 'Initial X-Change build publication failed.');
            }
        } catch (Throwable $exception) {
            return $this->renderResult(false, $exception->getMessage());
        }

        return $this->renderResult(true, $result['status'], $result);
    }

    /** @param array<string, mixed> $context */
    private function renderResult(bool $success, string $status, array $context = []): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'schema' => 'x-change.host-adoption.v2',
                'success' => $success,
                'status' => $status,
                ...$context,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } elseif ($success) {
            $this->components->info('X-Change host and Composer deployment commands are ready.');
        } else {
            $this->components->error($status);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
