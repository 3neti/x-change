<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeData;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Lifecycle\Output\NullLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunnerRegistry;
use LBHurtado\XChange\Lifecycle\Runners\StoredValueCollectionScenarioRunner;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\StoredValueCollection;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\StoredValue\StoredValueCollectionService;
use LBHurtado\XChange\Tests\Fakes\FakeOtpChallengeGateway;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('moves one OTP-authorized Partner API fare through the real wallet allocation exactly once', function (): void {
    $issuer = storedValueAcceptanceUser('Stored Value Issuer');
    $holder = storedValueAcceptanceUser('Stored Value Holder', '09173011987');
    $merchant = storedValueAcceptanceUser('Transit Merchant');
    enableNetbankTreasuryForTests();
    $portfolios = app(TreasuryAccountPortfolioProvisioningContract::class);
    $issuerPortfolio = $portfolios->provision($issuer, ['netbank-primary']);
    $portfolios->provision($holder, ['netbank-primary']);
    $portfolios->provision($merchant, ['netbank-primary']);
    $issuerClientFunds = collect($issuerPortfolio->positions)
        ->firstWhere('purpose', TreasuryPositionPurpose::ClientFunds);
    $issuerReserve = collect($issuerPortfolio->positions)
        ->firstWhere('purpose', TreasuryPositionPurpose::PayCodeReserve);
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 100_000,
        currency: 'PHP',
        evidenceReference: 'stored-value-acceptance:funding',
    );
    app(TreasuryPositionOperationContract::class)->reserve(new TreasuryPositionReservationData(
        operationReference: 'stored-value-acceptance:reservation',
        sourcePositionReference: $issuerClientFunds->positionReference,
        destinationPositionReference: $issuerReserve->positionReference,
        amountMinor: 100_000,
        currency: 'PHP',
        idempotencyKey: 'stored-value-acceptance:reservation:key',
        externalReference: 'stored-value-acceptance:voucher',
    ));
    $voucher = storedValueAcceptanceVoucher();
    $instruction = ExecutionInstructionData::from(
        data_get($voucher->metadata, 'instructions.execution'),
    );
    $this->actingAs($holder);
    $activation = app(ExecutionEngine::class)->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '+639173011987', 'country' => 'PH']),
        voucherCode: $voucher->code,
        voucher: $voucher,
        instruction: $instruction,
        correlation: ['execution_id' => 'stored-value-acceptance:activation'],
    ));
    $binding = StoredValueHolderBinding::query()->where('voucher_id', $voucher->getKey())->sole();
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Transit Merchant Acceptance',
        issuer: $merchant,
        scopes: ['stored-value:spend', 'stored-value:read'],
        mandate: [
            'stored_value_spend' => [
                'enabled' => true,
                'currencies' => ['PHP'],
                'maximum_amount_minor' => 10_000,
                'daily_amount_minor' => 100_000,
            ],
        ],
    );
    $oauth = Client::query()->findOrFail($credential->client_id);
    Passport::actingAsClient($oauth, ['stored-value:spend', 'stored-value:read']);
    $otp = new FakeOtpChallengeGateway;
    $this->app->instance(OtpChallengeGateway::class, $otp);
    $inventoryBalanceBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $challengeUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spend-challenges';
    $challengeReference = $this->postJson($challengeUrl, [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'stored-value-acceptance:challenge'])
        ->assertCreated()
        ->json('data.reference');
    $this->postJson($challengeUrl.'/'.$challengeReference.'/verification', ['code' => '000000'])
        ->assertOk()
        ->assertJsonPath('data.status', 'verified');
    $spendUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends';
    $spendPayload = [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
        'otp_challenge_reference' => $challengeReference,
    ];
    $spendHeaders = ['Idempotency-Key' => 'stored-value-acceptance:fare'];

    $first = $this->postJson($spendUrl, $spendPayload, $spendHeaders)
        ->assertCreated()
        ->assertJsonPath('data.transaction.balance_after_minor', 97_500)
        ->json('data');
    $allocation = app(TreasuryAllocationReadModelContract::class)->read(
        new TreasuryAllocationReadModelQueryData($binding->allocation_reference, 'PHP'),
    );
    $drawCount = TreasuryAllocationOperation::query()
        ->where('operation_type', TreasuryAllocationOperationType::Draw)
        ->count();
    $merchantBalance = treasuryClientFundsLedger($merchant)->getBalanceIntAttribute();

    $this->postJson($spendUrl, $spendPayload, $spendHeaders)
        ->assertOk()
        ->assertJsonPath('data', $first)
        ->assertJsonPath('meta.idempotency.replayed', true);

    expect($activation->successful)->toBeTrue()
        ->and($allocation->usableAmountMinor)->toBe(97_500)
        ->and($allocation->drawnAmountMinor)->toBe(2_500)
        ->and($merchantBalance)->toBe(2_500)
        ->and(treasuryClientFundsLedger($merchant)->getBalanceIntAttribute())->toBe($merchantBalance)
        ->and(TreasuryAllocationOperation::query()
            ->where('operation_type', TreasuryAllocationOperationType::Draw)
            ->count())->toBe($drawCount)
        ->and(PartnerApiOperation::query()->count())->toBe(1)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBalanceBefore);
});

