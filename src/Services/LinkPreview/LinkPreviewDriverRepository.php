<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\LinkPreview;

use Symfony\Component\Yaml\Yaml;
use Throwable;

final class LinkPreviewDriverRepository
{
    /**
     * @var null|array<string, LinkPreviewDriverData>
     */
    private ?array $drivers = null;

    public function __construct(
        private readonly LinkCanonicalizerRegistry $canonicalizers,
    ) {}

    /**
     * @return array<string, LinkPreviewDriverData>
     */
    public function all(): array
    {
        return $this->drivers ??= $this->loadConfiguredDrivers();
    }

    /**
     * @return list<array{path: string, key: ?string, valid: bool, enabled: bool, error: ?string}>
     */
    public function diagnostics(): array
    {
        $diagnostics = [];

        foreach ($this->driverPaths() as $path) {
            try {
                $manifest = Yaml::parseFile($path);

                if (! is_array($manifest)) {
                    throw new \InvalidArgumentException('Driver manifest must contain a mapping.');
                }

                $driver = LinkPreviewDriverData::fromManifest(
                    $manifest,
                    $this->canonicalizers,
                    $this->enabledOverride($manifest['key'] ?? null),
                );
                $diagnostics[] = [
                    'path' => $path,
                    'key' => $driver->key,
                    'valid' => true,
                    'enabled' => $driver->enabled,
                    'error' => null,
                ];
            } catch (Throwable $exception) {
                $diagnostics[] = [
                    'path' => $path,
                    'key' => null,
                    'valid' => false,
                    'enabled' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, LinkPreviewDriverData>
     */
    private function loadConfiguredDrivers(): array
    {
        if (config('x-change.cockpit.quick_generate.url_artwork.driver_source', 'yaml') === 'legacy') {
            return $this->legacyDrivers();
        }

        $drivers = [];

        foreach ($this->driverPaths() as $path) {
            try {
                $manifest = Yaml::parseFile($path);

                if (! is_array($manifest)) {
                    continue;
                }

                $driver = LinkPreviewDriverData::fromManifest(
                    $manifest,
                    $this->canonicalizers,
                    $this->enabledOverride($manifest['key'] ?? null),
                );

                if ($driver->enabled) {
                    $drivers[$driver->key] = $driver;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $drivers;
    }

    /**
     * @return array<string, LinkPreviewDriverData>
     */
    private function legacyDrivers(): array
    {
        $providers = config(
            'x-change.cockpit.quick_generate.url_artwork.providers',
            [],
        );
        $drivers = [];

        foreach (is_array($providers) ? $providers : [] as $key => $provider) {
            if (! is_string($key) || ! is_array($provider)) {
                continue;
            }

            $manifest = [
                'schema_version' => 'x-change.link-preview-driver.v1',
                'key' => $key,
                'label' => $provider['label'] ?? str($key)->headline()->toString(),
                'enabled' => ($provider['enabled'] ?? false) === true,
                'canonicalization' => [
                    'strategy' => ($provider['strip_query'] ?? true)
                        ? 'strip_query'
                        : 'preserve_query',
                ],
                'match' => [
                    'hosts' => $provider['page_hosts'] ?? [],
                    'path_pattern' => $provider['path_pattern'] ?? '',
                ],
                'metadata' => [
                    'strategies' => ['oembed', 'open_graph'],
                    'oembed_endpoint' => $provider['oembed_endpoint'] ?? null,
                ],
                'artwork' => [
                    'hosts' => $provider['image_hosts'] ?? [],
                    'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                ],
            ];

            try {
                $driver = LinkPreviewDriverData::fromManifest(
                    $manifest,
                    $this->canonicalizers,
                );

                if ($driver->enabled) {
                    $drivers[$driver->key] = $driver;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $drivers;
    }

    /**
     * @return list<string>
     */
    private function driverPaths(): array
    {
        $directory = config(
            'x-change.cockpit.quick_generate.url_artwork.driver_directory',
        );
        $publishedRoot = config_path('link-preview-drivers');
        $root = is_string($directory) && trim($directory) !== ''
            ? rtrim($directory, DIRECTORY_SEPARATOR)
            : (is_dir($publishedRoot)
                ? $publishedRoot
                : dirname(__DIR__, 3).'/config/link-preview-drivers');
        $paths = glob($root.'/*.yaml');

        if (! is_array($paths)) {
            return [];
        }

        sort($paths);

        return array_values($paths);
    }

    private function enabledOverride(mixed $key): ?bool
    {
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        $overrides = config(
            'x-change.cockpit.quick_generate.url_artwork.enabled_drivers',
            [],
        );

        if (! is_array($overrides) || ! array_key_exists($key, $overrides)) {
            return null;
        }

        return (bool) $overrides[$key];
    }
}
