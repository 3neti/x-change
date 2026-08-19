<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Models\StoredValueSpendChallenge;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateValidator;
use LBHurtado\XChange\Tests\Fakes\FakeOtpChallengeGateway;
use LBHurtado\XChange\Tests\Fakes\User;

it('requires an explicit bounded mandate for stored-value scopes', function (): void {
    $validator = app(PartnerApiMandateValidator::class);

    expect(fn () => $validator->validate(['stored-value:spend'], [
        'stored_value_spend' => ['enabled' => false],
    ]))->toThrow(InvalidArgumentException::class, 'explicitly enabled')
        ->and(fn () => $validator->validate(['stored-value:spend'], [
            'stored_value_spend' => [
                'enabled' => true,
                'currencies' => ['PHP'],
                'maximum_amount_minor' => 500,
                'daily_amount_minor' => 499,
            ],
        ]))->toThrow(InvalidArgumentException::class, 'daily limit');
});

it('spends by opaque instrument reference with durable replay evidence', function (): void {
    [, $binding] = partnerStoredValueInstrument();
    partnerStoredValueClient(['stored-value:spend', 'stored-value:read']);
    $engine = Mockery::mock(ExecutionEngine::class);
    $engine->shouldReceive('execute')->once()->withArgs(function ($context) use ($binding): bool {
        expect($context->voucher?->getKey())->toBe($binding->voucher_id)
            ->and($context->meta)->toBe(['operation' => 'spend', 'amount' => 2500])
            ->and(data_get($context->meta, 'otp_verified'))->toBeNull()
            ->and(data_get($context->correlation, 'execution_id'))->toHaveLength(64);

        return true;
    })->andReturn(ExecutionResultData::succeeded('stored_value', [
        'remaining_balance' => 97_500,
        'operation_reference' => 'treasury-operation:private',
    ]));
    $this->app->instance(ExecutionEngine::class, $engine);
    $headers = ['Idempotency-Key' => 'fare-001', 'X-Correlation-ID' => 'gate-run-001'];

    $first = $this->postJson(
        '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends',
        ['amount_minor' => 2500, 'currency' => 'PHP'],
        $headers,
    )->assertCreated()
        ->assertHeader('X-Correlation-ID', 'gate-run-001')
        ->assertJsonPath('data.instrument_reference', $binding->reference)
        ->assertJsonPath('data.transaction.amount_minor', 2500)
        ->assertJsonPath('data.transaction.balance_after_minor', 97_500)
        ->assertJsonPath('meta.idempotency.replayed', false)
        ->assertJsonMissing(['voucher_code' => $binding->voucher->code])
        ->json('data');

    $this->postJson(
        '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends',
        ['amount_minor' => 2500, 'currency' => 'PHP'],
        $headers,
    )->assertOk()
        ->assertJsonPath('data', $first)
        ->assertJsonPath('meta.idempotency.replayed', true);

    $operation = PartnerApiOperation::query()->sole();

    expect($operation->reference)->toHaveLength(26)
        ->and($operation->request_hash)->toHaveLength(64)
        ->and($operation->authority_reference_hash)->toHaveLength(64)
        ->and($operation->treasury_operation_reference_hash)->toHaveLength(64)
        ->and($operation->response_snapshot)->toBe($first);
});

it('rejects short codes, changed replays, and missing scopes before money movement', function (): void {
    [, $binding] = partnerStoredValueInstrument();
    partnerStoredValueClient(['stored-value:spend']);
    $engine = Mockery::mock(ExecutionEngine::class);
    $engine->shouldReceive('execute')->once()->andReturn(ExecutionResultData::succeeded('stored_value', [
        'remaining_balance' => 99_000,
        'operation_reference' => 'treasury-operation:once',
    ]));
    $this->app->instance(ExecutionEngine::class, $engine);

    $this->postJson('/api/partner/v1/stored-value-instruments/'.$binding->voucher->code.'/spends', [
        'amount_minor' => 1000,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'short-code'])->assertNotFound();

    $url = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends';
    $this->postJson($url, ['amount_minor' => 1000, 'currency' => 'PHP'], [
        'Idempotency-Key' => 'changed-replay',
    ])->assertCreated();
    $this->postJson($url, ['amount_minor' => 1001, 'currency' => 'PHP'], [
        'Idempotency-Key' => 'changed-replay',
    ])->assertUnprocessable();

    partnerStoredValueClient(['pay-codes:read']);
    $this->postJson($url, ['amount_minor' => 1000, 'currency' => 'PHP'], [
        'Idempotency-Key' => 'wrong-scope',
    ])->assertForbidden();
});

