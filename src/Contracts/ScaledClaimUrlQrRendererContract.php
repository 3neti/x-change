<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface ScaledClaimUrlQrRendererContract
{
    public function renderAtScale(string $claimUrl, int $scale): string;
}
