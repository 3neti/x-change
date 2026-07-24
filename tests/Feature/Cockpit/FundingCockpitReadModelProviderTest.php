<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Funding\ReverseSettledFundingIntent;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Cockpit\FundingCockpitReadModelProvider;
use LBHurtado\XChange\Tests\Fakes\User;

it('presents operator scoped funding controls without exposing provider evidence', function () {
    enableNetbankTreasuryForTests();
    config([
        'x-change.funding.providers.netbank.enabled' => true,
        'x-change.funding.providers.paynamics_constellation.enabled' => true,
        'x-change.funding.providers.qrph_simulator.enabled' => false,
    ]);

    $operator = actingAsTestUser(0);
    $otherOperator = User::query()->create([
        'name' => 'Other Operator',
        'email' => 'other+'.Str::uuid().'@example.com',
        'password' => bcrypt('password'),
    ]);
    fundTestUserWallet($otherOperator, 0);
    $wallet = $operator->wallet()->where('slug', 'platform')->firstOrFail();
    $otherWallet = $otherOperator->wallet()->where('slug', 'platform')->firstOrFail();

    $settledIntent = fundingCockpitVerifiedIntent($operator, $wallet);
    app(SettleVerifiedFundingIntent::class)->handle($settledIntent);
    treasuryClientFundsLedger($operator)->withdraw(20_000, ['source' => 'simulated_spend']);
    app(ReverseSettledFundingIntent::class)->handle(
        $settledIntent->refresh(),
        fundingCockpitObservation('reversed', $settledIntent->matched_observation_id),
    );

    $suspenseIntent = fundingCockpitIntent($operator, $wallet, FundingIntentStatus::Suspense);
    $suspenseCase = FundingSuspenseCase::query()->create([
        'case_key' => hash('sha256', 'cockpit-suspense'),
        'funding_intent_id' => $suspenseIntent->getKey(),
        'provider_code' => 'netbank',
        'reason_code' => 'amount_mismatch',
        'status' => 'open',
        'details' => ['provider_transaction_id' => 'must-not-leak'],
        'opened_at' => now(),
    ]);
    FundingReconciliationRequest::query()->create([
        'request_key' => hash('sha256', 'cockpit-reconciliation'),
        'funding_suspense_case_id' => $suspenseCase->getKey(),
        'action' => 'retry_verification',
        'status' => 'pending_approval',
        'payload' => [],
        'requested_by_type' => $otherOperator::class,
        'requested_by_id' => (string) $otherOperator->getAuthIdentifier(),
        'requested_at' => now(),
    ]);
    fundingCockpitIntent($otherOperator, $otherWallet, FundingIntentStatus::AwaitingFunds);

    $readModel = app(FundingCockpitReadModelProvider::class)
        ->forOperator($operator)
        ->toArray();

    expect($readModel)
        ->toMatchArray([
            'schema' => 'x-change.cockpit.funding-read-model.v1',
            'status' => 'available',
            'authorized' => true,
            'read_only' => true,
            'controls' => [
                'funding_intent_required' => true,
                'manual_balance_adjustment_enabled' => false,
                'webhook_direct_credit_enabled' => false,
                'authoritative_provider_verification_required' => true,
                'dual_control_reconciliation_required' => true,
                'live_provider_balance_connected' => true,
            ],
        ])
        ->and($readModel['redactions']['payloads'])->toBe('funding-operations-summary-only')
        ->and($readModel['redactions']['webhook_payloads_exposed'])->toBeFalse()
        ->and($readModel['redactions']['raw_evidence_exposed'])->toBeFalse()
        ->and($readModel['summary']['open_suspense'])->toBe(1)
        ->and($readModel['summary']['recovery_outstanding'])->toBe('₱200.00')
        ->and($readModel['intents'])->toHaveCount(2)
        ->and($readModel['intents'][0])->toHaveKeys([
            'can_check_provider',
            'can_reopen_instructions',
            'verification_status',
            'last_checked_at',
        ])
        ->and($readModel['suspense_cases'][0])->toMatchArray([
            'provider' => 'netbank',
            'reason' => 'amount_mismatch',
            'status' => 'open',
            'pending_approval' => true,
            'pending_action' => 'retry_verification',
            'allowed_actions' => [],
        ])
        ->and($readModel['approval_queue'][0])->toMatchArray([
            'case_reference' => $suspenseCase->reference,
            'provider' => 'netbank',
            'action' => 'retry_verification',
            'status' => 'pending_approval',
            'requested_by_self' => false,
            'can_approve' => true,
            'amount_input_allowed' => false,
            'evidence_input_allowed' => false,
        ])
        ->and($readModel['recovery_holds'][0])->toMatchArray([
            'status' => 'open',
            'hold_status' => 'active',
            'outstanding' => '₱200.00',
        ])
        ->and($readModel['treasury_positions'][0])->toMatchArray([
            'provider' => 'netbank',
            'recognized' => '₱0.00',
            'has_treasury_facts' => true,
        ])
        ->and($readModel['treasury_portfolio'])->toMatchArray([
            'schema' => 'x-change.cockpit.funding-treasury-portfolio.v1',
            'read_only' => true,
            'provider_calls' => false,
            'accounting_boundary' => [
                'provider_outflow' => 'provider_principal_only',
                'sender_system_charge' => 'deferred_accounting_wave',
                'provider_liquidity_source' => 'cached_projection_only',
            ],
        ])
        ->and($readModel['treasury_portfolio']['connections'][0])
        ->toHaveKeys([
            'client_funds',
            'pay_code_reserve',
            'account_position',
            'provider_inventory',
            'provider_liquidity',
            'issuance_capacity',
            'control_status',
        ])
        ->and(collect($readModel['providers'])->pluck('code')->all())
        ->toBe(['netbank', 'paynamics_constellation', 'qrph_simulator']);

    $serialized = json_encode($readModel, JSON_THROW_ON_ERROR);

    expect($serialized)
        ->not->toContain('must-not-leak')
        ->not->toContain((string) $settledIntent->provider_transaction_id)
        ->not->toContain((string) $settledIntent->provider_request_id)
        ->not->toContain((string) $settledIntent->account_reference)
        ->not->toContain('001234567890');
});

