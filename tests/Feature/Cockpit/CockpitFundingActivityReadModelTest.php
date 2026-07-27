<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
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
