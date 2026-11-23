<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Contracts\ProviderWalletProjectionSynchronizer;
use LBHurtado\EmiCore\Models\Wallet as EmiWallet;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\FundingAccountHold;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Banking\AssignedBankIssuanceReadiness;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;
use LBHurtado\XChange\Services\Banking\SharedBankTreasuryConnection;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountBalanceReadModel;
use Throwable;

class ProviderAwareFundingPolicy implements ProviderFundingPolicyContract
{
    public function __construct(
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected ProviderAccountLinkRepositoryContract $links,
        protected ProviderProvisioningGatewayContract $provisioning,
        protected WalletAccessContract $wallets,
        protected ?ProviderWalletProjectionSynchronizer $paynamicsBalances = null,
        protected ?CheckProviderSourceAccountReadiness $netbankSourceAccount = null,
        protected ?AccountBalanceReadModelContract $accountBalances = null,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function assertCanIssue(mixed $owner, mixed $localWallet, float|int|string $amount, array $context = []): FundingDecisionData
    {
        $this->assertAccountIsNotOnFundingHold($localWallet);

        if (! $owner instanceof Model && data_get($context, 'lifecycle_funding_boundary') === 'isolated_compatibility_wallet') {
            return $this->isolatedLifecycleDecision($localWallet, $amount, $context);
        }

        $fundedOrder = data_get($context, 'funded_order');
        if ($fundedOrder !== null) {
            if (! $owner instanceof Model || ! $fundedOrder instanceof PayCodeIssuanceFundingOrder) {
                throw new InsufficientWalletBalance('The funded issuance order is unavailable.');
            }
            $fundedOrder = PayCodeIssuanceFundingOrder::query()->whereKey($fundedOrder->getKey())
                ->where('issuer_type', $owner::class)->where('issuer_id', (string) $owner->getKey())
                ->where('status', PayCodeIssuanceFundingOrderStatus::Issuing->value)
                ->whereNotNull('funded_at')->first();
            if ($fundedOrder === null) {
                throw new InsufficientWalletBalance('The funded issuance order is unavailable.');
            }
            $connection = app(AssignedFundingTreasuryConnection::class)->resolveForOrder($owner, $fundedOrder);
            if ($connection === null
                && data_get($fundedOrder->fundingIntent?->destination_snapshot_ciphertext, 'mode') === 'shared'
                && in_array(data_get($context, 'provider'), [null, $fundedOrder->provider], true)) {
                $provider = $fundedOrder->provider;
                $assignedAccount = null;
            } elseif ($connection === null) {
                $provider = $this->effectiveProviderForOwner($owner, data_get($context, 'provider'));
                $assignedAccount = $this->selectedAssignedAccount($owner, $provider);
            } else {
                $provider = $fundedOrder->provider;
                $assignedAccount = (int) data_get(
                    $fundedOrder->fundingIntent->destination_snapshot_ciphertext,
                    'metadata.assigned_bank_account_link_id',
                );
            }
        } else {
            $provider = $this->effectiveProviderForOwner($owner, data_get($context, 'provider'));
            $assignedAccount = $this->selectedAssignedAccount($owner, $provider);
        }
        if ($assignedAccount !== null) {
            if (! (bool) config('x-change.commercial.enabled', true)
                || mb_strtoupper((string) data_get($context, 'currency', 'PHP')) !== 'PHP'
                || data_get($context, 'lifecycle_funding_boundary') === 'isolated_compatibility_wallet') {
                throw new InsufficientWalletBalance('Issuance from the selected bank account is not available in this context.');
            }

            return app(AssignedBankIssuanceReadiness::class)->assess(
                $owner, $assignedAccount, $this->normalizeAmountForWallet($amount),
            );
        }

        if (data_get($context, 'lifecycle_funding_boundary') === 'isolated_compatibility_wallet') {
            return $this->isolatedLifecycleDecision($localWallet, $amount, $context);
        }
        $topology = $this->settings->topology($provider);
        $requiredMinor = $this->normalizeAmountForWallet($amount);
        $currency = mb_strtoupper(trim((string) data_get($context, 'currency', config('x-change.pricing.currency', 'PHP'))));

        if ($topology === 'provider_customer_wallet') {
            $decision = $this->providerWalletDecision($owner, $provider, $requiredMinor, $currency);

            if (! $decision->allowed) {
                throw new InsufficientWalletBalance($decision->blocking_reason ?? 'Provider wallet balance is insufficient.');
            }

            return $decision;
        }

        $accountBalances = $this->accountBalances
            ?? app(AccountBalanceReadModelContract::class);

        if ($owner instanceof Model
            && method_exists($accountBalances, 'forget')) {
            $accountBalances->forget($owner, $currency, $provider);
        }

        try {
            $sharedConnection = app(SharedBankTreasuryConnection::class)
                ->resolveIfAccountsAssigned($provider, $currency);
        } catch (\RuntimeException $exception) {
            throw new InsufficientWalletBalance($exception->getMessage());
        }
        if ($fundedOrder instanceof PayCodeIssuanceFundingOrder
            && data_get($fundedOrder->fundingIntent?->destination_snapshot_ciphertext, 'mode') === 'shared'
            && $sharedConnection !== null && $provider === $fundedOrder->provider
            && $sharedConnection->reference !== $fundedOrder->connection_reference) {
            throw new InsufficientWalletBalance('The shared account Treasury connection changed after funding.');
        }
        $positionBalanceMinor = $sharedConnection !== null && $owner instanceof Model
            ? (app(TreasuryAccountBalanceReadModel::class)
                ->connectionBalanceMinor($owner, $provider, $sharedConnection->reference, $currency) ?? 0)
            : $accountBalances->providerBalanceMinor($owner, $provider, $currency);
        $availableMinor = $positionBalanceMinor
            ?? $this->normalizeBalanceForComparison($this->wallets->getBalance($localWallet));

        if ($availableMinor < $requiredMinor) {
            throw new InsufficientWalletBalance(sprintf(
                'Issuer wallet cannot afford the requested amount. Balance: %s, Required: %s',
                $availableMinor,
                $requiredMinor,
            ));
        }

        $sourceAccount = $this->netbankSourceAccountReadiness($requiredMinor, $provider);
        if (! (bool) ($sourceAccount['ready'] ?? false)) {
            if ($this->canBypassUnavailableNetbankSourceAccount($sourceAccount, $provider)) {
                $sourceAccount['bypassed'] = true;
                $sourceAccount['bypass_reason'] = 'Provider source account balance is unavailable and unavailable-balance bypass is enabled.';
            } else {
                throw new InsufficientWalletBalance((string) ($sourceAccount['message'] ?? 'Provider source account is not ready.'));
            }
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
                'treasury_connection_reference' => $sharedConnection?->reference,
                'source_account' => ($sourceAccount['applicable'] ?? true) ? $sourceAccount : null,
            ],
        );
    }