it('offers checks and QR reopening only for the owners unexpired NetBank intents', function () {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    $operator = actingAsTestUser(0);
    $wallet = $operator->wallet()->where('slug', 'platform')->firstOrFail();
    $intent = fundingCockpitIntent($operator, $wallet, FundingIntentStatus::AwaitingFunds);
    $intent->events()->create([
        'sequence' => 5,
        'event_type' => 'provider_funds_not_observed',
        'from_status' => FundingIntentStatus::Verifying,
        'to_status' => FundingIntentStatus::AwaitingFunds,
        'actor_type' => 'operator',
        'actor_id' => (string) $operator->getAuthIdentifier(),
        'evidence_reference' => 'provider-query:operator:'.$intent->reference,
        'metadata' => ['trigger' => 'operator'],
        'occurred_at' => now()->subMinute(),
    ]);

    $summary = app(FundingCockpitReadModelProvider::class)
        ->forOperator($operator)
        ->toArray()['intents'][0];

    expect($summary)->toMatchArray([
        'reference' => $intent->reference,
        'provider' => 'netbank',
        'status' => 'awaiting_funds',
        'can_check_provider' => true,
        'can_reopen_instructions' => true,
        'verification_status' => 'awaiting_funds',
    ])
        ->and($summary['last_checked_at'])->not->toBeNull()
        ->and($summary)->not->toHaveKeys([
            'funding_address',
            'qr_code',
            'provider_transaction_id',
            'provider_request_id',
        ]);
});

it('blocks an unverified dedicated destination in the Funding read model', function () {
    config([
        'x-change.funding.providers.netbank.enabled' => true,
        'x-change.funding.providers.paynamics_constellation.enabled' => true,
        'x-change.funding.providers.qrph_simulator.enabled' => false,
    ]);

    $operator = actingAsTestUser(0);
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $operator::class,
        'owner_id' => $operator->getKey(),
        'provider' => 'paynamics_constellation',
        'topology' => 'dedicated',
        'purpose' => 'funding',
        'mode' => 'wallet_link',
        'status' => 'ready',
        'verification_status' => 'reachable',
        'display_reference' => '•••• LLET01',
    ]);
    FundingDestinationPreference::query()->create([
        'owner_type' => $operator::class,
        'owner_id' => $operator->getKey(),
        'provider_code' => 'paynamics_constellation',
        'mode' => 'dedicated',
        'provider_account_link_id' => $link->getKey(),
    ]);

    $provider = collect(
        app(FundingCockpitReadModelProvider::class)
            ->forOperator($operator)
            ->toArray()['providers'],
    )->firstWhere('code', 'paynamics_constellation');

    expect($provider)->toMatchArray([
        'status' => 'blocked',
        'destination_mode' => 'dedicated',
        'destination_status' => 'reachable',
        'destination_reference' => '•••• LLET01',
    ]);
});

