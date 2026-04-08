<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface RedemptionCompletionStoreContract
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByReference(string $referenceId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByFlowId(string $flowId): ?array;
}
