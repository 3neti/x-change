<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class LocalEnvironmentFileWriter
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<string, string>  $values
     * @return array{created: bool, changed: bool, backup_path: string|null, application_key_generated: bool}
     */
    public function write(string $path, string $examplePath, array $values): array
    {
        $created = ! $this->files->exists($path);

        if ($created && ! $this->files->exists($examplePath)) {
            throw new RuntimeException("Environment example [{$examplePath}] does not exist.");
        }

        $original = $created ? $this->files->get($examplePath) : $this->files->get($path);
        $applicationKeyGenerated = $this->environmentValue($original, 'APP_KEY') === '';

        if ($applicationKeyGenerated) {
            $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        $updated = $original;

        foreach ($values as $key => $value) {
            $updated = $this->replaceOrAppend($updated, $key, $value);
        }

        $updated = rtrim($updated).PHP_EOL;

        if (! $created && hash_equals($original, $updated)) {
            return [
                'created' => false,
                'changed' => false,
                'backup_path' => null,
                'application_key_generated' => false,
            ];
        }

        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0700, true);
        }

        $lockPath = $path.'.lock';
        $lock = fopen($lockPath, 'c+');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException("Unable to lock environment file [{$path}].");
        }

        $backupPath = null;

        try {
            if (! $created) {
                $backupPath = $this->uniqueBackupPath($path);
                $this->files->copy($path, $backupPath);
                chmod($backupPath, 0600);
            }

            $temporary = $path.'.tmp';

            if ($this->files->put($temporary, $updated, true) === false) {
                throw new RuntimeException("Unable to write environment file [{$path}].");
            }

            chmod($temporary, 0600);

            if (! $this->files->move($temporary, $path)) {
                throw new RuntimeException("Unable to replace environment file [{$path}].");
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
        }

        return [
            'created' => $created,
            'changed' => true,
            'backup_path' => $backupPath,
            'application_key_generated' => $applicationKeyGenerated,
        ];
    }

    private function environmentValue(string $contents, string $key): ?string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1], " \t\n\r\0\x0B\"");
    }

    private function replaceOrAppend(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$this->encode($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $line, $contents, 1);
        }

        return rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }

    private function encode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/+=\-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\').'"';
    }

    private function uniqueBackupPath(string $path): string
    {
        $base = $path.'.x-change.backup.'.now()->format('YmdHis');
        $candidate = $base;
        $counter = 1;

        while ($this->files->exists($candidate)) {
            $candidate = $base.'.'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
