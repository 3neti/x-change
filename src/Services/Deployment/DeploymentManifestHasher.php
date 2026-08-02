<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

final readonly class DeploymentManifestHasher
{
    /** @param array<string, mixed> $manifest */
    public function hash(array $manifest): string
    {
        unset($manifest['manifest_hash']);

        return hash('sha256', json_encode(
            $this->normalize($manifest),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * @param  array<mixed>  $items
     * @return array<mixed>
     */
    private function normalize(array $items): array
    {
        if (! array_is_list($items)) {
            ksort($items);
        }

        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $items[$key] = $this->normalize($item);
            }
        }

        return $items;
    }
}
