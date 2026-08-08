<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Commercial\ApprovePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Actions\Commercial\PostCommercialSale;
use LBHurtado\XChange\Actions\Commercial\ReconcilePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Actions\Commercial\RecordProviderCostBatch;
use LBHurtado\XChange\Actions\Commercial\RequestPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Actions\Commercial\ReverseCommercialSale;
use LBHurtado\XChange\Actions\Commercial\SubmitPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Data\Commercial\PartnerCommissionPayoutBatchRequestData;
use LBHurtado\XChange\Data\Commercial\ProviderCostBatchEvidenceData;
use LBHurtado\XChange\Data\Disbursement\PayoutDestinationValidationData;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Enums\CommercialPartnerStatus;
use LBHurtado\XChange\Enums\CommercialProviderCostBatchStatus;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Models\CommercialProviderCostBatchLine;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatchLine;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\Commercial\CommercialControlReadModel;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XCommerce\Data\CommercialAttributionSnapshotData;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialCatalogItemData;
use LBHurtado\XCommerce\Data\CommercialQuoteLineInputData;
use LBHurtado\XCommerce\Data\CommercialSaleSnapshotData;
use LBHurtado\XCommerce\Data\CommercialWaterfallPolicyData;
use LBHurtado\XCommerce\Data\CommercialWaterfallRuleData;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;
use LBHurtado\XCommerce\Services\DeterministicCommercialQuoteBuilder;
use LBHurtado\XCommerce\Services\DeterministicCommercialSaleFactory;
use LBHurtado\XCommerce\Services\DeterministicCommercialWaterfallCalculator;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

it('snapshots governed partner provenance on commission allocations', function (): void {
    $operator = actingAsTestUser();
    $partner = CommercialPartner::query()->create([
        'reference' => 'recipient:partner',
        'display_name' => 'Posting Partner',
        'status' => CommercialPartnerStatus::Active,
        'created_by_type' => $operator->getMorphClass(),
        'created_by_id' => $operator->getKey(),
        'activated_at' => now(),
    ]);
    $revision = CommercialPartnerRevision::query()->create([
        'commercial_partner_id' => $partner->getKey(),
        'version' => 1,
        'status' => CommercialPartnerRevisionStatus::Approved,
        'display_name' => 'Posting Partner',
        'attribution_basis' => 'contractual_referral',
        'authorization_reference' => 'contract:posting-partner',
        'terms' => ['commission_basis' => 'fixed'],
        'snapshot_hash' => str_repeat('c', 64),
        'maker_type' => $operator->getMorphClass(),
        'maker_id' => $operator->getKey(),
        'approved_at' => now(),
        'effective_at' => now(),
    ]);
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'governed-partner-posting');

    $sale = app(PostCommercialSale::class)->execute(
        commercialSaleSnapshot('acceptance:governed-partner-posting'),
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        commercialSaleDestinations($positions),
    );
    $allocation = $sale->allocations->firstWhere('category', 'partner_commission');

    expect($allocation->commercial_partner_id)->toBe($partner->getKey())
        ->and($allocation->commercial_partner_revision_id)->toBe($revision->getKey())
        ->and($allocation->legacy_partner_reference)->toBeNull()
        ->and(data_get($sale->snapshot, 'partner_governance.rule:partner.status'))->toBe('governed')
        ->and(data_get($sale->snapshot, 'partner_governance.rule:partner.authorization_reference'))
        ->toBe('contract:posting-partner');
});

