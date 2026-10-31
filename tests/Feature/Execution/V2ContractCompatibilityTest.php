<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\CommercialPricingChanged;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use LBHurtado\XChange\Services\Execution\VoucherValueFlowPolicy;
use LBHurtado\XChange\Services\Execution\XChangeSettlementEnvelopeExecutionGateway;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XChange\Services\ValueFlowIssuancePolicy;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;

it('resolves the same destination from explicit v1 and v2 stored instructions', function (string $schema, array $execution): void {
    $voucher = new Voucher;
    $voucher->voucher_type = VoucherType::PAYABLE;
    $voucher->metadata = ['instructions' => ['execution' => ['schema' => $schema, ...$execution]]];
    expect(app(VoucherValueFlowPolicy::class)->destination($voucher))->toBe('instapay.gcash.mobile:09179999999');
})->with([
    'v1' => ['voucher.execution.v1', ['metadata' => ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]]],
    'v2' => ['voucher.execution.v2', ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]],
]);

it('preserves validated v2 value flow at the v2 path', function (): void {
    $normalized = app(ValueFlowIssuancePolicy::class)->normalize(['voucher_type' => 'settlement', 'execution' => ['schema' => 'voucher.execution.v2', 'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]]);
    expect(data_get($normalized, 'execution.value_flow.destination'))->toBe('instapay.gcash.mobile:09179999999')
        ->and(data_get($normalized, 'execution.metadata.value_flow'))->toBeNull();
});

it('rejects conflicting v2 value flow locations', function (): void {
    expect(fn () => app(ValueFlowIssuancePolicy::class)->normalize(['voucher_type' => 'settlement', 'value_flow' => ['destination' => 'account.mobile:09171234567.client_funds'], 'execution' => ['schema' => 'voucher.execution.v2']]))->toThrow(ValidationException::class);
});

it('feeds equivalent settlement metadata to the real gateway', function (string $schema, array $params): void {
    $instruction = ExecutionInstructionData::from(['schema' => $schema, 'driver' => 'settlement_envelope', ...$params]);
    $context = new ExecutionContextData(contact: null, voucherCode: 'AA317', instruction: $instruction);
    $loaded = app(XChangeSettlementEnvelopeExecutionGateway::class)->load($context);
    expect($loaded['reference'])->toBe('ENV-1')->and($loaded['readiness_gate'])->toBe('settleable');
})->with([
    'v1' => ['voucher.execution.v1', ['metadata' => ['settlement_envelope' => ['reference' => 'ENV-1', 'driver' => 'philhealth-bst', 'readiness_gate' => 'settleable']]]],
    'v2' => ['voucher.execution.v2', ['settlement' => ['reference' => 'ENV-1', 'driver' => 'philhealth-bst', 'readiness_gate' => 'settleable']]],
]);

it('dispatches v1 and v2 through the same registered execution engine driver', function (string $schema): void {
    $instruction = ExecutionInstructionData::from(['schema' => $schema, 'driver' => 'settlement_envelope']);
    $context = new ExecutionContextData(contact: null, voucherCode: 'AA317', instruction: $instruction);
    expect(app(ExecutionEngine::class)->driverKeyFor($context))->toBe('settlement_envelope');
})->with(['voucher.execution.v1', 'voucher.execution.v2']);

it('uses v2 settlement configuration with separately persisted evidence', function (): void {
    $voucher = new Voucher;
    $voucher->setRawAttributes(['instructions' => json_encode(['metadata' => []]), 'metadata' => json_encode(['instructions' => ['execution' => ['schema' => 'voucher.execution.v2', 'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable']]], 'settlement_envelope' => ['driver' => 'philhealth-bst', 'checklist' => ['amount_verified' => true]]])]);
    $context = app(SettlementCollectionGate::class)->contextFromVoucher($voucher);
    expect($context['driver'])->toBe('philhealth-bst')->and($context['checklist']['amount_verified'])->toBeTrue();
});

it('fails closed when envelope and v2 instruction disagree', function (): void {
    $voucher = new Voucher;
    $voucher->setRawAttributes(['instructions' => json_encode(['metadata' => []]), 'metadata' => json_encode(['instructions' => ['execution' => ['schema' => 'voucher.execution.v2', 'settlement' => ['driver' => 'philhealth-bst']]], 'settlement_envelope' => ['driver' => 'other']])]);
    expect(fn () => app(SettlementCollectionGate::class)->contextFromVoucher($voucher))->toThrow(InvalidArgumentException::class);
});

it('executes settlement driver for both versions through the real execution engine', function (string $schema, array $params): void {
    $instruction = ExecutionInstructionData::from(['schema' => $schema, 'driver' => 'settlement_envelope', ...$params]);
    $context = new ExecutionContextData(contact: null, voucherCode: 'AA317', instruction: $instruction);
    $result = app(ExecutionEngine::class)->execute($context);
    expect($result->successful)->toBeTrue()->and($result->driver)->toBe('settlement_envelope');
})->with([
    'v1' => ['voucher.execution.v1', ['metadata' => ['settlement_envelope' => ['reference' => 'ENV-1', 'driver' => 'philhealth-bst', 'readiness_gate' => 'settleable']]]],
    'v2' => ['voucher.execution.v2', ['settlement' => ['reference' => 'ENV-1', 'driver' => 'philhealth-bst', 'readiness_gate' => 'settleable']]],
]);

it('uses v1 and v2 destinations in the real value-flow payout service without a second submission', function (string $schema, array $execution): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 100, 'max_amount' => 5_000_000]);
    $owner = actingAsTestUser(0);
    fundTestUserClientFunds($owner, 10000);
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(inventoryReference: 'inventory:netbank:vca-cash', resourceType: 'cash_at_bank', currency: 'PHP', capacityMinor: 0, status: 'requested', idempotencyKey: 'register:v2-test', externalReference: 'resource:netbank:corporate-vca'));
    $inventory->recognize(new TreasuryInventoryRecognitionData(operationReference: 'v2:test-funding', inventoryReference: 'inventory:netbank:vca-cash', settlementResourceReference: 'resource:netbank:corporate-vca', amountMinor: 10000, currency: 'PHP', status: 'requested', idempotencyKey: 'v2:test-funding', externalReference: 'v2:test'));
    $voucher = Voucher::query()->create(['code' => 'V2'.Str::upper(Str::random(6)), 'voucher_type' => VoucherType::PAYABLE, 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'metadata' => ['instructions' => ['execution' => ['schema' => $schema, ...$execution]]]]);
    $voucher->forceFill(['voucher_type' => VoucherType::PAYABLE])->save();
    $voucher->owner()->associate($owner);
    $voucher->save();
    $service = app(ValueFlowPayoutService::class);
    $source = $service->bridge($voucher, 'netbank-primary', 'PHP');
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2500, 'v2-collection-one'));
    $replay = DB::transaction(fn () => $service->prepare($voucher, $source, 2500, 'v2-collection-one'));
    $provider = fakePayoutProvider()->willReturnPendingResult(provider: 'netbank');
    $service->submit($payout->id);
    $service->submit($payout->id);
    expect($replay->id)->toBe($payout->id)->and($provider->disburseCallCount)->toBe(1)->and($provider->lastRequest->account_number)->toBe('09179999999')->and($payout->fresh()->status)->toBe('pending');
})->with([
    'v1' => ['voucher.execution.v1', ['metadata' => ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]]],
    'v2' => ['voucher.execution.v2', ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]],
]);

