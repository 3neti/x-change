<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Treasury\ApproveTreasuryAccountGrant;
use LBHurtado\XChange\Actions\Treasury\ExecuteTreasuryAccountGrant;
use LBHurtado\XChange\Actions\Treasury\RequestTreasuryAccountGrant;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use Throwable;

final readonly class TreasuryAccountGrantSimulationScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private DatabaseManager $databases,
        private LifecycleScenarioBootstrapper $bootstrapper,
        private SystemUserResolverContract $systemUsers,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryProvisioningService $systemPositions,
        private TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryPositionOperationContract $positionOperations,
        private RequestTreasuryAccountGrant $requestGrant,
        private ApproveTreasuryAccountGrant $approveGrant,
        private ExecuteTreasuryAccountGrant $executeGrant,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! app()->environment(['local', 'testing'])
            || ! (bool) config('x-change.lifecycle.treasury_account_grant_simulation.enabled', false)) {
            return $this->failure($context, 'Treasury Account Grant simulation is disabled outside local and testing.');
        }

        $requiredTables = (array) config('x-change.lifecycle.treasury_account_grant_simulation.required_tables', []);
        $missing = array_values(array_filter($requiredTables, static fn (string $table): bool => ! Schema::hasTable($table)));

        if ($missing !== []) {
            return $this->failure($context, 'Treasury Account Grant simulation schema is not ready.', ['missing_tables' => $missing]);
        }

        try {
            [$maker, $checker, $system] = $this->actors($context);
        } catch (Throwable $exception) {
            return $this->failure($context, $exception->getMessage());
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingDigest = $this->stateDigest();
        $payload = [];
        $exitCode = Command::SUCCESS;
        $connection->beginTransaction();

        try {
            $payload = $this->simulate($context, $maker, $checker, $system);
        } catch (Throwable $exception) {
            report($exception);
            $exitCode = Command::FAILURE;
            $payload = ['success' => false, 'message' => $exception->getMessage()];
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingLevel
            && hash_equals($startingDigest, $this->stateDigest());

        if (! $rollbackCompleted) {
            return $this->failure($context, 'Treasury Account Grant simulation could not confirm complete rollback.');
        }

        return new ScenarioRunResult($exitCode, [
            'schema' => 'x-change.lifecycle.treasury-account-grant-simulation.v1',
            'scenario' => $context->scenarioKey,
            'mode' => 'treasury_account_grant_simulation',
            'safety' => [
                'provider_calls' => false,
                'real_money_movement' => false,
                'system_principal_impersonation' => false,
            ],
            ...$payload,
            'persisted' => false,
            'rollback_completed' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function simulate(ScenarioRunContext $context, Model $maker, Model $checker, Model $system): array
    {
        $scope = substr(hash('sha256', $context->idempotencyKey), 0, 20);
        $this->grantTemporaryAuthority($maker, $checker, $scope);
        $connection = collect($this->connections->active([
            (string) data_get($context->scenario, 'treasury.connection', 'netbank-primary'),
        ]))->sole();
        $amountMinor = max(1, (int) round(((float) data_get($context->scenario, '_runtime.amount', 100)) * 100));
        $sourceAmountMinor = max($amountMinor, $amountMinor * 2);
        $systemPortfolio = $this->systemPositions->provision([$connection->reference]);
        $recipientPortfolio = $this->accountPortfolios->provision($context->issuer, [$connection->reference]);
        $clearing = $this->position($systemPortfolio->positions, TreasuryPositionPurpose::TreasuryClearing);
        $institutionOwned = $this->position($systemPortfolio->positions, TreasuryPositionPurpose::InstitutionOwnedFunds);
        $clientFunds = $this->position($recipientPortfolio->positions, TreasuryPositionPurpose::ClientFunds);

        $this->inventoryOperations->registerInventory(new TreasuryInventoryData(
            inventoryReference: $connection->inventoryReference,
            resourceType: $connection->settlementResourceType,
            currency: $connection->currency,
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'grant-simulation-inventory:'.$scope,
            externalReference: $connection->settlementResourceReference,
        ));
        $this->inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
            operationReference: 'grant-simulation-inventory-recognition:'.$scope,
            inventoryReference: $connection->inventoryReference,
            settlementResourceReference: $connection->settlementResourceReference,
            amountMinor: $sourceAmountMinor,
            currency: $connection->currency,
            status: 'requested',
            idempotencyKey: 'grant-simulation-inventory-recognition-key:'.$scope,
            externalReference: 'simulation-evidence:'.$scope,
        ));
        $recognition = $this->positionOperations->recognize(new TreasuryPositionRecognitionData(
            operationReference: 'grant-simulation-position-recognition:'.$scope,
            destinationPositionReference: $clearing->positionReference,
            amountMinor: $sourceAmountMinor,
            currency: $connection->currency,
            idempotencyKey: 'grant-simulation-position-recognition-key:'.$scope,
            externalReference: 'simulation-evidence:'.$scope,
        ));
        $this->positionOperations->allocate(new TreasuryPositionAllocationData(
            operationReference: 'grant-simulation-classification:'.$scope,
            sourcePositionReference: $clearing->positionReference,
            destinationPositionReference: $institutionOwned->positionReference,
            amountMinor: $sourceAmountMinor,
            currency: $connection->currency,
            idempotencyKey: 'grant-simulation-classification-key:'.$scope,
            externalReference: $recognition->operationReference,
        ));

        $inventoryBeforeGrant = (int) TreasuryInventory::query()->sum('balance_minor');
        $grant = $this->requestGrant->handle(
            recipient: $context->issuer,
            amountMinor: $amountMinor,
            currency: $connection->currency,
            connectionReference: $connection->reference,
            purpose: 'Rollback-only Treasury Account Grant simulation',
            idempotencyReference: 'grant-simulation-request:'.$scope,
            maker: $maker,
        );
        $this->approveGrant->handle($grant, $checker);
        $executed = $this->executeGrant->handle($grant, $checker);
        $replayed = $this->executeGrant->handle($grant, $checker);

        return [
            'success' => true,
            'actors' => [
                'maker_id' => (string) $maker->getKey(),
                'checker_id' => (string) $checker->getKey(),
                'recipient_id' => (string) $context->issuer->getKey(),
                'system_principal_id' => (string) $system->getKey(),
            ],
            'grant' => [
                'reference' => $executed->reference,
                'status' => $executed->status->value,
                'amount_minor' => $executed->amount_minor,
                'currency' => $executed->currency,
                'operation_reference' => $executed->operation_reference,
            ],
            'stage_balances' => [
                'institution_owned_funds_minor' => $this->balance($institutionOwned),
                'recipient_client_funds_minor' => $this->balance($clientFunds),
                'provider_inventory_minor' => (int) TreasuryInventory::query()->sum('balance_minor'),
            ],
            'invariants' => [
                'maker_checker_separated' => ! $maker->is($checker),
                'provider_inventory_unchanged_by_grant' => $inventoryBeforeGrant === (int) TreasuryInventory::query()->sum('balance_minor'),
                'execution_replayed_exactly_once' => $replayed->operation_reference === $executed->operation_reference,
                'journal_evidence_recorded' => ExecutionJournalEntry::query()->where('event_type', 'treasury.account_grant.executed')->exists(),
            ],
        ];
    }

    /** @return array{0:Model,1:Model,2:Model} */
    private function actors(ScenarioRunContext $context): array
    {
        $makerId = trim((string) data_get($context->scenario, '_runtime.maker', ''));
        $checkerId = trim((string) data_get($context->scenario, '_runtime.checker', ''));

        if ($makerId === '' || $checkerId === '') {
            throw new \InvalidArgumentException('Treasury Account Grant simulation requires --maker and --checker.');
        }

        $maker = $this->bootstrapper->resolveIssuerModel((int) $makerId);
        $checker = $this->bootstrapper->resolveIssuerModel((int) $checkerId);
        $system = $this->systemUsers->resolve();

        if (! $system instanceof Model || $maker->is($checker) || $maker->is($system) || $checker->is($system)) {
            throw new \InvalidArgumentException('Treasury maker, checker, and System Principal must be three distinct identities.');
        }

        return [$maker, $checker, $system];
    }

    private function grantTemporaryAuthority(Model $maker, Model $checker, string $scope): void
    {
        foreach ([
            [$maker, TreasuryOperatorCapability::ViewAccountGrants],
            [$maker, TreasuryOperatorCapability::RequestAccountGrants],
            [$checker, TreasuryOperatorCapability::ViewAccountGrants],
            [$checker, TreasuryOperatorCapability::ApproveAccountGrants],
            [$checker, TreasuryOperatorCapability::ExecuteAccountGrants],
        ] as [$actor, $capability]) {
            TreasuryOperatorAuthorization::query()->create([
                'operator_type' => $actor->getMorphClass(),
                'operator_id' => $actor->getKey(),
                'capability' => $capability->value,
                'authorization_reference' => 'simulation:'.$scope.':'.$actor->getKey().':'.$capability->value,
                'valid_from' => now()->subMinute(),
                'valid_until' => now()->addHour(),
            ]);
        }
    }

    /** @param list<TreasuryPositionData> $positions */
    private function position(array $positions, TreasuryPositionPurpose $purpose): TreasuryPositionData
    {
        return collect($positions)->sole(static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose);
    }

    private function balance(TreasuryPositionData $position): int
    {
        $model = TreasuryPosition::query()
            ->where('position_reference', $position->positionReference)
            ->sole();

        return Wallet::query()->findOrFail($model->internal_ledger_id)->getBalanceIntAttribute();
    }

    private function stateDigest(): string
    {
        return hash('sha256', json_encode([
            TreasuryAccountGrant::query()->count(),
            TreasuryOperatorAuthorization::query()->count(),
            TreasuryInventory::query()->count(),
            (int) TreasuryInventory::query()->sum('balance_minor'),
            $this->databases->table('treasury_positions')->count(),
            $this->databases->table('treasury_position_operations')->count(),
            $this->databases->table('treasury_inventory_operations')->count(),
            ExecutionJournalEntry::query()->count(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $context */
    private function failure(ScenarioRunContext $scenario, string $message, array $context = []): ScenarioRunResult
    {
        return new ScenarioRunResult(Command::FAILURE, [
            'schema' => 'x-change.lifecycle.treasury-account-grant-simulation.v1',
            'scenario' => $scenario->scenarioKey,
            'mode' => 'treasury_account_grant_simulation',
            'success' => false,
            'message' => $message,
            ...$context,
            'persisted' => false,
        ]);
    }
}
