<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class PolicyCompletionTransportDispositionFileRepository
{
    private const MAX_BYTES = 65_536;

    /** @return array<string, mixed> */
    public function read(string $path): array
    {
        $path = $this->localPath($path);

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Policy completion transport disposition file is not readable.');
        }

        $size = filesize($path);
        if (! is_int($size) || $size > self::MAX_BYTES) {
            throw new RuntimeException('Policy completion transport disposition file exceeds the size limit.');
        }

        $manifest = Yaml::parseFile($path);
        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException('Policy completion transport disposition must be a YAML mapping.');
        }

        return $manifest;
    }

    public function writeTemplate(string $path, string $driverId, string $driverVersion): string
    {
        $path = $this->localPath($path);

        if (file_exists($path)) {
            throw new RuntimeException('Policy completion transport disposition template already exists.');
        }

        $directory = dirname($path);
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('Policy completion transport disposition template directory is not writable.');
        }

        $written = file_put_contents(
            $path,
            Yaml::dump($this->template($driverId, $driverVersion), 4, 2),
            LOCK_EX,
        );
        if (! is_int($written) || $written < 1) {
            throw new RuntimeException('Policy completion transport disposition template could not be written.');
        }

        chmod($path, 0640);

        return $path;
    }

    /** @return array<string, bool|int|string> */
    public function template(string $driverId, string $driverVersion): array
    {
        $driverId = trim($driverId);
        $driverVersion = trim($driverVersion);
        if ($driverId === '' || $driverVersion === '') {
            throw new RuntimeException('Policy completion transport disposition requires a driver ID and version.');
        }

        $template = Yaml::parseFile(
            dirname(__DIR__, 3).'/resources/policy-completion-transports/disposition.template.yaml',
        );
        if (! is_array($template) || array_is_list($template)) {
            throw new RuntimeException('Package policy completion transport disposition template is invalid.');
        }

        $template['driver_id'] = $driverId;
        $template['driver_version'] = $driverVersion;

        return $template;
    }

    private function localPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, '://')) {
            throw new RuntimeException('Policy completion transport disposition must use a local file path.');
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
    }
}
