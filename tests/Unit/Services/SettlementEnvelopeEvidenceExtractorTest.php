<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\SettlementEnvelopeEvidenceExtractor;
use LBHurtado\XChange\Services\SettlementEnvelopePreparationService;

it('maps philhealth bst form flow data into settlement payload', function () {
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
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
            'bio_fields' => [
                'name' => 'Juan Dela Cruz',
            ],
        ]);

    expect($evidence->payload)
        ->toMatchArray([
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ]);
});
