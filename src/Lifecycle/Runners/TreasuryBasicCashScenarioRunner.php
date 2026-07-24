<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use Throwable;

final class TreasuryBasicCashScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly WalletAccessContract $wallets,
        private readonly FundingAccountCreditContract $legacyAccounts,
        private readonly AccountBalanceReadModelContract $accountBalances,
        private readonly VoucherLiabilitySummaryContract $liabilities,
        private readonly LifecycleScenarioRepository $scenarios,
        private readonly LifecycleScenarioBootstrapper $bootstrapper,
        private readonly EstimatePayCodeCost $estimatePayCodeCost,
        private readonly TreasuryProviderConnectionCatalog $connections,
        private readonly TreasuryInventoryPositionReadModelContract $inventories,
        private readonly SettleVerifiedFundingIntent $settleFunding,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! $this->isAvailable()) {
            return $this->failure(
                $context,
                'The Treasury basic_cash lifecycle scenario is disabled in this environment.',
                true,
            );
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest($context->issuer);
        $originalProvider = config('x-change.provider_runtime.default_provider');
        $originalSourceReadiness = config(
            'x-change.provider_runtime.providers.netbank.source_account_readiness.enabled',
        );
        $payload = [];
        $exitCode = Command::SUCCESS;

        $connection->beginTransaction();

        try {
            $payload = $this->execute($context);

            if (data_get($payload, 'success') !== true) {
                $exitCode = Command::FAILURE;
            }
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            report($exception);

            $exitCode = Command::FAILURE;
            $payload = [
                'success' => false,
                'message' => 'The Treasury basic_cash lifecycle scenario could not complete safely.',
                'steps' => [],
            ];
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }

            config()->set(
                'x-change.provider_runtime.default_provider',
                $originalProvider,
            );
            config()->set(
                'x-change.provider_runtime.providers.netbank.source_account_readiness.enabled',
                $originalSourceReadiness,
            );
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingLevel
            && hash_equals($startingState, $this->stateDigest($context->issuer));

        if (! $rollbackCompleted) {
            return $this->failure(
                $context,
                'The Treasury basic_cash lifecycle scenario could not confirm rollback.',
                false,
            );
        }

        return new ScenarioRunResult(
            exitCode: $exitCode,
            payload: $this->payload($context, [
                ...$payload,
                'rollback_completed' => true,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(ScenarioRunContext $context): array
    {
        $provider = $this->requiredScenarioString($context, 'treasury.provider');
        $connectionReference = $this->requiredScenarioString(
            $context,
            'treasury.connection',
        );
        $baseScenarioKey = $this->requiredScenarioString(
            $context,
            'treasury.base_scenario',
        );
        $amountMinor = (int) data_get(
            $context->scenario,
            'treasury.funding_amount_minor',
        );

        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException(
                'The Treasury basic_cash scenario requires a positive funding amount.',
            );
        }

        $connection = collect($this->connections->active([$connectionReference]))
            ->sole();

        if ($connection->provider !== $provider) {
            throw new \InvalidArgumentException(
                'The Treasury basic_cash provider does not match its connection.',
            );
        }

        $currency = $connection->currency;
        config()->set('x-change.provider_runtime.default_provider', $provider);

        if ($provider === 'netbank') {
            config()->set(
                'x-change.provider_runtime.providers.netbank.source_account_readiness.enabled',
                false,
            );
        }

        $account = $this->wallets->resolveForUser($context->issuer);
        $accountReference = 'wallet:'.$account->uuid;
        $baseScenario = array_replace_recursive(
            (array) config('x-change.lifecycle.defaults', []),
            $this->scenarios->findOrFail($baseScenarioKey),
            [
                'provider' => $provider,
                'currency' => $currency,
            ],
        );
        $basicCashEstimate = $this->estimatePayCodeCost->handle(
            $this->bootstrapper->buildLifecycleInput(
                scenario: $baseScenario,
                issuerId: (int) $context->issuer->getKey(),
                walletId: (int) $context->issuer->getKey(),
                amount: (float) data_get($baseScenario, 'amount', 25),
                idempotencyKey: $context->idempotencyKey.'-estimate',
            ),
        );
        $compatibilityFeeMinor = (int) round(
            $basicCashEstimate->total * 100,
        );
        $basicCashAmountMinor = (int) round(
            (float) data_get($baseScenario, 'amount', 25) * 100,
        );
        $legacyCompatibilityAmountMinor = $basicCashAmountMinor
            + $compatibilityFeeMinor;
        $legacyBalanceBefore = (int) $this->wallets->getBalance($account);
        $liabilityBeforeFunding = $this->liabilities
            ->forIssuer($context->issuer)
            ->toArray();
        $observation = $this->stageProviderObservation(
            $context,
            $provider,
            $amountMinor,
            $currency,
        );
        $intent = $this->stageVerifiedIntent(
            $context,
            $accountReference,
            $observation,
            $amountMinor,
        );
        $settlement = $this->settleFunding->handle($intent);
        $balanceAfterFunding = $this->accountBalances->providerBalanceMinor(
            $context->issuer,
            $provider,
            $currency,
        );
        $replayed = $this->settleFunding->handle($intent->refresh());
        $balanceAfterReplay = $this->accountBalances->providerBalanceMinor(
            $context->issuer,
            $provider,
            $currency,
        );
        $inventory = $this->inventories->find(
            $connection->inventoryReference,
        );

        if (
            $balanceAfterFunding === null
            || $inventory === null
            || ! $replayed->is($settlement)
        ) {
            throw new \LogicException(
                'The verified Treasury funding stage did not converge.',
            );
        }

        $liabilityBeforeIssuance = $this->liabilities
            ->forIssuer($context->issuer)
            ->toArray();

        if ($legacyCompatibilityAmountMinor > 0) {
            $this->legacyAccounts->credit(
                $account,
                $legacyCompatibilityAmountMinor,
                [
                    'source' => 'treasury_basic_cash_lifecycle',
                    'purpose' => 'rollback_only_pay_code_compatibility',
                ],
            );
        }

        $basicCash = $this->bootstrapper->bootstrap(
            scenario: $baseScenario,
            issuerOption: (string) $context->issuer->getKey(),
            walletOption: (string) $context->issuer->getKey(),
        );
        $liabilityAfterIssuance = $this->liabilities
            ->forIssuer($context->issuer)
            ->toArray();
        $balanceAfterIssuance = $this->accountBalances->providerBalanceMinor(
            $context->issuer,
            $provider,
            $currency,
        );
        $legacyBalanceAfterIssuance = (int) $this->wallets->getBalance(
            $account->refresh(),
        );
        $outstandingBefore = (int) data_get(
            $liabilityBeforeIssuance,
            'outstanding_liability_minor',
        );
        $outstandingAfter = (int) data_get(
            $liabilityAfterIssuance,
            'outstanding_liability_minor',
        );
        $capacityBefore = $this->issuanceCapacity(
            $balanceAfterFunding,
            $inventory->balanceMinor,
            $outstandingBefore,
        );
        $capacityAfter = $this->issuanceCapacity(
            (int) $balanceAfterIssuance,
            $inventory->balanceMinor,
            $outstandingAfter,
        );
        $basicCashAmountMinor = (int) round($basicCash->amount * 100);
        $liabilityIncrease = $outstandingAfter - $outstandingBefore;
        $success = $settlement->net_amount_minor === $amountMinor
            && $balanceAfterFunding === $amountMinor
            && $balanceAfterReplay === $balanceAfterFunding
            && $balanceAfterIssuance === $balanceAfterFunding
            && $legacyBalanceAfterIssuance === $legacyBalanceBefore
            && $liabilityIncrease === $basicCashAmountMinor
            && $capacityBefore - $capacityAfter === $basicCashAmountMinor;

        return [
            'success' => $success,
            'message' => 'Rollback-only Treasury funding and basic_cash issuance lifecycle completed.',
            'base_scenario' => $baseScenarioKey,
            'steps' => [
                $this->step(
                    'provider_evidence_verified',
                    'Authoritative provider evidence identifies the Account and exact amount',
                    'verified',
                    [
                        'Provider' => $provider,
                        'Amount' => $this->money($amountMinor),
                        'Operator amount input' => 'None',
                    ],
                ),
                $this->step(
                    'provider_inventory_recognized',
                    'Provider Inventory is recognized before Account attribution',
                    'recognized',
                    [
                        'Inventory' => 'Registered',
                        'Recognized value' => $this->money($inventory->balanceMinor),
                        'Currency' => $inventory->currency,
                    ],
                ),
                $this->step(
                    'client_funds_allocated',
                    'Treasury Clearing allocates verified value to Client Funds',
                    'settled',
                    [
                        'Internal Balance' => $this->money($balanceAfterFunding),
                        'Position based' => data_get(
                            $settlement->metadata,
                            'treasury_position_based',
                        ) === true ? 'Yes' : 'No',
                        'Legacy Account credited' => 'No',
                    ],
                ),
                $this->step(
                    'funding_replay_noop',
                    'Identical funding settlement replay is a no-op',
                    $balanceAfterReplay === $balanceAfterFunding
                        ? 'protected'
                        : 'failed',
                    [
                        'Second settlement' => 'No',
                        'Second credit' => 'No',
                        'Internal Balance' => $this->money((int) $balanceAfterReplay),
                    ],
                ),
                $this->step(
                    'pay_code_compatibility_boundary',
                    'basic_cash escrow and fees still use the legacy voucher ledger',
                    'compatibility_boundary',
                    [
                        'Pay Code escrow' => $this->money($basicCashAmountMinor),
                        'Scenario fee' => $this->money($compatibilityFeeMinor),
                        'Position backed' => 'No',
                        'Fixture persisted' => 'No',
                    ],
                ),
                $this->step(
                    'basic_cash_issued',
                    'The canonical basic_cash definition issues one Pay Code',
                    'issued',
                    [
                        'Scenario' => $baseScenarioKey,
                        'Pay Code amount' => $this->money($basicCashAmountMinor),
                        'Claim attempted' => 'No',
                    ],
                ),
                $this->step(
                    'pay_code_liability_reserved',
                    'Outstanding Pay Codes increase without reducing Internal Balance',
                    'reserved',
                    [
                        'Outstanding before' => $this->money($outstandingBefore),
                        'Outstanding after' => $this->money($outstandingAfter),
                        'Internal Balance' => $this->money((int) $balanceAfterIssuance),
                    ],
                ),
                $this->step(
                    'issuance_capacity_reduced',
                    'Issuance Capacity reflects provider liquidity and Pay Code liability',
                    'complete',
                    [
                        'Capacity before' => $this->money($capacityBefore),
                        'Capacity after' => $this->money($capacityAfter),
                        'Reduction' => $this->money($capacityBefore - $capacityAfter),
                    ],
                ),
            ],
            'funding' => [
                'provider' => $provider,
                'connection' => $connectionReference,
                'amount_minor' => $amountMinor,
                'internal_balance_minor' => $balanceAfterFunding,
                'replay_balance_minor' => $balanceAfterReplay,
                'settlement_count' => FundingSettlement::query()
                    ->where('funding_intent_id', $intent->getKey())
                    ->count(),
            ],
            'basic_cash' => [
                'amount_minor' => $basicCashAmountMinor,
                'instruction_fee_minor' => $compatibilityFeeMinor,
                'instruction_fee_position_backed' => false,
                'escrow_position_backed' => false,
                'legacy_compatibility_amount_minor' => $legacyCompatibilityAmountMinor,
                'legacy_balance_after_minor' => $legacyBalanceAfterIssuance,
                'issued' => true,
                'claimed' => false,
            ],
            'balances' => [
                'before_funding' => $liabilityBeforeFunding,
                'after_funding' => $liabilityBeforeIssuance,
                'after_issuance' => $liabilityAfterIssuance,
            ],
            'issuance_capacity' => [
                'before_minor' => $capacityBefore,
                'after_minor' => $capacityAfter,
                'reduction_minor' => $capacityBefore - $capacityAfter,
            ],
        ];
    }

    private function stageProviderObservation(
        ScenarioRunContext $context,
        string $provider,
        int $amountMinor,
        string $currency,
    ): ProviderFundingObservation {
        $scope = hash('sha256', $context->idempotencyKey.'|provider-evidence');
        $providerTransactionId = 'LIFECYCLE-'.substr($scope, 0, 20);

        return ProviderFundingObservation::query()->create([
            'observation_key' => hash('sha256', $providerTransactionId),
            'provider_code' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'provider_operation_id' => 'OP-'.substr($scope, 0, 20),
            'request_id' => 'REQ-'.substr($scope, 0, 20),
            'funding_address' => 'lifecycle:treasury-basic-cash',
            'provider_account_reference' => 'lifecycle-provider-resource',
            'gross_amount_minor' => $amountMinor,
            'fee_amount_minor' => 0,
            'net_amount_minor' => $amountMinor,
            'currency' => $currency,
            'provider_status' => 'settled',
            'occurred_at' => now()->subMinute(),
            'settled_at' => now(),
            'verification_source' => 'lifecycle_synthetic_provider_history',
            'payload_hash' => hash('sha256', 'payload|'.$scope),
            'metadata' => [
                'destination_verified' => true,
                'rollback_only' => true,
            ],
        ]);
    }

    private function stageVerifiedIntent(
        ScenarioRunContext $context,
        string $accountReference,
        ProviderFundingObservation $observation,
        int $amountMinor,
    ): FundingIntent {
        $scope = hash('sha256', $context->idempotencyKey.'|funding-intent');

        return FundingIntent::query()->create([
            'account_reference' => $accountReference,
            'provider_code' => $observation->provider_code,
            'expected_amount_minor' => $amountMinor,
            'currency' => $observation->currency,
            'status' => FundingIntentStatus::Verified,
            'version' => 1,
            'idempotency_key_hash' => hash('sha256', 'idempotency|'.$scope),
            'idempotency_fingerprint' => hash('sha256', 'fingerprint|'.$scope),
            'created_by_type' => 'lifecycle_scenario',
            'created_by_id' => $context->scenarioKey,
            'provider_reference' => 'lifecycle-funding-intent',
            'provider_request_id' => $observation->request_id,
            'funding_address_ciphertext' => 'lifecycle:treasury-basic-cash',
            'funding_address_hash' => hash(
                'sha256',
                'lifecycle:treasury-basic-cash',
            ),
            'matched_observation_id' => $observation->getKey(),
            'provider_transaction_id' => $observation->provider_transaction_id,
            'instructions_created_at' => now()->subMinutes(2),
            'evidence_received_at' => now()->subMinute(),
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'metadata' => [
                'source' => 'treasury_basic_cash_lifecycle',
                'rollback_only' => true,
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $facts
     * @return array<string, mixed>
     */
    private function step(
        string $key,
        string $label,
        string $outcome,
        array $facts,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'outcome' => $outcome,
            'facts' => collect($facts)
                ->map(fn (string $value, string $label): array => compact(
                    'label',
                    'value',
                ))
                ->values()
                ->all(),
        ];
    }

    private function stateDigest(Model $issuer): string
    {
        $connection = $this->databases->connection();
        $account = $this->wallets->resolveForUser($issuer);

        return hash('sha256', json_encode([
            'legacy_balance' => (int) $this->wallets->getBalance(
                $account->refresh(),
            ),
            'account_balance' => $this->accountBalances->balanceMinor(
                $issuer,
                'PHP',
            ),
            'funding_observations' => $connection
                ->table('provider_funding_observations')
                ->count(),
            'funding_intents' => $connection
                ->table('x_change_funding_intents')
                ->count(),
            'funding_settlements' => $connection
                ->table('x_change_funding_settlements')
                ->count(),
            'treasury_inventories' => $connection
                ->table('treasury_inventories')
                ->count(),
            'treasury_inventory_operations' => $connection
                ->table('treasury_inventory_operations')
                ->count(),
            'treasury_positions' => $connection
                ->table('treasury_positions')
                ->count(),
            'treasury_position_operations' => $connection
                ->table('treasury_position_operations')
                ->count(),
            'vouchers' => $connection->table('vouchers')->count(),
        ], JSON_THROW_ON_ERROR));
    }

    private function requiredScenarioString(
        ScenarioRunContext $context,
        string $key,
    ): string {
        $value = trim((string) data_get($context->scenario, $key));

        if ($value === '') {
            throw new \InvalidArgumentException(
                "The Treasury basic_cash scenario requires [{$key}].",
            );
        }

        return $value;
    }

    private function issuanceCapacity(
        int $internalBalanceMinor,
        int $providerLiquidityMinor,
        int $outstandingMinor,
    ): int {
        return max(
            0,
            min($internalBalanceMinor, $providerLiquidityMinor)
                - $outstandingMinor,
        );
    }

    private function money(int $minor): string
    {
        return '₱'.number_format($minor / 100, 2);
    }

    private function isAvailable(): bool
    {
        return (bool) config(
            'x-change.lifecycle.treasury_basic_cash.enabled',
            false,
        ) && in_array(
            app()->environment(),
            (array) config(
                'x-change.lifecycle.treasury_basic_cash.allowed_environments',
                [],
            ),
            true,
        );
    }

    private function failure(
        ScenarioRunContext $context,
        string $message,
        bool $rollbackCompleted,
    ): ScenarioRunResult {
        return new ScenarioRunResult(
            exitCode: Command::FAILURE,
            payload: $this->payload($context, [
                'success' => false,
                'message' => $message,
                'steps' => [],
                'rollback_completed' => $rollbackCompleted,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function payload(
        ScenarioRunContext $context,
        array $result,
    ): array {
        return [
            'schema' => 'x-change.lifecycle.treasury-basic-cash.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'treasury_basic_cash',
            'simulation' => [
                'rollback_only' => true,
                'provider_calls' => 0,
                'manual_balance_input' => false,
                'persisted' => false,
            ],
            ...$result,
        ];
    }
}