it('collects scanned stored value through OTP and frozen collector authorization exactly once', function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $issuer = storedValueAcceptanceUser('Scan Issuer');
    $holder = storedValueAcceptanceUser('Scan Holder', '09173011987');
    $collector = storedValueAcceptanceUser('Scan Collector');
    enableNetbankTreasuryForTests();
    $portfolios = app(TreasuryAccountPortfolioProvisioningContract::class);
    $issuerPortfolio = $portfolios->provision($issuer, ['netbank-primary']);
    $portfolios->provision($holder, ['netbank-primary']);
    $portfolios->provision($collector, ['netbank-primary']);
    $issuerClientFunds = collect($issuerPortfolio->positions)->firstWhere('purpose', TreasuryPositionPurpose::ClientFunds);
    $issuerReserve = collect($issuerPortfolio->positions)->firstWhere('purpose', TreasuryPositionPurpose::PayCodeReserve);
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 100_000,
        currency: 'PHP',
        evidenceReference: 'stored-value-scan:funding',
    );
    app(TreasuryPositionOperationContract::class)->reserve(new TreasuryPositionReservationData(
        operationReference: 'stored-value-acceptance:reservation',
        sourcePositionReference: $issuerClientFunds->positionReference,
        destinationPositionReference: $issuerReserve->positionReference,
        amountMinor: 100_000,
        currency: 'PHP',
        idempotencyKey: 'stored-value-acceptance:reservation:key',
        externalReference: 'stored-value-scan:voucher',
    ));
    $voucher = storedValueAcceptanceVoucher();
    $this->actingAs($holder);
    app(ExecutionEngine::class)->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '+639173011987', 'country' => 'PH']),
        voucherCode: $voucher->code,
        voucher: $voucher,
        instruction: ExecutionInstructionData::from(data_get($voucher->metadata, 'instructions.execution')),
        correlation: ['execution_id' => 'stored-value-scan:activation'],
    ));

    $otp = new FakeOtpChallengeGateway;
    $this->app->instance(OtpChallengeGateway::class, $otp);
    $this->actingAs($collector);
    $start = $this->postJson('/x/cockpit/stored-value-collections', [
        'code' => (string) $voucher->code,
        'amount_minor' => 500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'sale-001'])->assertCreated();
    $reference = $start->json('data.reference');
    $start->assertJsonMissingPath('data.available_minor');
    $this->postJson('/x/cockpit/stored-value-collections', [
        'code' => '|| '.$voucher->code.' ||',
        'amount_minor' => 500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'sale-001'])->assertCreated()->assertJsonPath('data.reference', $reference);
    $this->postJson('/x/cockpit/stored-value-collections', [
        'code' => (string) $voucher->code,
        'amount_minor' => 600,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'sale-001'])->assertUnprocessable();
    $this->postJson('/x/cockpit/stored-value-collections', [
        'code' => '| '.$voucher->code.' |',
        'amount_minor' => 500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'sale-bad-mark'])->assertUnprocessable();
    $this->actingAs($issuer)->getJson('/x/cockpit/stored-value-collections/'.$reference)->assertNotFound();
    $this->actingAs($collector);
    $this->getJson('/x/cockpit/stored-value-collections/'.$reference)
        ->assertOk()->assertJsonMissingPath('data.available_minor');
    $this->postJson('/x/cockpit/stored-value-collections/'.$reference.'/commit')
        ->assertUnprocessable();
    $this->postJson('/x/cockpit/stored-value-collections/'.$reference.'/verification', [
        'code' => '123456',
    ])->assertUnprocessable();
    $this->postJson('/x/cockpit/stored-value-collections/'.$reference.'/verification', [
        'code' => '000000',
    ])->assertOk()->assertJsonPath('data.available_minor', 100_000);
    $this->postJson('/x/cockpit/stored-value-collections/'.$reference.'/commit')
        ->assertOk()->assertJsonPath('data.balance_after_minor', 99_500);
    $this->postJson('/x/cockpit/stored-value-collections/'.$reference.'/commit')
        ->assertOk()->assertJsonPath('data.balance_after_minor', 99_500);

    expect(treasuryClientFundsLedger($collector)->getBalanceIntAttribute())->toBe(500)
        ->and(TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count())->toBe(1);
    $this->actingAs($issuer)->getJson('/x/cockpit/stored-value-collections/'.$reference)->assertNotFound();

    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Scanner Partner',
        issuer: $collector,
        scopes: ['stored-value:spend'],
        mandate: ['stored_value_spend' => [
            'enabled' => true,
            'currencies' => ['PHP'],
            'maximum_amount_minor' => 1_000,
            'daily_amount_minor' => 1_000,
        ]],
    );
    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), ['stored-value:spend']);
    $partnerUrl = '/api/partner/v1/stored-value-collections';
    $partnerReference = $this->postJson($partnerUrl, [
        'code' => (string) $voucher->code,
        'amount_minor' => 100,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'partner-scan-001'])->assertCreated()->json('data.reference');
    $this->postJson($partnerUrl.'/'.$partnerReference.'/verification', ['code' => '000000'])
        ->assertOk()->assertJsonPath('data.available_minor', 99_500);
    $this->postJson($partnerUrl.'/'.$partnerReference.'/commit')
        ->assertOk()->assertJsonPath('data.balance_after_minor', 99_400);
    expect(treasuryClientFundsLedger($collector)->getBalanceIntAttribute())->toBe(600)
        ->and(TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count())->toBe(2);

    $escrow = CommercialPrincipal::query()->create([
        'reference' => 'scan-escrow',
        'legal_name' => 'Scan Escrow',
        'authorization_reference' => 'scan-escrow-approved',
        'active' => true,
    ]);
    $portfolios->provision($escrow, ['netbank-primary']);
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'scan-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Scan Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'scan-escrow-approved');
    config()->set('x-change.checkout_console.owner_password', 'local-test-password');
    $this->post('/x/operations/unlock', ['password' => 'local-test-password'])->assertRedirect();
    $operationsUrl = '/x/operations/stored-value-collections';
    $operationsReference = $this->postJson($operationsUrl, [
        'code' => (string) $voucher->code,
        'amount_minor' => 100,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'operations-scan-001'])->assertCreated()->json('data.reference');
    $this->postJson($operationsUrl.'/'.$operationsReference.'/verification', ['code' => '000000'])
        ->assertOk()->assertJsonPath('data.available_minor', 99_400);
    $this->postJson($operationsUrl.'/'.$operationsReference.'/commit')
        ->assertOk()->assertJsonPath('data.balance_after_minor', 99_300);
    $escrowPosition = TreasuryPosition::query()
        ->whereMorphedTo('principal', $escrow)
        ->where('provider', 'netbank')
        ->where('purpose', TreasuryPositionPurpose::ClientFunds)
        ->sole();
    expect(Wallet::query()->findOrFail($escrowPosition->internal_ledger_id)->getBalanceIntAttribute())->toBe(100)
        ->and(TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count())->toBe(3);

    $overdrawReference = $this->postJson($operationsUrl, [
        'code' => (string) $voucher->code,
        'amount_minor' => 99_400,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'operations-overdraw'])->assertCreated()->json('data.reference');
    $this->postJson($operationsUrl.'/'.$overdrawReference.'/verification', ['code' => '000000'])
        ->assertOk()->assertJsonPath('data.available_minor', 99_300);
    $this->postJson($operationsUrl.'/'.$overdrawReference.'/commit')->assertUnprocessable();
    expect(TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count())->toBe(3);
    $this->post('/x/operations/lock')->assertRedirect();
    $this->post('/x/operations/unlock', ['password' => 'local-test-password'])->assertRedirect();
    $this->getJson($operationsUrl.'/'.$operationsReference)->assertNotFound();
    expect(ExecutionJournalEntry::query()->where('event_type', 'stored_value.collection.committed')->count())->toBe(3);
    $journalPayload = ExecutionJournalEntry::query()
        ->where('event_type', 'stored_value.collection.committed')
        ->firstOrFail()->payload;
    expect(json_encode($journalPayload))->not->toContain('09173011987', '000000', (string) $voucher->code);

    expect(app(ScenarioRunnerRegistry::class)->has('stored_value_collection_simulation'))->toBeTrue();
    $scenario = app(StoredValueCollectionScenarioRunner::class)->run(new ScenarioRunContext(
        output: new NullLifecycleOutput,
        scenarioKey: 'stored_value_collection_simulation',
        scenario: ['stored_value_collection' => [
            'holder_email' => $holder->email,
            'collector_email' => $collector->email,
            'amount_minor' => 50,
        ]],
        issuer: $issuer,
        generated: null,
        voucher: $voucher,
        attempts: [],
        baseClaimMobile: '09173011987',
        estimate: [],
        idempotencyKey: 'stored-value-collection-scenario',
        readiness: app(SettlementEnvelopeReadinessContract::class),
    ));
    expect($scenario->exitCode)->toBe(0)
        ->and($scenario->payload['success'])->toBeTrue()
        ->and($scenario->payload['draws_added'])->toBe(1)
        ->and($scenario->payload['rollback_completed'])->toBeTrue()
        ->and(TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count())->toBe(3)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'stored_value.collection.committed')->count())->toBe(3);

    $flakyOtp = Mockery::mock(OtpChallengeGateway::class);
    $flakyOtp->shouldReceive('create')->once()->andThrow(new RuntimeException('OTP transport unavailable'));
    $retryService = new StoredValueCollectionService(
        $flakyOtp,
        app(ExecutionEngine::class),
        app(TreasuryAllocationReadModelContract::class),
        app(AppendableEventStoreContract::class),
    );
    expect(fn () => $retryService->start($collector, 'cockpit', (string) $voucher->code, 50, 'PHP', 'flaky-delivery'))
        ->toThrow(RuntimeException::class);
    $flakyOtp->shouldReceive('create')->once()->andReturn(new OtpChallengeData(
        reference: 'otp-recovered', status: 'pending', expires_in: 600,
    ));
    $retried = $retryService->start($collector, 'cockpit', (string) $voucher->code, 50, 'PHP', 'flaky-delivery');
    expect($retried['data']['status'])->toBe('pending')
        ->and(StoredValueCollection::query()->where('collector_channel', 'cockpit')->where('amount_minor', 50)->count())->toBe(1);

    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution.metadata.stored_value.collection_authorization', [
        'schema' => 'x-change.stored-value-collection-authorization.v1',
        'mode' => 'otp_above_threshold',
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();
    config()->set('x-change.execution.stored_value.collection.staff', [
        'enabled' => true,
        'maximum_amount_minor' => 50,
        'daily_amount_minor' => 1_000,
    ]);
    config()->set('x-change.execution.stored_value.collection.fixed_amount_minor', 50);
    Gate::define('x-change.stored-value.collect', fn (User $operator): bool => $operator->is($collector));
    $noOtp = Mockery::mock(OtpChallengeGateway::class);
    $noOtp->shouldNotReceive('create');
    $this->app->instance(OtpChallengeGateway::class, $noOtp);

    $collectorScenario = app(StoredValueCollectionScenarioRunner::class)->run(new ScenarioRunContext(
        output: new NullLifecycleOutput,
        scenarioKey: 'stored_value_collection_simulation',
        scenario: ['stored_value_collection' => [
            'holder_email' => $holder->email,
            'collector_email' => $collector->email,
            'amount_minor' => 50,
        ]],
        issuer: $issuer,
        generated: null,
        voucher: $voucher,
        attempts: [],
        baseClaimMobile: '09173011987',
        estimate: [],
        idempotencyKey: 'stored-value-collector-scenario',
        readiness: app(SettlementEnvelopeReadinessContract::class),
    ));
    expect($collectorScenario->payload['success'])->toBeTrue()
        ->and($collectorScenario->payload['authorization_method'])->toBe('collector')
        ->and($collectorScenario->payload['rollback_completed'])->toBeTrue();

    $this->actingAs($issuer)->postJson('/x/cockpit/stored-value-collections', [
        'code' => (string) $voucher->code,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'unapproved-staff'])->assertNotFound();
    $this->actingAs($collector)->postJson('/x/cockpit/stored-value-collections', [
        'code' => (string) $voucher->code,
        'amount_minor' => 51,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'wrong-fare'])->assertUnprocessable();
    $ready = $this->postJson('/x/cockpit/stored-value-collections', [
        'code' => '|| '.$voucher->code.' ||',
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'collector-only-001'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.authorization_method', 'collector');
    $readyReference = $ready->json('data.reference');
    $this->withHeader('X-Inertia', 'true')
        ->get('/x/cockpit/stored-value-collections?journey=collection_exception')
        ->assertOk()
        ->assertJsonPath('props.recent_collections.0.reference', $readyReference);
    $this->actingAs($issuer)->get('/x/cockpit/stored-value-collections?journey=collection_exception')
        ->assertOk()
        ->assertDontSee($readyReference);
    $this->actingAs($collector);
    $this->withoutHeader('X-Inertia');
    $this->postJson('/x/cockpit/stored-value-collections/'.$readyReference.'/verification', [
        'code' => '000000',
    ])->assertUnprocessable();
    config()->set('x-change.execution.stored_value.collection.staff.enabled', false);
    $this->postJson('/x/cockpit/stored-value-collections/'.$readyReference.'/commit')
        ->assertNotFound();
    config()->set('x-change.execution.stored_value.collection.staff.enabled', true);
    $this->postJson('/x/cockpit/stored-value-collections/'.$readyReference.'/commit')
        ->assertOk()
        ->assertJsonPath('data.receipt.authorization_method', 'collector');
    $this->withHeader('X-Inertia', 'true')
        ->get('/x/cockpit/stored-value-collections?journey=tap_activity')
        ->assertOk()
        ->assertJsonPath('props.recent_collections.0.reference', $readyReference);
    $this->get('/x/cockpit/stored-value-collections?journey=collection_exception')
        ->assertOk()
        ->assertDontSee($readyReference);
    $this->withoutHeader('X-Inertia');
    $this->postJson('/x/cockpit/stored-value-collections/'.$readyReference.'/commit')
        ->assertOk()
        ->assertJsonPath('data.receipt.authorization_method', 'collector');
    expect(TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count())->toBe(4)
        ->and(StoredValueCollection::query()->where('reference', $readyReference)->sole()->proof_reference_hash)->toBeNull();
    config()->set('x-change.execution.stored_value.collection.staff.daily_amount_minor', 550);
    $this->postJson('/x/cockpit/stored-value-collections', [
        'code' => (string) $voucher->code,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'daily-limit'])->assertUnprocessable();

    $this->app['auth']->guard('api')->forgetUser();
    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), ['stored-value:spend']);
    $partnerReady = $this->postJson($partnerUrl, [
        'code' => (string) $voucher->code,
        'amount_minor' => 50,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'partner-collector-only-001'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.authorization_method', 'collector');
    $this->postJson($partnerUrl.'/'.$partnerReady->json('data.reference').'/commit')
        ->assertOk()
        ->assertJsonPath('data.receipt.authorization_method', 'collector');
});

