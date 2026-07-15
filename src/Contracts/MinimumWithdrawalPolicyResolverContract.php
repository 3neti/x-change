<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\MinimumWithdrawalPolicyData;

interface MinimumWithdrawalPolicyResolverContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $payload = []): MinimumWithdrawalPolicyData;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertIssuancePayload(array $payload): void;
}