it('returns only client-scoped sanitized transaction history', function (): void {
    [, $binding] = partnerStoredValueInstrument();
    [$firstClient] = partnerStoredValueClient(['stored-value:read']);
    PartnerApiOperation::query()->create([
        'partner_api_client_id' => $firstClient->getKey(),
        'operation' => 'stored_value_spent',
        'idempotency_key' => 'history-1',
        'subject_reference' => $binding->reference,
        'principal_minor' => 500,
        'currency' => 'PHP',
        'balance_after_minor' => 99_500,
        'occurred_at' => now(),
    ]);
    $allocations = Mockery::mock(TreasuryAllocationReadModelContract::class);
    $allocations->shouldReceive('read')->once()->andReturn(new TreasuryAllocationReadModelData(
        allocationReference: $binding->allocation_reference,
        currency: 'PHP',
        allocatedAmountMinor: 100_000,
        drawnAmountMinor: 500,
        releasedAmountMinor: 0,
        outstandingAmountMinor: 500,
        usableAmountMinor: 99_500,
        sliceCount: 0,
        hasTreasuryFacts: true,
    ));
    $this->app->instance(TreasuryAllocationReadModelContract::class, $allocations);

    $this->getJson('/api/partner/v1/stored-value-instruments/'.$binding->reference.'/transactions')
        ->assertOk()
        ->assertJsonPath('data.instrument_reference', $binding->reference)
        ->assertJsonPath('data.available_minor', 99_500)
        ->assertJsonCount(1, 'data.transactions')
        ->assertJsonMissingPath('data.transactions.0.idempotency_key')
        ->assertJsonMissingPath('data.transactions.0.subject_reference')
        ->assertJsonMissingPath('data.transactions.0.request_hash');
});

it('rejects an expired instrument before execution can reach Treasury', function (): void {
    [, $binding] = partnerStoredValueInstrument();
    $binding->voucher->forceFill(['expires_at' => now()->subSecond()])->saveQuietly();
    partnerStoredValueClient(['stored-value:spend']);
    $engine = Mockery::mock(ExecutionEngine::class);
    $engine->shouldNotReceive('execute');
    $this->app->instance(ExecutionEngine::class, $engine);

    $this->postJson('/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends', [
        'amount_minor' => 100,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'expired-fare'])->assertUnprocessable();

    expect(PartnerApiOperation::query()->count())->toBe(0);
});

