<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Commercial\PostCommercialSale;
use LBHurtado\XChange\Actions\Commercial\ReverseCommercialSale;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
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
        ->and(commercialSalePositionBalance($positions['commercial_revenue']))->toBe(5_00);

    $reversal = app(ReverseCommercialSale::class);
    $reversed = $reversal->execute($snapshot->reference, 'administrative-void:posting');
    $reversalOperationCount = TreasuryPositionOperation::query()->count();
    $replayedReversal = $reversal->execute($snapshot->reference, 'administrative-void:posting');

    expect($reversed->status)->toBe('reversed')
        ->and($replayedReversal->getKey())->toBe($reversed->getKey())
        ->and(TreasuryPositionOperation::query()->count())->toBe($reversalOperationCount)
        ->and(commercialSalePositionBalance($positions['client_funds']))->toBe(25_00)
        ->and(commercialSalePositionBalance($positions['commercial_clearing']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['provider_cost']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['product_revenue']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['partner_commission']))->toBe(0)
        ->and(commercialSalePositionBalance($positions['commercial_revenue']))->toBe(0);
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
        connectionReference: 'primary',
        currency: 'PHP',
        decimalPlaces: 2,
        purpose: $purpose,
        custodyMode: TreasuryCustodyMode::ProviderProjection,
        legalProfile: 'treasury-settlement-ph-v1',
        legalProfileVersion: '2026-07-25.1',
        idempotencyKey: 'position-registration:x-change-commercial:'.substr($scope, 0, 32),
        reconciliationReference: 'reconciliation:netbank:primary',
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
