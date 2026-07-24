<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Treasury\LegacyAccountBalanceMigrationData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class LegacyAccountBalanceMigrationService
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryProvisioningService $systemPositions,
        private TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPositionOperationContract $operations,
        private WalletAccessContract $wallets,
    ) {}

    public function migrate(
        Model $accountOwner,
        string $connectionReference,
    ): LegacyAccountBalanceMigrationData {
        $connectionReference = trim($connectionReference);
        $connections = $this->connections->active([$connectionReference]);

        if (count($connections) !== 1) {
            throw new TreasuryConfigurationException(
                'Legacy Account migration requires exactly one active Treasury connection.',
            );
        }

        $connection = $connections[0];
        $lock = Cache::lock(
            'x-change:treasury:legacy-account:'.hash('sha256', implode('|', [
                $accountOwner::class,
                (string) $accountOwner->getKey(),
                $connection->reference,
            ])),
            max(1, (int) config('x-change.treasury.migration_lock_seconds', 60)),
        );

        return $lock->block(
            max(0, (int) config('x-change.treasury.migration_lock_wait_seconds', 5)),
            function () use ($accountOwner, $connection): LegacyAccountBalanceMigrationData {
                $legacyAccount = $this->wallets->resolveForUser($accountOwner);
                $amountMinor = $this->minorUnits(
                    $this->wallets->getBalance($legacyAccount),
                );

                if ($amountMinor <= 0) {
                    return new LegacyAccountBalanceMigrationData(
                        status: 'no_balance',
                        connectionReference: $connection->reference,
                        provider: $connection->provider,
                        currency: $connection->currency,
                        amountMinor: 0,
                    );
                }

                $system = $this->systemPositions->provision([$connection->reference]);
                $portfolio = $this->accountPortfolios->provision(
                    $accountOwner,
                    [$connection->reference],
                );
                $source = $this->position(
                    $system->positions,
                    TreasuryPositionPurpose::LegacyUnattributed,
                );
                $destination = $this->position(
                    $portfolio->positions,
                    TreasuryPositionPurpose::ClientFunds,
                );
                $currentSource = $this->positions->find($source->positionReference);

                if (
                    $currentSource === null
                    || $currentSource->balanceMinor < $amountMinor
                ) {
                    throw new TreasuryConfigurationException(
                        'Legacy Account balance exceeds provider-reconciled unattributed funds.',
                    );
                }

                $scope = hash('sha256', implode('|', [
                    $accountOwner::class,
                    (string) $accountOwner->getKey(),
                    $connection->reference,
                    $source->positionReference,
                    $destination->positionReference,
                ]));

                return DB::transaction(function () use (
                    $accountOwner,
                    $amountMinor,
                    $connection,
                    $destination,
                    $legacyAccount,
                    $scope,
                    $source,
                ): LegacyAccountBalanceMigrationData {
                    $allocation = $this->operations->allocate(
                        new TreasuryPositionAllocationData(
                            operationReference: 'legacy-account-allocation:'.$scope,
                            sourcePositionReference: $source->positionReference,
                            destinationPositionReference: $destination->positionReference,
                            amountMinor: $amountMinor,
                            currency: $connection->currency,
                            idempotencyKey: 'legacy-account-allocation-key:'.$scope,
                            externalReference: 'legacy-account-cutover:'.$scope,
                            metadata: [
                                'source' => 'legacy_account_balance_migration',
                                'provider' => $connection->provider,
                                'connection_reference' => $connection->reference,
                                'owner_type' => $accountOwner::class,
                                'owner_id' => (string) $accountOwner->getKey(),
                            ],
                        ),
                    );
                    $debit = $this->wallets->debit($legacyAccount, $amountMinor, [
                        'source' => 'legacy_account_balance_migration',
                        'treasury_position_operation_reference' => $allocation->operationReference,
                        'treasury_destination_position_reference' => $destination->positionReference,
                    ]);

                    if (! $debit instanceof Transaction) {
                        throw new TreasuryConfigurationException(
                            'Legacy Account migration did not return a committed debit.',
                        );
                    }

                    return new LegacyAccountBalanceMigrationData(
                        status: 'migrated',
                        connectionReference: $connection->reference,
                        provider: $connection->provider,
                        currency: $connection->currency,
                        amountMinor: $amountMinor,
                        sourcePositionReference: $source->positionReference,
                        destinationPositionReference: $destination->positionReference,
                        allocationOperationReference: $allocation->operationReference,
                        legacyDebitTransactionId: (int) $debit->getKey(),
                        legacyDebitTransactionUuid: (string) $debit->uuid,
                    );
                }, attempts: 5);
            },
        );
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                "Legacy Account migration requires one {$purpose->value} Position.",
            );
        }

        return $matches[0];
    }

    private function minorUnits(int|float|string $balance): int
    {
        if (is_int($balance)) {
            return $balance;
        }

        if (is_string($balance) && preg_match('/^-?\d+$/', trim($balance)) === 1) {
            return (int) trim($balance);
        }

        return (int) round(((float) $balance) * 100);
    }
}
