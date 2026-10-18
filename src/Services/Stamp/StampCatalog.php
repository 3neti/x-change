<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Stamp;

use Composer\InstalledVersions;
use InvalidArgumentException;
use LBHurtado\XStamp\Catalog;
use LBHurtado\XStamp\Design;
use RuntimeException;

final class StampCatalog
{
    private ?Catalog $catalog = null;

    public function catalog(): Catalog
    {
        if ($this->catalog === null) {
            $catalog = new Catalog;
            foreach ((array) config('x-change.experience.stamp_collections', ['3neti/x-stamp-designs']) as $package) {
                $path = InstalledVersions::getInstallPath($package);
                if ($path === null) {
                    throw new RuntimeException('The Stamp design collection is not installed.');
                }
                $catalog->load($path);
            }
            foreach ($catalog->all() as $design) {
                if (str_starts_with($design->id(), 'x-change-') || array_key_exists($design->id(), (array) config('x-change.experience.stamp_designs', []))) {
                    throw new RuntimeException('A Stamp collection cannot replace a historical x-change design.');
                }
            }
            $this->catalog = $catalog;
        }

        return $this->catalog;
    }

    public function resolve(string $id, int $revision): Design
    {
        $design = $this->catalog()->resolve($id, $revision);
        foreach (['brand', 'amount', 'recipient', 'purpose', 'expiry', 'instructions'] as $slot) {
            if (! in_array($slot, $design->slots(), true)) {
                throw new RuntimeException('The Stamp design is missing a required Pay Code slot.');
            }
        }
        if ($design->document['width'] !== 1200 || $design->document['height'] !== 630) {
            throw new RuntimeException('Unsupported Pay Code Stamp canvas.');
        }
        foreach ($design->document['layers'] as $layer) {
            if (isset($layer['slot']) && ($layer['x'] + $layer['width'] > 900 || $layer['y'] + $layer['height'] > 600)) {
                throw new RuntimeException('Pay Code content overlaps the protected claim marker.');
            }
        }

        return $design;
    }

    public function presentation(string $id, int $revision, string $position, string $amount = ''): Design
    {
        $design = $this->resolve($id, $revision);
        $layers = $design->document['layers'];
        foreach ($layers as &$layer) {
            if (! isset($layer['slot'])) {
                continue;
            }
            if (str_ends_with($position, '_left')) {
                $layer['x'] = in_array('provider_logo', $design->slots(), true) ? $layer['x'] + 265 : 330;
                $layer['width'] = min($layer['width'], 1150 - $layer['x']);
            }
            if ($layer['slot'] === 'amount' && $amount !== '') {
                $size = $layer['size'] ?? 24;
                do {
                    $box = imagettfbbox($size * .75, 0, $design->asset($design->document['font']), $amount);
                    if (abs($box[2] - $box[0]) <= $layer['width']) {
                        break;
                    }
                    $size--;
                } while ($size >= 24);
                if ($size < 24) {
                    throw new InvalidArgumentException('The amount is too long to display legibly on this Stamp.');
                }
                $layer['size'] = $size;
                $layer['max_lines'] = 1;
            }
        }
        unset($layer);
        $decoration = array_values(array_filter($layers, static fn (array $layer): bool => ! isset($layer['slot'])));
        $content = array_values(array_filter($layers, static fn (array $layer): bool => isset($layer['slot'])));

        return $design->withLayers([...$decoration, ...$content]);
    }

    public function providerPresentation(Design $design, string $name, bool $hasLogo): Design
    {
        $header = array_column(array_filter($design->document['layers'], static fn (array $layer): bool => isset($layer['slot'])), null, 'slot');
        if (! isset($header['provider_logo'], $header['provider_name'], $header['brand'])
            || $header['provider_logo']['x'] >= $header['brand']['x']) {
            return $design;
        }

        $provider = $hasLogo ? $header['provider_logo'] : $header['provider_name'];
        $width = $hasLogo ? $provider['width'] : 0;
        if (! $hasLogo && $name !== '') {
            $bounds = imagettfbbox(($provider['size'] ?? 24) * .75, 0, $design->asset($design->document['font']), $name);
            $width = min($provider['width'], abs($bounds[2] - $bounds[0]));
        }
        $brandX = $provider['x'] + $width + ($width > 0 ? 16 : 0);
        $layers = $design->document['layers'];
        foreach ($layers as &$layer) {
            if (($layer['slot'] ?? null) === 'brand') {
                $right = $layer['x'] + $layer['width'];
                $layer['x'] = $brandX;
                $layer['width'] = $right - $brandX;
            }
        }
        unset($layer);

        return $design->withLayers($layers);
    }

    /** @return array{paper: string, trim: string} */
    public function markerPalette(Design $design): array
    {
        $background = $design->document['background'];
        $brightness = hexdec(substr($background, 1, 2)) * .299
            + hexdec(substr($background, 3, 2)) * .587
            + hexdec(substr($background, 5, 2)) * .114;
        $paper = $brightness >= 128 ? $background : $design->document['foreground'];
        $trim = $design->document['foreground'];
        foreach ($design->document['layers'] as $layer) {
            if (($layer['slot'] ?? null) === 'brand') {
                $trim = $layer['color'];
                break;
            }
        }

        return ['paper' => $paper, 'trim' => $trim];
    }

    /** @return list<array<string, mixed>> */
    public function options(): array
    {
        $latest = [];
        foreach ($this->catalog()->all() as $design) {
            if (! isset($latest[$design->id()]) || $latest[$design->id()]->revision() < $design->revision()) {
                $latest[$design->id()] = $design;
            }
        }

        return array_values(array_map(static fn (Design $design): array => [
            'id' => $design->id(), 'revision' => $design->revision(), 'name' => $design->document['name'],
            'thumbnail' => '/vendor/x-stamp/designs/'.$design->id().'/'.$design->revision().'/preview.png',
            'customization' => isset($design->document['customization']) ? array_replace($design->document['customization'], ['defaults' => app(StampPresentation::class)->snapshot($design)['options']]) : null,
            'presentation' => app(StampPresentation::class)->snapshot($design),
        ], $latest));
    }
}
