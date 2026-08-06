<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Cockpit\FundingActivityCockpitReadModel;
use LBHurtado\XChange\Services\Cockpit\FundingRequestCockpitReadModel;

it('projects requester funding records into one sanitized activity lifecycle', function () {
    $operator = actingAsTestUser(0);
    $submittedAt = now()->subMinutes(5);
    $fundingRequest = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$operator->wallet->uuid,
            requesterType: $operator::class,
            requesterId: (string) $operator->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: 10_000,
            currency: 'PHP',
            description: 'Bank transfer awaiting provider verification.',
            idempotencyKey: 'funding-activity-bank-transfer-1001',
        ),
    );
    $fundingRequest->forceFill(['submitted_at' => $submittedAt])->saveQuietly();
    $address = StandingFundingAddress::query()->forceCreate([
        'reference' => (string) Str::ulid(),
        'binding_key' => hash('sha256', 'activity-address'),
        'owner_type' => $operator::class,
        'owner_id' => $operator->getKey(),
        'account_reference' => 'wallet:'.$operator->wallet->uuid,
        'provider_code' => 'netbank',
        'purpose' => 'account_funding',
        'recognition_mode' => 'automatic',
        'status' => 'active',
        'version' => 1,
        'provider_reference' => 'activity-address',
        'funding_address_ciphertext' => '9150009173011987',
        'funding_address_hash' => hash('sha256', '9150009173011987'),
        'currency' => 'PHP',
        'activated_at' => now()->subDay(),
    ]);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'activity-observation'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'provider-secret-1001',
        'funding_address' => '9150009173011987',
        'gross_amount_minor' => 5_000,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 5_000,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now()->subMinute(),
        'settled_at' => now(),
        'verification_source' => 'provider_history',
        'payload_hash' => hash('sha256', 'activity-payload'),
    ]);
    AccountFundingReceipt::query()->forceCreate([
        'reference' => (string) Str::ulid(),
        'standing_funding_address_id' => $address->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_transaction_key' => hash('sha256', 'activity-transaction'),
        'provider_code' => 'netbank',
        'account_reference' => 'wallet:'.$operator->wallet->uuid,
        'purpose' => 'account_funding',
        'recognition_mode_snapshot' => 'automatic',
        'status' => 'settled',
        'gross_amount_minor' => 5_000,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 5_000,
        'currency' => 'PHP',
        'treasury_operation_reference' => 'activity-recognition-1001',
        'wallet_transaction_id' => 1001,
        'observed_at' => now()->subMinute(),
        'settled_at' => now(),
    ]);

    $requests = app(FundingRequestCockpitReadModel::class)
        ->forOperator($operator);
    $activity = app(FundingActivityCockpitReadModel::class)
        ->forOperator($operator, $requests);
    $serialized = json_encode($activity, JSON_THROW_ON_ERROR);

    expect($activity['schema'])->toBe('x-change.cockpit.funding-activity.v1')
        ->and($activity['items'])->toHaveCount(2)
        ->and(data_get($activity, 'items.0.method'))->toBe('qr_ph')
        ->and(data_get($activity, 'items.0.status'))->toBe('recognized')
        ->and(data_get($activity, 'items.0.amount'))->toBe('₱50.00')
        ->and(data_get($activity, 'items.1.method'))->toBe('bank_transfer')
        ->and(data_get($activity, 'items.1.status'))->toBe('awaiting_payment')
        ->and(data_get($activity, 'filters.4.label'))->toBe('Reviewed Value')
        ->and(data_get($activity, 'redactions.payer_identity_exposed'))->toBeFalse()
        ->and($serialized)->not->toContain('provider-secret-1001')
        ->not->toContain('9150009173011987');
});

it('hydrates the unified activity projection on the Funding page', function () {
    $operator = actingAsTestUser(0);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath(
            'props.funding_activity.schema',
            'x-change.cockpit.funding-activity.v1',
        )
        ->assertJsonCount(0, 'props.funding_activity.items')
        ->assertJsonPath('props.funding_activity.filters.1.label', 'QR Ph')
        ->assertJsonPath(
            'props.funding_activity.redactions.raw_evidence_exposed',
            false,
        );
});

it('projects a claimed Account Funding Pay Code only for its recipient', function () {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    $otherOperator = actingAsTestUser(0);
    fundTestSystemAccountFundingReserve(
        $system,
        10_000,
        'cockpit-funding-activity-pay-code',
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 10_000,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'cockpit-funding-activity-pay-code',
            expiresAt: now()->addDay(),
            recipient: $recipient,
            evidenceReference: 'test-evidence:cockpit-funding-activity-pay-code',
            authorizationReference: 'test-authorization:cockpit-funding-activity-pay-code',
        ),
    );
    $claim = app(DispatchVoucherClaimOutcome::class)->handle(
        voucher: $issuance->voucher,
        requestedOutcome: 'account_funding',
        payload: [],
        claimant: $recipient,
    );

    $recipientActivity = app(FundingActivityCockpitReadModel::class)
        ->forOperator($recipient, ['requests' => []]);
    $otherActivity = app(FundingActivityCockpitReadModel::class)
        ->forOperator($otherOperator, ['requests' => []]);

    expect($recipientActivity['items'])->toHaveCount(1)
        ->and(data_get($recipientActivity, 'items.0.source'))
        ->toBe('system_account_funding_pay_code')
        ->and(data_get($recipientActivity, 'items.0.method'))->toBe('pay_code')
        ->and(data_get($recipientActivity, 'items.0.display_reference'))
        ->toBe($issuance->voucher?->code)
        ->and(data_get($recipientActivity, 'items.0.amount'))->toBe('₱100.00')
        ->and(data_get($recipientActivity, 'items.0.status'))->toBe('recognized')
        ->and(data_get($recipientActivity, 'items.0.status_label'))
        ->toBe('Recognized')
        ->and(data_get($recipientActivity, 'items.0.summary'))
        ->toBe('Added to Client Funds')
        ->and(data_get($recipientActivity, 'items.0.timestamps.recognized_at'))
        ->toEqual($claim->completed_at)
        ->and($otherActivity['items'])->toBeEmpty();
});
