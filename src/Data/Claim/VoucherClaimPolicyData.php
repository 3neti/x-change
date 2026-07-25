<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

final class VoucherClaimPolicyData extends Data
{
    /**
     * @param  list<VoucherClaimOutcomeData>  $outcomes
     * @param  array<string, mixed>|null  $onboarding
     * @param  array<string, mixed>|null  $claimantBinding
     */
    public function __construct(
        public readonly string $profile,
        public readonly array $outcomes,
        public readonly string $selection,
        public readonly string $consumption,
        public readonly ?string $defaultOutcome,
        public readonly ?array $onboarding,
        public readonly ?array $claimantBinding,
        public readonly bool $legacy,
    ) {}

    public function permits(string $outcome): bool
    {
        return collect($this->outcomes)
            ->contains(
                static fn (VoucherClaimOutcomeData $item): bool => $item->key === $outcome,
            );
    }
}