it('posts and reverses an immutable commercial sale exactly once', function () {
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'posting');
    $snapshot = commercialSaleSnapshot('acceptance:posting');
    $destinations = commercialSaleDestinations($positions);
    $posting = app(PostCommercialSale::class);

    $first = $posting->execute(
        $snapshot,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        $destinations,
    );
    $operationCount = TreasuryPositionOperation::query()->count();
    $replay = $posting->execute(
        $snapshot,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        $destinations,
    );
    config()->set('x-change.treasury.connections.netbank-primary.mode', 'required');
    app()->forgetInstance(TreasuryProviderConnectionCatalog::class);
    $controls = app(CommercialControlReadModel::class)->build(
        app(BootstrapCommercialOfferingFactory::class)->make('pay_code'),
    );
    $positionBalances = collect($controls['position_balances'])->keyBy('purpose');

    expect($first->status)->toBe('posted')
        ->and($replay->getKey())->toBe($first->getKey())
        ->and($replay->allocations)->toHaveCount(4)
        ->and(TreasuryPositionOperation::query()->count())->toBe($operationCount)
        ->and(CommercialSale::query()->count())->toBe(1)
        ->and(CommercialAllocation::query()->count())->toBe(4)
        ->and(commercialSalePositionBalance($positions['client_funds']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['commercial_clearing']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['provider_cost']))->toBe(10_00)
        ->and(commercialSalePositionBalance($positions['product_revenue']))->toBe(8_00)
        ->and(commercialSalePositionBalance($positions['partner_commission']))->toBe(2_00)
        ->and(commercialSalePositionBalance($positions['commercial_revenue']))->toBe(5_00)
        ->and($positionBalances->get('provider_cost_payable'))->toMatchArray([
            'current_minor' => 10_00,
            'lifetime_allocated_minor' => 10_00,
            'settled_minor' => 0,
            'remaining_minor' => 10_00,
            'reconciled' => true,
        ])
        ->and($positionBalances->get('product_revenue'))->toMatchArray([
            'current_minor' => 8_00,
            'lifetime_allocated_minor' => 8_00,
            'settled_minor' => 0,
            'remaining_minor' => 8_00,
            'reconciled' => true,
        ])
        ->and($positionBalances->get('partner_commission_payable'))->toMatchArray([
            'current_minor' => 2_00,
            'lifetime_allocated_minor' => 2_00,
            'settled_minor' => 0,
            'remaining_minor' => 2_00,
            'reconciled' => true,
        ])
        ->and($positionBalances->get('commercial_revenue'))->toMatchArray([
            'current_minor' => 5_00,
            'lifetime_allocated_minor' => 5_00,
            'settled_minor' => 0,
            'remaining_minor' => 5_00,
            'reconciled' => true,
        ])
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', 'commercial-sale:'.$snapshot->reference)
            ->count())->toBe(6);

    $reversal = app(ReverseCommercialSale::class);
    $reversed = $reversal->execute($snapshot->reference, 'administrative-void:posting');
    $reversalOperationCount = TreasuryPositionOperation::query()->count();
    $replayedReversal = $reversal->execute($snapshot->reference, 'administrative-void:posting');
    $reversedControls = app(CommercialControlReadModel::class)->build(
        app(BootstrapCommercialOfferingFactory::class)->make('pay_code'),
    );

    expect($reversed->status)->toBe('reversed')
        ->and($replayedReversal->getKey())->toBe($reversed->getKey())
        ->and(TreasuryPositionOperation::query()->count())->toBe($reversalOperationCount)
        ->and(commercialSalePositionBalance($positions['client_funds']))->toBe(25_00)
        ->and(commercialSalePositionBalance($positions['commercial_clearing']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['provider_cost']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['product_revenue']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['partner_commission']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['commercial_revenue']))->toBe(0)
        ->and(collect($reversedControls['position_balances'])->every(
            fn (array $balance): bool => $balance['current_minor'] === 0
                && $balance['lifetime_allocated_minor'] === 0
                && $balance['reconciled'] === true,
        ))->toBeTrue()
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', 'commercial-sale:'.$snapshot->reference)
            ->pluck('event_type')
            ->all())->toBe([
                'commercial.sale.accepted',
                'commercial.charge.posted',
                'commercial.allocation.posted',
                'commercial.allocation.posted',
                'commercial.allocation.posted',
                'commercial.allocation.posted',
                'commercial.sale.reversed',
            ]);
});

