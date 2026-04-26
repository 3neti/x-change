<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\VendorRegistryContract;
use LBHurtado\XChange\Data\VendorIdentityData;

class ConfigVendorRegistry implements VendorRegistryContract
{
    public function normalize(?string $alias): ?string
    {
        $alias = trim((string) $alias);

        return $alias === '' ? null : strtoupper($alias);
    }

    public function resolve(?string $alias, array $context = []): ?VendorIdentityData
    {
        $normalized = $this->normalize($alias);

        if ($normalized === null) {
            return null;
        }

        $vendors = config('x-change.vendors.aliases', []);

        foreach ($vendors as $canonical => $config) {
            $candidateAliases = array_map(
                fn ($value) => $this->normalize($value),
                array_merge([$canonical], $config['aliases'] ?? []),
            );

            if (! in_array($normalized, $candidateAliases, true)) {
                continue;
            }

            return new VendorIdentityData(
                canonicalAlias: $this->normalize($canonical),
                vendorId: $config['id'] ?? null,
                displayName: $config['name'] ?? $canonical,
                aliases: $candidateAliases,
                meta: $config['meta'] ?? [],
            );
        }

        return new VendorIdentityData(
            canonicalAlias: $normalized,
            aliases: [$normalized],
        );
    }
}