    private function selectedAssignedAccount(mixed $owner, string $provider): ?int
    {
        if (! $owner instanceof Model || $provider !== 'netbank') {
            return null;
        }

        $preference = FundingDestinationPreference::query()->whereMorphedTo('owner', $owner)
            ->where('provider_code', $provider)->first();
        if ($preference === null || $preference->mode === 'shared') {
            return null;
        }
        $selected = $preference->provider_account_link_id;
        if ($preference->mode !== 'dedicated' || $selected === null
            || ! BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
                ->where('provider_account_link_id', $selected)->exists()) {
            throw new InsufficientWalletBalance('The selected bank account is unavailable for issuance.');
        }

        return (int) $selected;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function isolatedLifecycleDecision(
        mixed $localWallet,
        float|int|string $amount,
        array $context,
    ): FundingDecisionData {
        if (! app()->environment(
            (array) config('x-change.lifecycle.synthetic_funding_environments', ['local', 'testing'])
        )) {
            throw new InsufficientWalletBalance(
                'The isolated lifecycle funding boundary is unavailable in this environment.'
            );
        }

        $requiredMinor = $this->normalizeAmountForWallet($amount);
        $availableMinor = $this->normalizeBalanceForComparison(
            $this->wallets->getBalance($localWallet)
        );
        $currency = (string) data_get(
            $context,
            'currency',
            config('x-change.pricing.currency', 'PHP'),
        );

        if ($availableMinor < $requiredMinor) {
            throw new InsufficientWalletBalance(sprintf(
                'Lifecycle issuer wallet cannot afford the requested amount. Balance: %s, Required: %s',
                $availableMinor,
                $requiredMinor,
            ));
        }

        return FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: $availableMinor,
            requiredMinor: $requiredMinor,
            currency: $currency,
            freshAsOf: now()->toIso8601String(),
            meta: [
                'provider' => 'lifecycle',
                'topology' => 'isolated_compatibility_wallet',
                'provider_calls' => false,
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
            $synchronizer = $this->paynamicsBalances?->supportsProvider($provider)
                ? $this->paynamicsBalances
                : app(ProviderWalletProjectionResolver::class)->for($provider);
            if ($synchronizer !== null) {
                $synchronizer->handle($link->provider_wallet_id, $owner);
                $refresh = ['refreshed' => true];
            } else {
                $refresh = $this->provisioning->refresh($link);
            }
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

    /**
     * @param  array<string, mixed>  $sourceAccount
     */
    protected function canBypassUnavailableNetbankSourceAccount(array $sourceAccount, string $provider): bool
    {
        if (! (bool) config("x-change.provider_runtime.providers.{$provider}.source_account_readiness.allow_unavailable", false)) {
            return false;
        }

        return ($sourceAccount['reason'] ?? null) === 'balance_unavailable'
            && ! array_key_exists('available_balance_minor', $sourceAccount);
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

    /**
     * @return array<string, mixed>
     */
    protected function netbankSourceAccountReadiness(int $requiredMinor, string $provider): array
    {
        $this->netbankSourceAccount ??= app(CheckProviderSourceAccountReadiness::class);

        return $this->netbankSourceAccount->handle($requiredMinor, $provider);
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

    protected function assertAccountIsNotOnFundingHold(mixed $wallet): void
    {
        if (! is_object($wallet)) {
            return;
        }

        $references = [];
        $uuid = data_get($wallet, 'uuid');

        if (is_string($uuid) && trim($uuid) !== '') {
            $references[] = 'wallet:'.trim($uuid);
        }

        if (method_exists($wallet, 'getKey') && $wallet->getKey() !== null) {
            $references[] = 'wallet:'.$wallet->getKey();
        }

        if ($references === []) {
            return;
        }

        $hold = FundingAccountHold::query()
            ->whereIn('account_reference', array_unique($references))
            ->where('status', 'active')
            ->first();

        if ($hold !== null) {
            throw new InsufficientWalletBalance(sprintf(
                'Account issuance is frozen while a funding recovery of %d %s minor units remains outstanding.',
                $hold->outstanding_amount_minor,
                $hold->currency,
            ));
        }
    }
}
