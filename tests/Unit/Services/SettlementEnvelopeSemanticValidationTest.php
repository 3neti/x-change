<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\SettlementEnvelopeEvaluationEngine;
use LBHurtado\XChange\Services\SettlementEnvelopeEvidenceExtractor;
use LBHurtado\XChange\Services\SettlementEnvelopePreparationService;

beforeEach(function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());
});

function evaluatePhilhealthBstSettlement(array $context)
{
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
        ->extract($voucher, $profile, $context);

    return app(SettlementEnvelopeEvaluationEngine::class)
        ->evaluate($profile, $evidence);
}

it('requires patient payload before the envelope can be settleable', function () {
    $result = evaluatePhilhealthBstSettlement([
        'payload' => [],
        'checklist' => [
            'amount_verified' => true,
        ],
    ]);

    expect($result->ready)->toBeFalse()
        ->and($result->missing)->toContain('payload_present')
        ->and($result->satisfied)->toContain('amount_verified');
});

it('requires amount verification before the envelope can be settleable', function () {
    $result = evaluatePhilhealthBstSettlement([
        'payload' => [
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ],
        'checklist' => [
            'amount_verified' => false,
        ],
    ]);

    expect($result->ready)->toBeFalse()
        ->and($result->satisfied)->toContain('payload_present')
        ->and($result->missing)->toContain('amount_verified');
});

it('becomes settleable only when payload is present and amount is verified', function () {
    $result = evaluatePhilhealthBstSettlement([
        'payload' => [
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ],
        'checklist' => [
            'amount_verified' => true,
        ],
    ]);

    expect($result->ready)->toBeTrue()
        ->and($result->satisfied)->toContain('payload_present')
        ->and($result->satisfied)->toContain('amount_verified')
        ->and($result->missing)->toBeEmpty();
});

it('does not require claim documents for the settleable gate when not listed as a gate condition', function () {
    $result = evaluatePhilhealthBstSettlement([
        'payload' => [
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ],
        'documents' => [],
        'checklist' => [
            'amount_verified' => true,
        ],
    ]);

    expect($result->ready)->toBeTrue()
        ->and($result->checklist['claim_documents_uploaded']['satisfied'])->toBeFalse()
        ->and($result->missing)->not->toContain('claim_documents_uploaded');
});

it('tracks uploaded claim documents as checklist evidence', function () {
    $result = evaluatePhilhealthBstSettlement([
        'payload' => [
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ],
        'documents' => [
            'claim_form' => 's3://claims/claim-form.pdf',
        ],
        'checklist' => [
            'amount_verified' => true,
        ],
    ]);

    expect($result->ready)->toBeTrue()
        ->and($result->checklist['claim_documents_uploaded']['satisfied'])->toBeTrue()
        ->and($result->documents)->toMatchArray([
            'claim_form' => 's3://claims/claim-form.pdf',
        ]);
});

it('maps bio fields and wallet info into patient payload semantically', function () {
    $result = evaluatePhilhealthBstSettlement([
        'bio_fields' => [
            'name' => 'Maria Santos',
        ],
        'wallet_info' => [
            'mobile' => '09175551234',
        ],
        'checklist' => [
            'amount_verified' => true,
        ],
    ]);

    expect($result->ready)->toBeTrue()
        ->and($result->payload)->toMatchArray([
            'patient_name' => 'Maria Santos',
            'patient_mobile' => '09175551234',
        ])
        ->and($result->satisfied)->toContain('payload_present')
        ->and($result->satisfied)->toContain('amount_verified');
});

it('prefers explicit payload over mapped form flow payload', function () {
    $result = evaluatePhilhealthBstSettlement([
        'payload' => [
            'patient_name' => 'Explicit Patient',
            'patient_mobile' => '09999999999',
        ],
        'bio_fields' => [
            'name' => 'Mapped Patient',
        ],
        'wallet_info' => [
            'mobile' => '09175551234',
        ],
        'checklist' => [
            'amount_verified' => true,
        ],
    ]);

    expect($result->ready)->toBeTrue()
        ->and($result->payload)->toMatchArray([
            'patient_name' => 'Explicit Patient',
            'patient_mobile' => '09999999999',
        ]);
});

it('treats empty patient values as missing payload evidence', function () {
    $result = evaluatePhilhealthBstSettlement([
        'payload' => [
            'patient_name' => '',
            'patient_mobile' => '',
        ],
        'checklist' => [
            'amount_verified' => true,
        ],
    ]);

    expect($result->ready)->toBeFalse()
        ->and($result->missing)->toContain('payload_present')
        ->and($result->satisfied)->toContain('amount_verified');
});