it('issues a payable using the versioned destination without discarding the instruction', function (string $schema, array $execution): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:v2-compatibility');
    $issuer = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    Queue::fake();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $payload = validPayCodePayload(0, 'INSTAPAY', ['provider' => 'netbank', 'issuer_id' => $issuer->getKey(), 'voucher_type' => 'payable', 'target_amount' => 100, 'fee_bearer' => 'payer', 'inputs' => ['fields' => []], 'feedback' => [], 'execution' => ['schema' => $schema, ...$execution]]);
    $result = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($result->voucher_id);
    expect(data_get($voucher->metadata, 'instructions.execution.schema'))->toBe($schema)
        ->and(app(VoucherValueFlowPolicy::class)->destination($voucher))->toBe('instapay.gcash.mobile:09179999999')
        ->and($voucher->instructions->cash->amount)->toBe(0.0);
})->with([
    'v1' => ['voucher.execution.v1', ['metadata' => ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]]],
    'v2' => ['voucher.execution.v2', ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]],
]);

it('rejects issuance of v2 instructions with event declarations until event execution is wired', function (): void {
    expect(fn () => app(VoucherIssuancePayloadNormalizer::class)->normalize([
        'cash' => ['amount' => 0, 'currency' => 'PHP'], 'voucher_type' => 'settlement', 'target_amount' => 100,
        'execution' => ['schema' => 'voucher.execution.v2', 'events' => ['attest' => ['actor' => 'beneficiary']]],
    ]))->toThrow(ValidationException::class, 'V2 event execution is not implemented');
});

