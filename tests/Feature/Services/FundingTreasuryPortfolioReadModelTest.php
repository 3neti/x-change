<?php

declare(strict_types=1);

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Cockpit\FundingTreasuryPortfolioReadModel;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Tests\Fakes\User;

use function Pest\Laravel\mock;

it('projects the authenticated account treasury portfolio without calling a provider', function () {
    enableNetbankTreasuryForTests();
    $operator = actingAsTestUser(0);
    $otherOperator = User::query()->create([
        'name' => 'Other Treasury Operator',
        'email' => 'other-treasury@example.test',
        'password' => bcrypt('password'),
    ]);
    fundTestUserWallet($otherOperator, 0);
    $operatorAccount = $operator->wallet()->where('slug', 'platform')->sole();
    $otherAccount = $otherOperator->wallet()->where('slug', 'platform')->sole();
    $inventoryOperations = app(TreasuryInventoryOperationContract::class);
    $inventoryOperations->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'register:funding-portfolio-test',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'funding-recognition:funding-portfolio-test',
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: 10_000,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'funding-recognition-key:funding-portfolio-test',
        externalReference: 'netbank:funding-portfolio-test',
    ));
    $allocations = app(VerifiedTreasuryFundingAllocationContract::class);
    $allocations->allocate(
        accountReference: 'wallet:'.$operatorAccount->uuid,
        provider: 'netbank',
        amountMinor: 3_000,
        currency: 'PHP',
        evidenceReference: 'netbank:funding-portfolio-operator',
    );
    $allocations->allocate(
        accountReference: 'wallet:'.$otherAccount->uuid,
        provider: 'netbank',
        amountMinor: 7_000,
        currency: 'PHP',
        evidenceReference: 'netbank:funding-portfolio-other',
    );

    $principalReference = app(
        TreasuryPrincipalReferenceResolverContract::class,
    )->resolve($operator);
    $positions = app(TreasuryPositionReadModelContract::class)
        ->forPrincipal($principalReference);
    $clientFunds = collect($positions)->first(
        fn (TreasuryPositionData $position): bool => $position->purpose
            === TreasuryPositionPurpose::ClientFunds,
    );
    $reserve = collect($positions)->first(
        fn (TreasuryPositionData $position): bool => $position->purpose
            === TreasuryPositionPurpose::PayCodeReserve,
    );

    expect($clientFunds)->toBeInstanceOf(TreasuryPositionData::class)
        ->and($reserve)->toBeInstanceOf(TreasuryPositionData::class);

    app(TreasuryPositionOperationContract::class)->reserve(
        new TreasuryPositionReservationData(
            operationReference: 'pay-code-position-reservation:funding-portfolio-test',
            sourcePositionReference: $clientFunds->positionReference,
            destinationPositionReference: $reserve->positionReference,
            amountMinor: 500,
            currency: 'PHP',
            idempotencyKey: 'pay-code-position-reservation-key:funding-portfolio-test',
            externalReference: 'pay-code:funding-portfolio-test',
        ),
    );

    $overview = mock(BuildBalanceOverview::class);
    $overview->shouldReceive('handle')
        ->once()
        ->with($operator, 'netbank', false)
        ->andReturn([
            'balances' => [[
                'key' => 'netbank_source_account',
                'source' => 'netbank',
                'is_liquidity_guard' => true,
                'is_stale' => false,
                'available_balance_minor' => 7_000,
                'currency' => 'PHP',
                'checked_at' => '2026-07-24T23:30:00+08:00',
                'sync_status' => 'cached',
            ]],
        ]);
    config()->set('x-change.treasury.connections.paynamics-primary', [
        'provider' => 'paynamics_constellation',
        'mode' => 'disabled',
        'currency' => 'PHP',
        'decimal_places' => 2,
        'inventory_reference' => 'inventory:paynamics:wallet-float',
        'settlement_resource_reference' => 'resource:paynamics:corporate-wallet',
        'settlement_resource_type' => 'emi_wallet_float',
        'custody_mode' => 'provider_projection',
        'required_capabilities' => [],
    ]);
    app()->forgetInstance(TreasuryProviderConnectionCatalog::class);

    $portfolio = app(FundingTreasuryPortfolioReadModel::class)
        ->forOperator($operator);
    $netbank = collect($portfolio['connections'])
        ->firstWhere('provider', 'netbank');
    $paynamics = collect($portfolio['connections'])
        ->firstWhere('provider', 'paynamics_constellation');

    expect($portfolio)->toMatchArray([
        'schema' => 'x-change.cockpit.funding-treasury-portfolio.v1',
        'status' => 'available',
        'read_only' => true,
        'provider_calls' => false,
        'totals' => [
            'client_funds_minor' => 2_500,
            'client_funds' => '₱25.00',
            'pay_code_reserve_minor' => 500,
            'pay_code_reserve' => '₱5.00',
            'account_position_minor' => 3_000,
            'account_position' => '₱30.00',
            'provider_inventory_minor' => 10_000,
            'provider_inventory' => '₱100.00',
            'issuance_capacity_minor' => 2_500,
            'issuance_capacity' => '₱25.00',
        ],
        'accounting_boundary' => [
            'provider_outflow' => 'provider_principal_only',
            'sender_system_charge' => 'immutable_commercial_waterfall',
            'provider_liquidity_source' => 'cached_projection_only',
        ],
    ])
        ->and($netbank)->toMatchArray([
            'provider' => 'netbank',
            'mode' => 'required',
            'currency' => 'PHP',
            'status' => 'active',
            'client_funds_minor' => 2_500,
            'pay_code_reserve_minor' => 500,
            'account_position_minor' => 3_000,
            'provider_inventory_minor' => 10_000,
            'provider_liquidity_minor' => 7_000,
            'provider_liquidity_status' => 'cached',
            'provider_liquidity_is_stale' => false,
            'provider_liquidity_checked_at' => '2026-07-24T23:30:00+08:00',
            'issuance_capacity_minor' => 2_500,
            'inventory_matches_positions' => true,
            'control_status' => 'reconciled',
        ])
        ->and($paynamics)->toMatchArray([
            'provider' => 'paynamics_constellation',
            'mode' => 'disabled',
            'status' => 'disabled',
            'provider_liquidity_status' => 'disabled',
            'issuance_capacity_minor' => null,
        ]);

    $serialized = json_encode($portfolio, JSON_THROW_ON_ERROR);

    expect($serialized)
        ->not->toContain($principalReference)
        ->not->toContain('netbank-primary')
        ->not->toContain('inventory:netbank:vca-cash')
        ->not->toContain('resource:netbank:corporate-vca')
        ->not->toContain('netbank:funding-portfolio-other');
});
