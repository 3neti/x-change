<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Carbon\CarbonInterface;
use LBHurtado\EmiCore\Models\Wallet as EmiWallet;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use Throwable;

class ProviderAwareFundingPolicy implements ProviderFundingPolicyContract
{
    public function __construct(
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected ProviderAccountLinkRepositoryContract $links,
        protected ProviderProvisioningGatewayContract $provisioning,
        protected WalletAccessContract $wallets,
        protected ?SyncPaynamicsWalletBalance $paynamicsBalances = null,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function assertCanIssue(mixed $owner, mixed $localWallet, float|int|string $amount, array $context = []): FundingDecisionData
    {
        $provider = $this->effectiveProviderForOwner($owner, data_get($context, 'provider'));
        $topology = $this->settings->topology($provider);
        $requiredMinor = $this->normalizeAmountForWallet($amount);
        $currency = (string) data_get($context, 'currency', config('x-change.pricing.currency', 'PHP'));

        if ($topology === 'provider_customer_wallet') {
            $decision = $this->providerWalletDecision($owner, $provider, $requiredMinor, $currency);

            if (! $decision->allowed) {
                throw new InsufficientWalletBalance($decision->blocking_reason ?? 'Provider wallet balance is insufficient.');
            }

            return $decision;
        }

        $availableMinor = $this->normalizeBalanceForComparison($this->wallets->getBalance($localWallet));

        if ($availableMinor < $requiredMinor) {
            throw new InsufficientWalletBalance(sprintf(
                'Issuer wallet cannot afford the requested amount. Balance: %s, Required: %s',
                $availableMinor,
                $requiredMinor,
            ));
        }

        return FundingDecisionData::allowed(
            authority: $topology === 'ledger_pooled' ? 'local_ledger' : 'manual',
            availableMinor: $availableMinor,
            requiredMinor: $requiredMinor,
            currency: $currency,
            freshAsOf: now()->toIso8601String(),
            meta: [
                'provider' => $provider,
                'topology' => $topology,
            ],
        );
    }

    protected function providerWalletDecision(mixed $owner, string $provider, int $requiredMinor, string $currency): FundingDecisionData
    {
        $link = $this->links->findReadyForOwner($owner, $provider);

        if ($link === null || blank($link->provider_wallet_id)) {
            return FundingDecisionData::blocked(
                authority: 'provider_wallet',
                availableMinor: 0,
                requiredMinor: $requiredMinor,
                reason: 'Issuer provider wallet is not ready.',
                currency: $currency,
                meta: ['provider' => $provider],
            );
        }

        try {
            $refresh = $provider === 'paynamics'
                ? $this->syncPaynamicsWallet($link->provider_wallet_id, $owner)
                : $this->provisioning->refresh($link);
        } catch (Throwable $e) {
            return FundingDecisionData::blocked(
                authority: 'provider_wallet',
                availableMinor: 0,
                requiredMinor: $requiredMinor,
                reason: 'Provider wallet balance could not be refreshed.',
                currency: $currency,
                freshAsOf: $link->last_synced_at?->toIso8601String(),
                meta: [
                    'provider' => $provider,
                    'link_id' => $link->getKey(),
                    'exception' => $e::class,
                ],
            );
        }

        if ((bool) data_get($refresh, 'refreshed', false) !== true) {
            return FundingDecisionData::blocked(
                authority: 'provider_wallet',
                availableMinor: 0,
                requiredMinor: $requiredMinor,
                reason: 'Provider wallet balance refresh did not complete.',
                currency: $currency,
                freshAsOf: $link->last_synced_at?->toIso8601String(),
                meta: [
                    'provider' => $provider,
                    'link_id' => $link->getKey(),
                ],
            );
        }

        $link->forceFill(['last_synced_at' => now()])->save();

        $wallet = $this->resolveEmiWallet($link->provider_wallet_id);

        if ($wallet === null) {
            return FundingDecisionData::blocked(
                authority: 'provider_wallet',
                availableMinor: 0,
                requiredMinor: $requiredMinor,
                reason: 'Provider wallet projection was not found after refresh.',
                currency: $currency,
                freshAsOf: now()->toIso8601String(),
                meta: [
                    'provider' => $provider,
                    'link_id' => $link->getKey(),
                    'provider_wallet_id' => $link->provider_wallet_id,
                ],
            );
        }

        $availableMinor = $this->normalizeBalanceForComparison($wallet->balance_cached);
        $freshAsOf = $wallet->updated_at ?? now();

        if (! $this->isFresh($freshAsOf)) {
            return FundingDecisionData::blocked(
                authority: 'provider_wallet',
                availableMinor: $availableMinor,
                requiredMinor: $requiredMinor,
                reason: 'Provider wallet balance snapshot is stale.',
                currency: (string) ($wallet->currency ?: $currency),
                freshAsOf: $freshAsOf->toIso8601String(),
                meta: [
                    'provider' => $provider,
                    'link_id' => $link->getKey(),
                    'provider_wallet_id' => $wallet->provider_wallet_id,
                ],
            );
        }

        if ($availableMinor < $requiredMinor) {
            return FundingDecisionData::blocked(
                authority: 'provider_wallet',
                availableMinor: $availableMinor,
                requiredMinor: $requiredMinor,
                reason: sprintf(
                    'Provider wallet cannot afford the requested amount. Balance: %s, Required: %s',
                    $availableMinor,
                    $requiredMinor,
                ),
                currency: (string) ($wallet->currency ?: $currency),
                freshAsOf: $freshAsOf->toIso8601String(),
                meta: [
                    'provider' => $provider,
                    'link_id' => $link->getKey(),
                    'provider_wallet_id' => $wallet->provider_wallet_id,
                ],
            );
        }

        return FundingDecisionData::allowed(
            authority: 'provider_wallet',
            availableMinor: $availableMinor,
            requiredMinor: $requiredMinor,
            currency: (string) ($wallet->currency ?: $currency),
            freshAsOf: $freshAsOf->toIso8601String(),
            meta: [
                'provider' => $provider,
                'link_id' => $link->getKey(),
                'provider_wallet_id' => $wallet->provider_wallet_id,
            ],
        );
    }

    protected function resolveEmiWallet(string $providerWalletId): ?EmiWallet
    {
        return EmiWallet::query()
            ->where('provider_wallet_id', $providerWalletId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncPaynamicsWallet(string $walletId, mixed $owner): array
    {
        $this->paynamicsBalances ??= app(SyncPaynamicsWalletBalance::class);
        $this->paynamicsBalances->handle($walletId, $owner);

        return ['refreshed' => true];
    }

    protected function effectiveProviderForOwner(mixed $owner, mixed $provider): string
    {
        $override = is_string($provider) ? $provider : null;
        $resolved = $this->settings->provider($override);

        if ($override !== null || $resolved !== 'manual') {
            return $resolved;
        }

        $link = $this->links->findReadyForOwner($owner, 'paynamics');

        return $link !== null && filled($link->provider_wallet_id)
            ? 'paynamics'
            : $resolved;
    }

    protected function isFresh(CarbonInterface $timestamp): bool
    {
        $maxAge = (int) config('x-change.funding.provider_balance_max_age_seconds', 300);

        return $timestamp->greaterThanOrEqualTo(now()->subSeconds($maxAge));
    }

    protected function normalizeAmountForWallet(float|int|string $amount): int
    {
        if (is_int($amount)) {
            return $amount;
        }

        if (is_string($amount)) {
            $trimmed = trim($amount);

            if (preg_match('/^-?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }

            $amount = (float) $trimmed;
        }

        return (int) round(((float) $amount) * 100);
    }

    protected function normalizeBalanceForComparison(int|float|string $balance): int
    {
        if (is_int($balance)) {
            return $balance;
        }

        if (is_string($balance)) {
            $trimmed = trim($balance);

            if (preg_match('/^-?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }

            return (int) round(((float) $trimmed) * 100);
        }

        return (int) round($balance * 100);
    }
}
