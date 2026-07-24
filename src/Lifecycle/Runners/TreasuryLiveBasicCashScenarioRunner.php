<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningBalanceConnectionData;
use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleMoneyRunStore;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\LifecycleMoneyRun;
use LBHurtado\XChange\Services\Treasury\TreasuryLifecycleAccountingSnapshot;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use Throwable;

final readonly class TreasuryLiveBasicCashScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private LifecycleMoneyRunStore $runs,
        private LifecycleScenarioBootstrapper $bootstrapper,
        private ExecutionEngineContractScenarioRunner $execution,
        private TreasuryOpeningBalanceReconciliationService $openingBalances,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryLifecycleAccountingSnapshot $accounting,
        private TreasuryPayCodeAccountingService $payCodeAccounting,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! $this->isAvailable()) {
            return $this->failure(
                $context,
                'The Treasury live basic_cash lifecycle is disabled in this environment.',
            );
        }

        $missingTables = $this->missingRequiredTables();

        if ($missingTables !== []) {
            return $this->failure(
                $context,
                'The Treasury live basic_cash lifecycle schema is not ready. Run [php artisan migrate --no-interaction] and try again.',
                ['missing_tables' => $missingTables],
            );
        }

        $runReference = trim((string) data_get(
            $context->scenario,
            '_runtime.run_reference',
        ));
        $provider = $this->requiredScenarioString(
            $context,
            'treasury.provider',
        );
        $currency = mb_strtoupper($this->requiredScenarioString(
            $context,
            'currency',
        ));
        $amountMinor = (int) round(
            (float) data_get($context->scenario, 'amount') * 100,
        );

        return Cache::lock(
            $this->runs->lockName($runReference),
            max(1, (int) config(
                'x-change.lifecycle.treasury_live_basic_cash.lock_seconds',
                600,
            )),
        )->block(
            max(0, (int) config(
                'x-change.lifecycle.treasury_live_basic_cash.lock_wait_seconds',
                5,
            )),
            fn (): ScenarioRunResult => $this->runLocked(
                $context,
                $runReference,
                $provider,
                $amountMinor,
                $currency,
            ),
        );
    }

    private function runLocked(
        ScenarioRunContext $context,
        string $runReference,
        string $provider,
        int $amountMinor,
        string $currency,
    ): ScenarioRunResult {
        $run = $this->runs->begin(
            $context->scenarioKey,
            $runReference,
            $context->issuer,
            $provider,
            $amountMinor,
            $currency,
        );

        if ($run->completed_at !== null && is_array($run->result_summary)) {
            if (
                $run->status === 'provider_sync_pending'
                && data_get($run->result_summary, 'provider_transfer_succeeded') === true
            ) {
                return $this->refreshProviderSync(
                    $context,
                    $run,
                    $this->requiredScenarioString($context, 'treasury.connection'),
                );
            }

            return new ScenarioRunResult(
                exitCode: data_get($run->result_summary, 'success') === true
                    ? Command::SUCCESS
                    : Command::FAILURE,
                payload: [
                    ...$run->result_summary,
                    'idempotency' => [
                        'run_reference' => 'accepted_and_hashed',
                        'run_record' => $run->reference,
                        'replayed' => true,
                        'provider_transfer_repeated' => false,
                    ],
                ],
            );
        }

        if ($run->voucher_id !== null) {
            return $this->incompleteRun($context, $run);
        }

        $connectionReference = $this->requiredScenarioString(
            $context,
            'treasury.connection',
        );

        try {
            $opening = $this->openingBalances->reconcile([$connectionReference]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                $context,
                'Authoritative provider liquidity could not be synchronized. No Pay Code was issued and no transfer was attempted.',
                [
                    'idempotency' => $this->idempotency($run, false),
                    'provider_transfer_succeeded' => false,
                ],
            );
        }

        if (! $opening->passes()) {
            return $this->failure(
                $context,
                'Provider liquidity does not reconcile with Treasury attribution. No Pay Code was issued and no transfer was attempted.',
                [
                    'accounting' => [
                        'before_issuance' => $this->accounting->capture(
                            $context->issuer,
                            $opening->connections,
                        ),
                    ],
                    'idempotency' => $this->idempotency($run, false),
                    'provider_transfer_succeeded' => false,
                ],
            );
        }

        $this->portfolios->provision(
            $context->issuer,
            [$connectionReference],
        );
        $beforeIssuance = $this->accounting->capture(
            $context->issuer,
            $opening->connections,
        );
        $providerPrincipalMinor = $amountMinor;

        try {
            [$bootstrap, $reservation, $run] = DB::transaction(
                function () use (
                    $connectionReference,
                    $context,
                    $currency,
                    $providerPrincipalMinor,
                    $run,
                ): array {
                    $bootstrap = $this->bootstrapper->bootstrap(
                        scenario: $context->scenario,
                        issuerOption: (string) $context->issuer->getKey(),
                        walletOption: (string) $context->issuer->getKey(),
                    );
                    $reservation = $this->payCodeAccounting->reserve(
                        accountOwner: $context->issuer,
                        voucher: $bootstrap->voucher,
                        connectionReference: $connectionReference,
                        providerPrincipalMinor: $providerPrincipalMinor,
                        currency: $currency,
                    );
                    $run = $this->runs->attachVoucher(
                        $run,
                        (int) $bootstrap->voucher->getKey(),
                    );

                    return [$bootstrap, $reservation, $run];
                },
                attempts: 5,
            );
            $afterIssuance = $this->accounting->capture($context->issuer);
            $execution = $this->execution->run(new ScenarioRunContext(
                output: $context->output,
                scenarioKey: $context->scenarioKey,
                scenario: $context->scenario,
                issuer: $bootstrap->issuer,
                generated: $bootstrap->generated,
                voucher: $bootstrap->voucher,
                attempts: $context->attempts,
                baseClaimMobile: $bootstrap->baseClaimMobile,
                estimate: $bootstrap->estimate,
                idempotencyKey: $bootstrap->idempotencyKey,
                readiness: $context->readiness,
            ));
            $transferSucceeded = data_get($execution->payload, 'success') === true;
            $reconciliation = $this->successfulReconciliation(
                (int) $bootstrap->voucher->getKey(),
            );
            $settlement = $transferSucceeded && $reconciliation !== null
                ? $this->payCodeAccounting->settle(
                    accountOwner: $context->issuer,
                    voucher: $bootstrap->voucher->refresh(),
                    reconciliation: $reconciliation,
                    connectionReference: $connectionReference,
                )
                : null;
            $postTransfer = $this->openingBalances->observe([$connectionReference]);
            $afterClaim = $this->accounting->capture(
                $context->issuer,
                $postTransfer->connections,
            );
            $accountingStatus = $this->accountingStatus(
                $postTransfer->connections,
            );
            $success = $transferSucceeded
                && $settlement !== null
                && $accountingStatus === 'reconciled';
            $result = $this->payload($context, [
                'success' => $success,
                'message' => $success
                    ? 'The live Pay Code transfer completed and the provider balance was reconciled.'
                    : ($transferSucceeded
                        ? ($accountingStatus === 'provider_sync_pending'
                            ? 'The transfer and Treasury posting completed; the provider balance update is still pending.'
                            : 'The transfer completed, but provider liquidity now requires accounting review.')
                        : 'The Pay Code claim did not complete successfully. The run is closed to prevent a duplicate transfer.'),
                'provider_transfer_succeeded' => $transferSucceeded,
                'accounting_status' => $accountingStatus,
                'pay_code' => [
                    'id' => (int) $bootstrap->voucher->getKey(),
                    'code' => (string) $bootstrap->voucher->code,
                    'amount_minor' => (int) round($bootstrap->amount * 100),
                    'issued' => true,
                    'claimed' => $bootstrap->voucher->refresh()->isRedeemed(),
                ],
                'execution' => $this->safeExecution($execution->payload),
                'treasury_settlement' => $settlement === null
                    ? null
                    : [
                        'reservation_operation_reference' => $reservation->operationReference,
                        'derecognition_operation_reference' => $settlement->derecognitionOperationReference,
                        'inventory_adjustment_operation_reference' => $settlement->inventoryAdjustmentOperationReference,
                        'beneficiary_amount_minor' => $settlement->beneficiaryAmountMinor,
                        'provider_inventory_outflow_minor' => $settlement->providerInventoryOutflowMinor,
                        'configured_rail_fee_minor' => $settlement->configuredRailFeeMinor,
                        'sender_system_charge_minor' => (int) round(
                            ((float) data_get(
                                $bootstrap->estimate,
                                'total',
                                0,
                            )) * 100,
                        ),
                        'sender_system_charge_status' => 'legacy_compatibility_ledger',
                        'currency' => $settlement->currency,
                    ],
                'accounting' => [
                    'before_issuance' => $beforeIssuance,
                    'after_issuance' => $afterIssuance,
                    'after_claim' => $afterClaim,
                ],
                'idempotency' => $this->idempotency($run, false),
                'accounting_boundary' => [
                    'funding_and_opening_balance' => 'treasury_position_based',
                    'pay_code_escrow_and_fees' => 'provider_principal_reserved_with_legacy_compatibility_mirror',
                    'outbound_treasury_posting' => 'provider_principal_only',
                    'sender_system_charge' => 'legacy_compatibility_ledger',
                    'post_transfer_provider_sync' => $accountingStatus,
                ],
            ]);
            $completed = $this->runs->complete(
                $run,
                $result,
                match (true) {
                    $success => 'completed',
                    $accountingStatus === 'provider_sync_pending' => 'provider_sync_pending',
                    $transferSucceeded => 'accounting_review_required',
                    default => 'transfer_failed',
                },
            );

            $result['idempotency']['run_record'] = $completed->reference;

            return new ScenarioRunResult(
                exitCode: $success ? Command::SUCCESS : Command::FAILURE,
                payload: $result,
            );
        } catch (Throwable $exception) {
            if ($run->voucher_id !== null) {
                $this->runs->fail(
                    $run->refresh(),
                    'Lifecycle execution stopped after Pay Code issuance; manual reconciliation is required.',
                );
            }

            report($exception);

            return $this->failure(
                $context,
                $run->voucher_id === null
                    ? 'The lifecycle stopped before Pay Code issuance. No provider transfer was attempted.'
                    : 'The lifecycle stopped after Pay Code issuance. The run is locked against replay and requires manual reconciliation.',
                [
                    'idempotency' => $this->idempotency($run->refresh(), false),
                    'provider_transfer_succeeded' => false,
                ],
            );
        }
    }

    private function refreshProviderSync(
        ScenarioRunContext $context,
        LifecycleMoneyRun $run,
        string $connectionReference,
    ): ScenarioRunResult {
        try {
            $observation = $this->openingBalances->observe([$connectionReference]);
            $accountingStatus = $this->accountingStatus($observation->connections);
            $success = $accountingStatus === 'reconciled';
            $result = [
                ...$run->result_summary,
                'success' => $success,
                'message' => $success
                    ? 'The live Pay Code transfer completed and the provider balance was reconciled.'
                    : ($accountingStatus === 'provider_sync_pending'
                        ? 'The transfer and Treasury posting completed; the provider balance update is still pending.'
                        : 'The transfer completed, but provider liquidity now requires accounting review.'),
                'accounting_status' => $accountingStatus,
                'accounting' => [
                    ...((array) data_get($run->result_summary, 'accounting', [])),
                    'after_claim' => $this->accounting->capture(
                        $context->issuer,
                        $observation->connections,
                    ),
                ],
                'accounting_boundary' => [
                    ...((array) data_get(
                        $run->result_summary,
                        'accounting_boundary',
                        [],
                    )),
                    'post_transfer_provider_sync' => $accountingStatus,
                ],
                'idempotency' => [
                    ...$this->idempotency($run, false),
                    'replayed' => true,
                ],
            ];
            $completed = $this->runs->complete(
                $run,
                $result,
                match ($accountingStatus) {
                    'reconciled' => 'completed',
                    'provider_sync_pending' => 'provider_sync_pending',
                    default => 'accounting_review_required',
                },
            );
            $result['idempotency']['run_record'] = $completed->reference;

            return new ScenarioRunResult(
                exitCode: $success ? Command::SUCCESS : Command::FAILURE,
                payload: $result,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                $context,
                'The transfer was not repeated, but the provider balance could not be observed.',
                [
                    'provider_transfer_succeeded' => true,
                    'accounting_status' => 'provider_sync_pending',
                    'idempotency' => [
                        ...$this->idempotency($run, false),
                        'replayed' => true,
                    ],
                ],
            );
        }
    }

    private function successfulReconciliation(
        int $voucherId,
    ): ?DisbursementReconciliation {
        return DisbursementReconciliation::query()
            ->where('voucher_id', $voucherId)
            ->where('status', 'succeeded')
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<TreasuryOpeningBalanceConnectionData>  $connections
     */
    private function accountingStatus(array $connections): string
    {
        if (collect($connections)->contains(
            static fn (TreasuryOpeningBalanceConnectionData $connection): bool => $connection->status
                === TreasuryOpeningBalanceStatus::ReviewRequired,
        )) {
            return 'review_required';
        }

        if (collect($connections)->contains(
            static fn (TreasuryOpeningBalanceConnectionData $connection): bool => $connection->status
                === TreasuryOpeningBalanceStatus::ProviderSyncPending,
        )) {
            return 'provider_sync_pending';
        }

        return 'reconciled';
    }

    private function incompleteRun(
        ScenarioRunContext $context,
        LifecycleMoneyRun $run,
    ): ScenarioRunResult {
        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_id', $run->voucher_id)
            ->latest('id')
            ->first();

        return $this->failure(
            $context,
            'This run already issued a Pay Code but did not record a completed lifecycle report. It will not be executed again automatically.',
            [
                'pay_code' => [
                    'id' => $run->voucher_id,
                    'code' => Voucher::query()->find($run->voucher_id)?->code,
                    'issued' => true,
                ],
                'reconciliation' => $reconciliation === null
                    ? null
                    : $this->safeReconciliation($reconciliation),
                'idempotency' => $this->idempotency($run, true),
                'provider_transfer_succeeded' => $reconciliation?->status === 'succeeded',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function safeExecution(array $payload): ?array
    {
        $execution = data_get($payload, 'execution');

        if (! is_array($execution)) {
            return null;
        }

        return [
            'operation' => $execution['operation'] ?? null,
            'successful' => $execution['successful'] ?? false,
            'status' => $execution['status'] ?? null,
            'driver' => $execution['driver'] ?? null,
            'events' => $execution['events'] ?? [],
            'failure' => $execution['failure'] ?? null,
            'provider_references' => $execution['provider_references'] ?? [],
            'reconciliation' => [
                'provider' => data_get($execution, 'reconciliation.provider'),
                'current_status' => data_get(
                    $execution,
                    'reconciliation.current_status',
                ),
                'provider_transaction_id' => data_get(
                    $execution,
                    'reconciliation.provider_transaction_id',
                ),
                'reference_number' => data_get(
                    $execution,
                    'reconciliation.reference_number',
                ),
                'amount' => data_get($execution, 'reconciliation.amount'),
                'currency' => data_get($execution, 'reconciliation.currency'),
                'settlement_rail' => data_get(
                    $execution,
                    'reconciliation.settlement_rail',
                ),
                'destination_account' => data_get(
                    $execution,
                    'reconciliation.destination_account',
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeReconciliation(
        DisbursementReconciliation $reconciliation,
    ): array {
        return [
            'status' => $reconciliation->status,
            'provider' => $reconciliation->provider,
            'provider_transaction_id' => $reconciliation->provider_transaction_id,
            'reference_number' => $reconciliation->provider_reference,
            'amount' => $reconciliation->amount,
            'currency' => $reconciliation->currency,
            'settlement_rail' => $reconciliation->settlement_rail,
            'destination_account' => $reconciliation->account_number_masked,
            'needs_review' => $reconciliation->needs_review,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function idempotency(
        LifecycleMoneyRun $run,
        bool $requiresReview,
    ): array {
        return [
            'run_reference' => 'accepted_and_hashed',
            'run_record' => $run->reference,
            'replayed' => false,
            'provider_transfer_repeated' => false,
            'requires_review' => $requiresReview,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function failure(
        ScenarioRunContext $context,
        string $message,
        array $details = [],
    ): ScenarioRunResult {
        return new ScenarioRunResult(
            exitCode: Command::FAILURE,
            payload: $this->payload($context, [
                'success' => false,
                'message' => $message,
                ...$details,
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
            'schema' => 'x-change.lifecycle.treasury-live-basic-cash.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'treasury_live_basic_cash',
            '_include_integrations' => false,
            'live' => [
                'provider_calls' => true,
                'can_move_real_money' => true,
                'manual_balance_input' => false,
                'persisted' => true,
            ],
            ...$result,
        ];
    }

    private function requiredScenarioString(
        ScenarioRunContext $context,
        string $key,
    ): string {
        $value = trim((string) data_get($context->scenario, $key));

        if ($value === '') {
            throw new \InvalidArgumentException(
                "The Treasury live basic_cash scenario requires [{$key}].",
            );
        }

        return $value;
    }

    private function isAvailable(): bool
    {
        return (bool) config(
            'x-change.lifecycle.treasury_live_basic_cash.enabled',
            false,
        ) && in_array(
            app()->environment(),
            (array) config(
                'x-change.lifecycle.treasury_live_basic_cash.allowed_environments',
                [],
            ),
            true,
        );
    }

    /**
     * @return list<string>
     */
    private function missingRequiredTables(): array
    {
        return collect((array) config(
            'x-change.lifecycle.treasury_live_basic_cash.required_tables',
            [],
        ))
            ->filter(fn (mixed $table): bool => is_string($table))
            ->reject(fn (string $table): bool => Schema::hasTable($table))
            ->values()
            ->all();
    }
}