it('accepts a verified-payment event for a payable voucher and rejects unsupported prerequisites', function (): void {
    $input = [
        'cash' => ['amount' => 0, 'currency' => 'PHP'], 'voucher_type' => 'payable', 'target_amount' => 100,
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default',
            'events' => ['settle' => ['actor' => 'payer', 'requires' => ['confirmed_payment'], 'surface' => 'pay', 'authentication' => 'optional']]],
        'feedback' => ['webhooks' => [['url' => 'https://example.test/events', 'events' => ['settlement.payment_collected']]]],
    ];

    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize($input);
    expect(data_get($normalized, 'execution.events.settle.actor'))->toBe('payer')
        ->and(data_get($normalized, 'metadata.flow_type'))->not->toBe('settlement');

    $payoutSubscription = $input;
    data_set($payoutSubscription, 'feedback.webhooks.0.events', ['settlement.payout_failed']);
    expect(fn () => app(VoucherIssuancePayloadNormalizer::class)->normalize($payoutSubscription))->toThrow(ValidationException::class);
    data_set($payoutSubscription, 'execution.value_flow.destination', 'instapay.gcash.mobile:09179999999');
    expect(data_get(app(VoucherIssuancePayloadNormalizer::class)->normalize($payoutSubscription), 'feedback.webhooks.0.events'))->toBe(['settlement.payout_failed']);
    $requiresAuthentication = $input;
    data_set($requiresAuthentication, 'execution.events.settle.authentication', 'required');
    expect(fn () => app(VoucherIssuancePayloadNormalizer::class)->normalize($requiresAuthentication))->toThrow(ValidationException::class);

    data_set($input, 'execution.events.settle.requires', ['unimplemented_requirement']);
    expect(fn () => app(VoucherIssuancePayloadNormalizer::class)->normalize($input))->toThrow(ValidationException::class);
});

it('issues a payable voucher with its verified-payment subscription intact', function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:v2-payable-events');
    $issuer = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    Queue::fake();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $payload = validPayCodePayload(0, 'INSTAPAY', [
        'provider' => 'netbank', 'issuer_id' => $issuer->getKey(), 'voucher_type' => 'payable',
        'target_amount' => 100, 'fee_bearer' => 'payer',
        'feedback' => ['webhooks' => [['url' => 'https://example.test/events', 'events' => ['settlement.payment_collected']]]],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default',
            'events' => ['settle' => ['actor' => 'payer', 'requires' => ['confirmed_payment'], 'surface' => 'pay', 'authentication' => 'optional']]],
    ]);

    $payload['cash']['validation'] = [];
    $quote = app(EstimatePayCodeCost::class)->handle($payload);
    $payload['pricing'] = [
        'quote_reference' => $quote->commercial_quote_reference,
        'currency' => $quote->currency,
        'fee_bearer' => 'payer',
        'quote_hash' => substr($quote->commercial_quote_reference, strlen('commercial-quote:')),
    ];
    $tampered = $payload;
    $tampered['pricing']['quote_reference'] = 'commercial-quote:'.str_repeat('0', 64);
    expect(fn () => app(GeneratePayCode::class)->handle($tampered))
        ->toThrow(CommercialPricingChanged::class);
    $tampered = $payload;
    $tampered['pricing']['quote_hash'] = str_repeat('0', 64);
    expect(fn () => app(GeneratePayCode::class)->handle($tampered))
        ->toThrow(CommercialPricingChanged::class);
    $tampered = $payload;
    $tampered['pricing']['fee_bearer'] = 'issuer';
    expect(fn () => app(GeneratePayCode::class)->handle($tampered))->toThrow(ValidationException::class);
    $tampered = $payload;
    $tampered['pricing']['currency'] = 'USD';
    expect(fn () => app(GeneratePayCode::class)->handle($tampered))->toThrow(CommercialPricingChanged::class);
    $tampered = $payload;
    $tampered['count'] = 2;
    expect(fn () => app(GeneratePayCode::class)->handle($tampered))->toThrow(CommercialPricingChanged::class);
    expect(fn () => app(PayCodeIssuanceContract::class)->issue($issuer, $tampered))
        ->toThrow(CommercialPricingChanged::class);

    $result = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($result->voucher_id);
    expect(data_get($voucher->metadata, 'instructions.execution.events.settle.requires'))->toBe(['confirmed_payment'])
        ->and(data_get($voucher->metadata, 'instructions.feedback.webhooks.0.events'))->toBe(['settlement.payment_collected'])
        ->and(data_get($voucher->metadata, 'instructions.pricing.quote_reference'))->toBe($quote->commercial_quote_reference);
});

it('applies v2 presentation to the stamp and preserves its share metadata', function (): void {
    $design = (string) config('x-change.experience.default_system_stamp_design', 'steampunk');
    $version = app(RiderStampDesignRegistry::class)->resolve($design, null)['version'];
    $input = [
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'execution' => ['schema' => 'voucher.execution.v2'],
        'presentation' => ['stamp' => ['design_id' => $design, 'design_version' => $version], 'share' => ['title' => 'Hospital settlement', 'image_source' => 'stamp']],
    ];
    $normalized = app(VoucherIssuancePayloadNormalizer::class)->normalize($input);
    expect(data_get($normalized, 'rider.stamp.design_id'))->toBe($design)
        ->and(data_get($normalized, 'presentation.share.title'))->toBe('Hospital settlement');
    data_set($input, 'rider.stamp.design_id', 'conflicting');
    expect(fn () => app(VoucherIssuancePayloadNormalizer::class)->normalize($input))->toThrow(ValidationException::class);
});

