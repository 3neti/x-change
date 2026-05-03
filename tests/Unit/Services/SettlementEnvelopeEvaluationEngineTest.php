<?php

declare(strict_types=1);

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
