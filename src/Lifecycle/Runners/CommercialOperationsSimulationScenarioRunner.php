<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Commercial\ApprovePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartner;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartnerDestination;
use LBHurtado\XChange\Actions\Commercial\PostCommercialSale;
use LBHurtado\XChange\Actions\Commercial\ReconcilePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Actions\Commercial\RecordProviderCostBatch;
use LBHurtado\XChange\Actions\Commercial\RequestPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Actions\Commercial\SubmitPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerDestinationData;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerRevisionData;
use LBHurtado\XChange\Data\Commercial\PartnerCommissionPayoutBatchRequestData;
use LBHurtado\XChange\Data\Commercial\ProviderCostBatchEvidenceData;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Lifecycle\Runners\Support\CommercialSimulationDestinationValidator;
use LBHurtado\XChange\Lifecycle\Runners\Support\CommercialSimulationPayoutProvider;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceInspector;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XCommerce\Data\CommercialAccountingContextData;
use LBHurtado\XCommerce\Data\CommercialAttributionSnapshotData;
use LBHurtado\XCommerce\Data\CommercialQuoteLineInputData;
use LBHurtado\XCommerce\Services\DeterministicCommercialQuoteBuilder;
use LBHurtado\XCommerce\Services\DeterministicCommercialSaleFactory;
use LBHurtado\XCommerce\Services\DeterministicCommercialWaterfallCalculator;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use Throwable;

