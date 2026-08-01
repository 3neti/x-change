<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Host;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class HostApplicationShellAdopter
{
    private const Marker = 'X-CHANGE HOST SHELL';

    public function __construct(private Filesystem $files) {}

    /**
     * @return array{status: string, path: string, changed: bool, backup_path: string|null}
     */
    public function adopt(bool $commit = true): array
    {
        return $this->adoptPath(
            resource_path('js/components/AppSidebar.vue'),
            $commit,
        );
    }

    /**
     * @return array{status: string, path: string, changed: bool, backup_path: string|null}
     */
    public function adoptPath(string $path, bool $commit = true): array
    {
        if (! $this->files->isFile($path)) {
            throw new RuntimeException("Host application sidebar [{$path}] does not exist.");
        }

        $source = $this->files->get($path);
        $replacement = $this->files->get($this->stubPath());

        if ($source === $replacement) {
            return $this->result('already_adopted', $path, false, null);
        }

        if (! $this->isSafeToReplace($source)) {
            throw new RuntimeException(
                'The host application sidebar is customized beyond the safe automatic adoption pattern. '
                .'Publish [x-change-shell] deliberately or integrate the Cockpit navigation manually; no file was changed.',
            );
        }

        if (! $commit) {
            return $this->result('would_adopt', $path, false, null);
        }

        $backupPath = storage_path(
            'app/x-change/host-adoption/AppSidebar.vue.'.now()->format('YmdHis').'.bak',
        );
        $this->files->ensureDirectoryExists(dirname($backupPath));
        $this->files->put($backupPath, $source);
        $this->files->replace($path, $replacement);

        return $this->result('adopted', $path, true, $backupPath);
    }

    private function isSafeToReplace(string $source): bool
    {
        if (str_contains($source, self::Marker)) {
            return true;
        }

        return str_contains($source, 'https://github.com/laravel/vue-starter-kit')
            && str_contains($source, 'https://laravel.com/docs/starter-kits#vue')
            && str_contains($source, 'const mainNavItems: NavItem[]')
            && str_contains($source, '<NavUser />');
    }

    private function stubPath(): string
    {
        return dirname(__DIR__, 3).'/stubs/resources/js/components/AppSidebar.vue.stub';
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
