<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface XChangeOnboardingGatewayContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function startIssuer(array $payload): mixed;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function startRedemption(array $payload): mixed;

    public function ensureReady(?string $reference): mixed;
}
