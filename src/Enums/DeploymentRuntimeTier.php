<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

use InvalidArgumentException;

enum DeploymentRuntimeTier: string
{
    case Local = 'local';
    case Staging = 'staging';
    case Production = 'production';

    public static function resolve(?string $value): self
    {
        $normalized = strtolower(trim((string) $value));
        $tier = self::tryFrom($normalized);

        if ($tier === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown X-Change runtime tier [%s]. Expected local, staging, or production.',
                $normalized === '' ? 'empty' : $normalized,
            ));
        }

        return $tier;
    }

    public function requiresDurableInfrastructure(): bool
    {
        return $this !== self::Local;
    }
}