it('limits the operations collection page to the password-unlocked escrow owner session', function (): void {
    CommercialPrincipal::query()->create([
        'reference' => 'scanner-escrow',
        'legal_name' => 'Scanner Escrow',
        'authorization_reference' => 'scanner-escrow-approval',
        'active' => true,
    ]);
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'scanner-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Scanner Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'scanner-escrow-approval');
    config()->set('x-change.checkout_console.owner_password', 'local-test-password');

    $this->get('/x/operations/stored-value-collections')->assertNotFound();
    $this->post('/x/operations/unlock', ['password' => 'local-test-password'])->assertRedirect();
    $this->withHeader('X-Inertia', 'true')
        ->get('/x/operations/stored-value-collections')
        ->assertOk()
        ->assertJsonPath('component', 'x-change/pos/CollectStoredValue');
    $this->postJson('/x/operations/stored-value-collections', [
        'code' => 'UNKNOWN',
        'amount_minor' => 100,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'operations-001'])->assertNotFound();
});

function storedValueAcceptanceUser(string $name, ?string $mobile = null): User
{
    $user = User::query()->create([
        'name' => $name,
        'email' => str()->slug($name).'-'.str()->uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    $user->forceFill([
        'mobile' => $mobile,
        'mobile_verified_at' => $mobile !== null ? now()->utc() : null,
    ])->save();
    fundTestUserWallet($user, 0);

    return $user;
}

function storedValueAcceptanceVoucher(): Voucher
{
    return Voucher::query()->create([
        'code' => 'SVAC',
        'state' => 'active',
        'metadata' => [
            'treasury' => [
                'pay_code_reservation' => [
                    'status' => 'reserved',
                    'operation_reference' => 'stored-value-acceptance:reservation',
                    'amount_minor' => 100_000,
                    'currency' => 'PHP',
                ],
            ],
            'instructions' => [
                'execution' => [
                    'schema' => 'voucher.execution.v1',
                    'driver' => 'stored_value',
                    'metadata' => [
                        'post_redemption' => ['mode' => 'execution_only'],
                        'stored_value' => [
                            'initial_balance' => 100_000,
                            'max_balance' => 100_000,
                            'replenishable' => false,
                            'otp_required_above' => 1_000,
                        ],
                    ],
                ],
            ],
        ],
    ]);
}