final class CommercialOperationsSimulationScenarioRunner implements ScenarioRunnerContract
{
    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'x_change_commercial_offerings',
        'x_change_commercial_offering_activations',
        'x_change_commercial_operator_authorizations',
        'x_change_commercial_component_economics_manifests',
        'x_change_commercial_component_economics_activations',
        'x_change_commercial_component_economics_heads',
        'x_change_commercial_recipient_designations',
        'x_change_commercial_partners',
        'x_change_commercial_partner_revisions',
        'x_change_commercial_partner_destination_revisions',
        'x_change_commercial_sales',
        'x_change_commercial_allocations',
        'x_change_commercial_provider_cost_batches',
        'x_change_commercial_provider_cost_batch_lines',
        'x_change_partner_commission_payout_batches',
        'x_change_partner_commission_payout_batch_lines',
        'x_change_partner_commission_payout_attempts',
        'treasury_inventories',
        'treasury_inventory_operations',
        'treasury_positions',
        'treasury_position_operations',
        'execution_journal_entries',
    ];

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly Container $container,
        private readonly LifecycleScenarioBootstrapper $bootstrapper,
        private readonly SystemUserResolverContract $systemUsers,
        private readonly CommercialOfferingResolverContract $offerings,
        private readonly TreasuryProviderConnectionCatalog $connections,
        private readonly TreasuryPositionProvisioningContract $positionProvisioning,
        private readonly TreasuryPositionOperationContract $positionOperations,
        private readonly TreasuryInventoryOperationContract $inventoryOperations,
        private readonly ProvisionCommercialBaselines $baselines,
        private readonly CommercialGovernanceInspector $governance,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! $this->available()) {
            return $this->failure($context, 'Commercial operations simulation is disabled outside local and testing.');
        }

        $missingTables = array_values(array_filter(
            (array) config('x-change.lifecycle.commercial_operations_simulation.required_tables', self::REQUIRED_TABLES),
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            return $this->failure($context, 'Commercial operations simulation schema is not ready.', [
                'missing_tables' => $missingTables,
            ]);
        }

        try {
            [$maker, $checker, $system] = $this->actors($context);
        } catch (Throwable $exception) {
            return $this->failure($context, $exception->getMessage());
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest();
        $originalLiveGate = config('x-change.commercial.operations.live_provider_calls_enabled');
        $originalPayoutProvider = $this->container->make(PayoutProvider::class);
        $originalDestinationValidator = $this->container->make(PayoutDestinationValidatorContract::class);
        $payload = [];
        $exitCode = Command::SUCCESS;

        $connection->beginTransaction();

        try {
            $simulationProvider = new CommercialSimulationPayoutProvider;
            $this->container->instance(PayoutProvider::class, $simulationProvider);
            $this->container->instance(
                PayoutDestinationValidatorContract::class,
                new CommercialSimulationDestinationValidator,
            );
            config()->set('x-change.commercial.operations.live_provider_calls_enabled', true);
            $this->forgetProviderActions();

            $payload = $this->execute($context, $maker, $checker, $system, $simulationProvider);
        } catch (Throwable $exception) {
            if ($this->container->runningUnitTests()) {
                throw $exception;
            }

            report($exception);
            $exitCode = Command::FAILURE;
            $payload = [
                'success' => false,
                'message' => 'Commercial operations simulation could not complete safely.',
                'error_type' => $exception::class,
            ];
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }

            config()->set('x-change.commercial.operations.live_provider_calls_enabled', $originalLiveGate);
            $this->container->instance(PayoutProvider::class, $originalPayoutProvider);
            $this->container->instance(PayoutDestinationValidatorContract::class, $originalDestinationValidator);
            $this->forgetProviderActions();
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingLevel
            && hash_equals($startingState, $this->stateDigest());

        if (! $rollbackCompleted) {
            return $this->failure($context, 'Commercial operations simulation could not confirm complete rollback.');
        }

        return new ScenarioRunResult($exitCode, [
            'schema' => 'x-change.lifecycle.commercial-operations-simulation.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'commercial_operations_simulation',
            ...$payload,
            'persisted' => false,
            'rollback_completed' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function execute(
        ScenarioRunContext $context,
        Model $maker,
        Model $checker,
        Model $system,
        CommercialSimulationPayoutProvider $simulationProvider,
    ): array {
        $this->grantTemporaryAuthority($maker, $checker, $context->idempotencyKey);
        $this->baselines->provision('lifecycle-simulation:'.$context->idempotencyKey);

        $offering = $this->offerings->resolve('pay_code');
        $governance = $this->governance->inspect();
        $treasuryConnection = collect($this->connections->active([
            (string) data_get($context->scenario, 'commercial.connection', 'netbank-primary'),
        ]))->sole();
        $provider = $treasuryConnection->provider;
        $currency = $treasuryConnection->currency;
        $scope = substr(hash('sha256', $context->idempotencyKey), 0, 20);
        $partnerReference = 'partner:lifecycle-commercial-'.$scope;

        $partnerAction = $this->container->make(ManageCommercialPartner::class);
        $partnerRevision = $partnerAction->createDraft($maker, new CommercialPartnerRevisionData(
            reference: $partnerReference,
            displayName: 'Lifecycle Commercial Partner',
            legalName: 'Lifecycle Commercial Partner (Simulation)',
            externalReference: 'simulation:'.$scope,
            attributionBasis: 'simulation_contractual_referral',
            authorizationReference: 'simulation-authority:'.$scope,
            terms: ['commission_basis' => 'governed_waterfall'],
        ));
        $partnerRevision = $partnerAction->approve(
            $checker,
            $partnerAction->submit($maker, $partnerRevision),
        );
        $partner = $partnerRevision->partner;

        $destinationAction = $this->container->make(ManageCommercialPartnerDestination::class);
        $destinationRevision = $destinationAction->createDraft(
            $maker,
            $partner,
            new CommercialPartnerDestinationData(
                provider: $provider,
                connectionReference: $treasuryConnection->reference,
                currency: $currency,
                bankCode: 'GXCHPHM2XXX',
                accountNumber: '09170000000',
                recipientName: 'Lifecycle Commercial Partner',
                mobile: '09170000000',
                authorizationReference: 'simulation-destination:'.$scope,
            ),
        );
        $destinationRevision = $destinationAction->approve(
            $checker,
            $destinationAction->submit($maker, $destinationRevision),
        );

        $quote = (new DeterministicCommercialQuoteBuilder(
            new DeterministicCommercialWaterfallCalculator,
        ))->build(
            sourceCommercialEventReference: 'lifecycle-commercial-sale:'.$scope,
            catalog: $offering->catalog,
            waterfallPolicy: $offering->waterfallPolicy,
            attribution: new CommercialAttributionSnapshotData(
                reference: 'lifecycle-commercial-attribution:'.$scope,
                version: 1,
                participants: ['sales_partner' => $partnerReference],
            ),
            lineInputs: [new CommercialQuoteLineInputData('cash.amount')],
            offering: $offering,
        );
        $expectedProviderCostMinor = (int) collect($quote->allocationPlan->lines)
            ->where('category', 'provider_cost')
            ->sum('amountMinor');
        $saleSnapshot = (new DeterministicCommercialSaleFactory)->accept(
            quote: $quote,
            acceptanceEventReference: 'lifecycle-commercial-acceptance:'.$scope,
            buyerReference: 'principal:account:'.$context->issuer->getKey(),
            acceptedAt: now()->toIso8601String(),
            accountingContext: new CommercialAccountingContextData(
                schemaVersion: 2,
                provider: $provider,
                connectionReference: $treasuryConnection->reference,
                settlementRail: 'INSTAPAY',
                currency: $currency,
                productReference: 'product:pay-code',
                recognitionPolicyReference: 'recognition:lifecycle-commercial-simulation:v1',
                expectedProviderCostMinor: $expectedProviderCostMinor,
                partnerReference: $partnerReference,
            ),
        );

        $positions = $this->positions(
            $context->issuer,
            $checker,
            $system,
            $treasuryConnection->provider,
            $treasuryConnection->reference,
            $treasuryConnection->currency,
            $treasuryConnection->settlementResourceReference,
            $treasuryConnection->settlementResourceType,
            $scope,
        );
        $this->recognizeSimulationFunds(
            $positions,
            $treasuryConnection->inventoryReference,
            $treasuryConnection->settlementResourceReference,
            $treasuryConnection->currency,
            $quote->totalPriceMinor,
            $scope,
        );
        $timeline = ['before_sale' => $this->positionSnapshot($positions)];

        $sale = $this->container->make(PostCommercialSale::class)->execute(
            $saleSnapshot,
            $positions['client_funds']->position_reference,
            $positions['commercial_clearing']->position_reference,
            [
                'provider-transfer-cost' => $positions['provider_cost']->position_reference,
                'product-revenue' => $positions['product_revenue']->position_reference,
                'partner-commission' => $positions['partner_commission']->position_reference,
                'commercial-residual' => $positions['commercial_revenue']->position_reference,
            ],
        );
        $saleReplay = $this->container->make(PostCommercialSale::class)->execute(
            $saleSnapshot,
            $positions['client_funds']->position_reference,
            $positions['commercial_clearing']->position_reference,
            [
                'provider-transfer-cost' => $positions['provider_cost']->position_reference,
                'product-revenue' => $positions['product_revenue']->position_reference,
                'partner-commission' => $positions['partner_commission']->position_reference,
                'commercial-residual' => $positions['commercial_revenue']->position_reference,
            ],
        );
        $timeline['after_sale'] = $this->positionSnapshot($positions);

        $periodStart = now()->subMinute()->toIso8601String();
        $periodEnd = now()->addMinute()->toIso8601String();
        $beforeMismatch = $this->positionSnapshot($positions);
        $providerCostMismatch = $this->container->make(RecordProviderCostBatch::class)->execute(
            $maker,
            new ProviderCostBatchEvidenceData(
                reference: 'lifecycle-provider-cost-mismatch:'.$scope,
                provider: $provider,
                connectionReference: $treasuryConnection->reference,
                currency: $currency,
                evidenceType: 'simulation_authoritative_observation',
                evidenceReference: 'simulation-evidence:provider-cost-mismatch:'.$scope,
                observedAmountMinor: max(0, $expectedProviderCostMinor - 1),
                periodStartedAt: $periodStart,
                periodEndedAt: $periodEnd,
                observedAt: now()->toIso8601String(),
                idempotencyKey: 'lifecycle-provider-cost-mismatch-key:'.$scope,
                metadata: ['simulation' => true],
            ),
        );
        $afterMismatch = $this->positionSnapshot($positions);
        $providerCost = $this->container->make(RecordProviderCostBatch::class)->execute(
            $maker,
            new ProviderCostBatchEvidenceData(
                reference: 'lifecycle-provider-cost:'.$scope,
                provider: $provider,
                connectionReference: $treasuryConnection->reference,
                currency: $currency,
                evidenceType: 'simulation_authoritative_observation',
                evidenceReference: 'simulation-evidence:provider-cost:'.$scope,
                observedAmountMinor: $expectedProviderCostMinor,
                periodStartedAt: $periodStart,
                periodEndedAt: $periodEnd,
                observedAt: now()->toIso8601String(),
                idempotencyKey: 'lifecycle-provider-cost-key:'.$scope,
                metadata: ['simulation' => true],
            ),
        );
        $timeline['after_provider_cost'] = $this->positionSnapshot($positions);

        $commission = $this->container->make(RequestPartnerCommissionPayoutBatch::class)->execute(
            $maker,
            new PartnerCommissionPayoutBatchRequestData(
                reference: 'lifecycle-commission:'.$scope,
                partnerReference: $partnerReference,
                provider: $provider,
                connectionReference: $treasuryConnection->reference,
                currency: $currency,
                periodStartedAt: $periodStart,
                periodEndedAt: $periodEnd,
                idempotencyKey: 'lifecycle-commission-request-key:'.$scope,
                metadata: ['simulation' => true],
            ),
        );
        $approvedCommission = $this->container->make(ApprovePartnerCommissionPayoutBatch::class)->execute(
            $checker,
            $commission,
            'lifecycle-commission-approval:'.$scope,
        );
        $pendingCommission = $this->container->make(SubmitPartnerCommissionPayoutBatch::class)->execute(
            $maker,
            $approvedCommission,
            'lifecycle-commission-submission:'.$scope,
        );
        $settledCommission = $this->container->make(ReconcilePartnerCommissionPayoutBatch::class)->execute(
            $maker,
            $pendingCommission,
        );
        $replayedCommission = $this->container->make(ReconcilePartnerCommissionPayoutBatch::class)->execute(
            $maker,
            $settledCommission,
        );
        $timeline['after_commission'] = $this->positionSnapshot($positions);

        $journalEvents = ExecutionJournalEntry::query()
            ->where('created_at', '>=', now()->subMinutes(2))
            ->where('event_type', 'like', 'commercial.%')
            ->orderBy('id')
            ->pluck('event_type')
            ->all();
        $allocations = $sale->allocations
            ->map(static fn ($allocation): array => [
                'sequence' => $allocation->sequence,
                'category' => $allocation->category,
                'recipient_reference' => $allocation->recipient_reference,
                'amount_minor' => $allocation->amount_minor,
                'currency' => $allocation->currency,
                'status' => $allocation->status,
            ])
            ->values()
            ->all();

        return [
            'success' => true,
            'message' => 'Commercial operations completed through production actions and were rolled back.',
            'safety' => [
                'external_provider_calls' => false,
                'real_money_movement' => false,
                'simulation_provider' => CommercialSimulationPayoutProvider::class,
                'allowed_environment' => app()->environment(),
            ],
            'authorities' => [
                'issuer' => $this->actorSummary($context->issuer),
                'maker' => $this->actorSummary($maker),
                'checker' => $this->actorSummary($checker),
                'maker_checker_separated' => ! $maker->is($checker),
                'system_principal_excluded' => ! $maker->is($system) && ! $checker->is($system),
            ],
            'offering' => [
                'reference' => $offering->reference,
                'version' => $offering->version,
                'snapshot_hash' => $offering->snapshotHash(),
                'catalog_reference' => $offering->catalog->reference,
                'catalog_version' => $offering->catalog->version,
                'waterfall_reference' => $offering->waterfallPolicy->reference,
                'waterfall_version' => $offering->waterfallPolicy->version,
                'legal_trace' => $offering->legalTrace->toArray(),
            ],
            'agreement_economics' => [
                'operational' => (bool) data_get($governance, 'component_economics.operational', false),
                'active_profiles' => (int) data_get($governance, 'component_economics.complete_profile_count', 0),
                'required_profiles' => (int) data_get($governance, 'component_economics.required_profile_count', 0),
                'recipient_authorities_active' => (int) data_get($governance, 'recipient_designations.active_count', 0),
                'recipient_authorities_required' => (int) data_get($governance, 'recipient_designations.required_count', 0),
                'simulation_control_policy' => 'synthetic_provider-cost-and-commission-waterfall',
            ],
            'partner' => [
                'reference' => $partner->reference,
                'revision_version' => $partnerRevision->version,
                'status' => $partner->status->value,
                'destination_version' => $destinationRevision->version,
                'destination_summary' => $destinationRevision->destination_summary,
                'destination_hash' => $destinationRevision->destination_hash,
            ],
            'sale' => [
                'reference' => $sale->reference,
                'status' => $sale->status,
                'currency' => $sale->currency,
                'total_price_minor' => $sale->total_price_minor,
                'quote_reference' => $sale->quote_reference,
                'snapshot_hash' => $sale->snapshot_hash,
                'allocations' => $allocations,
            ],
            'positions' => $timeline,
            'provider_cost_batch' => [
                'mismatch_control' => [
                    'status' => $providerCostMismatch->status->value,
                    'expected_amount_minor' => $providerCostMismatch->expected_amount_minor,
                    'observed_amount_minor' => $providerCostMismatch->observed_amount_minor,
                    'variance_amount_minor' => $providerCostMismatch->variance_amount_minor,
                    'line_count' => $providerCostMismatch->lines()->count(),
                    'accounting_unchanged' => $beforeMismatch === $afterMismatch,
                ],
                'reference' => $providerCost->reference,
                'status' => $providerCost->status->value,
                'expected_amount_minor' => $providerCost->expected_amount_minor,
                'observed_amount_minor' => $providerCost->observed_amount_minor,
                'variance_amount_minor' => $providerCost->variance_amount_minor,
                'line_count' => $providerCost->lines()->count(),
            ],
            'commission_batch' => [
                'reference' => $settledCommission->reference,
                'amount_minor' => $settledCommission->amount_minor,
                'currency' => $settledCommission->currency,
                'destination_summary' => $settledCommission->destination_summary,
                'requested_status' => $commission->status->value,
                'approved_status' => $approvedCommission->status->value,
                'submitted_status' => $pendingCommission->status->value,
                'settled_status' => $settledCommission->status->value,
                'replay_status' => $replayedCommission->status->value,
            ],
            'provider_simulation' => [
                'disbursement_calls' => $simulationProvider->disbursementCalls,
                'status_calls' => $simulationProvider->statusCalls,
            ],
            'journal' => [
                'event_count' => count($journalEvents),
                'events' => $journalEvents,
            ],
            'idempotency' => [
                'sale_replayed_without_duplicate' => $saleReplay->is($sale),
                'commission_replayed_without_provider_call' => $simulationProvider->statusCalls === 1,
            ],
            'invariants' => [
                'waterfall_conserved' => collect($allocations)->sum('amount_minor') === $sale->total_price_minor,
                'commercial_clearing_zero' => data_get($timeline, 'after_sale.commercial_clearing') === 0,
                'provider_cost_settled' => data_get($timeline, 'after_provider_cost.provider_cost') === 0,
                'provider_cost_mismatch_fail_closed' => $providerCostMismatch->lines()->count() === 0
                    && $beforeMismatch === $afterMismatch,
                'commission_settled' => data_get($timeline, 'after_commission.partner_commission') === 0,
                'provider_submission_once' => $simulationProvider->disbursementCalls === 1,
                'provider_status_check_once' => $simulationProvider->statusCalls === 1,
            ],
        ];
    }

    /** @return array{0:Model, 1:Model, 2:Model} */
    private function actors(ScenarioRunContext $context): array
    {
        $makerId = trim((string) data_get($context->scenario, '_runtime.maker', ''));
        $checkerId = trim((string) data_get($context->scenario, '_runtime.checker', ''));

        if ($makerId === '' || $checkerId === '') {
            throw new \InvalidArgumentException('Commercial operations simulation requires --maker and --checker.');
        }

        $maker = $this->bootstrapper->resolveIssuerModel((int) $makerId);
        $checker = $this->bootstrapper->resolveIssuerModel((int) $checkerId);
        $system = $this->systemUsers->resolve();

        if (! $system instanceof Model) {
            throw new \LogicException('The configured System Principal must be an Eloquent model.');
        }

        if ($maker->is($checker)) {
            throw new \InvalidArgumentException('Commercial maker and checker must be different people.');
        }

        if ($maker->is($system) || $checker->is($system)) {
            throw new \InvalidArgumentException('The System Principal cannot act as Commercial maker or checker.');
        }

        return [$maker, $checker, $system];
    }

    private function grantTemporaryAuthority(Model $maker, Model $checker, string $scope): void
    {
        foreach ([
            [$maker, CommercialOperatorCapability::ManagePartners],
            [$maker, CommercialOperatorCapability::ReconcileProviderCosts],
            [$maker, CommercialOperatorCapability::RequestCommissionPayouts],
            [$maker, CommercialOperatorCapability::ExecuteCommissionPayouts],
            [$checker, CommercialOperatorCapability::ApprovePartners],
            [$checker, CommercialOperatorCapability::ApproveCommissionPayouts],
        ] as [$operator, $capability]) {
            CommercialOperatorAuthorization::query()->firstOrCreate([
                'operator_type' => $operator->getMorphClass(),
                'operator_id' => $operator->getKey(),
                'capability' => $capability->value,
            ], [
                'authorization_reference' => 'lifecycle-simulation:'.$scope,
                'valid_from' => now()->subMinute(),
                'valid_until' => now()->addHour(),
            ]);
        }
    }

    /**
     * @return array<string, TreasuryPosition>
     */
    private function positions(
        Model $buyer,
        Model $partner,
        Model $system,
        string $provider,
        string $connectionReference,
        string $currency,
        string $settlementResourceReference,
        string $settlementResourceType,
        string $scope,
    ): array {
        $definitions = [
            'treasury_clearing' => [$system, TreasuryPositionPurpose::TreasuryClearing],
            'client_funds' => [$buyer, TreasuryPositionPurpose::ClientFunds],
            'commercial_clearing' => [$system, TreasuryPositionPurpose::CommercialClearing],
            'provider_cost' => [$system, TreasuryPositionPurpose::ProviderCostPayable],
            'product_revenue' => [$system, TreasuryPositionPurpose::ProductRevenue],
            'partner_commission' => [$partner, TreasuryPositionPurpose::PartnerCommissionPayable],
            'commercial_revenue' => [$system, TreasuryPositionPurpose::CommercialRevenue],
        ];
        $positions = [];

        foreach ($definitions as $key => [$principal, $purpose]) {
            $referenceScope = hash('sha256', $scope.'|'.$key.'|'.$principal->getMorphClass().'|'.$principal->getKey());
            $definition = new TreasuryPositionDefinitionData(
                positionReference: 'position:lifecycle-commercial:'.substr($referenceScope, 0, 32),
                principalReference: 'principal:'.$principal->getMorphClass().':'.$principal->getKey(),
                mandateReference: 'mandate:lifecycle-commercial:'.substr($referenceScope, 0, 32),
                settlementResourceReference: $settlementResourceReference,
                settlementResourceType: $settlementResourceType,
                provider: $provider,
                connectionReference: $connectionReference,
                currency: $currency,
                decimalPlaces: 2,
                purpose: $purpose,
                custodyMode: TreasuryCustodyMode::ProviderProjection,
                legalProfile: (string) config('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1'),
                legalProfileVersion: (string) config('x-change.treasury.legal_profile_version', 'unversioned'),
                idempotencyKey: 'position-registration:lifecycle-commercial:'.$referenceScope,
                reconciliationReference: 'reconciliation:'.$provider.':'.$connectionReference,
            );
            $this->positionProvisioning->provision($principal, $definition);
            $positions[$key] = TreasuryPosition::query()
                ->where('position_reference', $definition->positionReference)
                ->sole();
        }

        return $positions;
    }

    /** @param array<string, TreasuryPosition> $positions */
    private function recognizeSimulationFunds(
        array $positions,
        string $inventoryReference,
        string $settlementResourceReference,
        string $currency,
        int $amountMinor,
        string $scope,
    ): void {
        $this->inventoryOperations->registerInventory(new TreasuryInventoryData(
            inventoryReference: $inventoryReference,
            resourceType: 'cash_at_bank',
            currency: $currency,
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'lifecycle-commercial-inventory-registration:'.$scope,
            externalReference: $settlementResourceReference,
        ));
        $this->inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
            operationReference: 'lifecycle-commercial-inventory-recognition:'.$scope,
            inventoryReference: $inventoryReference,
            settlementResourceReference: $settlementResourceReference,
            amountMinor: $amountMinor,
            currency: $currency,
            status: 'requested',
            idempotencyKey: 'lifecycle-commercial-inventory-recognition-key:'.$scope,
            effectiveAt: now()->toIso8601String(),
            externalReference: 'simulation-funding:'.$scope,
        ));
        $this->positionOperations->recognize(new TreasuryPositionRecognitionData(
            operationReference: 'lifecycle-commercial-position-recognition:'.$scope,
            destinationPositionReference: $positions['treasury_clearing']->position_reference,
            amountMinor: $amountMinor,
            currency: $currency,
            idempotencyKey: 'lifecycle-commercial-position-recognition-key:'.$scope,
            externalReference: 'simulation-funding:'.$scope,
        ));
        $this->positionOperations->allocate(new TreasuryPositionAllocationData(
            operationReference: 'lifecycle-commercial-client-funding:'.$scope,
            sourcePositionReference: $positions['treasury_clearing']->position_reference,
            destinationPositionReference: $positions['client_funds']->position_reference,
            amountMinor: $amountMinor,
            currency: $currency,
            idempotencyKey: 'lifecycle-commercial-client-funding-key:'.$scope,
            externalReference: 'simulation-funding:'.$scope,
        ));
    }

    /** @param array<string, TreasuryPosition> $positions */
    private function positionSnapshot(array $positions): array
    {
        return collect($positions)->mapWithKeys(static function (TreasuryPosition $position, string $key): array {
            $wallet = Wallet::query()->findOrFail($position->internal_ledger_id);

            return [$key => $wallet->getBalanceIntAttribute()];
        })->all();
    }

    /** @return array<string, mixed> */
    private function actorSummary(Model $actor): array
    {
        return [
            'type' => $actor->getMorphClass(),
            'reference' => substr(hash('sha256', $actor->getMorphClass().':'.$actor->getKey()), 0, 16),
        ];
    }

    private function available(): bool
    {
        return ! app()->environment('production')
            && app()->environment((array) config(
                'x-change.lifecycle.commercial_operations_simulation.allowed_environments',
                ['local', 'testing'],
            ))
            && (bool) config('x-change.lifecycle.commercial_operations_simulation.enabled', false);
    }

    private function stateDigest(): string
    {
        $tables = array_values(array_filter(
            self::REQUIRED_TABLES,
            static fn (string $table): bool => Schema::hasTable($table),
        ));
        $state = [];

        foreach ($tables as $table) {
            $state[$table] = [
                'count' => DB::table($table)->count(),
                'max_id' => DB::table($table)->max('id'),
            ];
        }

        ksort($state);

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function forgetProviderActions(): void
    {
        $this->container->forgetInstance(SubmitPartnerCommissionPayoutBatch::class);
        $this->container->forgetInstance(ReconcilePartnerCommissionPayoutBatch::class);
        $this->container->forgetInstance(ManageCommercialPartnerDestination::class);
    }

    /** @param array<string, mixed> $extra */
    private function failure(ScenarioRunContext $context, string $message, array $extra = []): ScenarioRunResult
    {
        return new ScenarioRunResult(Command::FAILURE, [
            'schema' => 'x-change.lifecycle.commercial-operations-simulation.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'commercial_operations_simulation',
            'success' => false,
            'message' => $message,
            'safety' => [
                'external_provider_calls' => false,
                'real_money_movement' => false,
                'persisted' => false,
            ],
            ...$extra,
        ]);
    }
}
