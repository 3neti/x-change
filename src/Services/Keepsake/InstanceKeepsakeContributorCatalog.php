<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use InvalidArgumentException;
use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeContributor;

final readonly class InstanceKeepsakeContributorCatalog
{
    /** @param iterable<InstanceKeepsakeContributor> $contributors */
    public function __construct(private iterable $contributors) {}

    /** @return list<InstanceKeepsakeContributor> */
    public function contributors(): array
    {
        $resolved = [];

        foreach ($this->contributors as $contributor) {
            if (! $contributor instanceof InstanceKeepsakeContributor) {
                throw new InvalidArgumentException('An invalid instance keepsake contributor was registered.');
            }

            $key = trim($contributor->key());

            if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $key) !== 1) {
                throw new InvalidArgumentException("Invalid instance keepsake contributor key [{$key}].");
            }

            if (isset($resolved[$key])) {
                throw new InvalidArgumentException("Duplicate instance keepsake contributor key [{$key}].");
            }

            if ($contributor->snapshotSchemaVersion() < 1) {
                throw new InvalidArgumentException("Invalid snapshot schema version for [{$key}].");
            }

            $resolved[$key] = $contributor;
        }

        ksort($resolved);

        return array_values($resolved);
    }
}