it('records aggregate provider cost evidence without settling a variance', function (): void {
    $systemPrincipal = actingAsTestUser();
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $systemPrincipal->email,
            'identifier_column' => 'email',
        ],
    ]);
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::ReconcileProviderCosts->value,
        'authorization_reference' => 'test:provider-cost-operator',
        'valid_from' => now()->subMinute(),
    ]);
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'provider-cost-batch');
    $sale = app(PostCommercialSale::class)->execute(
        commercialSaleSnapshot('acceptance:provider-cost-batch'),
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        commercialSaleDestinations($positions),
    );
    $snapshot = $sale->snapshot;
    data_set($snapshot, 'accounting_context', [
        'schema_version' => 2,
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'currency' => 'PHP',
    ]);
    DB::table('x_change_commercial_sales')
        ->where('id', $sale->getKey())
        ->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
    $evidence = new ProviderCostBatchEvidenceData(
        reference: 'provider-cost-batch:2026-07-25',
        provider: 'netbank',
        connectionReference: 'netbank-primary',
        currency: 'PHP',
        evidenceType: 'provider_statement',
        evidenceReference: 'netbank:statement:2026-07-25',
        observedAmountMinor: 9_00,
        periodStartedAt: '2026-07-25T00:00:00+08:00',
        periodEndedAt: '2026-07-25T23:59:59+08:00',
        observedAt: '2026-07-26T08:00:00+08:00',
        idempotencyKey: 'provider-cost-batch:2026-07-25',
    );

    $batch = app(RecordProviderCostBatch::class)->execute($operator, $evidence);
    $replay = app(RecordProviderCostBatch::class)->execute($operator, $evidence);

    expect($batch->status)->toBe(CommercialProviderCostBatchStatus::ReviewRequired)
        ->and($batch->expected_amount_minor)->toBe(10_00)
        ->and($batch->variance_amount_minor)->toBe(-1_00)
        ->and($batch->metadata['candidate_allocation_ids'])->toBe([
            $sale->allocations()->where('category', 'provider_cost')->sole()->getKey(),
        ])
        ->and($replay->getKey())->toBe($batch->getKey())
        ->and(CommercialProviderCostBatchLine::query()->count())->toBe(0);
});

