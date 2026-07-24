<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;

final readonly class TreasuryAccountBalanceReadModel implements AccountBalanceReadModelContract
{
    public function __construct(
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionReadModelContract $positions,
    ) {}

    public function balanceMinor(mixed $owner, string $currency): ?int
    {
        return $this->sum($owner, $currency);
    }

    public function providerBalanceMinor(
        mixed $owner,
        string $provider,
        string $currency,
    ): ?int {
        return $this->sum($owner, $currency, mb_strtolower(trim($provider)));
    }

    private function sum(
        mixed $owner,
        string $currency,
        ?string $provider = null,
    ): ?int {
        if (! $owner instanceof Model) {
            return null;
        }

        $currency = mb_strtoupper(trim($currency));
        $positions = array_values(array_filter(
            $this->positions->forPrincipal(
                $this->principalReferences->resolve($owner),
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->purpose === TreasuryPositionPurpose::ClientFunds
                && $position->currency === $currency
                && ($provider === null || $position->provider === $provider),
        ));

        if ($positions === []) {
            return null;
        }

        return array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            $positions,
        ));
    }
}
