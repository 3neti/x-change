<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commissioning;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final class CommissioningManifestRepository
{
    /** @return array<string, mixed> */
    public function load(string $reference): array
    {
        return $this->loadResolved($reference, []);
    }

    /**
     * @param  list<string>  $stack
     * @return array<string, mixed>
     */
    private function loadResolved(string $reference, array $stack): array
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new InvalidArgumentException('A commissioning manifest reference is required.');
        }

        if (in_array($reference, $stack, true)) {
            throw new InvalidArgumentException('Commissioning manifest extends cycle detected.');
        }

        if (count($stack) >= 5) {
            throw new InvalidArgumentException('Commissioning manifest extends depth exceeded.');
        }

        $manifest = $this->parse($reference, $this->contents($reference));
        $parent = Arr::pull($manifest, 'extends');

        if (is_string($parent) && trim($parent) !== '') {
            $manifest = $this->merge(
                $this->loadResolved($parent, [...$stack, $reference]),
                $manifest,
            );
        }

        return $manifest;
    }

    private function contents(string $reference): string
    {
        if (str_starts_with($reference, 'x-change://commissioning/manifests/')) {
            $name = basename($reference);
            $path = $this->packagePath('commissioning/manifests/'.$name);

            return $this->readFile($path, $reference);
        }

        if (str_starts_with($reference, 'http://') || str_starts_with($reference, 'https://')) {
            return Http::connectTimeout(3)
                ->timeout(10)
                ->get($reference)
                ->throw()
                ->body();
        }

        $path = $reference;

        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! file_exists($path)) {
            $path = base_path($path);
        }

        return $this->readFile($path, $reference);
    }

    private function readFile(string $path, string $reference): string
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Commissioning manifest [{$reference}] was not found.");
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new InvalidArgumentException("Commissioning manifest [{$reference}] could not be read.");
        }

        return $contents;
    }

    /** @return array<string, mixed> */
    private function parse(string $reference, string $contents): array
    {
        $manifest = Yaml::parse($contents);

        if (! is_array($manifest)) {
            throw new InvalidArgumentException("Commissioning manifest [{$reference}] must contain a YAML mapping.");
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $base[$key] = $value;

                continue;
            }

            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($base[$key])) {
                $base[$key] = $this->merge($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private function packagePath(string $path = ''): string
    {
        $base = dirname(__DIR__, 3);

        return $path !== ''
            ? $base.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR)
            : $base;
    }
}
