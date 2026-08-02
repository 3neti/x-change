<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class HostComposerScriptAdopter
{
    private const PackageDiscoveryCommand = '@php artisan package:discover --ansi';

    private const PublicationCommand = '@php artisan x-change:publish --scope=build --force --verify --no-interaction';

    private const Scripts = [
        'x-change:cloud:plan' => '@php vendor/bin/x-change-cloud plan',
        'x-change:cloud:ship' => '@php vendor/bin/x-change-cloud ship',
        'x-change:cloud:verify' => '@php vendor/bin/x-change-cloud verify',
    ];

    public function __construct(private Filesystem $files) {}

    /** @return array{status: string, scripts: list<string>} */
    public function adopt(string $path, bool $commit): array
    {
        if (! $this->files->exists($path)) {
            throw new RuntimeException("Host Composer file [{$path}] does not exist.");
        }

        $composer = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($composer)) {
            throw new RuntimeException('Host composer.json must contain a JSON object.');
        }

        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];

        foreach (self::Scripts as $name => $command) {
            if (isset($scripts[$name]) && $scripts[$name] !== $command) {
                throw new RuntimeException("Host Composer script [{$name}] already has a different command.");
            }

            $scripts[$name] = $command;
        }

        $scripts['post-autoload-dump'] = $this->adoptPostAutoloadDump(
            $scripts['post-autoload-dump'] ?? [],
        );

        $changed = ($composer['scripts'] ?? []) !== $scripts;

        if ($commit && $changed) {
            $composer['scripts'] = $scripts;
            $this->files->put($path, json_encode(
                $composer,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ).PHP_EOL);
        }

        return [
            'status' => $changed ? ($commit ? 'adopted' : 'would_adopt') : 'already_adopted',
            'scripts' => [...array_keys(self::Scripts), 'post-autoload-dump'],
        ];
    }

    /**
     * @return list<string>
     */
    private function adoptPostAutoloadDump(mixed $commands): array
    {
        if (is_string($commands)) {
            $commands = [$commands];
        }

        if (! is_array($commands) || array_filter($commands, fn (mixed $command): bool => ! is_string($command)) !== []) {
            throw new RuntimeException('Host Composer script [post-autoload-dump] must be a string or a list of strings.');
        }

        $commands = array_values($commands);

        foreach ($commands as $command) {
            if (str_contains($command, 'x-change:publish') && $command !== self::PublicationCommand) {
                throw new RuntimeException('Host Composer script [post-autoload-dump] already has a different X-Change publication command.');
            }
        }

        $commands = array_values(array_filter(
            $commands,
            fn (string $command): bool => $command !== self::PublicationCommand,
        ));
        $discoveryIndex = null;

        foreach ($commands as $index => $command) {
            if (str_contains($command, 'artisan package:discover')) {
                $discoveryIndex = $index;
            }
        }

        if ($discoveryIndex === null) {
            $commands[] = self::PackageDiscoveryCommand;
            $discoveryIndex = array_key_last($commands);
        }

        array_splice($commands, $discoveryIndex + 1, 0, [self::PublicationCommand]);

        return $commands;
    }
}
