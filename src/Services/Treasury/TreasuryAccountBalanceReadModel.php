<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;

final class TreasuryAccountBalanceReadModel implements AccountBalanceReadModelContract
{
    /**
     * @var array<string, ?int>
     */
    private array $balanceCache = [];

    public function __construct(
        private readonly TreasuryPrincipalReferenceResolverContract $principalReferences,
        private readonly TreasuryPositionReadModelContract $positions,
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

    public function forget(Model $owner, string $currency, ?string $provider = null): void
    {
        $currency = mb_strtoupper(trim($currency));
        $provider = $provider === null ? null : mb_strtolower(trim($provider));

        unset($this->balanceCache[$this->cacheKey($owner, $currency, $provider)]);
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
        $provider = $provider === null ? null : mb_strtolower(trim($provider));
        $key = $this->cacheKey($owner, $currency, $provider);

        if (array_key_exists($key, $this->balanceCache)) {
            return $this->balanceCache[$key];
        }

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
            return $this->balanceCache[$key] = null;
        }

        return $this->balanceCache[$key] = array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            $positions,
        ));
    }

    private function cacheKey(Model $owner, string $currency, ?string $provider): string
    {
        return implode(':', [
            $owner->getMorphClass(),
            (string) $owner->getKey(),
            $currency,
            $provider ?? '*',
        ]);
    }
}
