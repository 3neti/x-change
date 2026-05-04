<?php

declare(strict_types=1);

use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\SettlementScenarioSupport;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

it('formats all settlement readiness fields', function () {
    $readiness = new SettlementEnvelopeReadinessData(
        required: true,
        exists: true,
        ready: false,
        driver: 'philhealth-bst',
        gate: 'settleable',
        satisfied: ['patient_attested', 'claim_documents_present'],
        missing: ['amount_verified'],
        failed: ['amount_mismatch'],
        warnings: ['manual_review_recommended'],
        checklist: [
            'patient_attested' => true,
            'claim_documents_present' => true,
            'amount_verified' => false,
        ],
        payload: [
            'claim_reference' => 'PHIC-CLAIM-001',
            'amount' => 20000,
        ],
        documents: [
            'soa' => 'soa.pdf',
            'claim_form' => 'cf2.pdf',
        ],
        meta: [
            'evaluated_at' => '2026-05-04T12:00:00+08:00',
        ],
    );

    $result = app(SettlementScenarioSupport::class)->formatReadiness($readiness);

    expect($result)->toBe([
        'required' => true,
        'exists' => true,
        'ready' => false,
        'driver' => 'philhealth-bst',
        'gate' => 'settleable',
        'satisfied' => ['patient_attested', 'claim_documents_present'],
        'missing' => ['amount_verified'],
        'failed' => ['amount_mismatch'],
        'warnings' => ['manual_review_recommended'],
        'checklist' => [
            'patient_attested' => true,
            'claim_documents_present' => true,
            'amount_verified' => false,
        ],
        'payload' => [
            'claim_reference' => 'PHIC-CLAIM-001',
            'amount' => 20000,
        ],
        'documents' => [
            'soa' => 'soa.pdf',
            'claim_form' => 'cf2.pdf',
        ],
        'meta' => [
            'evaluated_at' => '2026-05-04T12:00:00+08:00',
        ],
    ]);
});

it('preserves payload documents checklist and meta exactly', function () {
    $payload = [
        'nested' => [
            'amount' => '20000.00',
            'currency' => 'PHP',
        ],
    ];

    $documents = [
        'proofs' => [
            ['type' => 'soa', 'url' => 'https://example.test/soa.pdf'],
            ['type' => 'claim_form', 'url' => 'https://example.test/cf2.pdf'],
        ],
    ];

    $checklist = [
        'patient_attested' => [
            'required' => true,
            'satisfied' => true,
        ],
        'amount_verified' => [
            'required' => true,
            'satisfied' => false,
        ],
    ];

    $meta = [
        'source' => 'unit-test',
        'debug' => [
            'driver_version' => 'test',
        ],
    ];

    $readiness = new SettlementEnvelopeReadinessData(
        required: true,
        exists: true,
        ready: false,
        driver: 'philhealth-bst',
        gate: 'settleable',
        satisfied: [],
        missing: ['amount_verified'],
        failed: [],
        warnings: [],
        checklist: $checklist,
        payload: $payload,
        documents: $documents,
        meta: $meta,
    );

    $result = app(SettlementScenarioSupport::class)->formatReadiness($readiness);

    expect($result['payload'])->toBe($payload)
        ->and($result['documents'])->toBe($documents)
        ->and($result['checklist'])->toBe($checklist)
        ->and($result['meta'])->toBe($meta);
});
