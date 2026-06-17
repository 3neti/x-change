<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface ProviderProvisioningManagerContract
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function startOrResume(mixed $owner, array $payload): array;
}
