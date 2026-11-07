<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

final class ClaimPayoutLocalityScope
{
    private ?string $claimKey = null;

    public function get(): ?string
    {
        return $this->claimKey;
    }

    public function set(?string $claimKey): void
    {
        $this->claimKey = $claimKey;
    }
}
