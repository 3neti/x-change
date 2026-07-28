<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface ClaimUrlQrRendererContract
{
    public function render(string $claimUrl): string;
}