it('binds a provider-verified OTP challenge to one exact above-threshold spend', function (): void {
    [, $binding] = partnerStoredValueInstrument(1_000);
    partnerStoredValueClient(['stored-value:spend']);
    $otp = new FakeOtpChallengeGateway;
    $otp->expectedCode = '197200';
    $this->app->instance(OtpChallengeGateway::class, $otp);
    $challengeUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spend-challenges';

    $challenge = $this->postJson($challengeUrl, [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'fare-otp-challenge-1'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonMissingPath('data.mobile')
        ->assertJsonMissingPath('data.provider_reference')
        ->json('data');

    expect($otp->request?->mobile)->toBe('+639173011987')
        ->and($otp->request?->purpose)->toBe('stored-value.spend.v1')
        ->and(DB::table('x_change_stored_value_spend_challenges')
            ->value('provider_reference_ciphertext'))->not->toContain((string) $challenge['reference']);

    $verificationUrl = $challengeUrl.'/'.$challenge['reference'].'/verification';
    $this->postJson($verificationUrl, ['code' => '000000'])
        ->assertUnprocessable();
    $this->postJson($verificationUrl, ['code' => '197200'])
        ->assertOk()
        ->assertJsonPath('data.status', 'verified')
        ->assertJsonMissingPath('data.proof_reference');

    $engine = Mockery::mock(ExecutionEngine::class);
    $engine->shouldReceive('execute')->once()->withArgs(function ($context): bool {
        expect($context->meta)->toBe([
            'operation' => 'spend',
            'amount' => 2_500,
            'otp_verified' => true,
        ]);

        return true;
    })->andReturn(ExecutionResultData::succeeded('stored_value', [
        'remaining_balance' => 97_500,
        'operation_reference' => 'treasury-operation:otp-protected',
    ]));
    $this->app->instance(ExecutionEngine::class, $engine);
    $spendUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends';
    $spendPayload = [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
        'otp_challenge_reference' => $challenge['reference'],
    ];
    $headers = ['Idempotency-Key' => 'fare-otp-spend-1'];

    $this->postJson($spendUrl, $spendPayload, $headers)
        ->assertCreated()
        ->assertJsonPath('data.transaction.balance_after_minor', 97_500);
    $this->postJson($spendUrl, $spendPayload, $headers)
        ->assertOk()
        ->assertJsonPath('meta.idempotency.replayed', true);

    $storedChallenge = StoredValueSpendChallenge::query()->sole();

    expect($storedChallenge->status)->toBe('consumed')
        ->and($storedChallenge->attempts)->toBe(1)
        ->and($storedChallenge->proof_reference_hash)->toHaveLength(64)
        ->and($storedChallenge->consumed_partner_api_operation_id)->toBe(
            PartnerApiOperation::query()->sole()->getKey(),
        )
        ->and(DB::table('x_change_stored_value_spend_challenges')
            ->value('idempotency_key_hash'))->not->toBe('fare-otp-challenge-1')
        ->and(fn () => $storedChallenge->forceFill(['status' => 'pending'])->save())
        ->toThrow(LogicException::class, 'guarded lifecycle actions')
        ->and(fn () => $storedChallenge->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');

    $this->postJson($spendUrl, $spendPayload, ['Idempotency-Key' => 'fare-otp-spend-2'])
        ->assertUnprocessable();
});

it('rejects challenge replay drift and cross-client verification', function (): void {
    [, $binding] = partnerStoredValueInstrument(1_000);
    partnerStoredValueClient(['stored-value:spend']);
    $otp = new FakeOtpChallengeGateway;
    $this->app->instance(OtpChallengeGateway::class, $otp);
    $url = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spend-challenges';

    $reference = $this->postJson($url, [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'challenge-exact-facts'])
        ->assertCreated()
        ->json('data.reference');

    $this->postJson($url, [
        'amount_minor' => 2_501,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'challenge-exact-facts'])
        ->assertUnprocessable();

    partnerStoredValueClient(['stored-value:spend']);
    $this->postJson($url.'/'.$reference.'/verification', ['code' => '000000'])
        ->assertNotFound();

    expect(PartnerApiOperation::query()->count())->toBe(0);
});

it('rejects unverified and mismatched OTP proof without reaching execution', function (): void {
    [, $binding] = partnerStoredValueInstrument(1_000);
    partnerStoredValueClient(['stored-value:spend']);
    $otp = new FakeOtpChallengeGateway;
    $this->app->instance(OtpChallengeGateway::class, $otp);
    $challengeUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spend-challenges';
    $reference = $this->postJson($challengeUrl, [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'challenge-proof-guard'])
        ->assertCreated()
        ->json('data.reference');
    $engine = Mockery::mock(ExecutionEngine::class);
    $engine->shouldNotReceive('execute');
    $this->app->instance(ExecutionEngine::class, $engine);
    $spendUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends';

    $this->postJson($spendUrl, [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
        'otp_challenge_reference' => $reference,
    ], ['Idempotency-Key' => 'unverified-spend'])
        ->assertUnprocessable();

    $otp->proofPurpose = 'another.purpose';
    $this->postJson($challengeUrl.'/'.$reference.'/verification', ['code' => '000000'])
        ->assertUnprocessable();
    $otp->proofPurpose = 'stored-value.spend.v1';
    $otp->proofVerifiedAt = now()->subHour()->toIso8601String();
    $this->postJson($challengeUrl.'/'.$reference.'/verification', ['code' => '000000'])
        ->assertUnprocessable();

    expect(StoredValueSpendChallenge::query()->sole()->status)->toBe('pending')
        ->and(PartnerApiOperation::query()->count())->toBe(0);
});

/** @return array{0: User, 1: StoredValueHolderBinding} */
function partnerStoredValueInstrument(int $otpRequiredAbove = 0): array
{
    $holder = User::query()->create([
        'name' => 'Stored Value Holder',
        'email' => 'holder-'.str()->uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    $holder->forceFill([
        'mobile' => '09173011987',
        'mobile_verified_at' => now(),
    ])->save();
    $voucher = Voucher::query()->create([
        'code' => str()->upper(str()->random(4)),
        'state' => 'active',
        'metadata' => [
            'instructions' => [
                'execution' => [
                    'schema' => 'voucher.execution.v1',
                    'driver' => 'stored_value',
                    'metadata' => ['stored_value' => ['otp_required_above' => $otpRequiredAbove]],
                ],
            ],
        ],
    ]);
    $binding = StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucher->getKey(),
        'allocation_reference' => 'stored-value-allocation:'.str()->uuid(),
        'reservation_operation_reference' => 'reservation:'.str()->uuid(),
        'activation_operation_reference' => 'activation:'.str()->uuid(),
        'holder_type' => $holder->getMorphClass(),
        'holder_id' => (string) $holder->getKey(),
        'holder_principal_reference' => 'principal:holder',
        'holder_authority_reference' => 'authority:holder',
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);

    return [$holder, $binding->load('voucher')];
}

/** @return array{0: PartnerApiClient, 1: User} */
function partnerStoredValueClient(array $scopes): array
{
    $issuer = User::query()->create([
        'name' => 'Transit Merchant',
        'email' => 'merchant-'.str()->uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    fundTestUserWallet($issuer);
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Transit Merchant Sandbox',
        issuer: $issuer,
        scopes: $scopes,
        mandate: [
            'stored_value_spend' => [
                'enabled' => in_array('stored-value:read', $scopes, true) || in_array('stored-value:spend', $scopes, true),
                'currencies' => ['PHP'],
                'maximum_amount_minor' => 10_000,
                'daily_amount_minor' => 100_000,
            ],
        ],
    );
    $oauth = Client::query()->findOrFail($credential->client_id);
    Passport::actingAsClient($oauth, $scopes);
    $client = PartnerApiClient::query()
        ->where('reference', $credential->reference)
        ->sole();

    return [$client, $issuer];
}
