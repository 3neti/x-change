<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestType;
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
