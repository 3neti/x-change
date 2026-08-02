<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class HostComposerScriptAdopter
{
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
            'scripts' => array_keys(self::Scripts),
        ];
    }
}
