<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeEvidenceData;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeProfileData;
use LBHurtado\XChange\Services\SettlementEnvelopeEvaluationEngine;
use LBHurtado\XChange\Services\SettlementEnvelopeEvidenceExtractor;
use LBHurtado\XChange\Services\SettlementEnvelopePreparationService;

it('marks philhealth bst as not ready when amount is not verified', function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $profile = app(SettlementEnvelopePreparationService::class)
        ->prepare($voucher, 'settleable');

    $evidence = app(SettlementEnvelopeEvidenceExtractor::class)
        ->extract($voucher, $profile, [
            'payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'checklist' => [
                'amount_verified' => false,
            ],
        ]);

    $result = app(SettlementEnvelopeEvaluationEngine::class)
        ->evaluate($profile, $evidence);

    expect($result->ready)->toBeFalse()
        ->and($result->satisfied)->toContain('payload_present')
        ->and($result->missing)->toContain('amount_verified');
});

it('marks philhealth bst as ready when payload exists and amount is verified', function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $profile = app(SettlementEnvelopePreparationService::class)
        ->prepare($voucher, 'settleable');

    $evidence = app(SettlementEnvelopeEvidenceExtractor::class)
        ->extract($voucher, $profile, [
            'payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'checklist' => [
                'amount_verified' => true,
            ],
        ]);

    $result = app(SettlementEnvelopeEvaluationEngine::class)
        ->evaluate($profile, $evidence);

    expect($result->ready)->toBeTrue()
        ->and($result->satisfied)->toContain('payload_present')
        ->and($result->satisfied)->toContain('amount_verified')
        ->and($result->missing)->toBeEmpty();
});

it('reports missing settleable gate conditions', function () {
    $profile = new SettlementEnvelopeProfileData(
        driver: 'philhealth-bst',
        gate: 'settleable',
        requires_envelope: true,
        required_payload_fields: [
            'patient_name',
            'patient_mobile',
        ],
        checklist_items: [
            'payload_present' => [
                'label' => 'Patient information captured',
                'auto' => true,
            ],
            'amount_verified' => [
                'label' => 'Reimbursement amount verified',
                'auto' => false,
            ],
        ],
        gate_conditions: [
            'payload_present',
            'amount_verified',
        ],
    );

    $evidence = new SettlementEnvelopeEvidenceData(
        payload: [],
        checklist: [
            'amount_verified' => false,
        ],
    );

    $result = app(SettlementEnvelopeEvaluationEngine::class)
        ->evaluate($profile, $evidence);

    expect($result->ready)->toBeFalse()
        ->and($result->missing)->toContain('payload_present')
        ->and($result->missing)->toContain('amount_verified')
        ->and($result->satisfied)->toBeEmpty();
});

it('evaluates ready when all gate conditions pass', function () {
    $profile = new SettlementEnvelopeProfileData(
        driver: 'philhealth-bst',
        gate: 'settleable',
        requires_envelope: true,
        required_payload_fields: [
            'patient_name',
            'patient_mobile',
        ],
        checklist_items: [
            'payload_present' => [
                'label' => 'Patient information captured',
                'auto' => true,
            ],
            'amount_verified' => [
                'label' => 'Reimbursement amount verified',
                'auto' => false,
            ],
        ],
        gate_conditions: [
            'payload_present',
            'amount_verified',
        ],
    );

    $evidence = new SettlementEnvelopeEvidenceData(
        payload: [
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ],
        checklist: [
            'amount_verified' => true,
        ],
    );

    $result = app(SettlementEnvelopeEvaluationEngine::class)
        ->evaluate($profile, $evidence);

    expect($result->ready)->toBeTrue()
        ->and($result->missing)->toBeEmpty()
        ->and($result->satisfied)->toContain('payload_present')
        ->and($result->satisfied)->toContain('amount_verified');
});

it('does not block readiness for checklist items outside the requested gate', function () {
    $profile = new SettlementEnvelopeProfileData(
        driver: 'philhealth-bst',
        gate: 'settleable',
        requires_envelope: true,
        required_payload_fields: [
            'patient_name',
        ],
        checklist_items: [
            'payload_present' => [
                'label' => 'Patient information captured',
                'auto' => true,
            ],
            'amount_verified' => [
                'label' => 'Amount verified',
                'auto' => false,
            ],
            'claim_documents_uploaded' => [
                'label' => 'Claim documents uploaded',
                'auto' => true,
            ],
        ],
        gate_conditions: [
            'payload_present',
            'amount_verified',
        ],
    );

    $evidence = new SettlementEnvelopeEvidenceData(
        payload: [
            'patient_name' => 'Juan Dela Cruz',
        ],
        documents: [],
        checklist: [
            'amount_verified' => true,
        ],
    );

    $result = app(SettlementEnvelopeEvaluationEngine::class)
        ->evaluate($profile, $evidence);

    expect($result->ready)->toBeTrue()
        ->and($result->missing)->not->toContain('claim_documents_uploaded')
        ->and($result->checklist['claim_documents_uploaded']['satisfied'])->toBeFalse();
});

it('passes when profile does not require a settlement envelope', function () {
    $profile = new SettlementEnvelopeProfileData(
        driver: 'none',
        gate: 'settleable',
        requires_envelope: false,
    );

    $evidence = new SettlementEnvelopeEvidenceData();

    $result = app(SettlementEnvelopeEvaluationEngine::class)
        ->evaluate($profile, $evidence);

    expect($result->ready)->toBeTrue()
        ->and($result->missing)->toBeEmpty()
        ->and($result->meta['requires_envelope'])->toBeFalse();
});
