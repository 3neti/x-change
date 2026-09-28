<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\ApproveCampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetPayCodes;
use LBHurtado\XChange\Actions\Treasury\ApproveTreasuryAccountGrant;
use LBHurtado\XChange\Actions\Treasury\ExecuteTreasuryAccountGrant;
use LBHurtado\XChange\Actions\Treasury\RequestTreasuryAccountGrant;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use RuntimeException;

final readonly class CommercialPayCodeScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private CampaignWorksheetRepository $worksheets,
        private IssueCampaignWorksheetApprovalPayCode $approvalPayCodes,
        private ApproveCampaignWorksheetAuthorization $approveWorksheet,
        private IssueCampaignWorksheetPayCodes $payCodes,
        private LifecycleScenarioBootstrapper $bootstrapper,
        private CommercialOperatorAuthorityContract $commercialAuthority,
        private SystemUserResolverContract $systemUsers,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryProvisioningService $systemPositions,
        private TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private TreasuryInventoryRegistrationService $inventoryRegistration,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryPositionOperationContract $positionOperations,
        private RequestTreasuryAccountGrant $requestGrant,
        private ApproveTreasuryAccountGrant $approveGrant,
        private ExecuteTreasuryAccountGrant $executeGrant,
        private VoucherLifecycleServiceContract $voucherLifecycle,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        try {
            $runReference = $this->requiredRuntimeString($context, 'run_reference');
            $phase = $this->phase($context);
            $amountMinor = $this->amountMinor($context);
            $maker = $context->issuer;
            $checker = $this->checker($context, $maker);
            $principal = $this->commercialPrincipal();

            $this->assertCommercialAuthority($maker, $checker);

            $worksheet = $this->worksheet($maker, $runReference);

            if (! $worksheet instanceof CampaignWorksheet) {
                if ($phase !== 'prepare') {
                    throw new RuntimeException('The Commercial Pay Code must be prepared by its maker first.');
                }

                $fundingGrant = $this->fundCommercialPrincipal(
                    principal: $principal,
                    maker: $maker,
                    checker: $checker,
                    runReference: $runReference,
                );

                $worksheet = $this->prepareWorksheet(
                    maker: $maker,
                    checker: $checker,
                    principal: $principal,
                    runReference: $runReference,
                    amountMinor: $amountMinor,
                    fundingGrant: $fundingGrant,
                );
            } else {
                $this->assertReplayMatches($worksheet, $checker, $principal, $amountMinor);
            }

            $authorization = $worksheet->authorizations()->latest('id')->first();

            if ($phase === 'approve') {
                if (data_get($context->scenario, '_runtime.confirm_checker_approval') !== true) {
                    throw new RuntimeException('Commercial Pay Code approval requires explicit checker confirmation.');
                }

                if (! $authorization instanceof CampaignWorksheetAuthorization) {
                    throw new RuntimeException('The Commercial Pay Code approval instruction is unavailable.');
                }

                $authorization = $this->approveWorksheet->handle(
                    (string) $authorization->approval_pay_code,
                    $checker,
                );
                $this->payCodes->handle((string) $authorization->reference, $maker, 1);
            }

            if ($phase === 'cancel') {
                $payCode = $authorization?->fulfillments()->value('pay_code');

                if (! is_string($payCode) || trim($payCode) === '') {
                    throw new RuntimeException('The Commercial Pay Code must be issued before it can be cancelled.');
                }

                $this->voucherLifecycle->cancel($payCode, [
                    'reason' => 'Controlled Commercial Principal lifecycle scenario',
                ]);
            }

            return new ScenarioRunResult(
                exitCode: Command::SUCCESS,
                payload: $this->payload(
                    $worksheet->refresh(),
                    $authorization?->refresh(),
                    $principal,
                    $maker,
                    $checker,
                ),
            );
        } catch (RuntimeException $exception) {
            return new ScenarioRunResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'scenario' => $context->scenarioKey,
                    'mode' => 'commercial_pay_code',
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    private function fundCommercialPrincipal(
        CommercialPrincipal $principal,
        Model $maker,
        Model $checker,
        string $runReference,
    ): TreasuryAccountGrant {
        return DB::transaction(
            fn (): TreasuryAccountGrant => $this->fundCommercialPrincipalInsideTransaction(
                $principal,
                $maker,
                $checker,
                $runReference,
            ),
            attempts: 5,
        );
    }

    private function fundCommercialPrincipalInsideTransaction(
        CommercialPrincipal $principal,
        Model $maker,
        Model $checker,
        string $runReference,
    ): TreasuryAccountGrant {
        if (
            ! app()->environment(['local', 'testing'])
            || ! (bool) config('x-change.lifecycle.commercial_pay_code.simulated_funding_enabled', false)
        ) {
            throw new RuntimeException('Commercial Principal simulated funding is disabled outside local and testing.');
        }

        $system = $this->systemUsers->resolve();

        if (! $system instanceof Model || $system->is($maker) || $system->is($checker)) {
            throw new RuntimeException('The System Principal, Maker, and Checker must be distinct identities.');
        }

        $connection = collect($this->connections->active(['netbank-primary']))->sole();
        $scope = substr(hash('sha256', $runReference), 0, 24);
        $amountMinor = 10_000;
        $systemPortfolio = $this->systemPositions->provision([$connection->reference]);
        $clearing = $this->position($systemPortfolio->positions, TreasuryPositionPurpose::TreasuryClearing);
        $institutionOwned = $this->position(
            $systemPortfolio->positions,
            TreasuryPositionPurpose::InstitutionOwnedFunds,
        );

        $this->inventoryRegistration->ensure(new TreasuryInventoryData(
            inventoryReference: $connection->inventoryReference,
            resourceType: $connection->settlementResourceType,
            currency: $connection->currency,
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'commercial-pay-code-simulation-inventory:'.$scope,
            externalReference: $connection->settlementResourceReference,
        ));
        $this->inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
            operationReference: 'commercial-pay-code-simulation-inventory-recognition:'.$scope,
            inventoryReference: $connection->inventoryReference,
            settlementResourceReference: $connection->settlementResourceReference,
            amountMinor: $amountMinor,
            currency: $connection->currency,
            status: 'requested',
            idempotencyKey: 'commercial-pay-code-simulation-inventory-recognition-key:'.$scope,
            externalReference: 'simulation-evidence:'.$scope,
            metadata: [
                'simulation_only' => true,
                'scenario' => 'commercial_pay_code_issuance',
                'run_reference' => $runReference,
            ],
        ));
        $recognition = $this->positionOperations->recognize(new TreasuryPositionRecognitionData(
            operationReference: 'commercial-pay-code-simulation-position-recognition:'.$scope,
            destinationPositionReference: $clearing->positionReference,
            amountMinor: $amountMinor,
            currency: $connection->currency,
            idempotencyKey: 'commercial-pay-code-simulation-position-recognition-key:'.$scope,
            externalReference: 'simulation-evidence:'.$scope,
            metadata: ['simulation_only' => true, 'run_reference' => $runReference],
        ));
        $this->positionOperations->allocate(new TreasuryPositionAllocationData(
            operationReference: 'commercial-pay-code-simulation-capitalization:'.$scope,
            sourcePositionReference: $clearing->positionReference,
            destinationPositionReference: $institutionOwned->positionReference,
            amountMinor: $amountMinor,
            currency: $connection->currency,
            idempotencyKey: 'commercial-pay-code-simulation-capitalization-key:'.$scope,
            externalReference: $recognition->operationReference,
            metadata: ['simulation_only' => true, 'run_reference' => $runReference],
        ));

        $authorizations = $this->temporaryTreasuryAuthority($maker, $checker, $scope);

        try {
            $grant = $this->requestGrant->handle(
                recipient: $principal,
                amountMinor: $amountMinor,
                currency: $connection->currency,
                connectionReference: $connection->reference,
                purpose: 'Local Commercial Principal Pay Code lifecycle simulation',
                idempotencyReference: 'commercial-pay-code-simulation-grant:'.$scope,
                maker: $maker,
            );
            $this->approveGrant->handle($grant, $checker);

            return $this->executeGrant->handle($grant, $checker);
        } finally {
            TreasuryOperatorAuthorization::query()
                ->whereKey($authorizations)
                ->update(['revoked_at' => now()]);
        }
    }

    /** @return list<int> */
    private function temporaryTreasuryAuthority(Model $maker, Model $checker, string $scope): array
    {
        $authorizations = collect([
            [$maker, TreasuryOperatorCapability::RequestAccountGrants],
            [$checker, TreasuryOperatorCapability::ApproveAccountGrants],
            [$checker, TreasuryOperatorCapability::ExecuteAccountGrants],
        ])->map(function (array $grant) use ($scope): TreasuryOperatorAuthorization {
            [$operator, $capability] = $grant;

            return TreasuryOperatorAuthorization::query()->create([
                'operator_type' => $operator->getMorphClass(),
                'operator_id' => $operator->getKey(),
                'capability' => $capability->value,
                'authorization_reference' => implode(':', [
                    'lifecycle-simulation',
                    $scope,
                    str_replace('.', '-', $capability->value),
                    (string) Str::ulid(),
                ]),
                'valid_from' => now()->subMinute(),
                'valid_until' => now()->addMinutes(5),
            ]);
        });

        return $authorizations
            ->map(static fn (TreasuryOperatorAuthorization $authorization): int => (int) $authorization->getKey())
            ->all();
    }

    /** @param list<TreasuryPositionData> $positions */
    private function position(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException("Exactly one {$purpose->value} Position is required.");
        }

        return $matches[0];
    }

    private function prepareWorksheet(
        Model $maker,
        Model $checker,
        CommercialPrincipal $principal,
        string $runReference,
        int $amountMinor,
        TreasuryAccountGrant $fundingGrant,
    ): CampaignWorksheet {
        $ownerType = $maker->getMorphClass();
        $ownerId = (string) $maker->getKey();
        $facts = [
            'principal_reference' => (string) $principal->reference,
            'maker_type' => $ownerType,
            'maker_id' => $ownerId,
            'checker_type' => $checker->getMorphClass(),
            'checker_id' => (string) $checker->getKey(),
            'run_reference' => $runReference,
            'principal_minor' => $amountMinor,
            'currency' => 'PHP',
        ];
        $sponsorship = [
            'schema' => 'x-change.commercial-pay-code-sponsorship.v1',
            'legal_name' => (string) $principal->legal_name,
            'issued_by' => ['type' => $ownerType, 'id' => $ownerId],
            'approved_by' => [
                'type' => $checker->getMorphClass(),
                'id' => (string) $checker->getKey(),
            ],
            'commercial_principal' => (string) $principal->reference,
            'funded_by' => (string) $principal->reference,
            ...$facts,
            'instruction_hash' => hash(
                'sha256',
                json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ),
        ];
        $worksheetData = $this->worksheets->put(new CampaignWorksheetData(
            reference: null,
            ownerType: $ownerType,
            ownerId: $ownerId,
            profile: 'assistance',
            name: 'Commercial ₱'.number_format($amountMinor / 100, 2).' Pay Code',
            fulfillmentMode: 'pay_code_distribution',
            deliveryPlan: ['manual'],
            metadata: [
                'lifecycle' => [
                    'schema' => 'x-change.commercial-pay-code-browser-runner.v1',
                    'scenario' => 'commercial_pay_code_issuance',
                    'run_reference' => $runReference,
                    'automatic_fulfillment' => false,
                    'maker_type' => $ownerType,
                    'maker_id' => $ownerId,
                    'checker_type' => $checker->getMorphClass(),
                    'checker_id' => (string) $checker->getKey(),
                    'funding_grant_reference' => (string) $fundingGrant->reference,
                ],
            ],
        ));
        $this->worksheets->appendRows(
            $worksheetData->reference,
            $ownerType,
            $ownerId,
            [new CampaignWorksheetRowData(
                reference: null,
                ordinal: 0,
                beneficiary: ['name' => 'Commercial Pay Code holder'],
                amountMinor: $amountMinor,
                currency: 'PHP',
                deliveryPreference: 'manual',
            )],
        );
        $this->worksheets->updateInstructionBlueprint(
            $worksheetData->reference,
            $ownerType,
            $ownerId,
            [
                'rider' => ['message' => 'Commercial Pay Code'],
                'feedback' => ['channels' => []],
                'claim' => ['onboarding' => ['mode' => 'if_required']],
                'expiry_days' => 7,
                'commercial_sponsorship' => $sponsorship,
            ],
            'x-campaign.instruction-blueprint.v1',
            0,
        );
        $this->worksheets->freeze($worksheetData->reference, $ownerType, $ownerId);
        $this->approvalPayCodes->handle($worksheetData->reference, $maker);

        return CampaignWorksheet::query()
            ->with(['rows', 'authorizations.fulfillments'])
            ->where('reference', $worksheetData->reference)
            ->firstOrFail();
    }

    private function worksheet(Model $maker, string $runReference): ?CampaignWorksheet
    {
        return CampaignWorksheet::query()
            ->with(['rows', 'authorizations.fulfillments'])
            ->where('owner_type', $maker->getMorphClass())
            ->where('owner_id', (string) $maker->getKey())
            ->where('metadata->lifecycle->run_reference', $runReference)
            ->first();
    }

    private function assertReplayMatches(
        CampaignWorksheet $worksheet,
        Model $checker,
        CommercialPrincipal $principal,
        int $amountMinor,
    ): void {
        $sponsorship = (array) data_get(
            $worksheet->instruction_blueprint_ciphertext,
            'commercial_sponsorship',
            [],
        );

        if (
            data_get($worksheet->metadata, 'lifecycle.scenario') !== 'commercial_pay_code_issuance'
            || data_get($sponsorship, 'principal_reference') !== $principal->reference
            || data_get($sponsorship, 'checker_type') !== $checker->getMorphClass()
            || (string) data_get($sponsorship, 'checker_id') !== (string) $checker->getKey()
            || (int) data_get($sponsorship, 'principal_minor') !== $amountMinor
        ) {
            throw new RuntimeException('The lifecycle run reference is already bound to a different Commercial Pay Code instruction.');
        }
    }

    private function assertCommercialAuthority(Model $maker, Model $checker): void
    {
        if (! $this->commercialAuthority->allows($maker, CommercialOperatorCapability::PreparePayCodes)) {
            throw new RuntimeException('The selected maker cannot prepare Commercial Pay Codes.');
        }

        if (! $this->commercialAuthority->allows($checker, CommercialOperatorCapability::ApprovePayCodes)) {
            throw new RuntimeException('The selected checker cannot approve Commercial Pay Codes.');
        }
    }

    private function checker(ScenarioRunContext $context, Model $maker): Model
    {
        $checkerId = $this->requiredRuntimeString($context, 'checker');

        if (! ctype_digit($checkerId) || (int) $checkerId < 1) {
            throw new RuntimeException('Commercial Pay Code checker must be a persisted user id.');
        }

        $checker = $this->bootstrapper->resolveIssuerModel((int) $checkerId);

        if ($checker->getMorphClass() === $maker->getMorphClass()
            && (string) $checker->getKey() === (string) $maker->getKey()) {
            throw new RuntimeException('Commercial Pay Code maker and checker must be different users.');
        }

        return $checker;
    }

    private function commercialPrincipal(): CommercialPrincipal
    {
        $principal = CommercialPrincipal::query()
            ->where('reference', (string) config('x-change.commercial.principal.reference'))
            ->where('active', true)
            ->first();

        if (! $principal instanceof CommercialPrincipal) {
            throw new RuntimeException('The commissioned Commercial Principal is unavailable.');
        }

        return $principal;
    }

    private function amountMinor(ScenarioRunContext $context): int
    {
        $runtimeAmount = data_get($context->scenario, '_runtime.amount');
        $amount = $runtimeAmount === null || $runtimeAmount === ''
            ? 50.0
            : (float) $runtimeAmount;
        $amountMinor = (int) round($amount * 100);

        if ($amountMinor !== 5_000) {
            throw new RuntimeException('This controlled browser scenario requires an exact ₱50.00 Pay Code.');
        }

        return $amountMinor;
    }

    private function phase(ScenarioRunContext $context): string
    {
        $phase = trim((string) data_get($context->scenario, '_runtime.phase', 'prepare'));

        if (! in_array($phase, ['prepare', 'approve', 'cancel', 'status'], true)) {
            throw new RuntimeException('Commercial Pay Code phase must be prepare, approve, cancel, or status.');
        }

        return $phase;
    }

    /** @return array<string, mixed> */
    private function payload(
        CampaignWorksheet $worksheet,
        ?CampaignWorksheetAuthorization $authorization,
        CommercialPrincipal $principal,
        Model $maker,
        Model $checker,
    ): array {
        $fulfillment = $authorization?->fulfillments()->with('row')->first();
        $voucher = is_string($fulfillment?->pay_code)
            ? Voucher::query()->where('code', $fulfillment->pay_code)->first()
            : null;
        $phase = data_get($voucher?->metadata, 'treasury.terminal_release.terminal_reason') === 'cancelled'
            ? 'cancelled'
            : ($fulfillment?->pay_code !== null ? 'issued' : 'awaiting_checker');

        return [
            'success' => true,
            'scenario' => 'commercial_pay_code_issuance',
            'mode' => 'commercial_pay_code',
            'phase' => $phase,
            'commercial_principal' => [
                'reference' => (string) $principal->reference,
                'legal_name' => (string) $principal->legal_name,
            ],
            'maker' => ['type' => $maker->getMorphClass(), 'id' => (string) $maker->getKey()],
            'checker' => ['type' => $checker->getMorphClass(), 'id' => (string) $checker->getKey()],
            'amount_minor' => 5_000,
            'currency' => 'PHP',
            'worksheet_reference' => (string) $worksheet->reference,
            'authorization_reference' => $authorization?->reference,
            'approval_pay_code' => $authorization?->approval_pay_code,
            'funding_source' => 'commercial_principal_client_funds',
            'funding_grant_reference' => data_get(
                $worksheet->metadata,
                'lifecycle.funding_grant_reference',
            ),
            'balances' => $this->principalBalances($principal),
            'pay_code' => $fulfillment?->pay_code,
            'provider_calls' => 0,
            'external_money_moved' => false,
            'next_action' => match ($phase) {
                'cancelled' => 'The ₱50.00 reserve was returned to the Commercial Principal’s Client Funds.',
                'issued' => 'The ₱50.00 Pay Code is issued from the Commercial Principal’s Client Funds. Cancellation returns the reserve to that Principal.',
                default => 'The independent checker must approve the frozen instruction before funds are allocated or a Pay Code is issued.',
            },
        ];
    }

    /** @return array<string, int> */
    private function principalBalances(CommercialPrincipal $principal): array
    {
        $positions = $this->accountPortfolios
            ->provision($principal, ['netbank-primary'])
            ->positions;
        $balances = collect($positions)->mapWithKeys(
            static fn (TreasuryPositionData $position): array => [
                $position->purpose->value => $position->balanceMinor,
            ],
        );
        $revenueAccount = $principal->wallets()
            ->where('slug', (string) config('x-change.commercial.principal.revenue_account_slug'))
            ->first();

        return [
            'commercial_client_funds_minor' => (int) $balances->get(
                TreasuryPositionPurpose::ClientFunds->value,
                0,
            ),
            'commercial_pay_code_reserve_minor' => (int) $balances->get(
                TreasuryPositionPurpose::PayCodeReserve->value,
                0,
            ),
            'commercial_revenue_minor' => (int) data_get($revenueAccount, 'balanceInt', 0),
        ];
    }

    private function requiredRuntimeString(ScenarioRunContext $context, string $key): string
    {
        $value = data_get($context->scenario, '_runtime.'.$key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('Commercial Pay Code lifecycle requires '.$key.'.');
        }

        return trim($value);
    }
}
