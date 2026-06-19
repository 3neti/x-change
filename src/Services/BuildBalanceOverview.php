<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Models\Wallet as EmiWallet;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use Throwable;

class BuildBalanceOverview
{
    public function __construct(
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected ProviderAccountLinkRepositoryContract $links,
        protected ProviderProvisioningGatewayContract $provisioning,
        protected WalletAccessContract $wallets,
        protected ?SyncPaynamicsWalletBalance $paynamicsBalances = null,
        protected ?CheckNetbankSourceAccountReadiness $netbankSourceAccount = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true): array
    {
        $provider = $this->effectiveProviderForOwner($owner, $provider);
        $topology = $this->settings->topology($provider);
        $authority = $topology === 'provider_customer_wallet'
            ? 'provider_wallet'
            : ($topology === 'ledger_pooled' ? 'local_ledger' : 'manual');

        $local = $this->localBalance($owner, $authority !== 'provider_wallet');
        $providerBalance = $topology === 'provider_customer_wallet'
            ? $this->providerWalletBalance($owner, $provider, $syncIfStale)
            : null;
        $netbankSourceBalance = $this->netbankSourceAccountBalance($provider, $topology);

        $balances = array_values(array_filter([
            $providerBalance,
            $local,
            $netbankSourceBalance,
        ]));

        $authoritative = collect($balances)
            ->first(fn (array $balance): bool => (bool) ($balance['is_authoritative'] ?? false));

        return [
            'provider' => $provider,
            'topology' => $topology,
            'authority' => $authority,
            'checked_at' => now()->toIso8601String(),
            'max_age_seconds' => (int) config('x-change.funding.provider_balance_max_age_seconds', 300),
            'sync_status' => $providerBalance['sync_status'] ?? $netbankSourceBalance['sync_status'] ?? 'not_required',
            'sync_message' => $providerBalance['sync_message'] ?? $netbankSourceBalance['sync_message'] ?? 'Local ledger balance does not require provider balance sync.',
            'authoritative' => $authoritative,
            'balances' => $balances,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function localBalance(mixed $owner, bool $authoritative): ?array
    {
        try {
            $wallet = $this->wallets->resolveForUser($owner);
            $balanceMinor = $this->normalizeBalanceForComparison($this->wallets->getBalance($wallet));

            return [
                'key' => 'local_ledger',
                'label' => $authoritative ? 'Available Local Ledger Balance' : 'Local Ledger Projection',
                'description' => $authoritative
                    ? 'This is the balance x-change uses for this provider topology.'
                    : 'Shown for accounting context only. Paynamics wallet balance is authoritative.',
                'authority' => 'local_ledger',
                'source' => 'bavix_wallet',
                'is_authoritative' => $authoritative,
                'is_stale' => false,
                'balance_minor' => $balanceMinor,
                'balance' => $balanceMinor / 100,
                'currency' => (string) config('x-change.pricing.currency', 'PHP'),
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            return [
                'key' => 'local_ledger',
                'label' => 'Local Ledger Balance',
                'description' => 'The local wallet could not be resolved.',
                'authority' => 'local_ledger',
                'source' => 'bavix_wallet',
                'is_authoritative' => $authoritative,
                'is_stale' => true,
                'balance_minor' => null,
                'balance' => null,
                'currency' => (string) config('x-change.pricing.currency', 'PHP'),
                'checked_at' => now()->toIso8601String(),
                'sync_status' => 'unavailable',
                'sync_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function providerWalletBalance(mixed $owner, string $provider, bool $syncIfStale): array
    {
        $link = $this->links->findReadyForOwner($owner, $provider);

        if ($link === null || blank($link->provider_wallet_id)) {
            return $this->missingProviderWallet($provider, 'Provider wallet link is not ready.');
        }

        $wallet = $this->resolveEmiWallet($link->provider_wallet_id);
        $wasStale = $wallet === null || $this->isStale($wallet);
        $syncStatus = $wasStale ? 'stale' : 'fresh';
        $syncMessage = $wasStale
            ? 'Provider wallet projection is stale.'
            : 'Provider wallet projection is fresh.';

        if ($syncIfStale && $wasStale) {
            try {
                $refresh = $provider === 'paynamics'
                    ? $this->syncPaynamicsWallet($link->provider_wallet_id, $owner)
                    : $this->provisioning->refresh($link);

                if ((bool) data_get($refresh, 'refreshed', false) === true) {
                    $wallet = $this->resolveEmiWallet($link->provider_wallet_id);
                    $link->forceFill(['last_synced_at' => now()])->save();
                    $syncStatus = 'synced';
                    $syncMessage = 'Provider wallet balance was refreshed for this view.';
                } else {
                    $syncStatus = 'sync_failed';
                    $syncMessage = 'Provider wallet refresh did not complete.';
                }
            } catch (Throwable $e) {
                $syncStatus = 'sync_failed';
                $syncMessage = $e->getMessage();
            }
        }

        if ($wallet === null) {
            return $this->missingProviderWallet($provider, 'Provider wallet projection was not found after sync.', $syncStatus, $syncMessage);
        }

        $balanceMinor = $this->normalizeBalanceForComparison($wallet->balance_cached);
        $checkedAt = $wallet->updated_at?->toIso8601String();

        return [
            'key' => 'provider_wallet',
            'label' => 'Paynamics Wallet Balance',
            'description' => 'Externally funded Paynamics wallet balance. This is authoritative for Paynamics issuance.',
            'authority' => 'provider_wallet',
            'source' => 'paynamics',
            'is_authoritative' => true,
            'is_stale' => $this->isStale($wallet),
            'balance_minor' => $balanceMinor,
            'balance' => $balanceMinor / 100,
            'currency' => (string) ($wallet->currency ?: config('x-change.pricing.currency', 'PHP')),
            'checked_at' => $checkedAt,
            'provider_wallet_id' => $wallet->provider_wallet_id,
            'sync_status' => $syncStatus,
            'sync_message' => $syncMessage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function missingProviderWallet(string $provider, string $message, string $status = 'unavailable', ?string $syncMessage = null): array
    {
        return [
            'key' => 'provider_wallet',
            'label' => ucfirst($provider).' Wallet Balance',
            'description' => $message,
            'authority' => 'provider_wallet',
            'source' => $provider,
            'is_authoritative' => true,
            'is_stale' => true,
            'balance_minor' => null,
            'balance' => null,
            'currency' => (string) config('x-change.pricing.currency', 'PHP'),
            'checked_at' => null,
            'sync_status' => $status,
            'sync_message' => $syncMessage ?? $message,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function netbankSourceAccountBalance(string $provider, string $topology): ?array
    {
        if ($provider !== 'netbank' || $topology !== 'ledger_pooled') {
            return null;
        }

        $this->netbankSourceAccount ??= app(CheckNetbankSourceAccountReadiness::class);
        $readiness = $this->netbankSourceAccount->handle();
        $availableMinor = array_key_exists('available_balance_minor', $readiness)
            ? (int) $readiness['available_balance_minor']
            : null;
        $balanceMinor = array_key_exists('balance_minor', $readiness)
            ? (int) $readiness['balance_minor']
            : $availableMinor;
        $checked = (bool) ($readiness['checked'] ?? false);
        $enabled = (bool) ($readiness['enabled'] ?? false);
        $ready = (bool) ($readiness['ready'] ?? false);
        $syncStatus = match (true) {
            ! $enabled => 'disabled',
            $checked && $ready => 'fresh',
            $checked => 'unavailable',
            default => 'not_checked',
        };

        return [
            'key' => 'netbank_source_account',
            'label' => 'NetBank Source Account Balance',
            'description' => 'Provider-side mother account liquidity used to back NetBank Pay Code issuance.',
            'authority' => 'provider_source_account',
            'source' => 'netbank',
            'is_authoritative' => false,
            'is_liquidity_guard' => true,
            'is_stale' => ! $checked || ! $ready,
            'balance_minor' => $balanceMinor,
            'available_balance_minor' => $availableMinor,
            'balance' => $availableMinor !== null ? $availableMinor / 100 : null,
            'currency' => (string) ($readiness['currency'] ?? config('x-change.pricing.currency', 'PHP')),
            'checked_at' => $readiness['as_of'] ?? ($checked ? now()->toIso8601String() : null),
            'account_number_masked' => $readiness['account_number_masked'] ?? null,
            'sync_status' => $syncStatus,
            'sync_message' => $readiness['message'] ?? 'NetBank source account readiness was not checked.',
        ];
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

    protected function effectiveProviderForOwner(mixed $owner, ?string $provider): string
    {
        $resolved = $this->settings->provider($provider);

        if ($provider !== null || $resolved !== 'manual') {
            return $resolved;
        }

        $link = $this->links->findReadyForOwner($owner, 'paynamics');

        return $link !== null && filled($link->provider_wallet_id)
            ? 'paynamics'
            : $resolved;
    }

    protected function isStale(EmiWallet $wallet): bool
    {
        $maxAge = (int) config('x-change.funding.provider_balance_max_age_seconds', 300);

        return $wallet->updated_at === null
            || $wallet->updated_at->lessThan(now()->subSeconds($maxAge));
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
