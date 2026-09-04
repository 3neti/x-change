<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commissioning\CommissioningManifestRepository;
use LBHurtado\XChange\Services\Configuration\LocalEnvironmentFileWriter;
use Symfony\Component\Process\Process;

final class BootstrapXChangeFromManifestCommand extends Command
{
    protected $signature = 'x-change:bootstrap
        {--manifest= : YAML manifest path, URL, or x-change:// URI}
        {--skip-build : Skip npm install and npm run build}
        {--skip-verify : Skip final environment and route-list verification}';

    protected $description = 'Bootstrap an x-change host application from a commissioning YAML manifest.';

    public function handle(
        CommissioningManifestRepository $manifests,
        LocalEnvironmentFileWriter $environment,
    ): int
    {
        $manifestReference = trim((string) $this->option('manifest'));

        if ($manifestReference === '') {
            $this->components->error('A commissioning manifest is required.');

            return self::FAILURE;
        }

        $manifest = $manifests->load($manifestReference);

        $this->ensureEnvironmentFile((bool) data_get($manifest, 'bootstrap.environment.copy_env', true));
        $this->ensureSqliteDatabase(data_get($manifest, 'bootstrap.environment.sqlite_database', 'database/database.sqlite'));

        if (! $this->prepareEnvironment($manifest, $environment)) {
            return self::FAILURE;
        }

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
        $install = [
            'php',
            'artisan',
            'x-change:install',
            '--force',
            '--no-interaction',
            '--provision-system-principal',
            '--confirm-system-principal',
            '--system-principal-name='.$this->systemPrincipalName($manifest),
        ];

        $profile = trim((string) data_get($manifest, 'deployment.profile'));

        if ($profile !== '') {
            $install[] = '--profile='.$profile;
        }

        return [
            ['php', 'artisan', 'optimize:clear'],
            ['php', 'artisan', 'x-change:doctor', '--pre-install', '--strict'],
            ['php', 'artisan', 'key:generate', '--ansi', '--force'],
            ['php', 'artisan', 'migrate', '--graceful', '--ansi'],
            $install,
            [
                'php',
                'artisan',
                'x-change:commission:manifest',
                '--manifest='.$manifestReference,
            ],
            ['php', 'artisan', 'x-change:doctor', '--strict'],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function prepareEnvironment(
        array $manifest,
        LocalEnvironmentFileWriter $environment,
    ): bool {
        $values = $this->environmentDefaults($manifest);
        $missing = [];

        foreach ($this->environmentRequirements($manifest) as $requirement) {
            $key = $this->environmentKey($requirement);

            if ($key === '') {
                continue;
            }

            $value = $this->existingEnvironmentValue($key);

            if ($value !== null) {
                $values[$key] = $value;

                continue;
            }

            if (! $this->input->isInteractive()) {
                $missing[] = $key;

                continue;
            }

            $prompted = $this->promptEnvironmentValue($requirement, $key);

            if ($prompted === null) {
                $missing[] = $key;

                continue;
            }

            $values[$key] = $prompted;
        }

        if ($missing !== []) {
            $this->components->error('Required commissioning environment is missing: '.implode(', ', $missing).'.');

            return false;
        }

        if ($values === []) {
            return true;
        }

        $result = $environment->write(
            path: base_path('.env'),
            examplePath: base_path('.env.example'),
            values: $values,
        );

        if ($result['changed']) {
            $this->components->info('Commissioning environment was prepared.');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    private function environmentDefaults(array $manifest): array
    {
        $defaults = [];

        foreach ((array) data_get($manifest, 'bootstrap.environment.defaults', []) as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $defaults[$key] = (string) $value;
            }
        }

        $profile = trim((string) data_get($manifest, 'deployment.profile'));
        $runtimeTier = trim((string) data_get($manifest, 'deployment.runtime_tier'));

        if ($profile !== '') {
            $defaults['XCHANGE_DEPLOYMENT_PROFILE'] = $profile;
        }

        if ($runtimeTier !== '') {
            $defaults['XCHANGE_RUNTIME_TIER'] = $runtimeTier;
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private function environmentRequirements(array $manifest): array
    {
        return array_values(array_filter(
            (array) data_get($manifest, 'bootstrap.environment.required', []),
            static fn (mixed $value): bool => is_array($value) || is_string($value),
        ));
    }

    /**
     * @param  array<string, mixed>|string  $requirement
     */
    private function environmentKey(array|string $requirement): string
    {
        $key = is_array($requirement) ? ($requirement['key'] ?? '') : $requirement;
        $key = trim((string) $key);

        return preg_match('/^[A-Z][A-Z0-9_]*$/', $key) === 1 ? $key : '';
    }

    private function existingEnvironmentValue(string $key): ?string
    {
        $value = getenv($key);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $path = base_path('.env');

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1], " \t\n\r\0\x0B\"");

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>|string  $requirement
     */
    private function promptEnvironmentValue(array|string $requirement, string $key): ?string
    {
        $label = is_array($requirement)
            ? trim((string) ($requirement['label'] ?? $key))
            : $key;
        $secret = is_array($requirement) && (bool) ($requirement['secret'] ?? false);
        $default = is_array($requirement) && is_scalar($requirement['default'] ?? null)
            ? (string) $requirement['default']
            : null;
        $prompt = $label.($label === $key ? '' : " [{$key}]");
        $value = $secret
            ? $this->secret($prompt)
            : $this->ask($prompt, $default);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
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