it('offers the local simulator while live funding providers remain disabled', function () {
    config([
        'x-change.funding.providers.netbank.enabled' => false,
        'x-change.funding.providers.paynamics_constellation.enabled' => false,
        'x-change.funding.providers.qrph_simulator.enabled' => true,
    ]);

    $operator = actingAsTestUser(0);
    $providers = app(FundingCockpitReadModelProvider::class)
        ->forOperator($operator)
        ->toArray()['providers'];

    expect(collect($providers)->pluck('code')->all())
        ->toBe(['netbank', 'paynamics_constellation', 'qrph_simulator'])
        ->and(collect($providers)->pluck('status')->all())
        ->toBe(['disabled', 'disabled', 'available'])
        ->and($providers[2])->toMatchArray([
            'label' => 'QR Ph Simulator',
            'destination_status' => 'simulation_only',
            'destination_reference' => 'Local simulated clearing',
            'simulation_only' => true,
        ]);
});

function fundingCockpitIntent(
    User $operator,
    Wallet $wallet,
    FundingIntentStatus $status,
    ?ProviderFundingObservation $observation = null,
): FundingIntent {
    $idempotency = (string) Str::uuid();

    return FundingIntent::query()->create([
        'account_reference' => 'wallet:'.$wallet->uuid,
        'provider_code' => 'netbank',
        'expected_amount_minor' => 25_000,
        'currency' => 'PHP',
        'status' => $status,
        'version' => 4,
        'idempotency_key_hash' => hash('sha256', $idempotency),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$idempotency),
        'created_by_type' => $operator::class,
        'created_by_id' => (string) $operator->getAuthIdentifier(),
        'provider_reference' => 'sha256:'.hash('sha256', $idempotency),
        'provider_request_id' => $observation?->request_id,
        'funding_address_ciphertext' => $observation?->funding_address,
        'funding_address_hash' => hash('sha256', (string) ($observation?->funding_address ?? $idempotency)),
        'matched_observation_id' => $observation?->getKey(),
        'provider_transaction_id' => $observation?->provider_transaction_id,
        'instructions_created_at' => now()->subMinutes(2),
        'evidence_received_at' => $observation === null ? null : now()->subMinute(),
        'verified_at' => $status === FundingIntentStatus::Verified ? now() : null,
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'test'],
    ]);
}

function fundingCockpitVerifiedIntent(User $operator, Wallet $wallet): FundingIntent
{
    $observation = fundingCockpitObservation();

    return fundingCockpitIntent($operator, $wallet, FundingIntentStatus::Verified, $observation);
}

function fundingCockpitObservation(
    string $status = 'settled',
    ?int $settledObservationId = null,
): ProviderFundingObservation {
    $settledObservation = $settledObservationId === null
        ? null
        : ProviderFundingObservation::query()->findOrFail($settledObservationId);
    $transactionId = $settledObservation?->provider_transaction_id ?? 'NB-'.Str::upper(Str::random(12));

    return ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', $status.'-'.$transactionId),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => strtoupper($status).'-'.$transactionId,
        'request_id' => $settledObservation?->request_id ?? 'REQ-'.$transactionId,
        'funding_address' => $settledObservation?->funding_address ?? '001234567890',
        'provider_account_reference' => 'corporate-vca',
        'gross_amount_minor' => 25_000,
        'fee_amount_minor' => 50,
        'net_amount_minor' => 24_950,
        'currency' => 'PHP',
        'provider_status' => $status,
        'occurred_at' => now(),
        'settled_at' => $status === 'settled' ? now() : null,
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'payload-'.$status.'-'.$transactionId),
        'metadata' => ['destination_verified' => true],
    ]);
}