it('aggregates approves submits and authoritatively settles partner commissions', function (): void {
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = actingAsTestUser();
    $executor = actingAsTestUser();

    foreach ([
        [$maker, CommercialOperatorCapability::RequestCommissionPayouts],
        [$checker, CommercialOperatorCapability::ApproveCommissionPayouts],
        [$executor, CommercialOperatorCapability::ExecuteCommissionPayouts],
    ] as [$operator, $capability]) {
        CommercialOperatorAuthorization::query()->create([
            'operator_type' => $operator->getMorphClass(),
            'operator_id' => $operator->getKey(),
            'capability' => $capability->value,
            'authorization_reference' => 'test:'.$capability->value,
            'valid_from' => now()->subMinute(),
        ]);
    }

    app()->instance(PayoutDestinationValidatorContract::class, new class implements PayoutDestinationValidatorContract
    {
        public function validate(
            string $bankCode,
            string $accountNumber,
            string $settlementRail,
            ?string $mobile = null,
        ): PayoutDestinationValidationData {
            return new PayoutDestinationValidationData(
                status: 'format_valid_provider_unverified',
                bankCode: $bankCode,
                accountNumber: $accountNumber,
                mobile: $mobile,
                message: 'Format valid.',
                providerVerified: false,
                checks: ['account_format' => 'valid'],
            );
        }
    });
    $partner = CommercialPartner::query()->create([
        'reference' => 'recipient:partner',
        'display_name' => 'Test Partner',
        'status' => CommercialPartnerStatus::Active,
        'created_by_type' => $maker->getMorphClass(),
        'created_by_id' => $maker->getKey(),
        'activated_at' => now(),
    ]);
    $partnerRevision = CommercialPartnerRevision::query()->create([
        'commercial_partner_id' => $partner->getKey(),
        'version' => 1,
        'status' => CommercialPartnerRevisionStatus::Approved,
        'display_name' => 'Test Partner',
        'attribution_basis' => 'contractual_referral',
        'authorization_reference' => 'contract:test-partner',
        'terms' => ['commission_basis' => 'fixed'],
        'snapshot_hash' => str_repeat('d', 64),
        'maker_type' => $maker->getMorphClass(),
        'maker_id' => $maker->getKey(),
        'approved_at' => now(),
        'effective_at' => now(),
    ]);
    CommercialPartnerDestinationRevision::query()->create([
        'commercial_partner_id' => $partner->getKey(),
        'commercial_partner_revision_id' => $partnerRevision->getKey(),
        'version' => 1,
        'status' => CommercialPartnerRevisionStatus::Approved,
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'currency' => 'PHP',
        'destination' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
            'recipient_name' => 'Test Partner',
            'mobile' => '09171234567',
        ],
        'destination_hash' => str_repeat('e', 64),
        'destination_summary' => 'GCash · ••••4567',
        'maker_type' => $maker->getMorphClass(),
        'maker_id' => $maker->getKey(),
        'authorization_reference' => 'board:test-partner-destination',
        'approved_at' => now(),
        'effective_at' => now(),
    ]);
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 50_00, 'commission-batch');

    foreach ([1, 2] as $index) {
        $sale = app(PostCommercialSale::class)->execute(
            commercialSaleSnapshot('acceptance:commission-batch:'.$index),
            $positions['client_funds']->position_reference,
            $positions['commercial_clearing']->position_reference,
            commercialSaleDestinations($positions),
        );
        $snapshot = $sale->snapshot;
        data_set($snapshot, 'accounting_context', [
            'schema_version' => 2,
            'partner_reference' => 'partner:test',
            'provider' => 'netbank',
            'connection_reference' => 'netbank-primary',
            'currency' => 'PHP',
        ]);
        DB::table('x_change_commercial_sales')
            ->where('id', $sale->getKey())
            ->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
    }

    $request = new PartnerCommissionPayoutBatchRequestData(
        reference: 'commission-payout:partner-test:2026-07',
        partnerReference: 'recipient:partner',
        provider: 'netbank',
        connectionReference: 'netbank-primary',
        currency: 'PHP',
        periodStartedAt: '2026-07-25T00:00:00+08:00',
        periodEndedAt: '2026-07-25T23:59:59+08:00',
        idempotencyKey: 'commission-payout:partner-test:2026-07',
    );
    $batch = app(RequestPartnerCommissionPayoutBatch::class)->execute($maker, $request);

    expect($batch->amount_minor)->toBe(4_00)
        ->and($batch->status)->toBe(PartnerCommissionPayoutBatchStatus::AwaitingApproval)
        ->and($batch->lines)->toHaveCount(2)
        ->and(PartnerCommissionPayoutBatchLine::query()->sum('amount_minor'))->toBe(4_00)
        ->and(fn () => app(ApprovePartnerCommissionPayoutBatch::class)->execute(
            $maker,
            $batch,
            'approval:invalid-maker',
        ))->toThrow(AuthorizationException::class);

    expect(fn () => app(ReverseCommercialSale::class)->execute(
        CommercialSale::query()->oldest('id')->value('reference'),
        'administrative-void:commission-batch-started',
    ))->toThrow(CommercialSaleConflict::class, 'partner payout control begins');

    $approved = app(ApprovePartnerCommissionPayoutBatch::class)->execute(
        $checker,
        $batch,
        'approval:commission-payout:partner-test:2026-07',
    );

    expect($approved->status)->toBe(PartnerCommissionPayoutBatchStatus::Approved)
        ->and(fn () => app(SubmitPartnerCommissionPayoutBatch::class)->execute(
            $executor,
            $approved,
            'submission:commission-payout:partner-test:2026-07',
        ))->toThrow(CommercialSaleConflict::class, 'provider calls are disabled');

    recognizeCommercialInventoryForTest(50_00, 'commission-batch');
    config()->set('x-change.commercial.operations.live_provider_calls_enabled', true);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult('NETBANK-COMMISSION-001');
    $pending = app(SubmitPartnerCommissionPayoutBatch::class)->execute(
        $executor,
        $approved,
        'submission:commission-payout:partner-test:2026-07',
    );
    $inventoryBefore = DB::table('treasury_inventories')->sum('balance_minor');
    $settled = app(ReconcilePartnerCommissionPayoutBatch::class)->execute($executor, $pending);

    expect($pending->status)->toBe(PartnerCommissionPayoutBatchStatus::Pending)
        ->and($settled->status)->toBe(PartnerCommissionPayoutBatchStatus::Settled)
        ->and($settled->position_operation_reference)->not->toBeNull()
        ->and($settled->inventory_operation_reference)->not->toBeNull()
        ->and(commercialSalePositionBalance($positions['partner_commission']))->toBe(0)
        ->and((int) DB::table('treasury_inventories')->sum('balance_minor'))->toBe($inventoryBefore - 4_00)
        ->and($provider->disburseCallCount)->toBe(1)
        ->and($provider->checkStatusCallCount)->toBe(1)
        ->and($settled->attempts()->count())->toBe(1)
        ->and($settled->attempts()->sole()->status->value)->toBe('settled')
        ->and($settled->attempts()->sole()->commercial_partner_destination_revision_id)->not->toBeNull();

    expect(Artisan::call('x-change:treasury:attest-commercial-accounting', [
        '--connection' => ['netbank-primary'],
        '--json' => true,
    ]))->toBe(0)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['ready'])->toBeTrue();
});

