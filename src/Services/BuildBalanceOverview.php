<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\ProviderWalletProjectionSynchronizer;
use LBHurtado\EmiCore\Models\Wallet as EmiWallet;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\Funding\SourceAccountReadinessResolver;
use Throwable;

class BuildBalanceOverview
{
    public function __construct(
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected ProviderAccountLinkRepositoryContract $links,
        protected ProviderProvisioningGatewayContract $provisioning,
        protected WalletAccessContract $wallets,
        protected ?ProviderWalletProjectionSynchronizer $paynamicsBalances = null,
        protected ?CheckNetbankSourceAccountReadiness $netbankSourceAccount = null,
        protected ?ProviderBalanceSnapshotStore $providerBalanceSnapshots = null,
        protected ?AccountBalanceReadModelContract $accountBalances = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        mixed $owner,
        ?string $provider = null,
        bool $syncIfStale = true,
        bool $forceSync = false,
    ): array {
        $provider = $this->effectiveProviderForOwner($owner, $provider);
        $topology = $this->settings->topology($provider);
        $authority = $topology === 'provider_customer_wallet'
            ? 'provider_wallet'
            : ($topology === 'ledger_pooled' ? 'local_ledger' : 'manual');

        $local = $this->localBalance($owner, $authority !== 'provider_wallet');
        $providerBalance = $topology === 'provider_customer_wallet'
            ? $this->providerWalletBalance(
                $owner,
                $provider,
                $syncIfStale,
                $forceSync,
            )
            : null;
        $netbankSourceBalance = $this->netbankSourceAccountBalance($provider, $topology, $syncIfStale);

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
            $balanceMinor = ($this->accountBalances
                ?? app(AccountBalanceReadModelContract::class))
                ->balanceMinor(
                    $owner,
                    (string) config('x-change.pricing.currency', 'PHP'),
                );

            if ($balanceMinor !== null) {
                return [
                    'key' => 'local_ledger',
                    'label' => $authoritative
                        ? 'Internal Account Balance'
                        : 'Internal Account Projection',
                    'description' => 'Aggregated from active provider-specific Client Funds Positions.',
                    'authority' => 'local_ledger',
                    'source' => 'treasury_positions',
                    'is_authoritative' => $authoritative,
                    'is_stale' => false,
                    'balance_minor' => $balanceMinor,
                    'balance' => $balanceMinor / 100,
                    'currency' => (string) config('x-change.pricing.currency', 'PHP'),
                    'checked_at' => now()->toIso8601String(),
                ];
            }

            $wallet = $this->wallets->resolveForUser($owner);
            $balanceMinor = $this->normalizeBalanceForComparison($this->wallets->getBalance($wallet));

            return [
                'key' => 'local_ledger',
                'label' => $authoritative ? 'Available Local Ledger Balance' : 'Local Ledger Projection',
                'description' => $authoritative
                    ? 'This is the balance x-change uses for this provider topology.'
                    : 'Shown for accounting context only. The provider balance is authoritative.',
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
                'description' => 'The internal account balance could not be resolved.',
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
    protected function providerWalletBalance(
        mixed $owner,
        string $provider,
        bool $syncIfStale,
        bool $forceSync,
    ): array {
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

        if ($forceSync || ($syncIfStale && $wasStale)) {
            try {
                $synchronizer = $this->paynamicsBalances?->supportsProvider($provider)
                    ? $this->paynamicsBalances
                    : app(ProviderWalletProjectionResolver::class)->for($provider);
                if ($synchronizer !== null) {
                    $synchronizer->handle($link->provider_wallet_id, $owner);
                    $refresh = ['refreshed' => true];
                } else {
                    $refresh = $this->provisioning->refresh($link);
                }

                if ((bool) data_get($refresh, 'refreshed', false) === true) {
                    $wallet = $this->resolveEmiWallet($link->provider_wallet_id);
                    $link->forceFill(['last_synced_at' => now()])->save();
                    $syncStatus = 'synced';
                    $syncMessage = 'Provider wallet balance was refreshed for this view.';
                } else {
                    $syncStatus = 'sync_failed';
                    $syncMessage = 'Provider wallet refresh did not complete.';
                }
            } catch (Throwable) {
                $syncStatus = 'sync_failed';
                $syncMessage = 'Provider wallet balance refresh failed.';
            }
        }

        if ($wallet === null) {
            return $this->missingProviderWallet($provider, 'Provider wallet projection was not found after sync.', $syncStatus, $syncMessage);
        }

        $balanceMinor = $this->normalizeBalanceForComparison($wallet->balance_cached);
        $checkedAt = $wallet->updated_at?->toIso8601String();

        return [
            'key' => 'provider_wallet',
            'label' => ucfirst($provider).' Provider Balance',
            'description' => 'Externally funded provider account balance. This is authoritative for issuance.',
            'authority' => 'provider_wallet',
            'source' => $provider,
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
            'label' => ucfirst($provider).' Provider Balance',
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
    protected function netbankSourceAccountBalance(
        string $provider,
        string $topology,
        bool $syncIfStale,
    ): ?array {
        if ($topology !== 'ledger_pooled') {
            return null;
        }

        $sourceKey = $provider.'_source_account';
        $providerLabel = $provider === 'netbank' ? 'NetBank' : ucfirst($provider);
        if (app(SourceAccountReadinessResolver::class)->for($provider) === null
            && ! config("x-change.provider_runtime.providers.{$provider}.source_account_readiness.enabled", false)) {
            return null;
        }

        $this->providerBalanceSnapshots ??= app(ProviderBalanceSnapshotStore::class);

        if (! $syncIfStale) {
            return $this->netbankSourceAccountBalanceFromSnapshot(
                $this->providerBalanceSnapshots->find($provider, $sourceKey), $provider, $sourceKey, $providerLabel,
            );
        }

        $this->netbankSourceAccount ??= app(CheckNetbankSourceAccountReadiness::class);
        $readiness = $this->netbankSourceAccount->handle(null, $provider);
        $checked = (bool) ($readiness['checked'] ?? false);
        $ready = (bool) ($readiness['ready'] ?? false);

        if (
            $checked
            && $ready
            && array_key_exists('available_balance_minor', $readiness)
        ) {
            $this->providerBalanceSnapshots->recordSuccess(
                $provider,
                $sourceKey,
                $readiness,
            );
        } elseif ($checked) {
            $snapshot = $this->providerBalanceSnapshots->recordFailure(
                $provider,
                $sourceKey,
                "{$providerLabel} source account refresh failed.",
            );

            if ($snapshot !== null) {
                return $this->netbankSourceAccountBalanceFromSnapshot($snapshot, $provider, $sourceKey, $providerLabel);
            }
        }

        $availableMinor = array_key_exists('available_balance_minor', $readiness)
            ? (int) $readiness['available_balance_minor']
            : null;
        $balanceMinor = array_key_exists('balance_minor', $readiness)
            ? (int) $readiness['balance_minor']
            : $availableMinor;
        $enabled = (bool) ($readiness['enabled'] ?? false);
        $syncStatus = match (true) {
            ! $enabled => 'disabled',
            $checked && $ready => 'fresh',
            $checked => 'unavailable',
            default => 'not_checked',
        };

        return [
            'key' => $sourceKey,
            'label' => "{$providerLabel} Source Account Balance",
            'description' => "Provider-side mother account liquidity used to back {$providerLabel} Pay Code issuance.",
            'authority' => 'provider_source_account',
            'source' => $provider,
            'is_authoritative' => false,
            'is_liquidity_guard' => true,
            'is_stale' => ! $checked || ! $ready,
            'balance_minor' => $balanceMinor,
            'available_balance_minor' => $availableMinor,
            'balance' => $availableMinor !== null ? $availableMinor / 100 : null,
            'currency' => (string) ($readiness['currency'] ?? config('x-change.pricing.currency', 'PHP')),
            'checked_at' => $readiness['fetched_at'] ?? ($checked ? now()->toIso8601String() : null),
            'provider_as_of' => $readiness['as_of'] ?? null,
            'account_number_masked' => $readiness['account_number_masked'] ?? null,
            'sync_status' => $syncStatus,
            'sync_message' => $checked && ! $ready
                ? "{$providerLabel} source account refresh failed."
                : ($readiness['message'] ?? "{$providerLabel} source account readiness was not checked."),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function netbankSourceAccountBalanceFromSnapshot(?ProviderBalanceSnapshot $snapshot, string $provider, string $sourceKey, string $providerLabel): array
    {
        if ($snapshot === null) {
            return [
                'key' => $sourceKey,
                'label' => "{$providerLabel} Source Account Balance",
                'description' => "No cached {$providerLabel} liquidity snapshot is available.",
                'authority' => 'provider_source_account',
                'source' => $provider,
                'is_authoritative' => false,
                'is_liquidity_guard' => true,
                'is_stale' => true,
                'balance_minor' => null,
                'available_balance_minor' => null,
                'balance' => null,
                'currency' => (string) config('x-change.pricing.currency', 'PHP'),
                'checked_at' => null,
                'provider_as_of' => null,
                'account_number_masked' => null,
                'sync_status' => 'unavailable',
                'sync_message' => "Open Balances to refresh {$providerLabel} liquidity.",
            ];
        }

        $availableMinor = $snapshot->available_balance_minor;
        $maxAge = (int) config('x-change.funding.provider_balance_max_age_seconds', 300);
        $isStale = $snapshot->refresh_status !== 'fresh'
            || $snapshot->fetched_at === null
            || $snapshot->fetched_at->lessThan(now()->subSeconds($maxAge));

        return [
            'key' => $snapshot->balance_key,
            'label' => "{$providerLabel} Source Account Balance",
            'description' => "Cached provider-side mother account liquidity used to back {$providerLabel} Pay Code issuance.",
            'authority' => 'provider_source_account',
            'source' => $snapshot->provider_code,
            'is_authoritative' => false,
            'is_liquidity_guard' => true,
            'is_stale' => $isStale,
            'balance_minor' => $snapshot->balance_minor,
            'available_balance_minor' => $availableMinor,
            'balance' => $availableMinor !== null ? $availableMinor / 100 : null,
            'currency' => $snapshot->currency,
            'checked_at' => $snapshot->fetched_at?->toIso8601String(),
            'provider_as_of' => $snapshot->provider_as_of?->toIso8601String(),
            'account_number_masked' => $snapshot->account_reference_masked,
            'sync_status' => $isStale ? $snapshot->refresh_status : 'cached',
            'sync_message' => $snapshot->failure_reason
                ?? "Cached {$providerLabel} liquidity snapshot; no provider call was made.",
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
