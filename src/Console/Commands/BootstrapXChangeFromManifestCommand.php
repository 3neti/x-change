<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commissioning\CommissioningManifestRepository;
use Symfony\Component\Process\Process;

final class BootstrapXChangeFromManifestCommand extends Command
{
    protected $signature = 'x-change:bootstrap
        {--manifest= : YAML manifest path, URL, or x-change:// URI}
        {--skip-build : Skip npm install and npm run build}
        {--skip-verify : Skip final environment and route-list verification}';

    protected $description = 'Bootstrap an x-change host application from a commissioning YAML manifest.';

    public function handle(CommissioningManifestRepository $manifests): int
    {
        $manifestReference = trim((string) $this->option('manifest'));

        if ($manifestReference === '') {
            $this->components->error('A commissioning manifest is required.');

            return self::FAILURE;
        }

        $manifest = $manifests->load($manifestReference);

        $this->ensureEnvironmentFile((bool) data_get($manifest, 'bootstrap.environment.copy_env', true));
        $this->ensureSqliteDatabase(data_get($manifest, 'bootstrap.environment.sqlite_database', 'database/database.sqlite'));

        foreach ($this->commands($manifestReference, $manifest) as $command) {
            if (! $this->runProcess($command)) {
                return self::FAILURE;
            }
        }

        if (! (bool) $this->option('skip-build') && (bool) data_get($manifest, 'bootstrap.build.enabled', true)) {
            foreach ($this->buildCommands($manifest) as $command) {
                if (! $this->runProcess($command)) {
                    return self::FAILURE;
                }
            }
        }

        if (! (bool) $this->option('skip-verify') && (bool) data_get($manifest, 'bootstrap.verify.enabled', true)) {
            foreach ($this->verifyCommands($manifest) as $command) {
                if (! $this->runProcess($command)) {
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<list<string>>
     */
    private function commands(string $manifestReference, array $manifest): array
    {
        return [
            ['php', 'artisan', 'key:generate', '--ansi', '--force'],
            ['php', 'artisan', 'migrate', '--graceful', '--ansi'],
            [
                'php',
                'artisan',
                'x-change:install',
                '--force',
                '--no-interaction',
                '--provision-system-principal',
                '--confirm-system-principal',
                '--system-principal-name='.$this->systemPrincipalName($manifest),
            ],
            [
                'php',
                'artisan',
                'x-change:commission:manifest',
                '--manifest='.$manifestReference,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<list<string>>
     */
    private function buildCommands(array $manifest): array
    {
        $commands = [];

        if ((bool) data_get($manifest, 'bootstrap.build.npm_install', true)) {
            $commands[] = ['npm', 'install'];
        }

        if ((bool) data_get($manifest, 'bootstrap.build.npm_build', true)) {
            $commands[] = ['npm', 'run', 'build'];
        }

        return $commands;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<list<string>>
     */
    private function verifyCommands(array $manifest): array
    {
        $commands = [];

        if ((bool) data_get($manifest, 'bootstrap.verify.about', true)) {
            $commands[] = ['php', 'artisan', 'about', '--only=environment'];
        }

        $routePath = trim((string) data_get($manifest, 'bootstrap.verify.route_path', 'x'));

        if ($routePath !== '') {
            $commands[] = ['php', 'artisan', 'route:list', '--path='.$routePath];
        }

        return $commands;
    }

    private function ensureEnvironmentFile(bool $enabled): void
    {
        if ($enabled && ! file_exists(base_path('.env'))) {
            copy(base_path('.env.example'), base_path('.env'));
        }
    }

    private function ensureSqliteDatabase(mixed $relativePath): void
    {
        $relativePath = trim((string) $relativePath);

        if ($relativePath === '') {
            return;
        }

        $path = base_path($relativePath);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (! file_exists($path)) {
            touch($path);
        }
    }

    /** @param list<string> $command */
    private function runProcess(array $command): bool
    {
        $successful = false;

        $this->components->task(implode(' ', $command), function () use ($command, &$successful): void {
            $process = new Process($command, base_path());
            $process->setTimeout(null);
            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

            $successful = $process->isSuccessful();
        });

        return $successful;
    }

    /** @param array<string, mixed> $manifest */
    private function systemPrincipalName(array $manifest): string
    {
        $configured = trim((string) data_get($manifest, 'system_principal.name'));

        if ($configured !== '') {
            return $configured;
        }

        $appName = trim((string) data_get($manifest, 'application.name', config('app.name', 'x-change')));

        return $appName.' System';
    }
}