it('rejects unsupported commercial reversal reasons', function () {
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'unsupported-reversal');
    $snapshot = commercialSaleSnapshot('acceptance:unsupported-reversal');

    app(PostCommercialSale::class)->execute(
        $snapshot,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        commercialSaleDestinations($positions),
    );

    expect(fn () => app(ReverseCommercialSale::class)->execute(
        $snapshot->reference,
        'commercial-refund:unsupported',
    ))->toThrow(CommercialSaleConflict::class, 'failed-issuance or administrative-void')
        ->and(CommercialSale::query()->sole()->status)->toBe('posted');
});

it('does not reverse commercial revenue after a provider cost was settled', function () {
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'settled-cost-reversal');
    $snapshot = commercialSaleSnapshot('acceptance:settled-cost-reversal');
    $sale = app(PostCommercialSale::class)->execute(
        $snapshot,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        commercialSaleDestinations($positions),
    );
    $allocation = $sale->allocations()->where('category', 'provider_cost')->sole();

    CommercialProviderCostSettlement::query()->create([
        'commercial_sale_id' => $sale->getKey(),
        'commercial_allocation_id' => $allocation->getKey(),
        'idempotency_key' => 'provider-cost:settled-reversal-guard',
        'request_hash' => str_repeat('a', 64),
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'evidence_type' => 'account_debit',
        'evidence_reference' => 'netbank:settled-reversal-guard',
        'cash_movement_observed' => true,
        'expected_amount_minor' => $allocation->amount_minor,
        'observed_amount_minor' => $allocation->amount_minor,
        'variance_amount_minor' => 0,
        'currency' => 'PHP',
        'status' => 'settled',
        'metadata' => [],
        'observed_at' => now(),
        'settled_at' => now(),
    ]);

    expect(fn () => app(ReverseCommercialSale::class)->execute(
        $snapshot->reference,
        'administrative-void:settled-cost',
    ))->toThrow(CommercialSaleConflict::class, 'after provider cost settlement')
        ->and(commercialSalePositionBalance($positions['product_revenue']))->toBe(8_00)
        ->and(commercialSalePositionBalance($positions['commercial_revenue']))->toBe(5_00);
});

