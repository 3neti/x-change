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
                $layer['x'] = 330;
                $layer['width'] = min($layer['width'], 820);
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

    /** @return list<array{id: string, revision: int, name: string}> */
    public function options(): array
    {
        return array_map(fn (Design $design): array => ['id' => $design->id(), 'revision' => $design->revision(), 'name' => $design->document['name']], $this->catalog()->all());
    }
}
