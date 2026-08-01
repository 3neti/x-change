<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Host;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class HostUserModelAdopter
{
    private const XCHANGE_IMPORT = 'use LBHurtado\\XChange\\Auth\\XChangeAuthenticatable;';

    public function __construct(private Filesystem $files) {}

    /**
     * @return array{status: string, path: string, changed: bool, backup_path: string|null}
     */
    public function adopt(bool $commit = true): array
    {
        $model = config('auth.providers.users.model');

        if ($model !== 'App\\Models\\User') {
            throw new RuntimeException(
                'Automatic host adoption supports the conventional [App\\Models\\User] auth model only. '
                .'Extend [LBHurtado\\XChange\\Auth\\XChangeAuthenticatable] in the configured model, then retry.',
            );
        }

        return $this->adoptPath(app_path('Models/User.php'), $commit);
    }

    /**
     * @return array{status: string, path: string, changed: bool, backup_path: string|null}
     */
    public function adoptPath(string $path, bool $commit = true): array
    {
        if (! $this->files->isFile($path)) {
            throw new RuntimeException("Host auth model [{$path}] does not exist.");
        }

        $source = $this->files->get($path);

        if (str_contains($source, 'extends XChangeAuthenticatable')) {
            return $this->result('already_adopted', $path, false, null);
        }

        $foundationImport = 'use Illuminate\\Foundation\\Auth\\User as Authenticatable;';

        if (
            substr_count($source, $foundationImport) !== 1
            || preg_match('/\bclass\s+User\s+extends\s+Authenticatable\b/', $source) !== 1
        ) {
            throw new RuntimeException(
                'The host User model is customized beyond the safe automatic adoption pattern. '
                .'Extend [LBHurtado\\XChange\\Auth\\XChangeAuthenticatable] manually; no file was changed.',
            );
        }

        $adopted = str_replace($foundationImport, self::XCHANGE_IMPORT, $source, $importCount);
        $adopted = preg_replace(
            '/\bclass\s+User\s+extends\s+Authenticatable\b/',
            'class User extends XChangeAuthenticatable',
            $adopted,
            1,
            $classCount,
        );

        if ($importCount !== 1 || $classCount !== 1 || ! is_string($adopted)) {
            throw new RuntimeException('Host User model adoption could not be applied safely; no file was changed.');
        }

        if (! $commit) {
            return $this->result('would_adopt', $path, false, null);
        }

        $backupPath = storage_path(
            'app/x-change/host-adoption/User.php.'.now()->format('YmdHis').'.bak',
        );
        $this->files->ensureDirectoryExists(dirname($backupPath));
        $this->files->put($backupPath, $source);
        $this->files->replace($path, $adopted);

        return $this->result('adopted', $path, true, $backupPath);
    }

    /**
     * @return array{status: string, path: string, changed: bool, backup_path: string|null}
     */
    private function result(
        string $status,
        string $path,
        bool $changed,
        ?string $backupPath,
    ): array {
        return [
            'status' => $status,
            'path' => $path,
            'changed' => $changed,
            'backup_path' => $backupPath,
        ];
    }
}
