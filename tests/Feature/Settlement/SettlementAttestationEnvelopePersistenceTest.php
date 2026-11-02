<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Actions\Settlement\VerifyPhilhealthSettlementAmount;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Data\Settlement\SettlementExecutionResultData;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XChange\Services\SettlementEnvelopeMetadataSyncService;

beforeEach(function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());
});

it('records the hospital supplied claim reference during authorized review', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 3).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    $reviewer = actingAsTestUser();
    Gate::define('x-change.settlement.verify-amount', fn (): bool => true);
    $voucher = issueVoucher(validVoucherInstructions(overrides: ['voucher_type' => 'settlement', 'target_amount' => 100, 'metadata' => ['flow_type' => 'settlement', 'collection_wallet_id' => $reviewer->wallet->id]]));
    $claim = $voucher->claims()->create(['meta' => ['evidence' => ['manifest_sha256' => str_repeat('a', 64)]]]);
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', ['schema' => 'voucher.execution.v2', 'settlement' => ['driver' => 'philhealth-bst']]);
    data_set($metadata, 'settlement_envelope.attestation', ['claim_id' => $claim->id, 'evidence_manifest_sha256' => str_repeat('a', 64)]);
    data_set($metadata, 'settlement_envelope.payload', ['patient_name' => 'Patient', 'patient_mobile' => '09171234567']);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $envelope = app(EnvelopeService::class)->create($voucher->code, 'philhealth.bst.demo', '1.0.0', $voucher);
    app(VerifyPhilhealthSettlementAmount::class)
        ->handle($voucher->fresh(), $reviewer, 10000, 'review-1', str_repeat('a', 64), 'CLAIM-123');
    expect($envelope->fresh()->payload['reference'])->toBe('CLAIM-123')
        ->and($envelope->fresh()->getSignalBool('amount_verified'))->toBeTrue()
        ->and(data_get($voucher->fresh()->metadata, 'settlement_envelope.payload.reference'))->toBe('CLAIM-123');
});

it('projects complete patient attestation into the dedicated envelope without changing its reference', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 3).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    $voucher = issueVoucher(validVoucherInstructions(overrides: ['metadata' => ['flow_type' => 'settlement']]));
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', ['schema' => 'voucher.execution.v2', 'settlement' => ['driver' => 'philhealth-bst']]);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $envelope = app(EnvelopeService::class)->create($voucher->code, 'philhealth.bst.demo', '1.0.0', $voucher);
    $payload = ['inputs' => ['name' => 'Patient', 'mobile' => '09171234567'], 'settlement' => ['payload' => ['reference' => 'CLAIM-123']]];
    app(SettlementEnvelopeMetadataSyncService::class)->syncPatientAttestation($voucher, $payload);
    expect($envelope->fresh()->payload)->toMatchArray(['patient_name' => 'Patient', 'patient_mobile' => '09171234567', 'reference' => 'CLAIM-123']);
    data_set($payload, 'settlement.payload.reference', 'OTHER-123');
    expect(fn () => app(SettlementEnvelopeMetadataSyncService::class)->syncPatientAttestation($voucher->fresh(), $payload))
        ->toThrow(LogicException::class, 'reference cannot be changed');
    expect($envelope->fresh()->payload['reference'])->toBe('CLAIM-123');
});

it('links v2 attestation through the claim entry point to persisted evidence before settlement execution', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => ['fields' => ['name']],
        'metadata' => ['flow_type' => 'settlement', 'settlement_driver' => 'philhealth-bst'],
    ]));
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
        'settlement' => ['driver' => 'philhealth-bst'],
        'events' => ['attest' => ['actor' => 'beneficiary']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();

    $executor = Mockery::mock(SettlementExecutionContract::class);
    $executor->shouldReceive('execute')->once()->withArgs(function ($actual, $payload): bool {
        return data_get($actual->metadata, 'settlement_envelope.attestation.claim_id') !== null
            && data_get($actual->metadata, 'settlement_envelope.attestation.evidence_manifest_sha256') !== null;
    })->andReturn(new SettlementExecutionResultData(voucher_code: $voucher->code, status: 'blocked', message: 'Awaiting review'));
    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')->once()->andReturn($executor);
    app()->instance(ClaimExecutionFactoryContract::class, $factory);

    $result = app(SubmitPayCodeClaim::class)->handle($voucher->refresh(), [
        'inputs' => ['name' => 'Patient'], '_meta' => ['idempotency_key' => 'v2-attestation'],
    ]);
    $claim = $voucher->claims()->sole();
    expect($result->status)->toBe('blocked')
        ->and(data_get($voucher->fresh()->metadata, 'settlement_envelope.attestation.claim_id'))->toBe($claim->id)
        ->and(data_get($voucher->fresh()->metadata, 'settlement_envelope.attestation.evidence_manifest_sha256'))
        ->toBe(data_get($claim->meta, 'evidence.manifest_sha256'));
});

it('makes patient attestation payload available to settlement collection gate context', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = app(SettlementEnvelopeMetadataSyncService::class)
        ->syncPatientAttestation($voucher, [
            'inputs' => [
                'name' => 'Juan Dela Cruz',
            ],
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
        ]);

    $context = app(SettlementCollectionGate::class)
        ->contextFromVoucher($voucher);

    expect($context['driver'])->toBe('philhealth-bst')
        ->and($context['payload'])->toMatchArray([
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ]);
});

it('keeps settlement collection blocked after attestation until amount is verified', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = app(SettlementEnvelopeMetadataSyncService::class)
        ->syncPatientAttestation($voucher, [
            'inputs' => [
                'name' => 'Juan Dela Cruz',
            ],
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
        ]);

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: app(SettlementCollectionGate::class)->contextFromVoucher($voucher),
    );
})->throws(VoucherRequiresSettlementEnvelope::class);
