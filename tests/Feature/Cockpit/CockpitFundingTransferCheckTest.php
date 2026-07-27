<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Cockpit\FundingRequestCockpitReadModel;

it('lets only the request owner check an InstaPay reference without crediting sender evidence', function () {
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '113-001-00001-9',
    );
    $fundingRequest = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 25_000,
            currency: 'PHP',
            description: 'InstaPay transfer to the configured NetBank account.',
            idempotencyKey: 'cockpit-provider-transfer-check-1001',
            externalReference: '026208691236',
        ),
    );

    $this->post(route(
        'x-change.cockpit.funding.requests.transfer-checks.store',
        ['fundingRequest' => $fundingRequest->reference],
    ))->assertRedirect(route('x-change.cockpit.funding.index', [
        'mode' => 'bank_transfer',
    ]))->assertSessionHas(
        'funding_notice',
        'No exact receiver-side provider record is available yet.',
    );

    $readModel = app(FundingRequestCockpitReadModel::class)
        ->forOperator($requester);

    expect(data_get($readModel, 'requests.0.transfer'))->toMatchArray([
        'provider' => 'netbank',
        'target_label' => 'NetBank ••••0019',
        'reference_hint' => '••••1236',
        'verification_status' => 'awaiting_provider_evidence',
        'can_check' => true,
        'provider_authority_required' => true,
    ])->and($fundingRequest->events()
        ->where('event_type', 'provider_check_awaiting_evidence')
        ->count())->toBe(1);

    $otherUser = actingAsTestUser(0);

    $this->actingAs($otherUser)->post(route(
        'x-change.cockpit.funding.requests.transfer-checks.store',
        ['fundingRequest' => $fundingRequest->reference],
    ))->assertNotFound();
});

it('creates a reserved exact transfer instruction without checking the provider early', function () {
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.enabled',
        true,
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.minimum_adjustment_minor',
        537,
    );
    config()->set(
        'x-change.funding.requests.bank_transfer.reserved_amounts.maximum_adjustment_minor',
        537,
    );
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '113-001-00001-9',
    );

    $this->post(route('x-change.cockpit.funding.requests.store'), [
        'funding_type' => 'bank_transfer',
        'requested_value_minor' => 100_000,
        'currency' => 'PHP',
        'description' => 'Reserve an exact NetBank transfer instruction.',
        'transfer_window' => 'recent',
        'idempotency_key' => 'cockpit-reserved-transfer-1000',
    ])->assertRedirect(route('x-change.cockpit.funding.index', [
        'mode' => 'bank_transfer',
    ]))->assertSessionHas(
        'funding_notice',
        'Transfer exactly ₱1,005.37 to NetBank, then select Check NetBank. The full amount will be credited.',
    );

    $request = FundingRequest::query()->sole();
    $readModel = app(FundingRequestCockpitReadModel::class)
        ->forOperator($requester);

    expect($request->events()
        ->where('event_type', 'provider_check_awaiting_evidence')
        ->count())->toBe(0)
        ->and(data_get($readModel, 'schema'))
        ->toBe('x-change.cockpit.account-funding-requests.v3')
        ->and(data_get($readModel, 'requests.0.requested_value'))
        ->toBe('₱1,000.00')
        ->and(data_get($readModel, 'requests.0.transfer'))->toMatchArray([
            'requested_amount' => '₱1,000.00',
            'matching_adjustment' => '₱5.37',
            'expected_amount' => '₱1,005.37',
            'instruction_status' => 'reserved',
            'full_expected_amount_is_credited' => true,
            'can_check' => true,
        ])
        ->and(data_get(
            $readModel,
            'requests.0.transfer.instruction_expires_at',
        ))->toBeString()
        ->and(data_get(
            $readModel,
            'bank_transfer.reserved_exact_amounts_enabled',
        ))->toBeTrue()
        ->and(data_get(
            $readModel,
            'bank_transfer.instruction_valid_for_minutes',
        ))->toBe(10);
});
