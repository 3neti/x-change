<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
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