it('uses explicit v2 share copy without changing the share image', function (): void {
    $voucher = issueVoucher();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution.schema', 'voucher.execution.v2');
    data_set($metadata, 'instructions.presentation.share', ['title' => 'Hospital settlement', 'description' => 'Claim details', 'image_source' => 'stamp']);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $share = app(ClaimShareMetadataResolverContract::class)->resolve($voucher, 'https://example.test/claim', 'https://example.test/card.png');
    expect($share->title)->toBe('Hospital settlement')->and($share->description)->toBe('Claim details')
        ->and($share->imageUrl)->toBe('https://example.test/card.png');
});

it('issues the supported v2 PhilHealth settlement through x-change with its events and destination', function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:v2-philhealth');
    $issuer = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    Queue::fake();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $payload = validPayCodePayload(0, 'INSTAPAY', [
        'provider' => 'netbank', 'issuer_id' => $issuer->getKey(), 'voucher_type' => 'settlement',
        'target_amount' => 100, 'fee_bearer' => 'payer', 'inputs' => ['fields' => ['name']],
        'feedback' => ['webhooks' => [['url' => 'https://example.test/hook', 'events' => ['settlement.ready', 'settlement.payment_collected', 'settlement.payout_failed', 'settlement.payout_confirmed']]]],
        'presentation' => ['share' => ['title' => 'Hospital settlement', 'image_source' => 'stamp']],
        'execution' => [
            'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
            'mode' => null, 'pipeline' => null, 'fallback' => null, 'visibility' => null, 'metadata' => null,
            'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'],
            'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'],
            'events' => ['attest' => ['actor' => 'beneficiary'], 'review' => ['actor' => 'hospital_reviewer', 'requires' => ['attestation_recorded'], 'surface' => 'claim', 'authentication' => 'required'], 'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready', 'confirmed_payment']]],
        ],
    ]);
    $result = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($result->voucher_id);
    expect(data_get($voucher->metadata, 'instructions.execution.events.settle.actor'))->toBe('payer')
        ->and(data_get($voucher->metadata, 'instructions.feedback.webhooks.0.events'))->toBe(['settlement.ready', 'settlement.payment_collected', 'settlement.payout_failed', 'settlement.payout_confirmed'])
        ->and(data_get($voucher->metadata, 'instructions.presentation.share.title'))->toBe('Hospital settlement')
        ->and(data_get($voucher->metadata, 'instructions.execution.settlement.driver'))->toBe('philhealth-bst')
        ->and(app(VoucherValueFlowPolicy::class)->destination($voucher))->toBe('instapay.gcash.mobile:09179999999')
        ->and($voucher->instructions->cash->amount)->toBe(0.0);
});

it('rejects unsupported or pre-populated v2 PhilHealth settlement instructions', function (string $path, mixed $value): void {
    $payload = [
        'cash' => ['amount' => 0, 'currency' => 'PHP'], 'voucher_type' => 'settlement', 'target_amount' => 100,
        'inputs' => ['fields' => ['name']],
        'execution' => [
            'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
            'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'],
            'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'],
            'events' => ['attest' => ['actor' => 'beneficiary'], 'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready', 'confirmed_payment']]],
        ],
    ];
    data_set($payload, $path, $value);
    expect(fn () => app(VoucherIssuancePayloadNormalizer::class)->normalize($payload))->toThrow(ValidationException::class);
})->with([
    ['execution.events.settle.requires', ['confirmed_payment']],
    ['execution.events.attest.actor', 'system'],
    ['execution.settlement.payment_requirement', 'linked_confirmed_payment'],
    ['execution.pipeline', ['untrusted']],
    ['metadata.settlement_envelope.checklist.amount_verified', true],
    ['metadata.flow_type', 'collectible'],
    ['cash.amount', 100],
    ['execution.events.attest.authentication', 'required'],
    ['execution.events.settle.authentication', 'required'],
    ['execution.events.review', ['actor' => 'hospital_reviewer', 'requires' => ['attestation_recorded'], 'surface' => 'claim', 'authentication' => 'anonymous']],
    ['execution.events.review', ['actor' => 'system', 'requires' => ['attestation_recorded'], 'surface' => 'claim', 'authentication' => 'required']],
    ['feedback.webhooks', [['url' => 'https://example.test/hook', 'events' => ['settlement.ready']]]],
    ['pricing', ['quote_reference' => 'quote-1']],
    ['campaign', ['template_id' => 'sample']],
]);
