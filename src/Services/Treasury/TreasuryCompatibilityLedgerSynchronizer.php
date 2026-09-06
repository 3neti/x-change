<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;

final readonly class TreasuryCompatibilityLedgerSynchronizer
{
    public function __construct(
        private AccountBalanceReadModelContract $accountBalances,
        private WalletAccessContract $wallets,
    ) {}

    public function synchronize(
        Model $owner,
        mixed $wallet,
        FundingDecisionData $funding,
    ): void {
        if (! $this->usesTreasuryPositions($funding)) {
            return;
        }

        if (! $wallet instanceof Model || ! method_exists($wallet, 'deposit')) {
            throw new PayCodeIssuanceFailed(
                'The Pay Code compatibility ledger could not be resolved.',
            );
        }

        $provider = mb_strtolower(trim((string) data_get(
            $funding->meta,
            'provider',
        )));
        $currency = mb_strtoupper(trim($funding->currency));
        $this->forgetCachedPositionBalance($owner, $currency, $provider);
        $positionBalanceMinor = $this->accountBalances->providerBalanceMinor(
            $owner,
            $provider,
            $currency,
        );

        if ($positionBalanceMinor === null) {
            throw new PayCodeIssuanceFailed(
                'The authoritative Client Funds position could not be resolved.',
            );
        }

        /** @var Model $lockedWallet */
        $lockedWallet = $wallet::query()
            ->lockForUpdate()
            ->findOrFail($wallet->getKey());
        $compatibilityBalanceMinor = (int) $this->wallets->getBalance(
            $lockedWallet,
        );

        if ($compatibilityBalanceMinor > $positionBalanceMinor) {
            throw new PayCodeIssuanceFailed(
                'The Pay Code compatibility ledger exceeds authoritative Client Funds and requires review.',
            );
        }

        $differenceMinor = $positionBalanceMinor - $compatibilityBalanceMinor;

        if ($differenceMinor === 0) {
            return;
        }

        $lockedWallet->deposit($differenceMinor, [
            'source' => 'treasury_client_funds_compatibility_projection',
            'provider' => $provider,
            'currency' => $currency,
            'target_balance_minor' => $positionBalanceMinor,
            'projection_reference' => hash('sha256', implode('|', [
                $owner::class,
                (string) $owner->getKey(),
                $provider,
                $currency,
                (string) $positionBalanceMinor,
            ])),
        ], true);
    }

    public function reconcileAfterIssuance(
        Model $owner,
        mixed $wallet,
        FundingDecisionData $funding,
    ): void {
        if (! $this->usesTreasuryPositions($funding)) {
            return;
        }

        if (! $wallet instanceof Model || ! method_exists($wallet, 'withdraw')) {
            throw new PayCodeIssuanceFailed(
                'The Pay Code compatibility ledger could not be reconciled.',
            );
        }

        $provider = mb_strtolower(trim((string) data_get(
            $funding->meta,
            'provider',
        )));
        $currency = mb_strtoupper(trim($funding->currency));
        $this->forgetCachedPositionBalance($owner, $currency, $provider);
        $positionBalanceMinor = $this->accountBalances->providerBalanceMinor(
            $owner,
            $provider,
            $currency,
        );

        if ($positionBalanceMinor === null) {
            throw new PayCodeIssuanceFailed(
                'The authoritative Client Funds position could not be reconciled.',
            );
        }

        /** @var Model $lockedWallet */
        $lockedWallet = $wallet::query()
            ->lockForUpdate()
            ->findOrFail($wallet->getKey());
        $compatibilityBalanceMinor = (int) $this->wallets->getBalance(
            $lockedWallet,
        );

        if ($compatibilityBalanceMinor < $positionBalanceMinor) {
            throw new PayCodeIssuanceFailed(
                'The Pay Code compatibility ledger is below authoritative Client Funds after issuance.',
            );
        }

        $differenceMinor = $compatibilityBalanceMinor - $positionBalanceMinor;

        if ($differenceMinor === 0) {
            return;
        }

        $lockedWallet->withdraw($differenceMinor, [
            'source' => 'treasury_client_funds_compatibility_reconciliation',
            'provider' => $provider,
            'currency' => $currency,
            'target_balance_minor' => $positionBalanceMinor,
            'reconciliation_reference' => hash('sha256', implode('|', [
                $owner::class,
                (string) $owner->getKey(),
                $provider,
                $currency,
                (string) $positionBalanceMinor,
            ])),
        ]);
    }

    private function usesTreasuryPositions(FundingDecisionData $funding): bool
    {
        return $funding->required_minor > 0
            && $funding->authority === 'local_ledger'
            && data_get($funding->meta, 'topology') === 'ledger_pooled'
            && (bool) config('x-change.commercial.enabled', true);
    }

    private function forgetCachedPositionBalance(Model $owner, string $currency, string $provider): void
    {
        if (! method_exists($this->accountBalances, 'forget')) {
            return;
        }

        $this->accountBalances->forget($owner, $currency, $provider);
    }
}