it('rolls the whole sale back when a waterfall destination is unavailable', function () {
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'rollback');
    $snapshot = commercialSaleSnapshot('acceptance:rollback');
    $destinations = commercialSaleDestinations($positions);
    unset($destinations['rule:partner']);
    $operationCount = TreasuryPositionOperation::query()->count();

    expect(fn () => app(PostCommercialSale::class)->execute(
        $snapshot,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        $destinations,
    ))->toThrow(CommercialSaleConflict::class, 'rule:partner');

    expect(CommercialSale::query()->count())->toBe(0)
        ->and(CommercialAllocation::query()->count())->toBe(0)
        ->and(TreasuryPositionOperation::query()->count())->toBe($operationCount)
        ->and(commercialSalePositionBalance($positions['client_funds']))->toBe(25_00);
});

it('rolls accounting back when its journal cannot be persisted', function () {
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'journal-rollback');
    $snapshot = commercialSaleSnapshot('acceptance:journal-rollback');
    $recorder = Mockery::mock(ExecutionJournalRecorder::class);
    $recorder->shouldReceive('record')
        ->once()
        ->andThrow(new RuntimeException('journal unavailable'));
    app()->instance(ExecutionJournalRecorder::class, $recorder);

    expect(fn () => app(PostCommercialSale::class)->execute(
        $snapshot,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        commercialSaleDestinations($positions),
    ))->toThrow(RuntimeException::class, 'journal unavailable')
        ->and(CommercialSale::query()->count())->toBe(0)
        ->and(CommercialAllocation::query()->count())->toBe(0)
        ->and(commercialSalePositionBalance($positions['client_funds']))->toBe(25_00)
        ->and(commercialSalePositionBalance($positions['commercial_clearing']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['provider_cost']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['product_revenue']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['partner_commission']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['commercial_revenue']))->toBe(0);
});

it('rejects a changed sale snapshot under the same acceptance event', function () {
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 50_00, 'conflict');
    $first = commercialSaleSnapshot('acceptance:conflict', '2026-07-25T10:00:00+08:00');
    $changed = commercialSaleSnapshot('acceptance:conflict', '2026-07-25T10:01:00+08:00');
    $destinations = commercialSaleDestinations($positions);
    $posting = app(PostCommercialSale::class);

    $posting->execute(
        $first,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        $destinations,
    );

    expect(fn () => $posting->execute(
        $changed,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        $destinations,
    ))->toThrow(CommercialSaleConflict::class, 'different immutable sale snapshot')
        ->and(CommercialSale::query()->count())->toBe(1);
});

it('previews and guardedly backfills only reconstructible commercial journal events', function () {
    $positions = commercialSalePositions();
    fundCommercialClientPosition($positions, 25_00, 'journal-backfill');
    $snapshot = commercialSaleSnapshot('acceptance:journal-backfill');
    $sale = app(PostCommercialSale::class)->execute(
        $snapshot,
        $positions['client_funds']->position_reference,
        $positions['commercial_clearing']->position_reference,
        commercialSaleDestinations($positions),
    );
    DB::table('execution_journal_entries')
        ->where('correlation_id', 'commercial-sale:'.$sale->reference)
        ->delete();

    expect(Artisan::call(
        'x-change:treasury:backfill-commercial-accounting-journal',
        ['--sale' => [$sale->reference], '--json' => true],
    ))->toBe(0);
    $preview = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($preview['mode'])->toBe('preview')
        ->and($preview['sales'][0]['journal_complete'])->toBeFalse()
        ->and($preview['sales'][0]['can_backfill'])->toBeTrue()
        ->and($preview['sales'][0]['raw_provider_evidence_inferred'])->toBeFalse()
        ->and(ExecutionJournalEntry::query()->count())->toBe(0)
        ->and(Artisan::call(
            'x-change:treasury:backfill-commercial-accounting-journal',
            [
                '--sale' => [$sale->reference],
                '--commit' => true,
                '--json' => true,
            ],
        ))->toBe(1);

    expect(Artisan::call(
        'x-change:treasury:backfill-commercial-accounting-journal',
        [
            '--sale' => [$sale->reference],
            '--commit' => true,
            '--authorization-reference' => 'control:journal-backfill-2026-001',
            '--json' => true,
        ],
    ))->toBe(0);
    $committed = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($committed['mode'])->toBe('commit')
        ->and($committed['sales'][0]['journal_complete'])->toBeTrue()
        ->and($committed['sales'][0]['snapshot_rewritten'])->toBeFalse()
        ->and($committed['sales'][0]['authorization_reference_recorded'])
        ->toHaveLength(64)
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', 'commercial-sale:'.$sale->reference)
            ->count())->toBe(6);
});

