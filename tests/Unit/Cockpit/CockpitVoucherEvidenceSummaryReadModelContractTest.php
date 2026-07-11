<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitVoucherEvidenceSummaryData;
use LBHurtado\XChange\Data\Cockpit\CockpitVoucherReadModelData;

it('carries voucher detail evidence summary contract fields', function () {
    $readModel = new CockpitVoucherReadModelData(
        code: 'PC-EVIDENCE-001',
        status: 'issued',
        summary: ['code' => 'PC-EVIDENCE-001'],
        evidence_summary: [
            new CockpitVoucherEvidenceSummaryData(
                key: 'lifecycle',
                label: 'Lifecycle',
                status: 'issued',
                description: 'Sanitized voucher lifecycle facts are available.',
                read_only: true,
                available: true,
                source: 'voucher',
            ),
            new CockpitVoucherEvidenceSummaryData(
                key: 'claim',
                label: 'Claim evidence',
                status: 'not_wired',
                description: 'Claim evidence remains redacted.',
                read_only: true,
                available: false,
                source: 'claim',
            ),
        ],
        redactions: ['payloads' => 'sanitized-summary-only'],
        authorized: true,
    );

    expect($readModel->toArray())->toMatchArray([
        'code' => 'PC-EVIDENCE-001',
        'status' => 'issued',
        'summary' => ['code' => 'PC-EVIDENCE-001'],
        'evidence_summary' => [
            [
                'key' => 'lifecycle',
                'label' => 'Lifecycle',
                'status' => 'issued',
                'description' => 'Sanitized voucher lifecycle facts are available.',
                'read_only' => true,
                'available' => true,
                'source' => 'voucher',
            ],
            [
                'key' => 'claim',
                'label' => 'Claim evidence',
                'status' => 'not_wired',
                'description' => 'Claim evidence remains redacted.',
                'read_only' => true,
                'available' => false,
                'source' => 'claim',
            ],
        ],
        'redactions' => ['payloads' => 'sanitized-summary-only'],
        'authorized' => true,
    ]);
});
