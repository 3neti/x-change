<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use Illuminate\Database\Eloquent\Model;

final readonly class LifecycleScenarioBootstrapResult
{
    public function __construct(
        public int $issuerId,
        public int $walletId,
        public float $amount,
        public int $timeout,
        public int $poll,
        public ?int $maxPolls,
        public Model $issuer,
        public string $baseClaimMobile,
        public string $idempotencyKey,
        public array $lifecycleInput,
        public array $estimate,
        public mixed $generated,
        public mixed $voucher,
    ) {}
}