/**
 * @return array<string, TreasuryPosition>
 */
function commercialSalePositions(): array
{
    $system = User::query()->create([
        'name' => 'Commercial System',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'not-a-login-credential',
    ]);
    $buyer = User::query()->create([
        'name' => 'Commercial Buyer',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'not-a-login-credential',
    ]);
    $partner = User::query()->create([
        'name' => 'Commercial Partner',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'not-a-login-credential',
    ]);
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
    $provisioning = app(TreasuryPositionProvisioningContract::class);

    foreach ($definitions as $key => [$principal, $purpose]) {
        $definition = commercialSalePositionDefinition($principal, $purpose);
        $provisioning->provision($principal, $definition);
        $positions[$key] = TreasuryPosition::query()
            ->where('position_reference', $definition->positionReference)
            ->sole();
    }

    return $positions;
}

/**
 * @param  array<string, TreasuryPosition>  $positions
 */
function fundCommercialClientPosition(array $positions, int $amountMinor, string $scope): void
{
    $operations = app(TreasuryPositionOperationContract::class);
    $operations->recognize(new TreasuryPositionRecognitionData(
        operationReference: "commercial-sale-test:recognize:{$scope}",
        destinationPositionReference: $positions['treasury_clearing']->position_reference,
        amountMinor: $amountMinor,
        currency: 'PHP',
        idempotencyKey: "commercial-sale-test:recognize:{$scope}:key",
        externalReference: "provider-observation:{$scope}",
    ));
    $operations->allocate(new TreasuryPositionAllocationData(
        operationReference: "commercial-sale-test:fund:{$scope}",
        sourcePositionReference: $positions['treasury_clearing']->position_reference,
        destinationPositionReference: $positions['client_funds']->position_reference,
        amountMinor: $amountMinor,
        currency: 'PHP',
        idempotencyKey: "commercial-sale-test:fund:{$scope}:key",
        externalReference: "commercial-sale-test:recognize:{$scope}",
    ));
}

function commercialSalePositionDefinition(
    User $principal,
    TreasuryPositionPurpose $purpose,
): TreasuryPositionDefinitionData {
    $scope = hash('sha256', $principal->getKey().'|'.$purpose->value);

    return new TreasuryPositionDefinitionData(
        positionReference: 'position:x-change-commercial:'.substr($scope, 0, 32),
        principalReference: 'principal:user:'.$principal->getKey(),
        mandateReference: 'mandate:x-change-commercial:'.substr($scope, 0, 32),
        settlementResourceReference: 'resource:netbank:primary:php',
        settlementResourceType: 'provider_deposit_account',
        provider: 'netbank',
        connectionReference: 'netbank-primary',
        currency: 'PHP',
        decimalPlaces: 2,
        purpose: $purpose,
        custodyMode: TreasuryCustodyMode::ProviderProjection,
        legalProfile: 'treasury-settlement-ph-v1',
        legalProfileVersion: '2026-07-25.1',
        idempotencyKey: 'position-registration:x-change-commercial:'.substr($scope, 0, 32),
        reconciliationReference: 'reconciliation:netbank:netbank-primary',
    );
}

function commercialSaleSnapshot(
    string $acceptanceReference,
    string $acceptedAt = '2026-07-25T10:00:00+08:00',
): CommercialSaleSnapshotData {
    $catalog = new CommercialCatalogData(
        reference: 'catalog:pay-code:v1',
        version: 1,
        currency: 'PHP',
        items: [
            new CommercialCatalogItemData(
                reference: 'cash.amount',
                label: 'Cash instruction',
                category: 'instruction',
                unitPriceMinor: 25_00,
                currency: 'PHP',
            ),
        ],
    );
    $policy = new CommercialWaterfallPolicyData(
        reference: 'waterfall:pay-code:v1',
        version: 1,
        currency: 'PHP',
        rules: [
            new CommercialWaterfallRuleData(
                reference: 'rule:provider',
                sequence: 1,
                lineType: CommercialWaterfallLineType::Deduction,
                category: 'provider_cost',
                recipientReference: 'recipient:netbank',
                fixedAmountMinor: 10_00,
            ),
            new CommercialWaterfallRuleData(
                reference: 'rule:product',
                sequence: 2,
                lineType: CommercialWaterfallLineType::Allocation,
                category: 'product_revenue',
                recipientReference: 'recipient:product',
                fixedAmountMinor: 8_00,
            ),
            new CommercialWaterfallRuleData(
                reference: 'rule:partner',
                sequence: 3,
                lineType: CommercialWaterfallLineType::Allocation,
                category: 'partner_commission',
                recipientReference: 'recipient:partner',
                fixedAmountMinor: 2_00,
            ),
            new CommercialWaterfallRuleData(
                reference: 'rule:residual',
                sequence: 4,
                lineType: CommercialWaterfallLineType::Residual,
                category: 'commercial_revenue',
                recipientReference: 'recipient:operator',
                fixedAmountMinor: null,
            ),
        ],
    );
    $quote = (new DeterministicCommercialQuoteBuilder(
        new DeterministicCommercialWaterfallCalculator,
    ))->build(
        sourceCommercialEventReference: 'pay-code-generation:TEST',
        catalog: $catalog,
        waterfallPolicy: $policy,
        attribution: new CommercialAttributionSnapshotData(
            reference: 'attribution:TEST',
            version: 1,
            participants: ['partner' => 'recipient:partner'],
        ),
        lineInputs: [new CommercialQuoteLineInputData('cash.amount')],
    );

    return (new DeterministicCommercialSaleFactory)->accept(
        quote: $quote,
        acceptanceEventReference: $acceptanceReference,
        buyerReference: 'principal:account:buyer',
        acceptedAt: $acceptedAt,
    );
}

/**
 * @param  array<string, TreasuryPosition>  $positions
 * @return array<string, string>
 */
function commercialSaleDestinations(array $positions): array
{
    return [
        'rule:provider' => $positions['provider_cost']->position_reference,
        'rule:product' => $positions['product_revenue']->position_reference,
        'rule:partner' => $positions['partner_commission']->position_reference,
        'rule:residual' => $positions['commercial_revenue']->position_reference,
    ];
}

function commercialSalePositionBalance(TreasuryPosition $position): int
{
    return Wallet::query()
        ->findOrFail($position->internal_ledger_id)
        ->getBalanceIntAttribute();
}

function recognizeCommercialInventoryForTest(int $amountMinor, string $scope): void
{
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'commercial-inventory-registration:'.$scope,
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventory->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'commercial-inventory-recognition:'.$scope,
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: $amountMinor,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'commercial-inventory-recognition-key:'.$scope,
        effectiveAt: now()->toRfc3339String(),
        externalReference: 'provider-observation:'.$scope,
    ));
}
